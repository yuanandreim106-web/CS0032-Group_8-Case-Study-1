<?php

/**
 * K-Means Customer Clustering Script (Pure PHP Implementation)
 * ============================================================
 * This script performs k-means clustering on customer data without
 * requiring Python or any external dependencies.
 *
 * Can be run via:
 * - Command line: php run_clustering.php [num_clusters]
 * - Web browser: run_clustering.php?clusters=5
 * - Triggered from dashboard
 *
 * @author Customer Segmentation Dashboard
 * @version 1.0
 */

// Prevent script timeout for large datasets
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once 'db.php';

// Configuration
define('DEFAULT_CLUSTERS', 5);
define('MAX_ITERATIONS', 300);
define('CONVERGENCE_THRESHOLD', 0.0001);
define('RANDOM_SEED', 42);

// ============================================================================
// K-Means Algorithm Implementation
// ============================================================================

class KMeansClustering
{

    private $k;
    private $maxIterations;
    private $convergenceThreshold;
    private $centroids = [];
    private $clusters = [];

    public function __construct($k = 5, $maxIterations = 300, $convergenceThreshold = 0.0001)
    {
        $this->k = $k;
        $this->maxIterations = $maxIterations;
        $this->convergenceThreshold = $convergenceThreshold;
    }

    /**
     * Normalize features using z-score normalization
     */
    public function normalizeData($data)
    {
        $normalized = [];
        $features = ['age', 'income', 'purchase_amount'];
        $stats = [];

        // Calculate mean and standard deviation for each feature
        foreach ($features as $feature) {
            $values = array_column($data, $feature);
            $mean = array_sum($values) / count($values);
            $variance = 0;
            foreach ($values as $value) {
                $variance += pow($value - $mean, 2);
            }
            $stdDev = sqrt($variance / count($values));

            $stats[$feature] = ['mean' => $mean, 'stdDev' => $stdDev];
        }

        // Normalize each data point
        foreach ($data as $point) {
            $normalizedPoint = $point;
            foreach ($features as $feature) {
                $normalizedPoint[$feature] = ($point[$feature] - $stats[$feature]['mean']) /
                    ($stats[$feature]['stdDev'] ?: 1);
            }
            $normalized[] = $normalizedPoint;
        }

        return $normalized;
    }

    /**
     * Calculate Euclidean distance between two points
     */
    private function euclideanDistance($point1, $point2)
    {
        $sum = 0;
        $sum += pow($point1['age'] - $point2['age'], 2);
        $sum += pow($point1['income'] - $point2['income'], 2);
        $sum += pow($point1['purchase_amount'] - $point2['purchase_amount'], 2);
        return sqrt($sum);
    }

    /**
     * Initialize centroids using k-means++ algorithm
     */
    private function initializeCentroids($data)
    {
        srand(RANDOM_SEED);
        $centroids = [];

        // Choose first centroid randomly
        $centroids[] = $data[array_rand($data)];

        // Choose remaining centroids with probability proportional to distance
        for ($i = 1; $i < $this->k; $i++) {
            $distances = [];

            foreach ($data as $point) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($centroids as $centroid) {
                    $dist = $this->euclideanDistance($point, $centroid);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                    }
                }
                $distances[] = $minDist * $minDist;
            }

            $sum = array_sum($distances);
            $rand = mt_rand() / mt_getrandmax() * $sum;
            $cumulative = 0;

