<?php
// Simple test script for SegmentationExporter
require_once 'db.php';
require_once 'SegmentationExporter.php';

// Test the export functionality
try {
    // Create database connection
    $conn = new PDO("mysql:host=localhost;dbname=customer_segmentation", "root", "");

    // Create exporter instance
    $exporter = new SegmentationExporter($conn, 1);

    // Test CSV export with basic columns
    $result = $exporter->export('csv', 'gender', ['customer_id', 'gender', 'income']);

    if ($result['success']) {
        echo "Export successful!\n";
        echo "File: " . $result['file_name'] . "\n";
        echo "Records: " . $result['record_count'] . "\n";
        echo "Path: " . $result['file_path'] . "\n";
    } else {
        echo "Export failed: " . $result['error'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
