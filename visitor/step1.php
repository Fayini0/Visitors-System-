<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Visitor Portal</title>
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

        .visitor-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .system-logo {
            width: 80px;
            height: 80px;
            background: var(--secondary-color);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .system-logo i {
            font-size: 40px;
            color: white;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 0;
        }

        .choice-section {
            margin: 40px 0;
        }

        .choice-button {
            width: 100%;
            padding: 25px 20px;
            margin: 15px 0;
            border: 3px solid transparent;
            border-radius: 15px;
            background: var(--light-gray);
            color: var(--dark-gray);
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .choice-button i {
            font-size: 2rem;
            margin-right: 15px;
        }

        .choice-button.check-in {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3);
        }

        .choice-button.check-in:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(46, 204, 113, 0.4);
            color: white;
        }

        .choice-button.check-out {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .choice-button.check-out:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.4);
            color: white;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--secondary-color);
        }

        .info-text {
            text-align: center;
            color: var(--dark-gray);
            font-size: 0.9rem;
            margin-top: 20px;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .visitor-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .choice-button {
                padding: 20px 15px;
                font-size: 1.1rem;
            }

            .choice-button i {
                font-size: 1.5rem;
                margin-right: 10px;
            }
        }

        .animate-entrance {
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .choice-button:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="visitor-card animate-entrance">
            <div class="header-section">
                <div class="system-logo">
                    <i class="fas fa-home"></i>
                </div>
                <h1 class="page-title">Welcome to Sophen</h1>
                <p class="page-subtitle">Residence Management System</p>
            </div>

            <div class="choice-section">
                <p class="info-text">Please select what you would like to do:</p>
                
                <a href="step2.php" class="choice-button check-in">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Check In</span>
                </a>

                <a href="checkout.php" class="choice-button check-out">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Check Out</span>
                </a>
            </div>

            <div class="back-link">
                <a href="../index.php">
                    <i class="fas fa-arrow-left me-1"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Add click animation
            $('.choice-button').click(function(e) {
                const button = $(this);
                
                // Create ripple effect
                const ripple = $('<span class="ripple"></span>');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.css({
                    width: size,
                    height: size,
                    left: x,
                    top: y,
                    position: 'absolute',
                    background: 'rgba(255, 255, 255, 0.3)',
                    borderRadius: '50%',
                    transform: 'scale(0)',
                    animation: 'ripple 0.6s linear',
                    pointerEvents: 'none'
                });
                
                button.append(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>