            foreach ($distances as $idx => $dist) {
                $cumulative += $dist;
                if ($cumulative >= $rand) {
                    $centroids[] = $data[$idx];
                    break;
                }
            }
        }

        return $centroids;
    }

    /**
     * Assign each point to the nearest centroid
     */
    private function assignClusters($data, $centroids)
    {
        $clusters = array_fill(0, $this->k, []);

        foreach ($data as $point) {
            $minDist = PHP_FLOAT_MAX;
            $clusterIndex = 0;

            foreach ($centroids as $idx => $centroid) {
                $dist = $this->euclideanDistance($point, $centroid);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $clusterIndex = $idx;
                }
            }

            $clusters[$clusterIndex][] = $point;
        }

        return $clusters;
    }

    /**
     * Update centroids based on cluster means
     */
    private function updateCentroids($clusters)
    {
        $centroids = [];

        foreach ($clusters as $cluster) {
            if (empty($cluster)) {
                // If cluster is empty, keep old centroid or reinitialize
                $centroids[] = $this->centroids[count($centroids)];
                continue;
            }

            $centroid = [
                'age' => array_sum(array_column($cluster, 'age')) / count($cluster),
                'income' => array_sum(array_column($cluster, 'income')) / count($cluster),
                'purchase_amount' => array_sum(array_column($cluster, 'purchase_amount')) / count($cluster)
            ];

            $centroids[] = $centroid;
        }

        return $centroids;
    }

    /**
     * Check if centroids have converged
     */
    private function hasConverged($oldCentroids, $newCentroids)
    {
        for ($i = 0; $i < $this->k; $i++) {
            $dist = $this->euclideanDistance($oldCentroids[$i], $newCentroids[$i]);
            if ($dist > $this->convergenceThreshold) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run k-means clustering algorithm
     */
    public function fit($data)
    {
        // Normalize data
        $normalizedData = $this->normalizeData($data);

        // Initialize centroids
        $this->centroids = $this->initializeCentroids($normalizedData);

        // Iterate until convergence or max iterations
        for ($iteration = 0; $iteration < $this->maxIterations; $iteration++) {
            // Assign clusters
            $this->clusters = $this->assignClusters($normalizedData, $this->centroids);

            // Update centroids
            $newCentroids = $this->updateCentroids($this->clusters);

            // Check convergence
            if ($this->hasConverged($this->centroids, $newCentroids)) {
                echo "✓ Converged after " . ($iteration + 1) . " iterations\n";
                break;
            }

            $this->centroids = $newCentroids;
        }

        // Assign cluster labels to original data (using normalized data for distance)
        $labels = [];
        foreach ($normalizedData as $idx => $point) {
            $minDist = PHP_FLOAT_MAX;
            $label = 0;

            foreach ($this->centroids as $clusterIdx => $centroid) {
                $dist = $this->euclideanDistance($point, $centroid);
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $label = $clusterIdx;
                }
            }

            $labels[$data[$idx]['customer_id']] = $label;
        }

        return $labels;
    }
}

// ============================================================================
// Cluster Analysis Functions
// ============================================================================

function getAgeCategory($avgAge)
{
    if ($avgAge < 30) return "Young";
    if ($avgAge < 45) return "Middle-Aged";
    if ($avgAge < 60) return "Mature";
    return "Senior";
}

function getIncomeCategory($avgIncome)
{
    if ($avgIncome < 30000) return "Budget";
    if ($avgIncome < 50000) return "Mid-Tier";
    if ($avgIncome < 70000) return "Affluent";
    return "High-Income";
}

function getSpendingCategory($avgPurchase)
{
    if ($avgPurchase < 1500) return "Conservative";
    if ($avgPurchase < 2500) return "Moderate";
    if ($avgPurchase < 3500) return "Active";
    return "Premium";
}

function generateClusterName($avgAge, $avgIncome, $avgPurchase)
{
    return getIncomeCategory($avgIncome) . " " .
        getAgeCategory($avgAge) . " " .
        getSpendingCategory($avgPurchase);
}

function generateClusterDescription($clusterStats)
{
    return sprintf(
        "This segment consists of %s customers characterized by %s demographics (avg age %.1f), " .
            "%s income levels (avg $%s), and %s spending behavior (avg $%s per purchase).",
        number_format($clusterStats['customer_count']),
        strtolower(getAgeCategory($clusterStats['avg_age'])),
        $clusterStats['avg_age'],
        strtolower(getIncomeCategory($clusterStats['avg_income'])),
        number_format($clusterStats['avg_income'], 0),
        strtolower(getSpendingCategory($clusterStats['avg_purchase_amount'])),
        number_format($clusterStats['avg_purchase_amount'], 0)
    );
}

