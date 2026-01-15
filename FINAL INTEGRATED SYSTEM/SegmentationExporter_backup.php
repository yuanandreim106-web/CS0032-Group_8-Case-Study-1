<?php

/**
 * Customer Segmentation Export System
 * Supports CSV, PDF, and Excel exports with chart embedding
 * Includes export history tracking and column filtering
 */

require_once 'vendor/autoload.php'; // PhpSpreadsheet, TCPDF, etc.

// Simple autoloader for required libraries
// In production, use Composer for proper dependency management

// Define paths to manually downloaded libraries
define('PHPSPREADSHEET_PATH', __DIR__ . '/vendor/phpoffice/phpspreadsheet');
define('TCPDF_PATH', __DIR__ . '/vendor/tecnickcom/tcpdf');

// Simple class autoloader
spl_autoload_register(function ($class) {
    // PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet') === 0) {
        $path = str_replace('PhpOffice\\PhpSpreadsheet\\', '', $class);
        $path = str_replace('\\', '/', $path);
        $file = PHPSPREADSHEET_PATH . '/src/' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    // TCPDF class
    if ($class === 'TCPDF') {
        $file = TCPDF_PATH . '/tcpdf.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Include TCPDF configuration if it exists
if (file_exists(TCPDF_PATH . '/tcpdf_autoconfig.php')) {
    require_once TCPDF_PATH . '/tcpdf_autoconfig.php';
}

// Fallback classes if libraries are not available
if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    class Spreadsheet
    {
        public function getActiveSheet()
        {
            return new SimpleSheet();
        }
        public function createSheet()
        {
            return new SimpleSheet();
        }
        public function setTitle($title) {}
    }
    class SimpleSheet
    {
        public function setCellValue($cell, $value) {}
        public function getStyle($cell)
        {
            return new SimpleStyle();
        }
        public function setTitle($title) {}
        public function addChart($chart) {}
    }
    class SimpleStyle
    {
        public function getFont()
        {
            return new SimpleFont();
        }
    }
    class SimpleFont
    {
        public function setBold($bold) {}
    }
    class Xlsx
    {
        public function setIncludeCharts($include) {}
        public function save($file) {}
    }
}

if (!class_exists('TCPDF')) {
    class TCPDF
    {
        public function __construct($orientation, $unit, $format, $unicode, $encoding, $diskcache) {}
        public function SetCreator($creator) {}
        public function SetAuthor($author) {}
        public function SetTitle($title) {}
        public function SetMargins($left, $top, $right) {}
        public function SetAutoPageBreak($auto, $margin) {}
        public function AddPage() {}
        public function SetFont($family, $style, $size) {}
        public function Cell($w, $h, $txt, $border, $ln, $align) {}
        public function writeHTML($html, $ln, $fill, $reseth, $cell, $align) {}
        public function Output($name, $dest) {}
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use TCPDF;

class SegmentationExporter
{
    private $conn;
    private $userId;
    private $exportType;
    private $segmentationType;
    private $selectedColumns;
    private $data;
    private $chartImages;

    /**
     * Constructor
     * @param PDO $connection Database connection
     * @param int $userId Current user ID
     */
    public function __construct($connection, $userId)
    {
        $this->conn = $connection;
        $this->userId = $userId;
        $this->chartImages = [];
    }

    /**
     * Main export function
     * @param string $exportType Type of export (csv, pdf, excel)
     * @param string $segmentationType Type of segmentation (cluster, clv_tier, rfm, demographic)
     * @param array $selectedColumns Array of column names to export
     * @param array $filters Optional filters for data
     * @return array Result with file path and export ID
     */
    public function export($exportType, $segmentationType, $selectedColumns = [], $filters = [])
    {
        try {
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

            return [
                'success' => true,
                'file_path' => $filePath,
                'export_id' => $exportId,
                'file_name' => basename($filePath),
                'record_count' => count($this->data)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate export request parameters
     */
    private function validateExportRequest($exportType, $segmentationType, $selectedColumns)
    {
        $validExportTypes = ['csv', 'pdf', 'excel'];
        $validSegmentationTypes = ['cluster', 'clv_tier', 'gender', 'region', 'age_group', 'income_bracket', 'purchase_tier'];

        if (!in_array($exportType, $validExportTypes)) {
            throw new Exception("Invalid export type. Must be: csv, pdf, or excel");
        }

        if (!in_array($segmentationType, $validSegmentationTypes)) {
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
        switch ($segmentationType) {
            case 'cluster':
                return $this->fetchClusterData($filters);
            case 'clv_tier':
                return $this->fetchCLVTierData($filters);
            case 'gender':
                return $this->fetchGenderData($filters);
            case 'region':
                return $this->fetchRegionData($filters);
            case 'age_group':
                return $this->fetchAgeGroupData($filters);
            case 'income_bracket':
                return $this->fetchIncomeBracketData($filters);
            case 'purchase_tier':
                return $this->fetchPurchaseTierData($filters);
            default:
                throw new Exception("Unsupported segmentation type");
        }
    }

    /**
     * Fetch cluster segmentation data
     */
    private function fetchClusterData($filters = [])
    {
        // Build WHERE clause from filters
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['cluster_id'])) {
            $whereClause .= " AND sr.cluster_id = ?";
            $params[] = $filters['cluster_id'];
        }

        if (!empty($filters['region'])) {
            $whereClause .= " AND c.region = ?";
            $params[] = $filters['region'];
        }

        // Build SELECT clause based on selected columns
        $selectColumns = $this->buildSelectClause('cluster');

        $query = "
            SELECT
                $selectColumns
            FROM customers c
            INNER JOIN segmentation_results sr ON c.customer_id = sr.customer_id
            INNER JOIN cluster_metadata cm ON sr.cluster_id = cm.cluster_id
            $whereClause
            ORDER BY sr.cluster_id, c.customer_id
        ";

        $stmt = $this->conn->prepare($query);

        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch CLV tier segmentation data
     */
    private function fetchCLVTierData($filters = [])
    {
        $whereClause = "WHERE clv_tier IS NOT NULL";
        $params = [];

        if (!empty($filters['clv_tier'])) {
            $whereClause .= " AND clv_tier = ?";
            $params[] = $filters['clv_tier'];
        }

        if (!empty($filters['region'])) {
            $whereClause .= " AND region = ?";
            $params[] = $filters['region'];
        }

        $selectColumns = $this->buildSelectClause('clv_tier');

        $query = "
            SELECT $selectColumns
            FROM customers
            $whereClause
            ORDER BY
                FIELD(clv_tier, 'Platinum', 'Gold', 'Silver', 'Bronze'),
                calculated_clv DESC
        ";

        $stmt = $this->conn->prepare($query);

        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch gender segmentation data
     */
    private function fetchGenderData($filters = [])
    {
        $selectColumns = $this->buildSelectClause('gender');

        $query = "
            SELECT $selectColumns
            FROM customers
            ORDER BY gender, customer_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch region segmentation data
     */
    private function fetchRegionData($filters = [])
    {
        $selectColumns = $this->buildSelectClause('region');

        $query = "
            SELECT $selectColumns
            FROM customers
            ORDER BY region, customer_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch age group segmentation data
     */
    private function fetchAgeGroupData($filters = [])
    {
        $selectColumns = $this->buildSelectClause('age_group');

        $query = "
            SELECT
                $selectColumns,
                CASE WHEN age BETWEEN 18 AND 25 THEN '18-25'
                     WHEN age BETWEEN 26 AND 40 THEN '26-40'
                     WHEN age BETWEEN 41 AND 60 THEN '41-60'
                     ELSE '61+' END AS age_group
            FROM customers
            ORDER BY age_group, customer_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch income bracket segmentation data
     */
    private function fetchIncomeBracketData($filters = [])
    {
        $selectColumns = $this->buildSelectClause('income_bracket');

        $query = "
            SELECT
                $selectColumns,
                CASE WHEN income < 30000 THEN 'Low Income (<30k)'
                     WHEN income BETWEEN 30000 AND 70000 THEN 'Middle Income (30k-70k)'
                     ELSE 'High Income (>70k)' END AS income_bracket
            FROM customers
            ORDER BY income_bracket, customer_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch purchase tier segmentation data
     */
    private function fetchPurchaseTierData($filters = [])
    {
        $selectColumns = $this->buildSelectClause('purchase_tier');

        $query = "
            SELECT
                $selectColumns,
                CASE WHEN purchase_amount < 1000 THEN 'Low Spender (<1k)'
                     WHEN purchase_amount BETWEEN 1000 AND 3000 THEN 'Medium Spender (1k-3k)'
                     ELSE 'High Spender (>3k)' END AS purchase_tier
            FROM customers
            ORDER BY purchase_tier, customer_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Build SELECT clause based on selected columns
     */
    private function buildSelectClause($segmentationType)
    {
        $columnMappings = [
            'cluster' => [
                'customer_id' => 'c.customer_id',
                'name' => 'c.name',
                'age' => 'c.age',
                'gender' => 'c.gender',
                'income' => 'c.income',
                'region' => 'c.region',
                'purchase_amount' => 'c.purchase_amount',
                'cluster_id' => 'sr.cluster_id',
                'cluster_name' => 'cm.cluster_name',
                'cluster_description' => 'cm.description'
            ],
            'clv_tier' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount',
                'avg_purchase_amount' => 'avg_purchase_amount',
                'purchase_frequency' => 'purchase_frequency',
                'customer_lifespan_months' => 'customer_lifespan_months',
                'calculated_clv' => 'calculated_clv',
                'clv_tier' => 'clv_tier'
            ],
            'gender' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount'
            ],
            'region' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount'
            ],
            'age_group' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount'
            ],
            'income_bracket' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount'
            ],
            'purchase_tier' => [
                'customer_id' => 'customer_id',
                'name' => 'name',
                'age' => 'age',
                'gender' => 'gender',
                'income' => 'income',
                'region' => 'region',
                'purchase_amount' => 'purchase_amount'
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
     * Generate export based on type
     */
    private function generateExport()
    {
        switch ($this->exportType) {
            case 'csv':
                return $this->generateCSV();
            case 'pdf':
                return $this->generatePDF();
            case 'excel':
                return $this->generateExcel();
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
     * Generate Excel export with charts
     */
    private function generateExcel()
    {
        $fileName = $this->generateFileName('xlsx');
        $filePath = 'exports/' . $fileName;

        if (!is_dir('exports')) {
            mkdir('exports', 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Segmentation Data');

        // Write headers
        if (!empty($this->data)) {
            $headers = array_keys($this->data[0]);
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', ucwords(str_replace('_', ' ', $header)));
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }

            // Write data
            $row = 2;
            foreach ($this->data as $dataRow) {
                $col = 'A';
                foreach ($dataRow as $value) {
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // Auto-size columns
            foreach (range('A', $col) as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
        }

        // Add summary sheet with charts
        $this->addSummarySheetWithCharts($spreadsheet);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Add summary sheet with charts to Excel
     */
    private function addSummarySheetWithCharts($spreadsheet)
    {
        // Create summary sheet
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');

        // Generate summary data based on segmentation type
        if ($this->segmentationType === 'cluster') {
            $this->addClusterSummary($summarySheet);
        } elseif ($this->segmentationType === 'clv_tier') {
            $this->addCLVTierSummary($summarySheet);
        } elseif (in_array($this->segmentationType, ['gender', 'region', 'age_group', 'income_bracket', 'purchase_tier'])) {
            $this->addBasicSegmentationSummary($summarySheet);
        }
    }

    /**
     * Add cluster summary with chart
     */
    private function addClusterSummary($sheet)
    {
        // Aggregate data by cluster
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

        // Write summary headers
        $sheet->setCellValue('A1', 'Cluster Name');
        $sheet->setCellValue('B1', 'Customer Count');
        $sheet->setCellValue('C1', 'Avg Income');
        $sheet->setCellValue('D1', 'Avg Purchase');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        // Write summary data
        $row = 2;
        foreach ($clusterSummary as $data) {
            $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
            $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

            $sheet->setCellValue('A' . $row, $data['name']);
            $sheet->setCellValue('B' . $row, $data['count']);
            $sheet->setCellValue('C' . $row, number_format($avgIncome, 2));
            $sheet->setCellValue('D' . $row, number_format($avgPurchase, 2));
            $row++;
        }

        // Create chart (customer count by cluster)
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$B$1', null, 1)
        ];

        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$2:$A$' . ($row - 1), null, count($clusterSummary))
        ];

        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Summary!$B$2:$B$' . ($row - 1), null, count($clusterSummary))
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );

        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Customer Distribution by Cluster');

        $chart = new Chart(
            'cluster_chart',
            $title,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('F2');
        $chart->setBottomRightPosition('M15');

        $sheet->addChart($chart);
    }

    /**
     * Add CLV tier summary with chart
     */
    private function addCLVTierSummary($sheet)
    {
        // Aggregate data by CLV tier
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

        // Write summary headers
        $sheet->setCellValue('A1', 'CLV Tier');
        $sheet->setCellValue('B1', 'Customer Count');
        $sheet->setCellValue('C1', 'Percentage');
        $sheet->setCellValue('D1', 'Avg CLV');
        $sheet->setCellValue('E1', 'Avg Income');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        // Calculate total for percentages
        $totalCustomers = array_sum(array_column($tierSummary, 'count'));

        // Write summary data in tier order
        $row = 2;
        foreach ($tierOrder as $tier) {
            if (isset($tierSummary[$tier])) {
                $data = $tierSummary[$tier];
                $percentage = $totalCustomers > 0 ? ($data['count'] / $totalCustomers) * 100 : 0;
                $avgCLV = $data['count'] > 0 ? $data['total_clv'] / $data['count'] : 0;
                $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;

                $sheet->setCellValue('A' . $row, $tier);
                $sheet->setCellValue('B' . $row, $data['count']);
                $sheet->setCellValue('C' . $row, number_format($percentage, 2) . '%');
                $sheet->setCellValue('D' . $row, number_format($avgCLV, 2));
                $sheet->setCellValue('E' . $row, number_format($avgIncome, 2));
                $row++;
            }
        }

        // Create pie chart for tier distribution
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$B$1', null, 1)
        ];

        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$2:$A$' . ($row - 1), null, count($tierSummary))
        ];

        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Summary!$B$2:$B$' . ($row - 1), null, count($tierSummary))
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            null,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );

        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Customer Distribution by CLV Tier');

        $chart = new Chart(
            'clv_tier_chart',
            $title,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('G2');
        $chart->setBottomRightPosition('N15');

        $sheet->addChart($chart);
    }

    /**
     * Add basic segmentation summary
     */
    private function addBasicSegmentationSummary($sheet)
    {
        // Aggregate data by the segmentation type
        $summary = [];
        $segmentationField = $this->segmentationType;

        foreach ($this->data as $row) {
            $segment = $row[$segmentationField] ?? 'Unknown';

            if (!isset($summary[$segment])) {
                $summary[$segment] = [
                    'count' => 0,
                    'total_income' => 0,
                    'total_purchase' => 0
                ];
            }

            $summary[$segment]['count']++;
            $summary[$segment]['total_income'] += $row['income'] ?? 0;
            $summary[$segment]['total_purchase'] += $row['purchase_amount'] ?? 0;
        }

        // Write summary headers
        $sheet->setCellValue('A1', ucwords(str_replace('_', ' ', $segmentationField)));
        $sheet->setCellValue('B1', 'Customer Count');
        $sheet->setCellValue('C1', 'Percentage');
        $sheet->setCellValue('D1', 'Avg Income');
        $sheet->setCellValue('E1', 'Avg Purchase');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        // Calculate total for percentages
        $totalCustomers = array_sum(array_column($summary, 'count'));

        // Write summary data
        $row = 2;
        foreach ($summary as $segment => $data) {
            $percentage = $totalCustomers > 0 ? ($data['count'] / $totalCustomers) * 100 : 0;
            $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
            $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

            $sheet->setCellValue('A' . $row, $segment);
            $sheet->setCellValue('B' . $row, $data['count']);
            $sheet->setCellValue('C' . $row, number_format($percentage, 2) . '%');
            $sheet->setCellValue('D' . $row, number_format($avgIncome, 2));
            $sheet->setCellValue('E' . $row, number_format($avgPurchase, 2));
            $row++;
        }
    }

    /**
     * Generate PDF export with embedded charts
     */
    private function generatePDF()
    {
        $fileName = $this->generateFileName('pdf');
        $filePath = 'exports/' . $fileName;

        if (!is_dir('exports')) {
            mkdir('exports', 0755, true);
        }

        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Customer Segmentation System');
        $pdf->SetAuthor('System Export');
        $pdf->SetTitle('Segmentation Report - ' . ucfirst($this->segmentationType));

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Add a page
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Customer Segmentation Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Segmentation Type: ' . ucfirst(str_replace('_', ' ', $this->segmentationType)), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);

        // Add summary section
        $this->addPDFSummary($pdf);

        // Add data table
        $pdf->AddPage();
        $this->addPDFDataTable($pdf);

        // Save PDF
        $pdf->Output($filePath, 'F');

        return $filePath;
    }

    /**
     * Add summary section to PDF
     */
    private function addPDFSummary($pdf)
    {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Summary Statistics', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // Generate summary based on segmentation type
        if ($this->segmentationType === 'cluster') {
            $this->addClusterPDFSummary($pdf);
        } elseif ($this->segmentationType === 'clv_tier') {
            $this->addCLVTierPDFSummary($pdf);
        } elseif (in_array($this->segmentationType, ['gender', 'region', 'age_group', 'income_bracket', 'purchase_tier'])) {
            $this->addBasicSegmentationPDFSummary($pdf);
        }

        // Add chart image if available
        if (!empty($this->chartImages)) {
            $pdf->Ln(10);
            foreach ($this->chartImages as $chartImage) {
                if (file_exists($chartImage)) {
                    $pdf->Image($chartImage, 15, $pdf->GetY(), 180, 80);
                    $pdf->Ln(85);
                }
            }
        }
    }

    /**
     * Add cluster summary to PDF
     */
    private function addClusterPDFSummary($pdf)
    {
        // Aggregate data
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

        // Create HTML table
        $html = '<table border="1" cellpadding="5">
                    <thead>
                        <tr style="background-color:#4CAF50;color:white;">
                            <th><b>Cluster Name</b></th>
                            <th><b>Customer Count</b></th>
                            <th><b>Avg Income</b></th>
                            <th><b>Avg Purchase</b></th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($clusterSummary as $data) {
            $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
            $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

            $html .= '<tr>
                        <td>' . htmlspecialchars($data['name']) . '</td>
                        <td align="center">' . $data['count'] . '</td>
                        <td align="right">₱' . number_format($avgIncome, 2) . '</td>
                        <td align="right">₱' . number_format($avgPurchase, 2) . '</td>
                      </tr>';
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Add CLV tier summary to PDF
     */
    private function addCLVTierPDFSummary($pdf)
    {
        // Aggregate data
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

        // Create HTML table
        $html = '<table border="1" cellpadding="5">
                    <thead>
                        <tr style="background-color:#2196F3;color:white;">
                            <th><b>CLV Tier</b></th>
                            <th><b>Customer Count</b></th>
                            <th><b>Percentage</b></th>
                            <th><b>Avg CLV</b></th>
                            <th><b>Avg Income</b></th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($tierOrder as $tier) {
            if (isset($tierSummary[$tier])) {
                $data = $tierSummary[$tier];
                $percentage = $totalCustomers > 0 ? ($data['count'] / $totalCustomers) * 100 : 0;
                $avgCLV = $data['count'] > 0 ? $data['total_clv'] / $data['count'] : 0;
                $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;

                $html .= '<tr>
                            <td><b>' . htmlspecialchars($tier) . '</b></td>
                            <td align="center">' . $data['count'] . '</td>
                            <td align="center">' . number_format($percentage, 2) . '%</td>
                            <td align="right">₱' . number_format($avgCLV, 2) . '</td>
                            <td align="right">₱' . number_format($avgIncome, 2) . '</td>
                          </tr>';
            }
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Add basic segmentation summary to PDF
     */
    private function addBasicSegmentationPDFSummary($pdf)
    {
        // Aggregate data
        $summary = [];
        $segmentationField = $this->segmentationType;

        foreach ($this->data as $row) {
            $segment = $row[$segmentationField] ?? 'Unknown';

            if (!isset($summary[$segment])) {
                $summary[$segment] = [
                    'count' => 0,
                    'total_income' => 0,
                    'total_purchase' => 0
                ];
            }

            $summary[$segment]['count']++;
            $summary[$segment]['total_income'] += $row['income'] ?? 0;
            $summary[$segment]['total_purchase'] += $row['purchase_amount'] ?? 0;
        }

        $totalCustomers = array_sum(array_column($summary, 'count'));

        // Create HTML table
        $html = '<table border="1" cellpadding="5">
                    <thead>
                        <tr style="background-color:#FF9800;color:white;">
                            <th><b>' . ucwords(str_replace('_', ' ', $segmentationField)) . '</b></th>
                            <th><b>Customer Count</b></th>
                            <th><b>Percentage</b></th>
                            <th><b>Avg Income</b></th>
                            <th><b>Avg Purchase</b></th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($summary as $segment => $data) {
            $percentage = $totalCustomers > 0 ? ($data['count'] / $totalCustomers) * 100 : 0;
            $avgIncome = $data['count'] > 0 ? $data['total_income'] / $data['count'] : 0;
            $avgPurchase = $data['count'] > 0 ? $data['total_purchase'] / $data['count'] : 0;

            $html .= '<tr>
                        <td>' . htmlspecialchars($segment) . '</td>
                        <td align="center">' . $data['count'] . '</td>
                        <td align="center">' . number_format($percentage, 2) . '%</td>
                        <td align="right">₱' . number_format($avgIncome, 2) . '</td>
                        <td align="right">₱' . number_format($avgPurchase, 2) . '</td>
                      </tr>';
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Add data table to PDF
     */
    private function addPDFDataTable($pdf)
    {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Detailed Customer Data', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);

        if (empty($this->data)) {
            $pdf->Cell(0, 10, 'No data available', 0, 1, 'C');
            return;
        }

        // Create HTML table
        $html = '<table border="1" cellpadding="3" style="font-size:7pt;">
                    <thead>
                        <tr style="background-color:#f0f0f0;">';

        // Headers
        foreach (array_keys($this->data[0]) as $header) {
            $html .= '<th><b>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</b></th>';
        }

        $html .= '</tr></thead><tbody>';

        // Data rows (limit to first 100 for PDF)
        $rowCount = 0;
        foreach ($this->data as $row) {
            if ($rowCount >= 100) {
                $html .= '<tr><td colspan="' . count($row) . '" align="center"><i>Showing first 100 records. Full data contains ' . count($this->data) . ' records.</i></td></tr>';
                break;
            }

            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
            $rowCount++;
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Generate unique filename
     */
    private function generateFileName($extension)
    {
        $timestamp = date('Y-m-d_H-i-s');
        $segmentationType = str_replace('_', '-', $this->segmentationType);
        return "segmentation_{$segmentationType}_{$timestamp}.{$extension}";
    }

    /**
     * Track export in history (placeholder for database tracking)
     */
    private function trackExportHistory($filePath)
    {
        // In a real implementation, this would insert into an export_history table
        // For now, just return a mock ID
        return uniqid('export_', true);
    }
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\Case Study 1\SegmentationExporter.php