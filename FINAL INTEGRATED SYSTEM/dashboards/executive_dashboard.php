<?php

/**
 * Executive Dashboard - KPI & Metrics Summary
 * Displays strategic metrics and performance indicators
 */
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/Logger.php';

// Require authentication
requireLogin();

// Log dashboard access
Logger::info('Executive dashboard accessed');

// Mock Predictive Logic: Calculate CLV (Avg Purchase * 12 months * 3 years)
try {
    $metricsQuery = $pdo->query("SELECT 
        ROUND(AVG(avg_purchase_amount) * 36, 2) as system_clv,
        SUM(customer_count) as total_users
        FROM cluster_metadata");
    $meta = $metricsQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::warning('Could not fetch cluster metrics', ['error' => $e->getMessage()]);
    $meta = ['system_clv' => 0, 'total_users' => 0];
}

// Anomaly Detection: Check for shrinking Premium segments
try {
    $alertSql = "SELECT cluster_name FROM cluster_metadata WHERE avg_purchase_amount > 3000 AND customer_count < 200";
    $alerts = $pdo->query($alertSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::warning('Could not fetch anomaly alerts', ['error' => $e->getMessage()]);
    $alerts = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard | Customer Segmentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-sankey@0.12.0/dist/chartjs-chart-sankey.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
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
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: rgba(255,255,255,0.3);">
                            Reports
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="border: none; border-radius: 6px;">
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

        <h2 class="mb-4">Executive Dashboard</h2>

        .kpi-card {
        border: none;
        border-radius: 12px;
        transition: all 0.3s ease;
        }

        .kpi-card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        }

        .alert-dot {
        height: 10px;
        width: 10px;
        background-color: #ff4d4d;
        border-radius: 50%;
        display: inline-block;
        }

        .chart-container {
        position: relative;
        height: 400px;
        margin-bottom: 20px;
        }
        </style>
        </head>

        <body>

            <div class="container-fluid py-4">

                <!-- KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">Avg CLV</h6>
                            <h3 class="text-primary mb-1">$<?= $meta['system_clv'] ? number_format($meta['system_clv'], 0) : '0' ?></h3>
                            <small class="text-success">↑ 4% YoY</small>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">CAC Ratio</h6>
                            <h3 class="mb-1">3.2:1</h3>
                            <small class="text-muted">Target 3:1</small>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">Net Profit</h6>
                            <h3 class="text-success mb-1">24%</h3>
                            <small class="text-success">↑ 2%</small>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">Churn Risk</h6>
                            <h3 class="text-danger mb-1">3.1%</h3>
                            <small class="text-danger">⚠ Attention</small>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">Projected Rev</h6>
                            <h3 class="text-info mb-1">$1.4M</h3>
                            <small class="text-muted">Q4 Forecast</small>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="card kpi-card shadow-sm p-3">
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h3 class="mb-1"><?= $meta['total_users'] ? number_format($meta['total_users'], 0) : '0' ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>

                <!-- Anomaly Alerts -->
                <?php if (!empty($alerts)): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                        <div class="d-flex align-items-start">
                            <span class="alert-dot me-3 mt-1"></span>
                            <div>
                                <h5 class="alert-heading mb-2">⚠️ Anomaly Detection Alerts</h5>
                                <ul class="mb-0 small">
                                    <?php foreach ($alerts as $a): ?>
                                        <li>Critical Volume Drop: <strong><?= htmlspecialchars($a['cluster_name']) ?></strong> segment below retention threshold.</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Charts -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm p-4">
                            <h5 class="card-title mb-3">Revenue Contribution by Segment</h5>
                            <div class="chart-container">
                                <canvas id="revenueAreaChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm p-4 h-100 bg-primary text-white">
                            <h5 class="card-title mb-2">Market Share Forecast</h5>
                            <p class="small opacity-75 mb-4">ML Projection for Next 90 Days</p>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">High-Value Growth</h6>
                                    <span class="badge bg-light text-dark">+12.5%</span>
                                </div>
                                <div class="progress mb-4" style="height: 5px;">
                                    <div class="progress-bar bg-light" style="width: 75%"></div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Churn Mitigation</h6>
                                    <span class="badge bg-light text-dark">-2.1%</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-light" style="width: 45%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm p-4">
                            <h5 class="card-title mb-3">Segment Migration Flow</h5>
                            <div class="chart-container">
                                <canvas id="migrationSankey"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm p-4">
                            <h5 class="card-title mb-3">Marketing Efficiency (CAC vs CLV)</h5>
                            <div class="chart-container">
                                <canvas id="efficiencyBar"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <script>
                // Area Chart for Revenue
                new Chart(document.getElementById('revenueAreaChart'), {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                                label: 'Premium',
                                data: [30, 45, 60, 55, 75, 90],
                                fill: true,
                                backgroundColor: 'rgba(13, 110, 253, 0.2)',
                                borderColor: '#0d6efd',
                                tension: 0.3
                            },
                            {
                                label: 'Mid-Tier',
                                data: [20, 25, 30, 35, 40, 45],
                                fill: true,
                                backgroundColor: 'rgba(25, 135, 84, 0.2)',
                                borderColor: '#198754',
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                stacked: false
                            }
                        }
                    }
                });

                // Bar Chart for Efficiency
                new Chart(document.getElementById('efficiencyBar'), {
                    type: 'bar',
                    data: {
                        labels: ['Premium', 'Active', 'Budget'],
                        datasets: [{
                                label: 'CAC ($)',
                                data: [800, 300, 100],
                                backgroundColor: '#ff4d4d'
                            },
                            {
                                label: 'CLV ($)',
                                data: [5200, 2100, 600],
                                backgroundColor: '#198754'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'x'
                    }
                });

                // Sankey Chart for Migration
                new Chart(document.getElementById('migrationSankey'), {
                    type: 'sankey',
                    data: {
                        datasets: [{
                            label: 'Customer Migration',
                            data: [{
                                    from: 'New Leads',
                                    to: 'Budget',
                                    flow: 100
                                },
                                {
                                    from: 'Budget',
                                    to: 'Active',
                                    flow: 45
                                },
                                {
                                    from: 'Active',
                                    to: 'Premium',
                                    flow: 20
                                },
                                {
                                    from: 'Active',
                                    to: 'Churn',
                                    flow: 5
                                }
                            ],
                            colorFrom: (c) => '#0d6efd',
                            colorTo: (c) => '#198754'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            </script>

        </body>

</html>