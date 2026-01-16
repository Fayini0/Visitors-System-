<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Visitor Type</title>
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
            margin-bottom: 30px;
        }

        .system-logo {
            width: 60px;
            height: 60px;
            background: var(--secondary-color);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .system-logo i {
            font-size: 30px;
            color: white;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .question-section {
            margin: 30px 0;
            text-align: center;
        }

        .question-text {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 30px;
        }

        .choice-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .choice-button {
            padding: 20px;
            border: 2px solid transparent;
            border-radius: 12px;
            background: white;
            color: var(--dark-gray);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .choice-button i {
            font-size: 1.5rem;
            margin-right: 12px;
        }

        .choice-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .returning-visitor {
            border-color: var(--success-color);
        }

        .returning-visitor:hover {
            background: var(--success-color);
            border-color: var(--success-color);
        }

        .first-time-visitor {
            border-color: var(--secondary-color);
        }

        .first-time-visitor:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .back-button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            background: var(--light-gray);
            color: var(--dark-gray);
        }

        .back-button:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
            transform: translateX(-3px);
        }

        .info-box {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .info-box p {
            margin: 0;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .visitor-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .choice-button {
                padding: 15px;
                font-size: 1rem;
            }

            .choice-button i {
                font-size: 1.3rem;
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
    </style>
</head>
<body>
    <div class="main-container">
        <div class="visitor-card animate-entrance">
            <div class="header-section">
                <div class="system-logo">
                    <i class="fas fa-home"></i>
                </div>
                <h1 class="page-title">Visitor Information</h1>
                <p class="page-subtitle">Help us serve you better</p>
            </div>

            <div class="question-section">
                <div class="question-text">
                    Have you visited this residence before?
                </div>
                
                <div class="choice-buttons">
                    <a href="step5.php" class="choice-button returning-visitor">
                        <i class="fas fa-check-circle"></i>
                        <span>Yes, I'm returning</span>
                    </a>

                    <a href="step4.php" class="choice-button first-time-visitor">
                        <i class="fas fa-user-plus"></i>
                        <span>No, first time</span>
                    </a>
                </div>

                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle me-2"></i>
                        If you're returning, we'll help you find your previous visit information. 
                        If it's your first time, we'll collect some basic details for security purposes.
                    </p>
                </div>
            </div>

            <div class="navigation-buttons">
                <a href="step2.php" class="back-button">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Add click animations and ripple effects
            $('.choice-button').on('click', function(e) {
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
                    background: 'rgba(255, 255, 255, 0.4)',
                    borderRadius: '50%',
                    transform: 'scale(0)',
                    animation: 'ripple 0.6s linear',
                    pointerEvents: 'none'
                });
                
                button.css('position', 'relative').append(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });

            // Add hover effects
            $('.choice-button').hover(
                function() {
                    $(this).addClass('animated');
                },
                function() {
                    $(this).removeClass('animated');
                }
            );
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