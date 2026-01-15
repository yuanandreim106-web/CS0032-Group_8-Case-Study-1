<?php

/**
 * Executive Summary Dashboard
 * Strategic insights with performance metrics and predictive charts
 */
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/Logger.php';

// Require authentication
requireLogin();

// Log dashboard access
Logger::info('Executive summary dashboard accessed');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Summary | Customer Segmentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .chart-area {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
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

        <h2 class="mb-4">Executive Summary</h2>
    </div>

    <div class="container-fluid py-4">

        <!-- KPI Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-lg col-md-6">
                <div class="card stat-card p-3">
                    <small class="text-muted fw-bold">REVENUE</small>
                    <h3 class="fw-bold text-primary mb-1">$1,240,000</h3>
                    <small class="text-success">↑ 12.5% vs Last Week</small>
                </div>
            </div>
            <div class="col-lg col-md-6">
                <div class="card stat-card p-3">
                    <small class="text-muted fw-bold">CAC</small>
                    <h3 class="fw-bold mb-1">$42.10</h3>
                    <small class="text-success">↓ 2.1% (Saving)</small>
                </div>
            </div>
            <div class="col-lg col-md-6">
                <div class="card stat-card p-3">
                    <small class="text-muted fw-bold">RETENTION</small>
                    <h3 class="fw-bold mb-1">94.2%</h3>
                    <small class="text-success">Stable</small>
                </div>
            </div>
            <div class="col-lg col-md-6">
                <div class="card stat-card p-3">
                    <small class="text-muted fw-bold">CHURN</small>
                    <h3 class="fw-bold mb-1">1.8%</h3>
                    <small class="text-success">Low Risk</small>
                </div>
            </div>
            <div class="col-lg col-md-6">
                <div class="card stat-card p-3">
                    <small class="text-muted fw-bold">LTV</small>
                    <h3 class="fw-bold mb-1">$850</h3>
                    <small class="text-primary">↑ 4% growth</small>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-container">
                    <h6 class="fw-bold text-muted mb-3">Revenue by Segment (Historical vs Predicted)</h6>
                    <div class="chart-area">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <h6 class="fw-bold text-muted mb-3">CAC by Segment</h6>
                    <div class="chart-area">
                        <canvas id="cacChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="chart-container">
                    <h6 class="fw-bold text-muted mb-3">Customer Segment Migration (Monthly Flow)</h6>
                    <div style="position: relative; height: 200px;">
                        <canvas id="migrationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Revenue Chart (Line)
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun (Pred)'],
                datasets: [{
                        label: 'High-Value',
                        data: [450, 480, 460, 510, 550, 600],
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.05)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Standard',
                        data: [300, 310, 340, 330, 350, 380],
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.05)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // CAC Chart (Bar)
        new Chart(document.getElementById('cacChart'), {
            type: 'bar',
            data: {
                labels: ['High-Value', 'Standard', 'At-Risk'],
                datasets: [{
                    label: 'Cost ($)',
                    data: [85, 35, 12],
                    backgroundColor: ['#0d6efd', '#6c757d', '#dc3545'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Migration Chart (Horizontal Bar)
        new Chart(document.getElementById('migrationChart'), {
            type: 'bar',
            indexAxis: 'y',
            data: {
                labels: ['Standard → High-Value', 'High-Value → At-Risk', 'At-Risk → Churn'],
                datasets: [{
                    label: 'Customers Moved',
                    data: [450, 120, 85],
                    backgroundColor: '#20c997',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

</body>

</html>