function generateBusinessRecommendations($clusterStats)
{
    $recommendations = [];

    $avgIncome = $clusterStats['avg_income'];
    $avgPurchase = $clusterStats['avg_purchase_amount'];
    $avgAge = $clusterStats['avg_age'];

    // High-value customers
    if ($avgIncome > 70000 && $avgPurchase > 3000) {
        $recommendations[] = "Target with premium product offerings and exclusive services";
        $recommendations[] = "Implement VIP loyalty program with personalized benefits";
        $recommendations[] = "Focus on high-touch customer service and relationship building";
        $recommendations[] = "Offer premium financing options and extended warranties";
    }
    // High income, low purchase (untapped potential)
    elseif ($avgIncome > 70000 && $avgPurchase < 2000) {
        $recommendations[] = "Identify barriers to purchase and address them through targeted campaigns";
        $recommendations[] = "Introduce mid-tier to premium product lines to match income level";
        $recommendations[] = "Provide educational content about product value propositions";
        $recommendations[] = "Test promotional offers to convert high-income browsers to buyers";
    }
    // Young customers
    elseif ($avgAge < 30) {
        $recommendations[] = "Leverage social media marketing and influencer partnerships";
        $recommendations[] = "Offer entry-level product bundles and flexible payment plans";
        $recommendations[] = "Create referral programs with incentives for word-of-mouth marketing";
        $recommendations[] = "Develop mobile-first shopping experiences and apps";
    }
    // Middle-aged customers
    elseif ($avgAge >= 30 && $avgAge < 55) {
        $recommendations[] = "Focus on value proposition and quality messaging";
        $recommendations[] = "Offer family-oriented products and bundled solutions";
        $recommendations[] = "Implement email marketing with personalized recommendations";
        $recommendations[] = "Provide loyalty rewards that accumulate over time";
    }
    // Senior customers
    elseif ($avgAge >= 55) {
        $recommendations[] = "Emphasize ease of use, reliability, and customer support";
        $recommendations[] = "Provide clear documentation and instructional content";
        $recommendations[] = "Offer phone-based customer service and personal assistance";
        $recommendations[] = "Focus on products that enhance comfort and convenience";
    }
    // Budget-conscious customers
    elseif ($avgIncome < 40000) {
        $recommendations[] = "Highlight value pricing and cost-saving benefits";
        $recommendations[] = "Offer payment plans and budget-friendly options";
        $recommendations[] = "Create promotional campaigns around seasonal sales";
        $recommendations[] = "Develop entry-level product lines with strong quality-to-price ratio";
    }
    // Moderate spenders (general recommendations)
    else {
        $recommendations[] = "Implement cross-selling strategies based on purchase history";
        $recommendations[] = "Create targeted email campaigns with personalized offers";
        $recommendations[] = "Develop customer retention programs with periodic incentives";
        $recommendations[] = "Test upselling opportunities with complementary products";
    }

    return implode('; ', $recommendations);
}

// ============================================================================
// Database Functions
// ============================================================================

function extractCustomerData($pdo)
{
    try {
        $sql = "SELECT customer_id, age, gender, income, purchase_amount, region
                FROM customers
                WHERE age IS NOT NULL
                  AND income IS NOT NULL
                  AND purchase_amount IS NOT NULL";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✓ Extracted " . count($data) . " customer records\n";
        return $data;
    } catch (PDOException $e) {
        die("✗ Error extracting data: " . $e->getMessage() . "\n");
    }
}

