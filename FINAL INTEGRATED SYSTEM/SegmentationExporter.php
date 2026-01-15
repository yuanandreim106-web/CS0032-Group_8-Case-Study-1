<?php

/**
 * Simplified Customer Segmentation Export System
 * Basic CSV export with HTML fallback for PDF/Excel
 * No external library dependencies
 */

// Include logger for export tracking
require_once __DIR__ . '/includes/Logger.php';

class SegmentationExporter
{
    private $conn;
    private $userId;
    private $exportType;
    private $segmentationType;
    private $selectedColumns;
    private $data;

    /**
     * Constructor
     * @param PDO $connection Database connection
     * @param int $userId Current user ID
     */
    public function __construct($connection, $userId)
    {
        $this->conn = $connection;
        $this->userId = $userId;
    }

    /**
     * Main export function
     * @param string $exportType Type of export (csv, pdf, excel)
     * @param string $segmentationType Type of segmentation
     * @param array $selectedColumns Array of column names to export
     * @param array $filters Optional filters for data
     * @return array Result with file path and export ID
     */
    public function export($exportType, $segmentationType, $selectedColumns = [], $filters = [])
    {
        try {
            // Log export request
            Logger::info('Export initiated', [
                'type' => $exportType,
                'segmentation' => $segmentationType,
                'user_id' => $this->userId
            ]);

            // Validate inputs
            $this->validateExportRequest($exportType, $segmentationType, $selectedColumns);

            // Store export parameters
            $this->exportType = $exportType;
            $this->segmentationType = $segmentationType;
            $this->selectedColumns = $selectedColumns;

            // Fetch data based on segmentation type
            $this->data = $this->fetchSegmentationData($segmentationType, $filters);

            // Generate appropriate export
            $filePath = $this->generateExport();

            // Track export in history
            $exportId = $this->trackExportHistory($filePath);

            // Log successful export
            Logger::info('Export completed successfully', [
                'export_id' => $exportId,
                'file_path' => $filePath,
                'record_count' => count($this->data)
            ]);

            return [
                'success' => true,
                'file_path' => $filePath,
                'export_id' => $exportId,
                'file_name' => basename($filePath),
                'record_count' => count($this->data)
            ];
        } catch (Exception $e) {
            // Log export error
            Logger::error('Export failed', [
                'error' => $e->getMessage(),
                'type' => $exportType,
                'segmentation' => $segmentationType
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate export request
     */
    private function validateExportRequest($exportType, $segmentationType, $selectedColumns)
    {
        $validTypes = ['csv', 'pdf', 'excel'];
        if (!in_array($exportType, $validTypes)) {
            throw new Exception("Invalid export type. Supported: " . implode(', ', $validTypes));
        }

        $validSegmentations = ['cluster', 'clv_tier', 'rfm', 'gender', 'region', 'age_group', 'income_bracket', 'purchase_tier'];
        if (!in_array($segmentationType, $validSegmentations)) {
            throw new Exception("Invalid segmentation type");
        }

        if (empty($selectedColumns)) {
            throw new Exception("No columns selected for export");
        }
    }

    /**
     * Fetch segmentation data based on type
     */
    private function fetchSegmentationData($segmentationType, $filters = [])
    {
        $query = $this->buildQuery($segmentationType, $filters);
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Build query based on segmentation type
     */
    private function buildQuery($segmentationType, $filters)
    {
        $baseQuery = "SELECT ";

        // Build select clause based on segmentation type and selected columns
        $selectClause = $this->buildSelectClause($segmentationType);
        $baseQuery .= $selectClause;

        // Build from clause
        $fromClause = $this->buildFromClause($segmentationType);
        $baseQuery .= $fromClause;

        // Add where clause for filters
        $whereClause = $this->buildWhereClause($filters);
        if (!empty($whereClause)) {
            $baseQuery .= " WHERE " . $whereClause;
        }

        // Add order by
        $baseQuery .= $this->buildOrderByClause($segmentationType);

        return $baseQuery;
    }

    /**
     * Build select clause
     */
    private function buildSelectClause($segmentationType)
    {
        $columnMappings = [
            'cluster' => [
                'customer_id' => 'c.customer_id',
                'cluster_id' => 'c.cluster_id',
                'cluster_name' => 'cl.cluster_name',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'age' => 'c.age',
                'gender' => 'c.gender',
                'region' => 'c.region'
            ],
            'clv_tier' => [
                'customer_id' => 'c.customer_id',
                'clv_tier' => 'c.clv_tier',
                'calculated_clv' => 'c.calculated_clv',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'age' => 'c.age',
                'gender' => 'c.gender',
                'region' => 'c.region'
            ],
            'rfm' => [
                'customer_id' => 'c.customer_id',
                'recency_score' => 'c.recency_score',
                'frequency_score' => 'c.frequency_score',
                'monetary_score' => 'c.monetary_score',
                'rfm_segment' => 'c.rfm_segment',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount'
            ],
            'gender' => [
                'customer_id' => 'c.customer_id',
                'gender' => 'c.gender',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'age' => 'c.age',
                'region' => 'c.region'
            ],
            'region' => [
                'customer_id' => 'c.customer_id',
                'region' => 'c.region',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'age' => 'c.age',
                'gender' => 'c.gender'
            ],
            'age_group' => [
                'customer_id' => 'c.customer_id',
                'age_group' => "CASE WHEN c.age BETWEEN 18 AND 25 THEN '18-25' WHEN c.age BETWEEN 26 AND 40 THEN '26-40' WHEN c.age BETWEEN 41 AND 60 THEN '41-60' ELSE '61+' END",
                'age' => 'c.age',
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'gender' => 'c.gender',
                'region' => 'c.region'
            ],
            'income_bracket' => [
                'customer_id' => 'c.customer_id',
                'income_bracket' => "CASE WHEN c.income < 30000 THEN 'Low Income (<30k)' WHEN c.income BETWEEN 30000 AND 70000 THEN 'Middle Income (30k-70k)' ELSE 'High Income (>70k)' END",
                'income' => 'c.income',
                'purchase_amount' => 'c.purchase_amount',
                'age' => 'c.age',
                'gender' => 'c.gender',
                'region' => 'c.region'
            ],
            'purchase_tier' => [
                'customer_id' => 'c.customer_id',
                'purchase_tier' => "CASE WHEN c.purchase_amount < 1000 THEN 'Low Spender (<1k)' WHEN c.purchase_amount BETWEEN 1000 AND 3000 THEN 'Medium Spender (1k-3k)' ELSE 'High Spender (>3k)' END",
                'purchase_amount' => 'c.purchase_amount',
                'income' => 'c.income',
                'age' => 'c.age',
                'gender' => 'c.gender',
                'region' => 'c.region'
            ]
        ];

        $mapping = $columnMappings[$segmentationType] ?? [];
        $selectParts = [];

        foreach ($this->selectedColumns as $column) {
            if (isset($mapping[$column])) {
                $selectParts[] = $mapping[$column] . " AS " . $column;
            }
        }

        return !empty($selectParts) ? implode(", ", $selectParts) : "*";
    }

    /**
     * Build from clause
     */
    private function buildFromClause($segmentationType)
    {
        $fromClauses = [
            'cluster' => " FROM customers c LEFT JOIN customer_clusters cl ON c.cluster_id = cl.cluster_id",
            'clv_tier' => " FROM customers c",
            'rfm' => " FROM customers c",
            'gender' => " FROM customers c",
            'region' => " FROM customers c",
            'age_group' => " FROM customers c",
            'income_bracket' => " FROM customers c",
            'purchase_tier' => " FROM customers c"
        ];

        return $fromClauses[$segmentationType] ?? " FROM customers c";
    }

    /**
     * Build where clause
     */
    private function buildWhereClause($filters)
    {
        $whereParts = [];

        if (!empty($filters['date_from'])) {
            $whereParts[] = "c.created_at >= '" . $filters['date_from'] . "'";
        }

        if (!empty($filters['date_to'])) {
            $whereParts[] = "c.created_at <= '" . $filters['date_to'] . "'";
        }

        if (!empty($filters['min_income'])) {
            $whereParts[] = "c.income >= " . (float)$filters['min_income'];
        }

        if (!empty($filters['max_income'])) {
            $whereParts[] = "c.income <= " . (float)$filters['max_income'];
        }

        return implode(" AND ", $whereParts);
    }

    /**
     * Build order by clause
     */
    private function buildOrderByClause($segmentationType)
    {
        $orderClauses = [
            'cluster' => " ORDER BY c.cluster_id, c.customer_id",
            'clv_tier' => " ORDER BY c.calculated_clv DESC, c.customer_id",
            'rfm' => " ORDER BY c.recency_score DESC, c.frequency_score DESC, c.monetary_score DESC",
            'gender' => " ORDER BY c.gender, c.customer_id",
            'region' => " ORDER BY c.region, c.customer_id",
            'age_group' => " ORDER BY c.age_group, c.customer_id",
            'income_bracket' => " ORDER BY c.income_bracket, c.customer_id",
            'purchase_tier' => " ORDER BY c.purchase_tier, c.customer_id"
        ];

        return $orderClauses[$segmentationType] ?? " ORDER BY c.customer_id";
    }

    /**
     * Generate export based on type
     */
    private function generateExport()
    {
        switch ($this->exportType) {
            case 'csv':
                return $this->generateCSV();
            case 'pdf':
                return $this->generateHTML(); // Generate HTML for PDF conversion
            default:
                throw new Exception("Unsupported export type");
        }
    }

    /**
     * Generate CSV export
     */
    private function generateCSV()
    {
        $fileName = $this->generateFileName('csv');
        $filePath = 'exports/' . $fileName;

        // Ensure exports directory exists
        if (!is_dir('exports')) {
            mkdir('exports', 0755, true);
        }

        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new Exception("Could not create CSV file");
        }

        // Write headers
        if (!empty($this->data)) {
            fputcsv($handle, array_keys($this->data[0]));

            // Write data rows
            foreach ($this->data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);

        return $filePath;
    }

    /**
     * Generate HTML export (fallback for PDF/Excel)
     */
    private function generateHTML()
    {
        $fileName = $this->generateFileName('html');
        $filePath = 'exports/' . $fileName;

        if (!is_dir('exports')) {
            mkdir('exports', 0755, true);
        }

        $html = $this->buildHTMLContent();
        file_put_contents($filePath, $html);

        return $filePath;
    }

    /**
     * Build HTML content for export
     */
    private function buildHTMLContent()
    {
        $title = ucfirst(str_replace('_', ' ', $this->segmentationType)) . ' Segmentation Report';
        $generated = date('Y-m-d H:i:s');

        $html = "<!DOCTYPE html>
<html>
<head>
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .summary { margin-bottom: 30px; }
        .summary h2 { color: #666; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p><strong>Generated:</strong> {$generated}</p>
    <p><strong>Total Records:</strong> " . count($this->data) . "</p>

    <div class='summary'>
        <h2>Summary Statistics</h2>";

        // Add summary based on segmentation type
        $html .= $this->buildHTMLSummary();

        $html .= "</div>

    <h2>Data Export</h2>
    <table>
        <thead>
            <tr>";

        // Table headers
        if (!empty($this->data)) {
            foreach (array_keys($this->data[0]) as $header) {
                $html .= "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . "</th>";
            }
        }

        $html .= "</tr>
        </thead>
        <tbody>";

        // Table data
        foreach ($this->data as $row) {
            $html .= "<tr>";
            foreach ($row as $value) {
                $html .= "<td>" . htmlspecialchars($value) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</tbody>
    </table>
</body>
</html>";

        return $html;
    }

    /**
     * Build HTML summary section
     */
    private function buildHTMLSummary()
    {
        $summary = "<table>
            <tr><th>Metric</th><th>Value</th></tr>";

        if ($this->segmentationType === 'cluster') {
            $clusterSummary = [];
            foreach ($this->data as $row) {
                $clusterId = $row['cluster_id'] ?? 'Unknown';
                $clusterName = $row['cluster_name'] ?? 'Unknown';

                if (!isset($clusterSummary[$clusterId])) {
                    $clusterSummary[$clusterId] = [
                        'name' => $clusterName,
                        'count' => 0,
                        'total_income' => 0,
                        'total_purchase' => 0
                    ];
                }

                $clusterSummary[$clusterId]['count']++;
                $clusterSummary[$clusterId]['total_income'] += $row['income'] ?? 0;
                $clusterSummary[$clusterId]['total_purchase'] += $row['purchase_amount'] ?? 0;
            }

            foreach ($clusterSummary as $data) {
                $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
                $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

                $summary .= "<tr><td>{$data['name']} Customers</td><td>{$data['count']}</td></tr>";
                $summary .= "<tr><td>{$data['name']} Avg Income</td><td>$" . number_format($avgIncome, 2) . "</td></tr>";
                $summary .= "<tr><td>{$data['name']} Avg Purchase</td><td>$" . number_format($avgPurchase, 2) . "</td></tr>";
            }
        } elseif ($this->segmentationType === 'clv_tier') {
            $tierSummary = [];
            $tierOrder = ['Platinum', 'Gold', 'Silver', 'Bronze'];

            foreach ($this->data as $row) {
                $tier = $row['clv_tier'] ?? 'Unknown';

                if (!isset($tierSummary[$tier])) {
                    $tierSummary[$tier] = [
                        'count' => 0,
                        'total_clv' => 0,
                        'total_income' => 0
                    ];
                }

                $tierSummary[$tier]['count']++;
                $tierSummary[$tier]['total_clv'] += $row['calculated_clv'] ?? 0;
                $tierSummary[$tier]['total_income'] += $row['income'] ?? 0;
            }

            $totalCustomers = array_sum(array_column($tierSummary, 'count'));

            foreach ($tierOrder as $tier) {
                if (isset($tierSummary[$tier])) {
                    $data = $tierSummary[$tier];
                    $percentage = $totalCustomers > 0 ? ($data['count'] / $totalCustomers) * 100 : 0;
                    $avgCLV = $data['count'] > 0 ? $data['total_clv'] / $data['count'] : 0;

                    $summary .= "<tr><td>{$tier} Customers</td><td>{$data['count']} (" . number_format($percentage, 1) . "%)</td></tr>";
                    $summary .= "<tr><td>{$tier} Avg CLV</td><td>$" . number_format($avgCLV, 2) . "</td></tr>";
                }
            }
        } elseif ($this->segmentationType === 'age_group') {
            $ageSummary = [];
            $ageOrder = ['18-25', '26-40', '41-60', '61+'];

            foreach ($this->data as $row) {
                $ageGroup = $row['age_group'] ?? 'Unknown';

                if (!isset($ageSummary[$ageGroup])) {
                    $ageSummary[$ageGroup] = [
                        'count' => 0,
                        'total_income' => 0,
                        'total_purchase' => 0
                    ];
                }

                $ageSummary[$ageGroup]['count']++;
                $ageSummary[$ageGroup]['total_income'] += $row['income'] ?? 0;
                $ageSummary[$ageGroup]['total_purchase'] += $row['purchase_amount'] ?? 0;
            }

            foreach ($ageOrder as $ageGroup) {
                if (isset($ageSummary[$ageGroup])) {
                    $data = $ageSummary[$ageGroup];
                    $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
                    $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

                    $summary .= "<tr><td>Age Group {$ageGroup} Customers</td><td>{$data['count']}</td></tr>";
                    $summary .= "<tr><td>Age Group {$ageGroup} Avg Income</td><td>$" . number_format($avgIncome, 2) . "</td></tr>";
                    $summary .= "<tr><td>Age Group {$ageGroup} Avg Purchase</td><td>$" . number_format($avgPurchase, 2) . "</td></tr>";
                }
            }
        } elseif ($this->segmentationType === 'income_bracket') {
            $incomeSummary = [];
            $incomeOrder = ['Low Income (<30k)', 'Middle Income (30k-70k)', 'High Income (>70k)'];

            foreach ($this->data as $row) {
                $bracket = $row['income_bracket'] ?? 'Unknown';

                if (!isset($incomeSummary[$bracket])) {
                    $incomeSummary[$bracket] = [
                        'count' => 0,
                        'total_income' => 0,
                        'total_purchase' => 0
                    ];
                }

                $incomeSummary[$bracket]['count']++;
                $incomeSummary[$bracket]['total_income'] += $row['income'] ?? 0;
                $incomeSummary[$bracket]['total_purchase'] += $row['purchase_amount'] ?? 0;
            }

            foreach ($incomeOrder as $bracket) {
                if (isset($incomeSummary[$bracket])) {
                    $data = $incomeSummary[$bracket];
                    $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
                    $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

                    $summary .= "<tr><td>{$bracket} Customers</td><td>{$data['count']}</td></tr>";
                    $summary .= "<tr><td>{$bracket} Avg Income</td><td>$" . number_format($avgIncome, 2) . "</td></tr>";
                    $summary .= "<tr><td>{$bracket} Avg Purchase</td><td>$" . number_format($avgPurchase, 2) . "</td></tr>";
                }
            }
        } elseif ($this->segmentationType === 'purchase_tier') {
            $purchaseSummary = [];
            $purchaseOrder = ['Low Spender (<1k)', 'Medium Spender (1k-3k)', 'High Spender (>3k)'];

            foreach ($this->data as $row) {
                $tier = $row['purchase_tier'] ?? 'Unknown';

                if (!isset($purchaseSummary[$tier])) {
                    $purchaseSummary[$tier] = [
                        'count' => 0,
                        'total_income' => 0,
                        'total_purchase' => 0
                    ];
                }

                $purchaseSummary[$tier]['count']++;
                $purchaseSummary[$tier]['total_income'] += $row['income'] ?? 0;
                $purchaseSummary[$tier]['total_purchase'] += $row['purchase_amount'] ?? 0;
            }

            foreach ($purchaseOrder as $tier) {
                if (isset($purchaseSummary[$tier])) {
                    $data = $purchaseSummary[$tier];
                    $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
                    $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

                    $summary .= "<tr><td>{$tier} Customers</td><td>{$data['count']}</td></tr>";
                    $summary .= "<tr><td>{$tier} Avg Income</td><td>$" . number_format($avgIncome, 2) . "</td></tr>";
                    $summary .= "<tr><td>{$tier} Avg Purchase</td><td>$" . number_format($avgPurchase, 2) . "</td></tr>";
                }
            }
        } else {
            // Basic summary for other segmentations (gender, region)
            $totalRecords = count($this->data);
            $totalIncome = 0;
            $totalPurchase = 0;

            foreach ($this->data as $row) {
                $totalIncome += $row['income'] ?? 0;
                $totalPurchase += $row['purchase_amount'] ?? 0;
            }

            $avgIncome = $totalRecords > 0 ? $totalIncome / $totalRecords : 0;
            $avgPurchase = $totalRecords > 0 ? $totalPurchase / $totalRecords : 0;

            $summary .= "<tr><td>Total Records</td><td>{$totalRecords}</td></tr>";
            $summary .= "<tr><td>Average Income</td><td>$" . number_format($avgIncome, 2) . "</td></tr>";
            $summary .= "<tr><td>Average Purchase</td><td>$" . number_format($avgPurchase, 2) . "</td></tr>";
            $summary .= "<tr><td>Total Income</td><td>$" . number_format($totalIncome, 2) . "</td></tr>";
            $summary .= "<tr><td>Total Purchase Value</td><td>$" . number_format($totalPurchase, 2) . "</td></tr>";
        }

        $summary .= "</table>";
        return $summary;
    }

    /**
     * Generate unique file name
     */
    private function generateFileName($extension)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $segmentation = str_replace('_', '-', $this->segmentationType);
        return "segmentation_{$segmentation}_{$timestamp}.{$extension}";
    }

    /**
     * Track export in history
     */
    private function trackExportHistory($filePath)
    {
        try {
            // Create export history table if it doesn't exist
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS export_history (
                    export_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    segmentation_type VARCHAR(50),
                    export_type VARCHAR(20),
                    file_path VARCHAR(255),
                    record_count INT,
                    exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Insert export record
            $stmt = $this->conn->prepare("
                INSERT INTO export_history (user_id, segmentation_type, export_type, file_path, record_count)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $this->userId,
                $this->segmentationType,
                $this->exportType,
                $filePath,
                count($this->data)
            ]);

            return $this->conn->lastInsertId();
        } catch (Exception $e) {
            // Log error but don't fail the export
            error_log("Failed to track export history: " . $e->getMessage());
            return null;
        }
    }
}
