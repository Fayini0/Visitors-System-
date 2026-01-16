<?php
require_once '../config/config.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen Residence System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-gray: #ecf0f1;
            --dark-gray: #34495e;
            --admin-color: #8e44ad;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--admin-color), #7d3c98);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo i {
            font-size: 30px;
            color: white;
        }

        .sidebar-title {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 5px 15px;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }

        .main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--dark-gray);
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .action-btn:hover {
            background: var(--light-gray);
            color: var(--admin-color);
        }

        .content-wrapper {
            padding: 30px;
        }

        .card {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .card-header {
            background: var(--light-gray);
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
        }

        .form-inline {
            display: flex;
            align-items: center;
        }

        .input-group-sm .form-control {
            border-radius: 8px 0 0 8px;
        }

        .input-group-append .btn {
            border-radius: 0 8px 8px 0;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(142, 68, 173, 0.05);
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
            font-weight: 600;
            color: black !important;
        }

        .btn-group-sm .btn {
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content-wrapper {
                padding: 20px;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-inline {
                width: 100%;
            }
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
            </div>
            <h3 class="sidebar-title">Sophen</h3>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="overview.php" class="nav-link">
                    <i class="fas fa-eye"></i>
                    Overview
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-visitors.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    Manage Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="manage-residents.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Manage Residents
                </a>
            </div>
            <div class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>
                    Profile Management
                </a>
            </div>
            <div class="nav-item">
                <a href="blocked-visitors.php" class="nav-link">
                    <i class="fas fa-user-slash"></i>
                    Blocked Visitors
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div style="display: flex; align-items: center;">
                <button class="mobile-menu-btn d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Admin Panel</h1>
            </div>
            
            <div class="navbar-actions">
                <a href="index.php" class="action-btn" title="Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                </a>
                <a href="logout.php" class="action-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