function calculateClusterStatistics($pdo, $labels, $k)
{
    $clusterStats = [];

    for ($i = 0; $i < $k; $i++) {
        // Get customer IDs for this cluster
        $customerIds = array_keys(array_filter($labels, function ($label) use ($i) {
            return $label === $i;
        }));

        if (empty($customerIds)) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $sql = "SELECT
                    COUNT(*) as customer_count,
                    ROUND(AVG(age), 2) as avg_age,
                    MIN(age) as age_min,
                    MAX(age) as age_max,
                    ROUND(AVG(income), 2) as avg_income,
                    ROUND(MIN(income), 2) as income_min,
                    ROUND(MAX(income), 2) as income_max,
                    ROUND(AVG(purchase_amount), 2) as avg_purchase_amount,
                    ROUND(MIN(purchase_amount), 2) as purchase_min,
                    ROUND(MAX(purchase_amount), 2) as purchase_max
                FROM customers
                WHERE customer_id IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($customerIds);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get dominant gender
        $sql = "SELECT gender, COUNT(*) as cnt
                FROM customers
                WHERE customer_id IN ($placeholders)
                GROUP BY gender
                ORDER BY cnt DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($customerIds);
        $genderRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['dominant_gender'] = $genderRow['gender'] ?? 'Unknown';

        // Get dominant region
        $sql = "SELECT region, COUNT(*) as cnt
                FROM customers
                WHERE customer_id IN ($placeholders)
                GROUP BY region
                ORDER BY cnt DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($customerIds);
        $regionRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['dominant_region'] = $regionRow['region'] ?? 'Unknown';

        $stats['cluster_id'] = $i;
        $clusterStats[] = $stats;
    }

    return $clusterStats;
}

function updateDatabase($pdo, $labels, $clusterStats)
{
    try {
        $pdo->beginTransaction();

        // Clear existing segmentation results
        $pdo->exec("DELETE FROM segmentation_results");
        echo "✓ Cleared existing segmentation results\n";

        // Insert new cluster assignments
        $stmt = $pdo->prepare("INSERT INTO segmentation_results (customer_id, cluster_label) VALUES (?, ?)");
        foreach ($labels as $customerId => $label) {
            $stmt->execute([$customerId, $label]);
        }
        echo "✓ Inserted " . count($labels) . " cluster assignments\n";

        // Clear existing cluster metadata
        $pdo->exec("DELETE FROM cluster_metadata");
        echo "✓ Cleared existing cluster metadata\n";

        // Insert new cluster metadata
        $stmt = $pdo->prepare("
            INSERT INTO cluster_metadata (
                cluster_id, cluster_name, description,
                avg_age, avg_income, avg_purchase_amount,
                customer_count, age_min, age_max,
                income_min, income_max, purchase_min, purchase_max,
                dominant_gender, dominant_region, business_recommendation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($clusterStats as $stats) {
            $clusterName = generateClusterName($stats['avg_age'], $stats['avg_income'], $stats['avg_purchase_amount']);
            $description = generateClusterDescription($stats);
            $recommendations = generateBusinessRecommendations($stats);

            $stmt->execute([
                $stats['cluster_id'],
                $clusterName,
                $description,
                $stats['avg_age'],
                $stats['avg_income'],
                $stats['avg_purchase_amount'],
                $stats['customer_count'],
                $stats['age_min'],
                $stats['age_max'],
                $stats['income_min'],
                $stats['income_max'],
                $stats['purchase_min'],
                $stats['purchase_max'],
                $stats['dominant_gender'],
                $stats['dominant_region'],
                $recommendations
            ]);
        }

        echo "✓ Inserted metadata for " . count($clusterStats) . " clusters\n";

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("✗ Error updating database: " . $e->getMessage() . "\n");
    }
}

// ============================================================================
// Main Execution
// ============================================================================

// Determine if running from CLI or web
$isCLI = php_sapi_name() === 'cli';

// Check for export request BEFORE any output
$exportRequested = false;
$exportType = null;
if (!$isCLI && isset($_GET['export']) && isset($_GET['format'])) {
    $exportRequested = true;
    $exportType = $_GET['format']; // 'csv' or 'pdf'
}

// Get number of clusters
$numClusters = DEFAULT_CLUSTERS;
if ($isCLI && isset($argv[1])) {
    $numClusters = (int)$argv[1];
} elseif (!$isCLI && isset($_GET['clusters'])) {
    $numClusters = (int)$_GET['clusters'];
}

if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>K-Means Clustering</title>";
    echo "<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}";
    echo ".success{color:#4ec9b0;} .error{color:#f48771;} .info{color:#569cd6;}</style></head><body>";
    echo "<h2>K-Means Customer Clustering</h2><pre>";

    // Start output buffering if exporting
    if ($exportRequested) {
        ob_start();
    }
}

echo str_repeat("=", 70) . "\n";
echo "K-MEANS CUSTOMER CLUSTERING (PHP)\n";
echo str_repeat("=", 70) . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Number of clusters: $numClusters\n";
echo str_repeat("=", 70) . "\n\n";

// Step 1: Extract customer data
echo "STEP 1: Extracting customer data...\n";
$customerData = extractCustomerData($pdo);
echo "\n";

// Step 2: Run k-means clustering
echo "STEP 2: Running k-means clustering...\n";
$kmeans = new KMeansClustering($numClusters, MAX_ITERATIONS, CONVERGENCE_THRESHOLD);
$labels = $kmeans->fit($customerData);
echo "✓ Clustering complete\n\n";

// Step 3: Calculate cluster statistics
echo "STEP 3: Calculating cluster statistics...\n";
$clusterStats = calculateClusterStatistics($pdo, $labels, $numClusters);
echo "✓ Statistics calculated for " . count($clusterStats) . " clusters\n\n";

// Step 4: Update database
echo "STEP 4: Updating database...\n";
updateDatabase($pdo, $labels, $clusterStats);
echo "\n";

// Step 5: Display summary
echo str_repeat("=", 70) . "\n";
echo "CLUSTERING SUMMARY\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($clusterStats as $stats) {
    $clusterName = generateClusterName($stats['avg_age'], $stats['avg_income'], $stats['avg_purchase_amount']);
    echo "Cluster {$stats['cluster_id']}: $clusterName\n";
    echo "  Customers: " . number_format($stats['customer_count']) . "\n";
    echo "  Age: {$stats['avg_age']} ({$stats['age_min']}-{$stats['age_max']})\n";
    echo "  Income: $" . number_format($stats['avg_income'], 0) .
        " ($" . number_format($stats['income_min'], 0) . "-$" . number_format($stats['income_max'], 0) . ")\n";
    echo "  Purchase: $" . number_format($stats['avg_purchase_amount'], 0) .
        " ($" . number_format($stats['purchase_min'], 0) . "-$" . number_format($stats['purchase_max'], 0) . ")\n";
    echo "  Gender: {$stats['dominant_gender']}, Region: {$stats['dominant_region']}\n\n";
}

