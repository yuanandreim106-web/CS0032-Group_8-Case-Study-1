<?php

/**
 * System Performance Dashboard
 * Monitors system health, resource usage, and performance metrics
 */
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/Logger.php';

// Require authentication
requireLogin();

// Log dashboard access
Logger::info('Performance dashboard accessed');

// In a real production app, these values would be fetched from a monitoring system.
// For this case study, we simulate the current system state.
$metrics = [
    'avg_response_time' => 1.2,      // seconds
    'peak_memory' => 184,             // MB
    'query_latency' => 450,           // ms
    'convergence_time' => 8.5,        // seconds for k-means
    'error_rate' => 0.02,             // 2%
    'uptime' => 99.95,                // percentage
    'requests_per_minute' => 245,     // requests
    'database_connections' => 5       // active connections
];

// Determine health status
$responseTimeStatus = $metrics['avg_response_time'] < 2 ? 'success' : 'warning';
$memoryStatus = $metrics['peak_memory'] < 200 ? 'success' : 'danger';
$errorStatus = $metrics['error_rate'] < 0.05 ? 'success' : 'danger';
$uptimeStatus = $metrics['uptime'] > 99 ? 'success' : 'warning';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Performance | Customer Segmentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .metric-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .metric-value {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }

        .gauge {
            width: 100%;
            height: 40px;
            margin-top: 10px;
            border-radius: 20px;
            background: #e9ecef;
            overflow: hidden;
        }

        .gauge-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .chart-area {
            position: relative;
            height: 300px;
            margin-top: 20px;
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

        <h2 class="mb-4">System Performance</h2>
    </div>

    <div class="container-fluid py-4">

        <!-- Key Metrics Grid -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted">Avg Response Time</small>
                            <div class="metric-value"><?= $metrics['avg_response_time'] ?>s</div>
                            <small class="text-muted">Target: < 2.0s</small>
                        </div>
                        <span class="status-badge bg-<?= $responseTimeStatus ?>">OK</span>
                    </div>
                    <div class="gauge">
                        <div class="gauge-fill" style="width: <?= ($metrics['avg_response_time'] / 2) * 100 ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="metric-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted">Memory Usage</small>
                            <div class="metric-value"><?= $metrics['peak_memory'] ?>MB</div>
                            <small class="text-muted">Limit: 256MB</small>
                        </div>
                        <span class="status-badge bg-<?= $memoryStatus ?>">OK</span>
                    </div>
                    <div class="gauge">
                        <div class="gauge-fill" style="width: <?= ($metrics['peak_memory'] / 256) * 100 ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="metric-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted">Query Latency</small>
                            <div class="metric-value"><?= $metrics['query_latency'] ?>ms</div>
                            <small class="text-muted">Database Health</small>
                        </div>
                        <span class="status-badge bg-info">Monitor</span>
                    </div>
                    <div class="gauge">
                        <div class="gauge-fill" style="width: <?= min(($metrics['query_latency'] / 1000) * 100, 100) ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="metric-card p-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted">Error Rate</small>
                            <div class="metric-value"><?= ($metrics['error_rate'] * 100) ?>%</div>
                            <small class="text-muted">Target: < 0.1%</small>
                        </div>
                        <span class="status-badge bg-<?= $errorStatus ?>">OK</span>
                    </div>
                    <div class="gauge">
                        <div class="gauge-fill" style="width: <?= ($metrics['error_rate'] * 10) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="metric-card p-3 text-center">
                    <small class="text-muted d-block">Uptime</small>
                    <h4 class="mb-0"><?= $metrics['uptime'] ?>%</h4>
                    <span class="status-badge bg-<?= $uptimeStatus ?> mt-2">Excellent</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="metric-card p-3 text-center">
                    <small class="text-muted d-block">Clustering Time</small>
                    <h4 class="mb-0"><?= $metrics['convergence_time'] ?>s</h4>
                    <span class="status-badge bg-success mt-2">Optimal</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="metric-card p-3 text-center">
                    <small class="text-muted d-block">Requests/Min</small>
                    <h4 class="mb-0"><?= $metrics['requests_per_minute'] ?></h4>
                    <span class="status-badge bg-info mt-2">Active</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="metric-card p-3 text-center">
                    <small class="text-muted d-block">DB Connections</small>
                    <h4 class="mb-0"><?= $metrics['database_connections'] ?>/20</h4>
                    <span class="status-badge bg-success mt-2">Healthy</span>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm p-4">
                    <h5 class="card-title mb-3">Performance Timeline (24 Hours)</h5>
                    <div class="chart-area">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm p-4">
                    <h5 class="card-title mb-3">System Status</h5>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <span>Database</span>
                                <span class="badge bg-success">Connected</span>
                            </div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <span>API Endpoints</span>
                                <span class="badge bg-success">Responsive</span>
                            </div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <span>Export Service</span>
                                <span class="badge bg-success">Running</span>
                            </div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <span>Cache</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <span>Logging</span>
                                <span class="badge bg-success">Recording</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Performance Chart
        new Chart(document.getElementById('performanceChart'), {
            type: 'line',
            data: {
                labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '24:00'],
                datasets: [{
                        label: 'Response Time (s)',
                        data: [1.1, 1.3, 1.2, 0.9, 1.4, 1.5, 1.2],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.05)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Error Rate (%)',
                        data: [0.01, 0.02, 0.015, 0.01, 0.03, 0.025, 0.02],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.05)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left'
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

</body>

</html>