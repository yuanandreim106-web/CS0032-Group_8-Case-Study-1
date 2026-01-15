<?php

/**
 * Alerts & Anomaly Detection Dashboard
 * Monitors segment health and detects anomalies
 */
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/Logger.php';

// Require authentication
requireLogin();

// Log dashboard access
Logger::info('Alerts dashboard accessed');

// Mock data: In a real app, this would come from your SQL Database
$current_high_value_revenue = 45000;
$previous_week_revenue = 55000; // Previous baseline

// Logic: Detect a "Sudden Drop" (Anomaly)
// Trigger if revenue drops by more than 15%
$drop_threshold = 0.15;
$percentage_change = ($previous_week_revenue - $current_high_value_revenue) / $previous_week_revenue;
$is_anomaly = ($percentage_change > $drop_threshold);

// Log anomaly detection result
if ($is_anomaly) {
    Logger::warning('Anomaly detected', [
        'type' => 'revenue_drop',
        'percentage' => round($percentage_change * 100, 2),
        'segment' => 'High-Value'
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Monitoring | Customer Segmentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .alert-card {
            border-left: 4px solid;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .alert-card.danger {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .alert-card.success {
            border-left-color: #198754;
            background: #f5fff5;
        }

        .alert-card.warning {
            border-left-color: #ffc107;
            background: #fffef5;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .metric-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #0d6efd;
        }
    </style>
</head>

<body>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm" style="background: linear-gradient(90deg, #0d6efd 0%, #0b5ed7 100%) !important;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
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
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="reportsDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: rgba(255,255,255,0.3);">
                            Reports
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="reportsDropdown" style="border: none; border-radius: 6px;">
                            <li><a class="dropdown-item" href="executive_dashboard.php"><i class="fas fa-tachometer-alt" style="margin-right: 8px;"></i>Executive Dashboard</a></li>
                            <li><a class="dropdown-item" href="executive_summary.php"><i class="fas fa-chart-area" style="margin-right: 8px;"></i>Executive Summary</a></li>
                            <li><a class="dropdown-item" href="alerts.php"><i class="fas fa-bell" style="margin-right: 8px;"></i>Alerts & Monitoring</a></li>
                            <li><a class="dropdown-item" href="performance.php"><i class="fas fa-gauge-high" style="margin-right: 8px;"></i>System Performance</a></li>
                        </ul>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="../logout.php" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,0.3);">
                            <i class="fas fa-sign-out-alt" style="margin-right: 4px;"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Back to Main Dashboard -->
        <div class="mb-3">
            <a href="../index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Main Dashboard
            </a>
        </div>

        <h2 class="mb-4">Alerts & Anomaly Detection</h2>
    </div>

    <div class="container-fluid py-4">

        <!-- Main Alert -->
        <div class="row mb-4">
            <div class="col-12">
                <?php if ($is_anomaly): ?>
                    <div class="alert-card danger p-4">
                        <div class="d-flex align-items-start">
                            <div style="font-size: 32px; margin-right: 20px;">⚠️</div>
                            <div class="flex-grow-1">
                                <h4 class="text-danger fw-bold mb-2">Anomaly Detected!</h4>
                                <p class="mb-2">
                                    Sudden <strong><?php echo round($percentage_change * 100); ?>% drop</strong> in High-Value Segment revenue detected compared to last week.
                                </p>
                                <p class="text-muted small mb-3">
                                    Current Revenue: <strong>$<?= number_format($current_high_value_revenue, 0) ?></strong> vs
                                    Previous Week: <strong>$<?= number_format($previous_week_revenue, 0) ?></strong>
                                </p>
                                <div>
                                    <button class="btn btn-danger btn-sm" onclick="alert('Migration report generated')">Generate Migration Report</button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('Alert acknowledged')">Acknowledge Alert</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert-card success p-4">
                        <div class="d-flex align-items-start">
                            <div style="font-size: 32px; margin-right: 20px;">✅</div>
                            <div class="flex-grow-1">
                                <h4 class="text-success fw-bold mb-2">All Systems Normal</h4>
                                <p class="mb-0">
                                    Segment migration is within normal variance (+/- 5%). No anomalies detected.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monitoring Metrics -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">Current Segment Health</h5>
                <div class="metrics-grid">
                    <div class="metric-item">
                        <small class="text-muted">High-Value Revenue</small>
                        <h6 class="mb-0">$<?= number_format($current_high_value_revenue, 0) ?></h6>
                        <span class="badge bg-danger small mt-1">-<?= round($percentage_change * 100) ?>%</span>
                    </div>
                    <div class="metric-item">
                        <small class="text-muted">Previous Week</small>
                        <h6 class="mb-0">$<?= number_format($previous_week_revenue, 0) ?></h6>
                        <span class="badge bg-secondary small mt-1">Baseline</span>
                    </div>
                    <div class="metric-item">
                        <small class="text-muted">Churn Rate</small>
                        <h6 class="mb-0">3.1%</h6>
                        <span class="badge bg-warning small mt-1">Monitor</span>
                    </div>
                    <div class="metric-item">
                        <small class="text-muted">Retention Score</small>
                        <h6 class="mb-0">94.2%</h6>
                        <span class="badge bg-success small mt-1">Healthy</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert History -->
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">Recent Alerts</h5>
                <div class="card border-0 shadow-sm">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Alert Type</th>
                                <th>Severity</th>
                                <th>Segment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo date('Y-m-d H:i'); ?></td>
                                <td>Revenue Drop</td>
                                <td><span class="badge bg-danger">Critical</span></td>
                                <td>High-Value</td>
                                <td><span class="badge bg-warning">Pending Review</span></td>
                            </tr>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', time() - 3600); ?></td>
                                <td>Churn Increase</td>
                                <td><span class="badge bg-warning">Warning</span></td>
                                <td>Standard</td>
                                <td><span class="badge bg-success">Acknowledged</span></td>
                            </tr>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', time() - 7200); ?></td>
                                <td>Low Activity</td>
                                <td><span class="badge bg-info">Info</span></td>
                                <td>Budget</td>
                                <td><span class="badge bg-secondary">Resolved</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>