echo str_repeat("=", 70) . "\n";
echo "✓ Clustering complete! $numClusters clusters created.\n";
echo str_repeat("=", 70) . "\n";

if (!$isCLI) {
    // Step 6: Export if requested
    if ($exportRequested && $exportType) {
        // Clean output buffer before sending download headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Fetch cluster data with customer details for export
        try {
            $exportQuery = "
                SELECT 
                    c.customer_id,
                    c.name,
                    c.age,
                    c.gender,
                    c.income,
                    c.region,
                    c.purchase_amount,
                    sr.cluster_label,
                    cm.cluster_id,
                    cm.cluster_name,
                    cm.avg_age,
                    cm.avg_income,
                    cm.avg_purchase_amount,
                    cm.customer_count as cluster_customer_count,
                    cm.business_recommendation
                FROM customers c
                JOIN segmentation_results sr ON c.customer_id = sr.customer_id
                JOIN cluster_metadata cm ON sr.cluster_label = cm.cluster_id
                ORDER BY sr.cluster_label, c.customer_id
            ";

            $stmt = $pdo->query($exportQuery);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch cluster metadata for HTML report
            $metadataQuery = "SELECT * FROM cluster_metadata ORDER BY cluster_id";
            $metadataStmt = $pdo->query($metadataQuery);
            $clusterMetadata = $metadataStmt->fetchAll(PDO::FETCH_ASSOC);

            // Generate file
            $fileName = generateClusteringFileName($numClusters, $exportType);
            $filePath = 'exports/' . $fileName;

            // Ensure exports directory exists
            if (!is_dir('exports')) {
                mkdir('exports', 0755, true);
            }

            if ($exportType === 'csv') {
                // Export as CSV
                $handle = fopen($filePath, 'w');

                if ($handle && !empty($exportData)) {
                    // Write headers
                    fputcsv($handle, array_keys($exportData[0]));

                    // Write data rows
                    foreach ($exportData as $row) {
                        fputcsv($handle, $row);
                    }

                    fclose($handle);

                    // Send file for download
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $fileName . '"');
                    header('Content-Length: ' . filesize($filePath));
                    readfile($filePath);
                    exit;
                } else {
                    throw new Exception("No data to export or could not create file");
                }
            } else if ($exportType === 'pdf') {
                // Export as HTML (can be printed to PDF)
                $htmlContent = generateClusteringHTML($numClusters, $clusterMetadata, $exportData);
                file_put_contents($filePath, $htmlContent);

                // Send file for download/display
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Disposition: inline; filename="' . $fileName . '"');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        } catch (Exception $e) {
            // If export fails, show error in browser
            echo "✗ Export failed: " . $e->getMessage() . "\n";
        }
    }

    echo "</pre>";
    echo "<div style='margin-top: 20px; padding: 15px; background: #2d2d2d; border-radius: 5px;'>";
    echo "<h3 style='color: #4ec9b0; margin-top: 0;'>Export Clustering Results</h3>";
    echo "<p style='margin: 10px 0;'>";
    echo "<a href='run_clustering.php?clusters=" . $numClusters . "&export=1&format=csv' style='display: inline-block; margin-right: 10px; padding: 10px 15px; background: #0d6efd; color: white; text-decoration: none; border-radius: 4px;'>📥 Download as CSV</a>";
    echo "<a href='run_clustering.php?clusters=" . $numClusters . "&export=1&format=pdf' style='display: inline-block; padding: 10px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;'>📄 Generate PDF Report</a>";
    echo "</p>";
    echo "</div>";
    echo "<p><a href='index.php' style='color:#569cd6;'>← Back to Dashboard</a></p>";
    echo "</body></html>";
}

