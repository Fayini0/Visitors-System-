<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Visit Reason</title>
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

        .reason-section {
            margin: 30px 0;
        }

        .reason-label {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }

        .reason-option {
            margin: 15px 0;
        }

        .reason-radio {
            display: none;
        }

        .reason-button {
            width: 100%;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 12px;
            background: white;
            color: var(--dark-gray);
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .reason-button i {
            font-size: 1.5rem;
            margin-right: 12px;
        }

        .reason-button:hover {
            border-color: var(--secondary-color);
            background: rgba(52, 152, 219, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .reason-radio:checked + .reason-button {
            border-color: var(--secondary-color);
            background: var(--secondary-color);
            color: white;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .reason-radio:checked + .reason-button:hover {
            background: #2980b9;
            border-color: #2980b9;
        }

        .student-reason {
            border-color: var(--success-color);
        }

        .reason-radio:checked + .student-reason {
            background: var(--success-color);
            border-color: var(--success-color);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
        }

        .maintenance-reason {
            border-color: var(--warning-color);
        }

        .reason-radio:checked + .maintenance-reason {
            background: var(--warning-color);
            border-color: var(--warning-color);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }

        .other-reason {
            border-color: var(--secondary-color);
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .nav-button {
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
        }

        .back-button {
            background: var(--light-gray);
            color: var(--dark-gray);
        }

        .back-button:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
            transform: translateX(-3px);
        }

        .next-button {
            background: var(--secondary-color);
            color: white;
            opacity: 0.5;
            pointer-events: none;
        }

        .next-button.active {
            opacity: 1;
            pointer-events: all;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .next-button.active:hover {
            background: #2980b9;
            transform: translateX(3px);
        }

        @media (max-width: 768px) {
            .visitor-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .reason-button {
                padding: 15px;
                font-size: 1rem;
            }

            .reason-button i {
                font-size: 1.3rem;
                margin-right: 10px;
            }

            .navigation-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .nav-button {
                width: 100%;
                justify-content: center;
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
                <h1 class="page-title">Visit Reason</h1>
                <p class="page-subtitle">Please select the reason for your visit</p>
            </div>

            <form id="reasonForm" method="POST" action="step3.php">
                <div class="reason-section">
                    <div class="reason-label">What brings you here today?</div>
                    
                    <div class="reason-option">
                        <input type="radio" id="student" name="visit_reason" value="student" class="reason-radio">
                        <label for="student" class="reason-button student-reason">
                            <i class="fas fa-user-graduate"></i>
                            <span>Visiting a Student</span>
                        </label>
                    </div>

                    <div class="reason-option">
                        <input type="radio" id="maintenance" name="visit_reason" value="maintenance" class="reason-radio">
                        <label for="maintenance" class="reason-button maintenance-reason">
                            <i class="fas fa-tools"></i>
                            <span>Maintenance</span>
                        </label>
                    </div>

                    <div class="reason-option">
                        <input type="radio" id="other" name="visit_reason" value="other" class="reason-radio">
                        <label for="other" class="reason-button other-reason">
                            <i class="fas fa-ellipsis-h"></i>
                            <span>Other</span>
                        </label>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <a href="step1.php" class="nav-button back-button">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back
                    </a>
                    <button type="submit" class="nav-button next-button" id="nextButton">
                        Next
                        <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Enable next button when a reason is selected
            $('input[name="visit_reason"]').change(function() {
                $('#nextButton').addClass('active');
            });

            // Add click animations
            $('.reason-button').click(function() {
                // Remove previous selections
                $('.reason-button').removeClass('selected');
                $(this).addClass('selected');
                
                // Check the corresponding radio button
                $(this).prev('input[type="radio"]').prop('checked', true).trigger('change');
            });

            // Form submission
            $('#reasonForm').submit(function(e) {
                if (!$('input[name="visit_reason"]:checked').val()) {
                    e.preventDefault();
                    alert('Please select a reason for your visit.');
                    return false;
                }
            });

            // Add ripple effect to buttons
            $('.reason-button').on('click', function(e) {
                const button = $(this);
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
                
                button.css('position', 'relative').append(ripple);
                
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