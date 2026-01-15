<?php

/**
 * Customer Segmentation Dashboard - Main Index
 * Primary UI for customer segmentation analysis and visualization
 */
require_once 'includes/auth.php';
require_once 'db.php';
require_once 'includes/Logger.php';

// Require authentication
requireLogin();

// Log access to dashboard
Logger::info('User accessed main dashboard', ['timestamp' => date('Y-m-d H:i:s')]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $segmentationType = filter_input(INPUT_POST, 'segmentation_type', FILTER_SANITIZE_STRING);

    // Handle export requests
    if ($action === 'export') {
        require_once 'SegmentationExporter.php';

        $exportType = filter_input(INPUT_POST, 'export_type', FILTER_SANITIZE_STRING);
        $selectedColumns = isset($_POST['columns']) ? $_POST['columns'] : [];

        try {
            $exporter = new SegmentationExporter($pdo, 1); // User ID 1 for now
            $result = $exporter->export($exportType, $segmentationType, $selectedColumns);

            if ($result['success']) {
                // Set success message and file info
                $exportSuccess = true;
                $exportFileName = $result['file_name'];
                $exportFilePath = $result['file_path'];
                $exportRecordCount = $result['record_count'];

                // Redirect to download the file
                header('Location: ' . $result['file_path']);
                exit;
            } else {
                $exportError = $result['error'];
            }
        } catch (Exception $e) {
            $exportError = 'Export failed: ' . $e->getMessage();
            Logger::error('Export exception', ['error' => $e->getMessage()]);
        }
    }

    // Handle segmentation requests
    if ($action === 'segment' || !isset($action)) {
        // Log segmentation request
        Logger::info('Segmentation request', ['type' => $segmentationType]);

        switch ($segmentationType) {
            case 'gender':
                $sql = "SELECT gender, COUNT(*) AS total_customers, ROUND(AVG(income), 2) AS avg_income, ROUND(AVG(purchase_amount), 2) AS avg_purchase_amount FROM customers GROUP BY gender";
                break;

            case 'region':
                $sql = "SELECT region, COUNT(*) AS total_customers, ROUND(AVG(income), 2) AS avg_income, ROUND(AVG(purchase_amount), 2) AS avg_purchase_amount FROM customers GROUP BY region ORDER BY total_customers DESC";
                break;

            case 'age_group':
                $sql = "SELECT CASE WHEN age BETWEEN 18 AND 25 THEN '18-25' WHEN age BETWEEN 26 AND 40 THEN '26-40' WHEN age BETWEEN 41 AND 60 THEN '41-60' ELSE '61+' END AS age_group, COUNT(*) AS total_customers, ROUND(AVG(income), 2) AS avg_income, ROUND(AVG(purchase_amount), 2) AS avg_purchase_amount FROM customers GROUP BY age_group ORDER BY age_group";
                break;

            case 'income_bracket':
                $sql = "SELECT CASE WHEN income < 30000 THEN 'Low Income (<30k)' WHEN income BETWEEN 30000 AND 70000 THEN 'Middle Income (30k-70k)' ELSE 'High Income (>70k)' END AS income_bracket, COUNT(*) AS total_customers, ROUND(AVG(purchase_amount), 2) AS avg_purchase_amount FROM customers GROUP BY income_bracket ORDER BY income_bracket";
                break;

            case 'cluster':
                $sql = "SELECT sr.cluster_label, COUNT(*) AS total_customers, ROUND(AVG(c.income), 2) AS avg_income, ROUND(AVG(c.purchase_amount), 2) AS avg_purchase_amount, MIN(c.age) AS min_age, MAX(c.age) AS max_age FROM segmentation_results sr JOIN customers c ON sr.customer_id = c.customer_id GROUP BY sr.cluster_label ORDER BY sr.cluster_label";

                // Fetch cluster metadata for enhanced visualizations
                try {
                    $metadata_sql = "SELECT * FROM cluster_metadata ORDER BY cluster_id";
                    $metadata_stmt = $pdo->query($metadata_sql);
                    $cluster_metadata = $metadata_stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fetch detailed customer data for scatter plots
                    $detail_sql = "SELECT c.customer_id, c.age, c.income, c.purchase_amount, sr.cluster_label
                               FROM customers c
                               JOIN segmentation_results sr ON c.customer_id = sr.customer_id
                               ORDER BY sr.cluster_label";
                    $detail_stmt = $pdo->query($detail_sql);
                    $cluster_details = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // If cluster_metadata table doesn't exist yet, set to empty arrays
                    $cluster_metadata = [];
                    $cluster_details = [];
                }
                break;

            case 'purchase_tier':
                $sql = "SELECT CASE WHEN purchase_amount < 1000 THEN 'Low Spender (<1k)' WHEN purchase_amount BETWEEN 1000 AND 3000 THEN 'Medium Spender (1k-3k)' ELSE 'High Spender (>3k)' END AS purchase_tier, COUNT(*) AS total_customers, ROUND(AVG(income), 2) AS avg_income FROM customers GROUP BY purchase_tier ORDER BY purchase_tier";
                break;

            case 'clv_tier':
                // Get tier distribution summary
                $tierSummaryQuery = "
                SELECT 
                    clv_tier,
                    COUNT(*) as customer_count,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM customers WHERE clv_tier IS NOT NULL), 2) as percentage,
                    MIN(calculated_clv) as min_clv,
                    MAX(calculated_clv) as max_clv,
                    ROUND(AVG(calculated_clv), 2) as avg_clv,
                    ROUND(AVG(income), 2) as avg_income,
                    ROUND(AVG(age), 2) as avg_age,
                    ROUND(AVG(purchase_amount), 2) as avg_purchase
                FROM customers
                WHERE clv_tier IS NOT NULL
                GROUP BY clv_tier
                ORDER BY FIELD(clv_tier, 'Platinum', 'Gold', 'Silver', 'Bronze')
            ";

                $tierSummaryStmt = $pdo->query($tierSummaryQuery);
                $tierSummary = $tierSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get detailed customer data by tier
                $customerDataQuery = "
                SELECT 
                    customer_id,
                    name,
                    age,
                    gender,
                    income,
                    region,
                    purchase_amount,
                    avg_purchase_amount,
                    purchase_frequency,
                    customer_lifespan_months,
                    calculated_clv,
                    clv_tier
                FROM customers
                WHERE clv_tier IS NOT NULL
                ORDER BY 
                    FIELD(clv_tier, 'Platinum', 'Gold', 'Silver', 'Bronze'),
                    calculated_clv DESC
            ";

                $customerDataStmt = $pdo->query($customerDataQuery);
                $customerData = $customerDataStmt->fetchAll(PDO::FETCH_ASSOC);

                // Store customer data grouped by tier
                $customersByTier = [
                    'Platinum' => [],
                    'Gold' => [],
                    'Silver' => [],
                    'Bronze' => []
                ];

                foreach ($customerData as $row) {
                    $customersByTier[$row['clv_tier']][] = $row;
                }

                // Set results for display
                $results = $tierSummary;
                $clvTierSummary = $tierSummary;
                $clvCustomersByTier = $customersByTier;
                $clvTotalCustomers = array_sum(array_column($tierSummary, 'customer_count'));
                break;

            default:
                $sql = "SELECT * FROM customers LIMIT 10"; // Default query
        }

        // Execute query for standard segmentations (skip for clv_tier which sets results directly)
        if ($segmentationType !== 'clv_tier') {
            try {
                $stmt = $pdo->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Query execution failed: " . $e->getMessage());
            }
        }
    } // End segmentation action check
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Segmentation Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Modern Dashboard Card Styling */
        .dashboard-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        /* Gradient Background */
        .dashboard-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .dashboard-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(50px, 50px);
            }
        }
    </style>
</head>