/**
 * Generate clustering export filename
 */
function generateClusteringFileName($numClusters, $format)
{
    $timestamp = date('Y-m-d_H-i-s');
    $extension = ($format === 'csv') ? 'csv' : 'html';
    return "clustering_{$numClusters}clusters_{$timestamp}.{$extension}";
}

/**
 * Generate HTML report for clustering results
 */
function generateClusteringHTML($numClusters, $clusterMetadata, $exportData)
{
    $title = "K-Means Clustering Report ({$numClusters} Clusters)";
    $generated = date('Y-m-d H:i:s');
    $totalCustomers = count($exportData);

    // Calculate summary statistics
    $totalIncome = 0;
    $totalAge = 0;
    $totalPurchase = 0;

    foreach ($exportData as $row) {
        $totalIncome += $row['income'];
        $totalAge += $row['age'];
        $totalPurchase += $row['purchase_amount'];
    }

    $avgIncome = $totalCustomers > 0 ? $totalIncome / $totalCustomers : 0;
    $avgAge = $totalCustomers > 0 ? $totalAge / $totalCustomers : 0;
    $avgPurchase = $totalCustomers > 0 ? $totalPurchase / $totalCustomers : 0;

    $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$title}</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 5px;
        }
        .report-meta {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .report-meta p {
            margin: 5px 0;
        }
        h2 {
            color: #0b5ed7;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
        }
        .summary-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        table { 
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 30px;
        }
        th { 
            background-color: #0d6efd;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #0d6efd;
        }
        td { 
            border: 1px solid #ddd;
            padding: 10px 12px;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .cluster-section {
            page-break-inside: avoid;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
        }
        .cluster-section h3 {
            margin-top: 0;
            color: #0b5ed7;
        }
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #0d6efd;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #0d6efd;
            margin-top: 5px;
        }
        .recommendation {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            overflow: hidden;
        }
        .recommendation p {
            margin: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            max-width: 100%;
            min-width: 0;
        }
        @media print {
            body { background: white; }
            .container { box-shadow: none; padding: 0; }
            .cluster-section { page-break-inside: avoid; }
            .recommendation { word-wrap: break-word; }
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>{$title}</h1>
        
        <div class='report-meta'>
            <p><strong>Generated:</strong> {$generated}</p>
            <p><strong>Number of Clusters:</strong> {$numClusters}</p>
            <p><strong>Total Customers Analyzed:</strong> " . number_format($totalCustomers) . "</p>
        </div>
        
        <h2>Executive Summary</h2>
        <div class='summary-cards'>
            <div class='summary-card'>
                <h3>Total Customers</h3>
                <div class='value'>" . number_format($totalCustomers) . "</div>
            </div>
            <div class='summary-card'>
                <h3>Avg Age</h3>
                <div class='value'>" . number_format($avgAge, 0) . "</div>
            </div>
            <div class='summary-card'>
                <h3>Avg Income</h3>
                <div class='value'>\$" . number_format($avgIncome, 0) . "</div>
            </div>
            <div class='summary-card'>
                <h3>Avg Purchase</h3>
                <div class='value'>\$" . number_format($avgPurchase, 0) . "</div>
            </div>
        </div>
        
        <h2>Cluster Details</h2>";

    foreach ($clusterMetadata as $stats) {
        $clusterName = isset($stats['cluster_name']) ? $stats['cluster_name'] : 'Unknown Cluster';
        $businessRec = isset($stats['business_recommendation']) ? $stats['business_recommendation'] : 'No recommendation available';

        $html .= "
        <div class='cluster-section'>
            <h3>Cluster {$stats['cluster_id']}: {$clusterName}</h3>
            
            <div class='stat-row'>
                <div class='stat-item'>
                    <div class='stat-label'>Customers in Cluster</div>
                    <div class='stat-value'>" . number_format($stats['customer_count']) . "</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Average Age</div>
                    <div class='stat-value'>" . number_format($stats['avg_age'], 0) . " years</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Average Income</div>
                    <div class='stat-value'>\$" . number_format($stats['avg_income'], 0) . "</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Average Purchase</div>
                    <div class='stat-value'>\$" . number_format($stats['avg_purchase_amount'], 0) . "</div>
                </div>
            </div>
            
            <div class='stat-row'>
                <div class='stat-item'>
                    <div class='stat-label'>Age Range</div>
                    <div class='stat-value'>" . number_format($stats['age_min'], 0) . " - " . number_format($stats['age_max'], 0) . "</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Income Range</div>
                    <div class='stat-value'>\$" . number_format($stats['income_min'], 0) . " - \$" . number_format($stats['income_max'], 0) . "</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Purchase Range</div>
                    <div class='stat-value'>\$" . number_format($stats['purchase_min'], 0) . " - \$" . number_format($stats['purchase_max'], 0) . "</div>
                </div>
            </div>
            
            <div class='stat-row'>
                <div class='stat-item'>
                    <div class='stat-label'>Dominant Gender</div>
                    <div class='stat-value'>" . ucfirst($stats['dominant_gender']) . "</div>
                </div>
                <div class='stat-item'>
                    <div class='stat-label'>Dominant Region</div>
                    <div class='stat-value'>" . ucfirst($stats['dominant_region']) . "</div>
                </div>
            </div>
            
            <div class='recommendation'>
                <strong>Business Recommendation:</strong><br>
                {$businessRec}
            </div>
        </div>";
    }

    $html .= "
        <h2>Customer Data by Cluster</h2>
        <table>
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Income</th>
                    <th>Region</th>
                    <th>Purchase Amount</th>
                    <th>Cluster</th>
                    <th>Cluster Name</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($exportData as $row) {
        $html .= "
                <tr>
                    <td>{$row['customer_id']}</td>
                    <td>{$row['name']}</td>
                    <td>" . number_format($row['age'], 0) . "</td>
                    <td>" . ucfirst($row['gender']) . "</td>
                    <td>\$" . number_format($row['income'], 0) . "</td>
                    <td>" . ucfirst($row['region']) . "</td>
                    <td>\$" . number_format($row['purchase_amount'], 0) . "</td>
                    <td>{$row['cluster_id']}</td>
                    <td>{$row['cluster_name']}</td>
                </tr>";
    }

    $html .= "
            </tbody>
        </table>
        
        <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;'>
            <p>This report was automatically generated by the Customer Segmentation System.</p>
        </div>
    </div>
</body>
</html>";

    return $html;
}
