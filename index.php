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
            --accent-color: #e74c3c;
            --light-gray: #ecf0f1;
            --dark-gray: #34495e;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .landing-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 50px 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .system-logo {
            width: 120px;
            height: 120px;
            background: var(--secondary-color);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.3);
        }

        .system-logo i {
            font-size: 60px;
            color: white;
        }

        .system-title {
            color: var(--primary-color);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .system-subtitle {
            color: var(--dark-gray);
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.8;
        }

        .feature-list {
            text-align: left;
            margin: 30px 0;
            padding: 0;
            list-style: none;
        }

        .feature-list li {
            padding: 12px 0;
            color: var(--dark-gray);
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        .feature-list li i {
            color: var(--secondary-color);
            margin-right: 15px;
            font-size: 1.2rem;
            width: 20px;
        }

        .cta-button {
            background: linear-gradient(45deg, var(--secondary-color), #2980b9);
            border: none;
            color: white;
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
            text-decoration: none;
            display: inline-block;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(52, 152, 219, 0.4);
            color: white;
        }

        .admin-links {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .admin-link {
            color: var(--dark-gray);
            text-decoration: none;
            margin: 0 15px;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .admin-link:hover {
            color: var(--secondary-color);
        }

        .footer-text {
            margin-top: 30px;
            color: var(--dark-gray);
            font-size: 0.9rem;
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .landing-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .system-title {
                font-size: 2rem;
            }

            .cta-button {
                padding: 12px 30px;
                font-size: 1.1rem;
            }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="landing-card">
            <div class="system-logo pulse-animation">
                <i class="fas fa-home"></i>
            </div>
            
            <h1 class="system-title">Sophen</h1>
            <p class="system-subtitle">Visitor Management System for Residences</p>
            
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Digital Check-in & Check-out</li>
                <li><i class="fas fa-shield-alt"></i> Enhanced Security Management</li>
                <li><i class="fas fa-users"></i> Visitor & Resident Tracking</li>
                <li><i class="fas fa-clock"></i> Real-time Monitoring</li>
                <li><i class="fas fa-chart-bar"></i> Comprehensive Reports</li>
            </ul>
            
            <a href="visitor/step1.php" class="cta-button">
                <i class="fas fa-play-circle me-2"></i>Start Visitor Check-in
            </a>
            
            <div class="admin-links">
                <a href="admin/login.php" class="admin-link">
                    <i class="fas fa-user-shield me-1"></i>Administrator
                </a>
                <a href="security/login.php" class="admin-link">
                    <i class="fas fa-security me-1"></i>Security
                </a>
            </div>
            
            <div class="footer-text">
                © <?= date('Y') ?> Sophen Residence System<br>
                Streamline your residence management
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Add smooth scroll and interaction effects
        $(document).ready(function() {
            $('.cta-button').hover(
                function() {
                    $(this).addClass('pulse-animation');
                },
                function() {
                    $(this).removeClass('pulse-animation');
                }
            );
            
            // Add click effect
            $('.cta-button').click(function() {
                $(this).css('transform', 'scale(0.98)');
                setTimeout(() => {
                    $(this).css('transform', '');
                }, 150);
            });
        });
    </script>
</body>
</html>