<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm" style="background: linear-gradient(90deg, #0d6efd 0%, #0b5ed7 100%) !important;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-chart-line" style="margin-right: 8px;"></i>Customer Segmentation System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-3">
                        <span class="navbar-text text-white" style="font-weight: 600;">
                            <i class="fas fa-user-circle" style="margin-right: 6px;"></i>admin
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: rgba(255,255,255,0.3);">
                            Reports
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="border: none; border-radius: 6px;">
                            <li><a class="dropdown-item" href="dashboards/executive_dashboard.php"><i class="fas fa-tachometer-alt" style="margin-right: 8px;"></i>Executive Dashboard</a></li>
                            <li><a class="dropdown-item" href="dashboards/executive_summary.php"><i class="fas fa-chart-area" style="margin-right: 8px;"></i>Executive Summary</a></li>
                            <li><a class="dropdown-item" href="dashboards/alerts.php"><i class="fas fa-bell" style="margin-right: 8px;"></i>Alerts & Monitoring</a></li>
                            <li><a class="dropdown-item" href="dashboards/performance.php"><i class="fas fa-gauge-high" style="margin-right: 8px;"></i>System Performance</a></li>
                        </ul>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="logout.php" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,0.3);">
                            <i class="fas fa-sign-out-alt" style="margin-right: 4px;"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="text-center mb-4">Customer Segmentation Dashboard</h1>

        <!-- Action Button -->
        <div class="d-flex justify-content-start mb-3">
            <a href="run_clustering.php?clusters=5" class="btn btn-success" target="_blank"
                title="Run k-means clustering to segment customers">
                <i class="fas fa-cog"></i> Run Clustering
            </a>
            <small class="text-muted ms-2 d-flex align-items-center">Generate customer segments</small>
        </div>

        <!-- Segmentation Form -->
        <form method="POST" class="mb-4" id="segmentationForm">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Customer Segmentation</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="segmentation_type" class="form-label">Select Segmentation Type</label>
                                    <select name="segmentation_type" id="segmentation_type" class="form-select" required>
                                        <option value="" disabled selected>Select Segmentation Type</option>
                                        <option value="gender">By Gender</option>
                                        <option value="region">By Region</option>
                                        <option value="age_group">By Age Group</option>
                                        <option value="income_bracket">By Income Bracket</option>
                                        <option value="cluster">By Cluster</option>
                                        <option value="purchase_tier">By Purchase Tier</option>
                                        <option value="clv_tier">By CLV Tier</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" name="action" value="segment" class="btn btn-primary">
                                            <i class="fas fa-chart-bar"></i> Show Results
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Export Form (shown when results are available) -->
        <?php if (isset($results) && !empty($results)): ?>
            <form method="POST" class="mb-4" id="exportForm">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-download"></i> Export Segmentation Data</h5>
                            </div>
                            <div class="card-body">
                                <input type="hidden" name="segmentation_type" value="<?= htmlspecialchars($segmentationType) ?>">
                                <input type="hidden" name="action" value="export">

                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Export Format</label>
                                        <select name="export_type" class="form-select" required>
                                            <option value="csv">CSV File</option>
                                            <option value="excel">Excel (with Charts)</option>
                                            <option value="pdf">PDF Report</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">Select Columns to Export</label>
                                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                            <div class="row">
                                                <?php
                                                $availableColumns = [];
                                                if ($segmentationType === 'cluster') {
                                                    $availableColumns = ['customer_id', 'name', 'age', 'gender', 'income', 'region', 'purchase_amount', 'cluster_id', 'cluster_name', 'cluster_description'];
                                                } elseif ($segmentationType === 'clv_tier') {
                                                    $availableColumns = ['customer_id', 'name', 'age', 'gender', 'income', 'region', 'purchase_amount', 'avg_purchase_amount', 'purchase_frequency', 'customer_lifespan_months', 'calculated_clv', 'clv_tier'];
                                                } else {
                                                    $availableColumns = ['customer_id', 'name', 'age', 'gender', 'income', 'region', 'purchase_amount'];
                                                }

                                                foreach ($availableColumns as $column): ?>
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="columns[]" value="<?= $column ?>" id="col_<?= $column ?>" checked>
                                                            <label class="form-check-label" for="col_<?= $column ?>">
                                                                <?= ucwords(str_replace('_', ' ', $column)) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-download"></i> Export
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectAllColumns()">
                                                Select All
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Export Status Messages -->
            <?php if (isset($exportSuccess) && $exportSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <strong>Export Successful!</strong>
                    File "<strong><?= htmlspecialchars($exportFileName) ?></strong>" has been generated with <?= number_format($exportRecordCount) ?> records.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif (isset($exportError)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Export Failed!</strong>
                    <?= htmlspecialchars($exportError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- Results Table -->
        <?php if (isset($results)): ?>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <?php foreach (array_keys($results[0]) as $header): ?>
                            <th><?= ucfirst(str_replace('_', ' ', $header)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= htmlspecialchars($value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Insights Section -->
            <div class="alert alert-info mb-4">
                <h5>Analysis Insights:</h5>
                <div id="insights"></div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <canvas id="mainChart" width="400" height="200"></canvas>
                </div>
                <div class="col-md-4">
                    <canvas id="doughnutChart" width="200" height="200"></canvas>
                </div>
            </div>

            <script>
                const segmentationType = '<?= $segmentationType ?>';
                const labels = <?= json_encode(array_column($results, array_keys($results[0])[0])) ?>;
                const data = <?= json_encode(array_column($results, array_keys($results[0])[1])) ?>;
                const results = <?= json_encode($results) ?>;

                // Generate insights based on segmentation type
                let insights = '';
                const totalCustomers = data.reduce((a, b) => a + b, 0);

                switch (segmentationType) {
                    case 'gender':
                        insights = `<ul>
                            <li>Total customers analyzed: ${totalCustomers.toLocaleString()}</li>
                            <li>Gender distribution shows ${labels.length} categories</li>
                            <li>Largest segment: ${labels[data.indexOf(Math.max(...data))]} with ${Math.max(...data).toLocaleString()} customers (${(Math.max(...data)/totalCustomers*100).toFixed(1)}%)</li>
                            ${results.length > 0 && results[0].avg_income ? `<li>Average income across genders ranges from $${Math.min(...results.map(r => parseFloat(r.avg_income))).toLocaleString()} to $${Math.max(...results.map(r => parseFloat(r.avg_income))).toLocaleString()}</li>` : ''}
                        </ul>`;
                        break;

                    case 'region':
                        insights = `<ul>
                            <li>Total customers across ${labels.length} regions: ${totalCustomers.toLocaleString()}</li>
                            <li>Top region: ${labels[0]} with ${data[0].toLocaleString()} customers</li>
                            <li>Regional concentration: Top 3 regions represent ${((data[0] + (data[1]||0) + (data[2]||0))/totalCustomers*100).toFixed(1)}% of total customers</li>
                            ${results.length > 0 && results[0].avg_purchase_amount ? `<li>Purchase amounts vary from $${Math.min(...results.map(r => parseFloat(r.avg_purchase_amount))).toLocaleString()} to $${Math.max(...results.map(r => parseFloat(r.avg_purchase_amount))).toLocaleString()} across regions</li>` : ''}
                        </ul>`;
                        break;

                    case 'age_group':
                        insights = `<ul>
                            <li>Customer base distributed across ${labels.length} age groups</li>
                            <li>Dominant age group: ${labels[data.indexOf(Math.max(...data))]} with ${Math.max(...data).toLocaleString()} customers (${(Math.max(...data)/totalCustomers*100).toFixed(1)}%)</li>
                            ${results.length > 0 && results[0].avg_income ? `<li>Income peaks in the ${results.reduce((max, r) => parseFloat(r.avg_income) > parseFloat(max.avg_income) ? r : max).age_group || results[0].age_group} age group at $${Math.max(...results.map(r => parseFloat(r.avg_income))).toLocaleString()}</li>` : ''}
                            ${results.length > 0 && results[0].avg_purchase_amount ? `<li>Highest spending age group: ${results.reduce((max, r) => parseFloat(r.avg_purchase_amount) > parseFloat(max.avg_purchase_amount) ? r : max).age_group || results[0].age_group}</li>` : ''}
                        </ul>`;
                        break;

                    case 'income_bracket':
                        insights = `<ul>
                            <li>Customers segmented into ${labels.length} income brackets</li>
                            <li>Largest income segment: ${labels[data.indexOf(Math.max(...data))]} (${(Math.max(...data)/totalCustomers*100).toFixed(1)}% of customers)</li>
                            ${results.length > 0 && results[0].avg_purchase_amount ? `<li>Purchase behavior: ${results.reduce((max, r) => parseFloat(r.avg_purchase_amount) > parseFloat(max.avg_purchase_amount) ? r : max).income_bracket || results[0].income_bracket} shows highest average spending at $${Math.max(...results.map(r => parseFloat(r.avg_purchase_amount))).toLocaleString()}</li>` : ''}
                            <li>Income-purchase correlation can guide targeted marketing strategies</li>
                        </ul>`;
                        break;

                    case 'cluster':
                        // Check if we have enhanced metadata
                        if (typeof clusterMetadata !== 'undefined' && clusterMetadata.length > 0) {
                            const largestCluster = clusterMetadata.reduce((max, c) =>
                                c.customer_count > max.customer_count ? c : max
                            );
                            insights = `<ul>
                                <li>Advanced k-means clustering identified <strong>${clusterMetadata.length} distinct customer segments</strong></li>
                                <li>Largest segment: <strong>${largestCluster.cluster_name}</strong> with ${parseInt(largestCluster.customer_count).toLocaleString()} customers (${((largestCluster.customer_count/totalCustomers)*100).toFixed(1)}%)</li>
                                <li>Clusters range from "${clusterMetadata[0].cluster_name}" to "${clusterMetadata[clusterMetadata.length-1].cluster_name}"</li>
                                <li>Each cluster has unique demographics, income levels, and purchasing behaviors - view detailed analysis below</li>
                                <li><strong>Actionable insights:</strong> Scroll down to see cluster characteristics, statistics, visualizations, and marketing recommendations</li>
                            </ul>`;
                        } else {
                            // Fallback to original insights if metadata not available
                            insights = `<ul>
                                <li>Machine learning clustering identified ${labels.length} distinct customer segments</li>
                                <li>Largest cluster: ${labels[data.indexOf(Math.max(...data))]} with ${Math.max(...data).toLocaleString()} customers</li>
                                ${results.length > 0 && results[0].min_age && results[0].max_age ? `<li>Age ranges vary across clusters, providing demographic differentiation</li>` : ''}
                                <li>Each cluster represents a unique customer profile for targeted campaigns</li>
                                <li><em>Note: Run the Python clustering script to generate enhanced cluster analysis with detailed explanations</em></li>
                            </ul>`;
                        }
                        break;

                    case 'purchase_tier':
                        insights = `<ul>
                            <li>Customers categorized into ${labels.length} spending tiers</li>
                            <li>Largest tier: ${labels[data.indexOf(Math.max(...data))]} (${(Math.max(...data)/totalCustomers*100).toFixed(1)}% of customers)</li>
                            ${results.length > 0 && results[0].avg_income ? `<li>High spenders correlate with income levels averaging $${Math.max(...results.map(r => parseFloat(r.avg_income))).toLocaleString()}</li>` : ''}
                            <li>Understanding spending tiers enables personalized product recommendations</li>
                        </ul>`;
                        break;

                    case 'clv_tier':
                        const platinumTier = results.find(r => r.clv_tier === 'Platinum');
                        const goldTier = results.find(r => r.clv_tier === 'Gold');
                        const topTwoPercentage = platinumTier && goldTier ?
                            ((parseFloat(platinumTier.percentage) + parseFloat(goldTier.percentage))) : 0;
                        insights = `<ul>
                            <li><strong>CLV-based segmentation:</strong> Customers divided into 4 tiers based on lifetime value percentiles</li>
                            <li><strong>Platinum tier:</strong> Top 25% of customers by CLV (${platinumTier ? platinumTier.customer_count : 0} customers)</li>
                            <li><strong>Revenue concentration:</strong> Top 50% of customers (Platinum + Gold) represent ${topTwoPercentage.toFixed(1)}% of the total</li>
                            <li><strong>Value distribution:</strong> CLV ranges from $${Math.min(...results.map(r => parseFloat(r.min_clv))).toLocaleString()} to $${Math.max(...results.map(r => parseFloat(r.max_clv))).toLocaleString()}</li>
                            <li><strong>Strategic focus:</strong> Platinum and Gold tiers should receive premium attention and loyalty programs</li>
                            <li><strong>Detailed analysis:</strong> Scroll down for comprehensive tier statistics, customer samples, and marketing insights</li>
                        </ul>`;
                        break;
                }

                document.getElementById('insights').innerHTML = insights;

                // Main Bar/Line Chart
                const ctx1 = document.getElementById('mainChart').getContext('2d');

                // Default settings (Bar Chart)
                let chartType = 'bar';
                let bgColors = 'rgba(54, 162, 235, 0.6)'; // Default Blue
                let borderColors = 'rgba(54, 162, 235, 1)';
                let chartOptions = {
                    indexAxis: 'x',
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Customer Distribution by ' + segmentationType.replace('_', ' ').toUpperCase()
                        },
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                };

                // Logic to switch chart types based on segmentation
                if (segmentationType === 'region') {
                    chartOptions.indexAxis = 'y'; // Switch to Horizontal
                } else if (segmentationType === 'purchase_tier') {
                    // Switch to Polar Area Chart
                    chartType = 'polarArea';

                    // Distinct colors for tiers
                    bgColors = [
                        'rgba(255, 99, 132, 0.7)', // Red
                        'rgba(255, 205, 86, 0.7)', // Yellow
                        'rgba(75, 192, 192, 0.7)' // Green
                    ];
                    borderColors = '#ffffff'; // White borders look better on Polar

                    // Polar specific options
                    chartOptions = {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Spending Power Distribution'
                            },
                            legend: {
                                position: 'right',
                                display: true
                            }
                        },
                        scales: {
                            r: {
                                ticks: {
                                    backdropColor: 'transparent',
                                    z: 1
                                }
                            }
                        }
                    };
                } else if (segmentationType === 'age_group' || segmentationType === 'income_bracket') {
                    chartType = 'line';
                    bgColors = 'rgba(54, 162, 235, 0.2)';
                }

                // Initialize Main Chart
                new Chart(ctx1, {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Customer Count',
                            data: data,
                            backgroundColor: bgColors,
                            borderColor: borderColors,
                            borderWidth: 1,
                            fill: (chartType === 'line')
                        }]
                    },
                    options: chartOptions
                });

                // --- 3. Initialize Doughnut Chart (Side Chart) ---
                const ctx2 = document.getElementById('doughnutChart').getContext('2d');
                const doughnutColors = [
                    'rgba(255, 99, 132, 0.8)', 'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)', 'rgba(255, 159, 64, 0.8)'
                ];

                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: doughnutColors.slice(0, labels.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Distribution %'
                            },
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 15,
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            </script>

            <!-- Enhanced Cluster Visualizations -->
            <?php if ($segmentationType === 'cluster' && !empty($cluster_metadata)): ?>
                <hr class="my-5">

                <!-- Section 1: Cluster Characteristics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Cluster Characteristics</h4>
                    </div>
                    <?php
                    $total_customers = array_sum(array_column($cluster_metadata, 'customer_count'));
                    foreach ($cluster_metadata as $cluster):
                        $percentage = round(($cluster['customer_count'] / $total_customers) * 100, 1);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-primary h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Cluster <?= $cluster['cluster_id'] ?>: <?= htmlspecialchars($cluster['cluster_name']) ?></h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?= htmlspecialchars($cluster['description']) ?></p>
                                    <p class="text-muted mb-0">
                                        <strong><?= number_format($cluster['customer_count']) ?></strong> customers
                                        (<?= $percentage ?>%)
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Section 2: Statistical Summaries -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Cluster Statistics</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Cluster</th>
                                        <th>Customers</th>
                                        <th>Age Range</th>
                                        <th>Avg Age</th>
                                        <th>Avg Income</th>
                                        <th>Avg Purchase</th>
                                        <th>Top Gender</th>
                                        <th>Top Region</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cluster_metadata as $cluster): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($cluster['cluster_name']) ?></strong></td>
                                            <td><?= number_format($cluster['customer_count']) ?></td>
                                            <td><?= $cluster['age_min'] ?>-<?= $cluster['age_max'] ?></td>
                                            <td><?= round($cluster['avg_age'], 1) ?></td>
                                            <td>$<?= number_format($cluster['avg_income'], 2) ?></td>
                                            <td>$<?= number_format($cluster['avg_purchase_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($cluster['dominant_gender']) ?></td>
                                            <td><?= htmlspecialchars($cluster['dominant_region']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Cluster Feature Visualizations -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Cluster Feature Comparisons</h4>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <canvas id="clusterRadarChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <canvas id="clusterComparisonChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <canvas id="clusterScatterChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Business Recommendations -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Recommended Marketing Strategies</h4>
                    </div>
                    <?php foreach ($cluster_metadata as $cluster): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><?= htmlspecialchars($cluster['cluster_name']) ?> (<?= number_format($cluster['customer_count']) ?> customers)</h6>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <?php
                                        $recommendations = explode(';', $cluster['business_recommendation']);
                                        foreach ($recommendations as $rec):
                                        ?>
                                            <li><?= htmlspecialchars(trim($rec)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Additional Charts JavaScript -->
                <script>
                    // Prepare data for advanced visualizations
                    const clusterMetadata = <?= json_encode($cluster_metadata) ?>;
                    const clusterDetails = <?= json_encode($cluster_details) ?>;

                    // Chart colors for clusters
                    const clusterColors = [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ];

                    // 1. Radar Chart - Normalized Feature Comparison
                    const radarCtx = document.getElementById('clusterRadarChart').getContext('2d');

                    // Normalize features to 0-1 scale
                    const allAges = clusterMetadata.map(c => parseFloat(c.avg_age));
                    const allIncomes = clusterMetadata.map(c => parseFloat(c.avg_income));
                    const allPurchases = clusterMetadata.map(c => parseFloat(c.avg_purchase_amount));

                    const minAge = Math.min(...allAges),
                        maxAge = Math.max(...allAges);
                    const minIncome = Math.min(...allIncomes),
                        maxIncome = Math.max(...allIncomes);
                    const minPurchase = Math.min(...allPurchases),
                        maxPurchase = Math.max(...allPurchases);

                    const radarDatasets = clusterMetadata.map((cluster, index) => ({
                        label: cluster.cluster_name,
                        data: [
                            (parseFloat(cluster.avg_age) - minAge) / (maxAge - minAge),
                            (parseFloat(cluster.avg_income) - minIncome) / (maxIncome - minIncome),
                            (parseFloat(cluster.avg_purchase_amount) - minPurchase) / (maxPurchase - minPurchase)
                        ],
                        borderColor: clusterColors[index],
                        backgroundColor: clusterColors[index].replace('0.8', '0.2'),
                        borderWidth: 2
                    }));

                    new Chart(radarCtx, {
                        type: 'radar',
                        data: {
                            labels: ['Age', 'Income', 'Purchase Amount'],
                            datasets: radarDatasets
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Cluster Feature Profile Comparison'
                                },
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 15,
                                        font: {
                                            size: 10
                                        }
                                    }
                                }
                            },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 1,
                                    ticks: {
                                        stepSize: 0.2
                                    }
                                }
                            }
                        }
                    });

                    // 2. Grouped Bar Chart - Average Metrics
                    const groupedBarCtx = document.getElementById('clusterComparisonChart').getContext('2d');

                    new Chart(groupedBarCtx, {
                        type: 'bar',
                        data: {
                            labels: clusterMetadata.map(c => c.cluster_name),
                            datasets: [{
                                    label: 'Average Income',
                                    data: clusterMetadata.map(c => parseFloat(c.avg_income)),
                                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Average Purchase',
                                    data: clusterMetadata.map(c => parseFloat(c.avg_purchase_amount)),
                                    backgroundColor: 'rgba(255, 206, 86, 0.6)',
                                    borderColor: 'rgba(255, 206, 86, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Average Income and Purchase by Cluster'
                                },
                                legend: {
                                    position: 'bottom'
                                }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Income ($)'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Purchase ($)'
                                    },
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    });

                    // 3. Scatter Plot - Income vs Purchase by Cluster
                    const scatterCtx = document.getElementById('clusterScatterChart').getContext('2d');

                    // Group customer data by cluster
                    const scatterDatasets = [];
                    const maxCluster = Math.max(...clusterDetails.map(c => parseInt(c.cluster_label)));

                    for (let i = 0; i <= maxCluster; i++) {
                        const clusterData = clusterDetails.filter(c => parseInt(c.cluster_label) === i);
                        const clusterName = clusterMetadata.find(m => m.cluster_id == i)?.cluster_name || `Cluster ${i}`;

                        scatterDatasets.push({
                            label: clusterName,
                            data: clusterData.map(c => ({
                                x: parseFloat(c.income),
                                y: parseFloat(c.purchase_amount)
                            })),
                            backgroundColor: clusterColors[i],
                            borderColor: clusterColors[i].replace('0.8', '1'),
                            borderWidth: 1,
                            pointRadius: 3,
                            pointHoverRadius: 5
                        });
                    }

                    new Chart(scatterCtx, {
                        type: 'scatter',
                        data: {
                            datasets: scatterDatasets
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Customer Distribution: Income vs Purchase Amount by Cluster'
                                },
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 15,
                                        font: {
                                            size: 10
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Income ($)'
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Purchase Amount ($)'
                                    }
                                }
                            }
                        }
                    });
                </script>
            <?php endif; ?>

            <!-- CLV Tier Segmentation Results -->
            <?php if ($segmentationType === 'clv_tier' && !empty($clvTierSummary)): ?>
                <hr class="my-5">

                <!-- CLV Tier Summary Cards -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">CLV Tier Distribution</h4>
                        <p class="text-muted">Customer Lifetime Value segmentation based on calculated CLV percentiles</p>
                    </div>
                    <?php foreach ($clvTierSummary as $tier): ?>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="card h-100 border-<?php
                                                            switch ($tier['clv_tier']) {
                                                                case 'Platinum':
                                                                    echo 'warning';
                                                                    break;
                                                                case 'Gold':
                                                                    echo 'warning';
                                                                    break;
                                                                case 'Silver':
                                                                    echo 'secondary';
                                                                    break;
                                                                case 'Bronze':
                                                                    echo 'dark';
                                                                    break;
                                                            }
                                                            ?>">
                                <div class="card-header bg-<?php
                                                            switch ($tier['clv_tier']) {
                                                                case 'Platinum':
                                                                    echo 'warning';
                                                                    break;
                                                                case 'Gold':
                                                                    echo 'warning';
                                                                    break;
                                                                case 'Silver':
                                                                    echo 'secondary';
                                                                    break;
                                                                case 'Bronze':
                                                                    echo 'dark';
                                                                    break;
                                                            }
                                                            ?> text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-<?php
                                                            switch ($tier['clv_tier']) {
                                                                case 'Platinum':
                                                                    echo 'crown';
                                                                    break;
                                                                case 'Gold':
                                                                    echo 'medal';
                                                                    break;
                                                                case 'Silver':
                                                                    echo 'award';
                                                                    break;
                                                                case 'Bronze':
                                                                    echo 'trophy';
                                                                    break;
                                                            }
                                                            ?>"></i>
                                        <?= htmlspecialchars($tier['clv_tier']) ?> Tier
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h4 class="card-title text-<?php
                                                                switch ($tier['clv_tier']) {
                                                                    case 'Platinum':
                                                                        echo 'warning';
                                                                        break;
                                                                    case 'Gold':
                                                                        echo 'warning';
                                                                        break;
                                                                    case 'Silver':
                                                                        echo 'secondary';
                                                                        break;
                                                                    case 'Bronze':
                                                                        echo 'dark';
                                                                        break;
                                                                }
                                                                ?>">
                                        <?= number_format($tier['customer_count']) ?> customers
                                    </h4>
                                    <p class="card-text">
                                        <strong><?= $tier['percentage'] ?>%</strong> of total customers<br>
                                        <small class="text-muted">
                                            CLV Range: $<?= number_format($tier['min_clv'], 2) ?> - $<?= number_format($tier['max_clv'], 2) ?><br>
                                            Avg CLV: $<?= number_format($tier['avg_clv'], 2) ?>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- CLV Tier Statistics Table -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">CLV Tier Statistics</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tier</th>
                                        <th>Customers</th>
                                        <th>Percentage</th>
                                        <th>CLV Range</th>
                                        <th>Avg CLV</th>
                                        <th>Avg Income</th>
                                        <th>Avg Age</th>
                                        <th>Avg Purchase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clvTierSummary as $tier): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-<?php
                                                                    switch ($tier['clv_tier']) {
                                                                        case 'Platinum':
                                                                            echo 'warning';
                                                                            break;
                                                                        case 'Gold':
                                                                            echo 'warning';
                                                                            break;
                                                                        case 'Silver':
                                                                            echo 'secondary';
                                                                            break;
                                                                        case 'Bronze':
                                                                            echo 'dark';
                                                                            break;
                                                                    }
                                                                    ?>">
                                                    <i class="fas fa-<?php
                                                                        switch ($tier['clv_tier']) {
                                                                            case 'Platinum':
                                                                                echo 'crown';
                                                                                break;
                                                                            case 'Gold':
                                                                                echo 'medal';
                                                                                break;
                                                                            case 'Silver':
                                                                                echo 'award';
                                                                                break;
                                                                            case 'Bronze':
                                                                                echo 'trophy';
                                                                                break;
                                                                        }
                                                                        ?>"></i>
                                                    <?= htmlspecialchars($tier['clv_tier']) ?>
                                                </strong>
                                            </td>
                                            <td><?= number_format($tier['customer_count']) ?></td>
                                            <td><?= $tier['percentage'] ?>%</td>
                                            <td>$<?= number_format($tier['min_clv'], 2) ?> - $<?= number_format($tier['max_clv'], 2) ?></td>
                                            <td>$<?= number_format($tier['avg_clv'], 2) ?></td>
                                            <td>$<?= number_format($tier['avg_income'], 2) ?></td>
                                            <td><?= round($tier['avg_age'], 1) ?> years</td>
                                            <td>$<?= number_format($tier['avg_purchase'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CLV Tier Customer Samples -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">Sample Customers by CLV Tier</h4>
                        <?php foreach ($clvCustomersByTier as $tierName => $customers): ?>
                            <?php if (!empty($customers)): ?>
                                <div class="card mb-3">
                                    <div class="card-header bg-<?php
                                                                switch ($tierName) {
                                                                    case 'Platinum':
                                                                        echo 'warning';
                                                                        break;
                                                                    case 'Gold':
                                                                        echo 'warning';
                                                                        break;
                                                                    case 'Silver':
                                                                        echo 'secondary';
                                                                        break;
                                                                    case 'Bronze':
                                                                        echo 'dark';
                                                                        break;
                                                                }
                                                                ?> text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-<?php
                                                                switch ($tierName) {
                                                                    case 'Platinum':
                                                                        echo 'crown';
                                                                        break;
                                                                    case 'Gold':
                                                                        echo 'medal';
                                                                        break;
                                                                    case 'Silver':
                                                                        echo 'award';
                                                                        break;
                                                                    case 'Bronze':
                                                                        echo 'trophy';
                                                                        break;
                                                                }
                                                                ?>"></i>
                                            <?= htmlspecialchars($tierName) ?> Tier Customers (Top 5 by CLV)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Age</th>
                                                        <th>Gender</th>
                                                        <th>Region</th>
                                                        <th>Income</th>
                                                        <th>Purchase Amount</th>
                                                        <th>CLV</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sampleCustomers = array_slice($customers, 0, 5);
                                                    foreach ($sampleCustomers as $customer):
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($customer['customer_id']) ?></td>
                                                            <td><?= htmlspecialchars($customer['name']) ?></td>
                                                            <td><?= htmlspecialchars($customer['age']) ?></td>
                                                            <td><?= htmlspecialchars($customer['gender']) ?></td>
                                                            <td><?= htmlspecialchars($customer['region']) ?></td>
                                                            <td>$<?= number_format($customer['income'], 2) ?></td>
                                                            <td>$<?= number_format($customer['purchase_amount'], 2) ?></td>
                                                            <td>$<?= number_format($customer['calculated_clv'], 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if (count($customers) > 5): ?>
                                            <p class="text-muted mt-2">... and <?= count($customers) - 5 ?> more customers</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CLV Tier Insights -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="mb-3">CLV Tier Insights & Recommendations</h4>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb"></i> Key Insights:</h6>
                            <ul class="mb-0">
                                <li><strong>Total Customers Segmented:</strong> <?= number_format($clvTotalCustomers) ?></li>
                                <li><strong>Highest Value Tier (Platinum):</strong> Top 25% of customers by CLV</li>
                                <li><strong>Revenue Concentration:</strong> Platinum and Gold tiers (top 50%) represent the majority of lifetime value</li>
                                <li><strong>Targeted Marketing:</strong> Focus premium services and loyalty programs on higher tiers</li>
                            </ul>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Logout Script -->
    <script>
        document.querySelector('.btn-danger').addEventListener('click', function(e) {
            e.preventDefault();
            fetch('logout.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'login.php';
                    }
                });
        });

        // Function to select/deselect all columns
        function selectAllColumns() {
            const checkboxes = document.querySelectorAll('#exportForm input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
        }

        // Auto-submit segmentation form when type changes
        document.getElementById('segmentation_type').addEventListener('change', function() {
            document.getElementById('segmentationForm').submit();
        });
    </script>

    <!-- CLV Tier Insights JavaScript -->
    <script>
        // CLV Tier Segmentation Insights Generator

        // Main function to generate CLV insights
        function generateCLVInsights(tierSummary, customersByTier) {
            const insights = {
                overview: generateOverviewInsights(tierSummary),
                tierSpecific: generateTierSpecificInsights(tierSummary),
                recommendations: generateCLVRecommendations(tierSummary, customersByTier),
                riskOpportunities: identifyRiskAndOpportunities(tierSummary, customersByTier),
                actionItems: generateActionItems(tierSummary)
            };

            return insights;
        }

        // Generate overview insights
        function generateOverviewInsights(tierSummary) {
            const totalCustomers = tierSummary.reduce((sum, tier) => sum + parseInt(tier.customer_count), 0);
            const totalCLV = tierSummary.reduce((sum, tier) => sum + (parseFloat(tier.avg_clv) * parseInt(tier.customer_count)), 0);
            const avgCLVOverall = totalCLV / totalCustomers;

            const platinumTier = tierSummary.find(t => t.clv_tier === 'Platinum');
            const bronzeTier = tierSummary.find(t => t.clv_tier === 'Bronze');

            const platinumRevenue = platinumTier ? parseFloat(platinumTier.avg_clv) * parseInt(platinumTier.customer_count) : 0;
            const platinumRevenuePercent = (platinumRevenue / totalCLV * 100).toFixed(2);

            return {
                totalCustomers: totalCustomers,
                avgCLVOverall: avgCLVOverall.toFixed(2),
                platinumRevenuePercent: platinumRevenuePercent,
                clvRange: {
                    highest: platinumTier ? parseFloat(platinumTier.max_clv) : 0,
                    lowest: bronzeTier ? parseFloat(bronzeTier.min_clv) : 0,
                    ratio: platinumTier && bronzeTier ? (parseFloat(platinumTier.avg_clv) / parseFloat(bronzeTier.avg_clv)).toFixed(2) : 0
                },
                message: `Your customer base shows a total lifetime value of ₱${formatNumber(totalCLV)}. 
                          The top ${platinumTier?.percentage || 25}% of customers (Platinum tier) contribute 
                          ${platinumRevenuePercent}% of total potential revenue, demonstrating the importance of 
                          high-value customer retention.`
            };
        }

        // Generate tier-specific insights
        function generateTierSpecificInsights(tierSummary) {
            const insights = [];

            tierSummary.forEach(tier => {
                const tierName = tier.clv_tier;
                const avgCLV = parseFloat(tier.avg_clv);
                const avgIncome = parseFloat(tier.avg_income);
                const avgAge = parseFloat(tier.avg_age);
                const customerCount = parseInt(tier.customer_count);
                const percentage = parseFloat(tier.percentage);

                let insight = {
                    tier: tierName,
                    customerCount: customerCount,
                    percentage: percentage,
                    avgCLV: avgCLV,
                    avgIncome: avgIncome,
                    avgAge: avgAge,
                    characteristics: [],
                    strengths: [],
                    concerns: []
                };

                // Platinum Tier Insights
                if (tierName === 'Platinum') {
                    insight.characteristics = [
                        `Highest lifetime value customers with average CLV of ₱${formatNumber(avgCLV)}`,
                        `Typically ${Math.round(avgAge)} years old with income of ₱${formatNumber(avgIncome)}`,
                        `Represent ${percentage}% of customer base but drive significant revenue`
                    ];
                    insight.strengths = [
                        'High purchase frequency and loyalty',
                        'Premium spending behavior',
                        'Long customer lifespan'
                    ];
                    insight.concerns = [
                        'High churn risk would significantly impact revenue',
                        'May require personalized attention and premium service',
                        'Competition likely targeting this segment'
                    ];
                }

                // Gold Tier Insights
                if (tierName === 'Gold') {
                    insight.characteristics = [
                        `Strong value customers with CLV of ₱${formatNumber(avgCLV)}`,
                        `Average age ${Math.round(avgAge)} with income ₱${formatNumber(avgIncome)}`,
                        `${percentage}% of total customer base`
                    ];
                    insight.strengths = [
                        'Good purchase frequency and spending',
                        'Potential to upgrade to Platinum tier',
                        'Stable customer segment'
                    ];
                    insight.concerns = [
                        'Risk of downgrade if not engaged properly',
                        'May need incentives to increase spending',
                        'Could be enticed by competitor offers'
                    ];
                }

                // Silver Tier Insights
                if (tierName === 'Silver') {
                    insight.characteristics = [
                        `Moderate value customers with CLV of ₱${formatNumber(avgCLV)}`,
                        `Typically ${Math.round(avgAge)} years old earning ₱${formatNumber(avgIncome)}`,
                        `Makes up ${percentage}% of customer base`
                    ];
                    insight.strengths = [
                        'Room for significant growth',
                        'Responsive to targeted campaigns',
                        'Can be upgraded with right strategy'
                    ];
                    insight.concerns = [
                        'May have lower engagement levels',
                        'Need strategies to increase purchase frequency',
                        'Risk of becoming inactive'
                    ];
                }

                // Bronze Tier Insights
                if (tierName === 'Bronze') {
                    insight.characteristics = [
                        `Entry-level customers with CLV of ₱${formatNumber(avgCLV)}`,
                        `Average age ${Math.round(avgAge)} with income ₱${formatNumber(avgIncome)}`,
                        `Comprises ${percentage}% of customer base`
                    ];
                    insight.strengths = [
                        'Largest growth potential',
                        'New customer acquisition target profile',
                        'Can be nurtured to higher tiers'
                    ];
                    insight.concerns = [
                        'Low engagement and purchase frequency',
                        'May be price-sensitive',
                        'Higher churn risk',
                        'Need cost-effective retention strategies'
                    ];
                }

                insights.push(insight);
            });

            return insights;
        }

        // Generate CLV-specific recommendations
        function generateCLVRecommendations(tierSummary, customersByTier) {
            const recommendations = [];

            const platinumTier = tierSummary.find(t => t.clv_tier === 'Platinum');
            const goldTier = tierSummary.find(t => t.clv_tier === 'Gold');
            const silverTier = tierSummary.find(t => t.clv_tier === 'Silver');
            const bronzeTier = tierSummary.find(t => t.clv_tier === 'Bronze');

            // Platinum recommendations
            if (platinumTier) {
                recommendations.push({
                    tier: 'Platinum',
                    priority: 'CRITICAL',
                    title: 'VIP Customer Retention Program',
                    description: `Implement a dedicated VIP program for your ${platinumTier.customer_count} Platinum customers`,
                    actions: [
                        'Assign dedicated account managers',
                        'Provide exclusive early access to new products',
                        'Create invitation-only events and experiences',
                        'Offer premium customer service (24/7 priority support)',
                        'Implement loyalty rewards with high-value perks',
                        'Regular personalized communication and check-ins'
                    ],
                    expectedImpact: 'Prevent churn and maintain high-value relationships',
                    budget: 'High - but justified by revenue contribution'
                });
            }

            // Gold recommendations
            if (goldTier) {
                recommendations.push({
                    tier: 'Gold',
                    priority: 'HIGH',
                    title: 'Platinum Upgrade Campaign',
                    description: `Target ${goldTier.customer_count} Gold customers to upgrade to Platinum tier`,
                    actions: [
                        'Analyze purchase patterns to identify barriers',
                        'Create targeted upsell campaigns',
                        'Offer bundle deals to increase purchase amounts',
                        'Implement graduated loyalty program benefits',
                        'Provide purchase frequency incentives',
                        'Test limited-time premium product access'
                    ],
                    expectedImpact: 'Increase average CLV by 20-30%',
                    budget: 'Medium - focus on conversion incentives'
                });
            }

            // Silver recommendations
            if (silverTier) {
                recommendations.push({
                    tier: 'Silver',
                    priority: 'MEDIUM',
                    title: 'Engagement & Growth Initiative',
                    description: `Activate ${silverTier.customer_count} Silver tier customers to increase spending`,
                    actions: [
                        'Send personalized product recommendations',
                        'Implement email nurture campaigns',
                        'Offer first-purchase-back discounts',
                        'Create educational content about product value',
                        'Test subscription or auto-replenishment programs',
                        'Gamify the shopping experience with points/rewards'
                    ],
                    expectedImpact: 'Move 15-20% to Gold tier within 6 months',
                    budget: 'Medium - automated campaigns with incentives'
                });
            }

            // Bronze recommendations
            if (bronzeTier) {
                recommendations.push({
                    tier: 'Bronze',
                    priority: 'MEDIUM',
                    title: 'Win-Back & Activation Strategy',
                    description: `Re-engage ${bronzeTier.customer_count} Bronze customers cost-effectively`,
                    actions: [
                        'Identify inactive vs. active Bronze customers',
                        'Send win-back campaigns with special offers',
                        'Implement onboarding sequences for new customers',
                        'Create entry-level product bundles',
                        'Use surveys to understand barriers to purchase',
                        'Test low-cost incentives (free shipping, small discounts)'
                    ],
                    expectedImpact: 'Reduce churn and increase activation rate',
                    budget: 'Low - focus on automated, scalable tactics'
                });
            }

            // Cross-tier recommendation
            recommendations.push({
                tier: 'All Tiers',
                priority: 'HIGH',
                title: 'CLV Monitoring Dashboard',
                description: 'Implement real-time CLV tracking and tier movement monitoring',
                actions: [
                    'Set up automated alerts for tier changes',
                    'Track at-risk Platinum customers',
                    'Monitor Gold customers approaching Platinum',
                    'Identify Bronze customers ready to upgrade',
                    'Create monthly CLV performance reports',
                    'Implement predictive churn modeling'
                ],
                expectedImpact: 'Proactive customer management and retention',
                budget: 'Low - mostly technology and dashboard setup'
            });

            return recommendations;
        }

        // Identify risks and opportunities
        function identifyRiskAndOpportunities(tierSummary, customersByTier) {
            const analysis = {
                risks: [],
                opportunities: []
            };

            const platinumTier = tierSummary.find(t => t.clv_tier === 'Platinum');
            const goldTier = tierSummary.find(t => t.clv_tier === 'Gold');
            const silverTier = tierSummary.find(t => t.clv_tier === 'Silver');
            const bronzeTier = tierSummary.find(t => t.clv_tier === 'Bronze');

            // Risks
            if (platinumTier) {
                const platinumPercent = parseFloat(platinumTier.percentage);
                if (platinumPercent < 20) {
                    analysis.risks.push({
                        level: 'HIGH',
                        category: 'Revenue Concentration',
                        description: `Only ${platinumPercent}% of customers are in Platinum tier`,
                        impact: 'Limited high-value customer base increases revenue vulnerability',
                        mitigation: 'Focus on upgrading Gold customers to Platinum'
                    });
                }

                const platinumCLV = parseFloat(platinumTier.avg_clv);
                const goldCLV = goldTier ? parseFloat(goldTier.avg_clv) : 0;
                const clvGap = platinumCLV - goldCLV;

                if (clvGap > platinumCLV * 0.5) {
                    analysis.risks.push({
                        level: 'MEDIUM',
                        category: 'Tier Gap',
                        description: 'Large CLV gap between Platinum and Gold tiers',
                        impact: 'Difficult for Gold customers to upgrade to Platinum',
                        mitigation: 'Create intermediate tier or stepped upgrade program'
                    });
                }
            }

            if (bronzeTier) {
                const bronzePercent = parseFloat(bronzeTier.percentage);
                if (bronzePercent > 30) {
                    analysis.risks.push({
                        level: 'MEDIUM',
                        category: 'Low-Value Concentration',
                        description: `${bronzePercent}% of customers are in Bronze tier`,
                        impact: 'Large portion of customer base contributes minimal revenue',
                        mitigation: 'Implement aggressive Bronze-to-Silver upgrade campaigns'
                    });
                }
            }

            // Opportunities
            if (goldTier) {
                const goldCount = parseInt(goldTier.customer_count);
                const goldCLV = parseFloat(goldTier.avg_clv);
                const platinumCLV = platinumTier ? parseFloat(platinumTier.avg_clv) : goldCLV * 2;
                const potentialRevenue = goldCount * (platinumCLV - goldCLV) * 0.2; // 20% conversion

                analysis.opportunities.push({
                    level: 'HIGH',
                    category: 'Tier Upgrade',
                    description: `${goldCount} Gold customers ready for upgrade`,
                    potential: `₱${formatNumber(potentialRevenue)} additional CLV if 20% upgrade to Platinum`,
                    strategy: 'Targeted upsell campaigns and exclusive offers'
                });
            }

            if (silverTier && goldTier) {
                const silverCount = parseInt(silverTier.customer_count);
                const silverCLV = parseFloat(silverTier.avg_clv);
                const goldCLV = parseFloat(goldTier.avg_clv);
                const potentialRevenue = silverCount * (goldCLV - silverCLV) * 0.15; // 15% conversion

                analysis.opportunities.push({
                    level: 'MEDIUM',
                    category: 'Engagement Growth',
                    description: `${silverCount} Silver customers with growth potential`,
                    potential: `₱${formatNumber(potentialRevenue)} additional CLV if 15% upgrade to Gold`,
                    strategy: 'Email marketing and purchase frequency incentives'
                });
            }

            if (bronzeTier) {
                const bronzeCount = parseInt(bronzeTier.customer_count);
                analysis.opportunities.push({
                    level: 'MEDIUM',
                    category: 'Customer Activation',
                    description: `${bronzeCount} Bronze customers can be activated`,
                    potential: 'Increase in purchase frequency and average order value',
                    strategy: 'Win-back campaigns and onboarding improvements'
                });
            }

            // Cross-sell opportunity
            analysis.opportunities.push({
                level: 'HIGH',
                category: 'Personalization',
                description: 'Tier-based personalization not yet implemented',
                potential: '10-25% increase in overall CLV through targeted experiences',
                strategy: 'Deploy tier-specific marketing automation and product recommendations'
            });

            return analysis;
        }

        // Generate action items
        function generateActionItems(tierSummary) {
            const actionItems = [];

            // Immediate actions (Week 1)
            actionItems.push({
                timeframe: 'Immediate (Week 1)',
                priority: 'CRITICAL',
                actions: [
                    'Export Platinum customer list and review for any service issues',
                    'Set up alerts for Platinum customers showing decreased activity',
                    'Audit current customer service quality for top tier',
                    'Review competitive offerings targeting high-value customers'
                ]
            });

            // Short-term actions (Month 1)
            actionItems.push({
                timeframe: 'Short-term (Month 1)',
                priority: 'HIGH',
                actions: [
                    'Launch VIP recognition program for Platinum customers',
                    'Create Gold-to-Platinum upgrade campaign',
                    'Implement CLV tracking dashboard',
                    'Segment email lists by CLV tier',
                    'Design tier-specific landing pages'
                ]
            });

            // Medium-term actions (Quarter 1)
            actionItems.push({
                timeframe: 'Medium-term (Quarter 1)',
                priority: 'MEDIUM',
                actions: [
                    'Develop tiered loyalty program with graduated benefits',
                    'Launch predictive churn model for Platinum customers',
                    'Create Silver customer engagement campaigns',
                    'Test Bronze customer win-back strategies',
                    'Implement tier-based product recommendations'
                ]
            });

            // Long-term actions (6-12 months)
            actionItems.push({
                timeframe: 'Long-term (6-12 months)',
                priority: 'STRATEGIC',
                actions: [
                    'Build dedicated Platinum customer service team',
                    'Develop tier-specific product lines',
                    'Create exclusive Platinum events and experiences',
                    'Implement AI-driven CLV optimization',
                    'Establish tier movement KPIs and reporting'
                ]
            });

            return actionItems;
        }

        // Utility function to format numbers
        function formatNumber(num) {
            return new Intl.NumberFormat('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(num);
        }

        // Display insights in HTML
        function displayCLVInsights(insights) {
            let html = '<div class="clv-insights-container">';

            // Overview Section
            html += '<div class="insight-section overview-section">';
            html += '<h2>📊 CLV Overview</h2>';
            html += `<p>${insights.overview.message}</p>`;
            html += '<div class="stats-grid">';
            html += `<div class="stat-card">
                        <h3>${formatNumber(insights.overview.totalCustomers)}</h3>
                        <p>Total Customers</p>
                     </div>`;
            html += `<div class="stat-card">
                        <h3>₱${formatNumber(insights.overview.avgCLVOverall)}</h3>
                        <p>Average CLV</p>
                     </div>`;
            html += `<div class="stat-card">
                        <h3>${insights.overview.platinumRevenuePercent}%</h3>
                        <p>Platinum Revenue Share</p>
                     </div>`;
            html += `<div class="stat-card">
                        <h3>${insights.overview.clvRange.ratio}x</h3>
                        <p>Platinum vs Bronze CLV Ratio</p>
                     </div>`;
            html += '</div></div>';

            // Tier-Specific Insights
            html += '<div class="insight-section tier-insights-section">';
            html += '<h2>🎯 Tier-Specific Insights</h2>';
            insights.tierSpecific.forEach(tier => {
                html += `<div class="tier-insight-card tier-${tier.tier.toLowerCase()}">`;
                html += `<h3>${tier.tier} Tier - ${tier.customerCount} customers (${tier.percentage}%)</h3>`;

                html += '<div class="characteristics"><h4>Characteristics:</h4><ul>';
                tier.characteristics.forEach(char => html += `<li>${char}</li>`);
                html += '</ul></div>';

                html += '<div class="strengths"><h4>✓ Strengths:</h4><ul>';
                tier.strengths.forEach(strength => html += `<li>${strength}</li>`);
                html += '</ul></div>';

                html += '<div class="concerns"><h4>⚠ Concerns:</h4><ul>';
                tier.concerns.forEach(concern => html += `<li>${concern}</li>`);
                html += '</ul></div>';

                html += '</div>';
            });
            html += '</div>';

            // Recommendations
            html += '<div class="insight-section recommendations-section">';
            html += '<h2>💡 Strategic Recommendations</h2>';
            insights.recommendations.forEach(rec => {
                const priorityClass = rec.priority.toLowerCase().replace(' ', '-');
                html += `<div class="recommendation-card priority-${priorityClass}">`;
                html += `<div class="rec-header">
                            <h3>${rec.title}</h3>
                            <span class="priority-badge ${priorityClass}">${rec.priority}</span>
                         </div>`;
                html += `<p class="rec-tier"><strong>Target:</strong> ${rec.tier}</p>`;
                html += `<p class="rec-description">${rec.description}</p>`;
                html += '<h4>Action Steps:</h4><ul class="action-list">';
                rec.actions.forEach(action => html += `<li>${action}</li>`);
                html += '</ul>';
                html += `<div class="rec-footer">
                            <p><strong>Expected Impact:</strong> ${rec.expectedImpact}</p>
                            <p><strong>Budget:</strong> ${rec.budget}</p>
                         </div>`;
                html += '</div>';
            });
            html += '</div>';

            // Risks and Opportunities
            html += '<div class="insight-section risk-opportunity-section">';
            html += '<div class="risk-column">';
            html += '<h2>⚠️ Risks</h2>';
            insights.riskOpportunities.risks.forEach(risk => {
                html += `<div class="risk-card risk-${risk.level.toLowerCase()}">`;
                html += `<h3><span class="level-badge">${risk.level}</span> ${risk.category}</h3>`;
                html += `<p><strong>Issue:</strong> ${risk.description}</p>`;
                html += `<p><strong>Impact:</strong> ${risk.impact}</p>`;
                html += `<p><strong>Mitigation:</strong> ${risk.mitigation}</p>`;
                html += '</div>';
            });
            html += '</div>';

            html += '<div class="opportunity-column">';
            html += '<h2>🚀 Opportunities</h2>';
            insights.riskOpportunities.opportunities.forEach(opp => {
                html += `<div class="opportunity-card opp-${opp.level.toLowerCase()}">`;
                html += `<h3><span class="level-badge">${opp.level}</span> ${opp.category}</h3>`;
                html += `<p><strong>Opportunity:</strong> ${opp.description}</p>`;
                html += `<p><strong>Potential:</strong> ${opp.potential}</p>`;
                html += `<p><strong>Strategy:</strong> ${opp.strategy}</p>`;
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';

            // Action Items
            html += '<div class="insight-section action-items-section">';
            html += '<h2>✅ Action Plan</h2>';
            insights.actionItems.forEach(item => {
                html += `<div class="action-item-card priority-${item.priority.toLowerCase()}">`;
                html += `<h3>${item.timeframe} <span class="priority-badge">${item.priority}</span></h3>`;
                html += '<ul class="action-checklist">';
                item.actions.forEach(action => {
                    html += `<li><input type="checkbox"> ${action}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            });
            html += '</div>';

            html += '</div>';

            return html;
        }

        // Initialize CLV insights when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're on CLV tier page
            const segmentationType = '<?= $segmentationType ?>';
            if (segmentationType === 'clv_tier' && typeof clvTierSummary !== 'undefined') {
                // Generate insights
                const tierSummary = <?= json_encode($clvTierSummary ?? []) ?>;
                const customersByTier = <?= json_encode($clvCustomersByTier ?? []) ?>;

                if (tierSummary.length > 0) {
                    const insights = generateCLVInsights(tierSummary, customersByTier);

                    // Create insights container and append to the CLV tier section
                    const insightsHTML = displayCLVInsights(insights);
                    const clvSection = document.querySelector('.clv-insights-container') || document.createElement('div');

                    if (!document.querySelector('.clv-insights-container')) {
                        // Find the CLV tier insights section and append
                        const insightsSection = document.querySelector('.row.mb-4 .col-12 .alert.alert-info');
                        if (insightsSection) {
                            insightsSection.insertAdjacentHTML('afterend', insightsHTML);
                        }
                    }
                }
            }
        });
    </script>

    <!-- CLV Insights CSS -->
    <style>
        .clv-insights-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .insight-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .insight-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 32px;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .stat-card p {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .tier-insight-card {
            border-left: 5px solid #ddd;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .tier-insight-card.tier-platinum {
            border-left-color: #E5E4E2;
            background: #f8f8f8;
        }

        .tier-insight-card.tier-gold {
            border-left-color: #FFD700;
            background: #fffef0;
        }

        .tier-insight-card.tier-silver {
            border-left-color: #C0C0C0;
            background: #f5f5f5;
        }

        .tier-insight-card.tier-bronze {
            border-left-color: #CD7F32;
            background: #fff5ee;
        }

        .tier-insight-card h3 {
            color: #2c3e50;
            margin-top: 0;
        }

        .tier-insight-card h4 {
            color: #34495e;
            margin: 15px 0 10px 0;
            font-size: 16px;
        }

        .tier-insight-card ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .tier-insight-card li {
            margin: 5px 0;
            color: #555;
        }

        .recommendation-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            background: white;
        }

        .recommendation-card.priority-critical {
            border-left: 6px solid #e74c3c;
        }

        .recommendation-card.priority-high {
            border-left: 6px solid #f39c12;
        }

        .recommendation-card.priority-medium {
            border-left: 6px solid #3498db;
        }

        .recommendation-card.priority-strategic {
            border-left: 6px solid #9b59b6;
        }

        .rec-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .rec-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .priority-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .priority-badge.critical {
            background: #e74c3c;
            color: white;
        }

        .priority-badge.high {
            background: #f39c12;
            color: white;
        }

        .priority-badge.medium {
            background: #3498db;
            color: white;
        }

        .priority-badge.strategic {
            background: #9b59b6;
            color: white;
        }

        .rec-tier {
            color: #7f8c8d;
            font-size: 14px;
            margin: 5px 0;
        }

        .rec-description {
            color: #555;
            margin: 15px 0;
            line-height: 1.6;
        }

        .action-list {
            background: #f8f9fa;
            padding: 15px 15px 15px 35px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .action-list li {
            margin: 8px 0;
            color: #2c3e50;
        }

        .rec-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .rec-footer p {
            margin: 8px 0;
            color: #555;
        }

        .risk-opportunity-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .risk-column,
        .opportunity-column {
            min-width: 0;
        }

        .risk-card,
        .opportunity-card {
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }

        .risk-card {
            background: #fee;
            border-left: 4px solid #e74c3c;
        }

        .risk-card.risk-high {
            border-left-color: #c0392b;
        }

        .risk-card.risk-medium {
            border-left-color: #e67e22;
        }

        .opportunity-card {
            background: #efe;
            border-left: 4px solid #27ae60;
        }

        .opportunity-card.opp-high {
            border-left-color: #229954;
        }

        .opportunity-card.opp-medium {
            border-left-color: #52be80;
        }

        .level-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 8px;
        }

        .risk-card .level-badge {
            background: #e74c3c;
            color: white;
        }

        .opportunity-card .level-badge {
            background: #27ae60;
            color: white;
        }

        .action-item-card {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }

        .action-checklist {
            list-style: none;
            padding: 0;
        }

        .action-checklist li {
            padding: 10px;
            margin: 8px 0;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .action-checklist input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .risk-opportunity-section {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>