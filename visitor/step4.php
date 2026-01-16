<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed!");
}

try {
    // Fetch rooms that have active residents
    $rooms_query = "SELECT DISTINCT r.room_number FROM rooms r 
                    INNER JOIN residents res ON r.room_id = res.room_id 
                    WHERE res.is_active = TRUE 
                    ORDER BY r.room_number";
    $rooms_stmt = $db->prepare($rooms_query);
    $rooms_stmt->execute();
    $rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- Debug: Rooms count: " . count($rooms) . " -->";

    // Fetch active residents
    $residents_query = "SELECT CONCAT(first_name, ' ', last_name) as full_name 
                        FROM residents 
                        WHERE is_active = TRUE 
                        ORDER BY first_name, last_name";
    $residents_stmt = $db->prepare($residents_query);
    $residents_stmt->execute();
    $residents = $residents_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- Debug: Residents count: " . count($residents) . " -->";

} catch (PDOException $e) {
    die("Query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Visitor Details</title>
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
            max-width: 600px;
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

        .form-section {
            margin: 30px 0;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
            opacity: 0.7;
            z-index: 1;
            pointer-events: none;
        }

        .input-wrapper .with-icon {
            padding-left: 42px;
        }

        .required-field::after {
            content: '*';
            color: var(--danger-color);
            margin-left: 4px;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 15px;
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
            justify-content: center;
        }

        .back-button {
            background: var(--light-gray);
            color: var(--dark-gray);
            flex: 1;
        }

        .back-button:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
            transform: translateX(-3px);
        }

        .continue-button {
            background: var(--secondary-color);
            color: white;
            flex: 2;
        }

        .continue-button:hover {
            background: #2980b9;
            transform: translateX(3px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .continue-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .info-text {
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-row .input-group {
            flex: 1 1 280px;
        }

        @media (max-width: 768px) {
            .visitor-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .form-row {
                flex-direction: column;
                gap: 15px;
            }

            .navigation-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .nav-button {
                width: 100%;
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

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            display: block;
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 8px;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="visitor-card animate-entrance">
            <div class="header-section">
                <div class="system-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="page-title">Tell Us Who You Are</h1>
                <p class="page-subtitle">Please provide your details for security purposes</p>
            </div>

            <div class="info-text">
                <i class="fas fa-shield-alt me-2"></i>
                Your information is kept secure and will only be used for residence security purposes.
            </div>

            <form id="visitorForm" method="POST" action="step6.php">
                <div class="form-section">
                    <div class="input-group">
                        <label for="fullName" class="form-label required-field">Full Name</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control with-icon" id="fullName" name="full_name" 
                                   placeholder="Enter your full name" required>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="input-group">
                        <label for="idNumber" class="form-label required-field">ID Number or Student ID</label>
                        <div class="input-wrapper">
                            <i class="fas fa-id-card input-icon"></i>
                            <input type="text" class="form-control with-icon" id="idNumber" name="id_number" 
                                   placeholder="Enter your ID or Student number" required>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="roomNumber" class="form-label required-field">Room Number</label>
                            <div class="input-wrapper">
                                <i class="fas fa-door-open input-icon"></i>
                                <select class="form-control with-icon" id="roomNumber" name="room_number" required>
                                    <option value="">Select Room Number</option>
                                    <?php 
                                    // Debug: Output rooms data
                                    echo "<!-- DEBUG ROOMS: ";
                                    print_r($rooms);
                                    echo " -->";
                                    foreach($rooms as $room): 
                                    ?>
                                    <option value="<?php echo htmlspecialchars($room['room_number']); ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="input-group">
                            <label for="hostName" class="form-label required-field">Who are you visiting?</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user-friends input-icon"></i>
                                <select class="form-control with-icon" id="hostName" name="host_name" required disabled>
                                    <option value="">Select Room First</option>
                                </select>
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="email" class="form-label required-field">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" class="form-control with-icon" id="email" name="email" 
                                   placeholder="your.email@example.com" required>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="input-group">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" class="form-control with-icon" id="phone" name="phone" 
                                   placeholder="+27 123 456 789">
                        </div>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <a href="step3.php" class="nav-button back-button">
                        <i class="fas fa-arrow-left me-2"></i>
                        Back
                    </a>
                    <button type="submit" class="nav-button continue-button">
                        Continue
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
            // Form validation
            function validateForm() {
                let isValid = true;
                const requiredFields = ['fullName', 'idNumber', 'roomNumber', 'hostName', 'email'];
                
                requiredFields.forEach(function(fieldId) {
                    const field = $('#' + fieldId);
                    const value = field.val().trim();
                    
                    if (!value) {
                        showFieldError(field, 'This field is required');
                        isValid = false;
                    } else {
                        clearFieldError(field);
                        
                        // Email validation
                        if (fieldId === 'email' && !isValidEmail(value)) {
                            showFieldError(field, 'Please enter a valid email address');
                            isValid = false;
                        }
                        
                        // ID number validation (basic)
                        if (fieldId === 'idNumber' && value.length < 6) {
                            showFieldError(field, 'ID number must be at least 6 characters');
                            isValid = false;
                        }
                    }
                });
                
                return isValid;
            }

            function showFieldError(field, message) {
                field.addClass('is-invalid');
                const group = field.closest('.input-group');
                group.find('.invalid-feedback').text(message);
            }

            function clearFieldError(field) {
                field.removeClass('is-invalid');
                const group = field.closest('.input-group');
                group.find('.invalid-feedback').text('');
            }

            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            // Real-time validation
            $('input[required], select[required]').on('blur change', function() {
                const field = $(this);
                const value = field.val().trim();
                const fieldId = field.attr('id');
                
                if (!value) {
                    showFieldError(field, 'This field is required');
                } else {
                    clearFieldError(field);
                    
                    // Specific validations
                    if (fieldId === 'email' && !isValidEmail(value)) {
                        showFieldError(field, 'Please enter a valid email address');
                    } else if (fieldId === 'idNumber' && value.length < 6) {
                        showFieldError(field, 'ID number must be at least 6 characters');
                    }
                }
            });

            // Filter residents based on selected room
            $('#roomNumber').on('change', function() {
                const roomNumber = $(this).val();
                const hostSelect = $('#hostName');
                const submitBtn = $('.continue-button');

                if (roomNumber) {
                    // Show loading
                    hostSelect.prop('disabled', true).html('<option value="">Loading residents...</option>');

                    $.ajax({
                        url: 'process/get_residents_by_room.php',
                        type: 'POST',
                        data: { room_number: roomNumber },
                        dataType: 'json',
                        success: function(residents) {
                            hostSelect.html('<option value="">Select Resident</option>');
                            if (residents.error) {
                                hostSelect.html('<option value="">Error loading residents</option>');
                            } else if (residents.length > 0) {
                                residents.forEach(function(resident) {
                                    hostSelect.append('<option value="' + resident.full_name + '">' + resident.full_name + '</option>');
                                });
                            } else {
                                hostSelect.html('<option value="">No residents in this room</option>');
                            }
                            hostSelect.prop('disabled', false);
                        },
                        error: function() {
                            hostSelect.html('<option value="">Error loading residents</option>').prop('disabled', false);
                        }
                    });
                } else {
                    hostSelect.prop('disabled', true).html('<option value="">Select Room First</option>');
                }

                // Update submit button state
                if (roomNumber) {
                    submitBtn.prop('disabled', false);
                } else {
                    submitBtn.prop('disabled', true);
                }
            });

            // Clear errors on input/change
            $('input, select').on('input change', function() {
                if ($(this).hasClass('is-invalid')) {
                    clearFieldError($(this));
                }
            });

            // Form submission
            $('#visitorForm').submit(function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    // Show loading state
                    const submitBtn = $('.continue-button');
                    const originalText = submitBtn.html();
                    submitBtn.prop('disabled', true)
                           .html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
                    
                    // Simulate processing delay
                    setTimeout(() => {
                        this.submit();
                    }, 1000);
                } else {
                    // Scroll to first error
                    const firstError = $('.is-invalid').first();
                    if (firstError.length) {
                        $('html, body').animate({
                            scrollTop: firstError.offset().top - 100
                        }, 300);
                    }
                }
            });

            // Format phone number as user types
            $('#phone').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.startsWith('27')) {
                        value = '+27 ' + value.substring(2);
                    } else if (!value.startsWith('+')) {
                        value = '+27 ' + value;
                    }
                    
                    // Format: +27 123 456 789
                    value = value.replace(/(\+27)(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');
                }
                $(this).val(value);
            });

            // Auto-capitalize names
            $('#fullName').on('input', function() {
                const value = $(this).val();
                const capitalized = value.replace(/\b\w/g, l => l.toUpperCase());
                $(this).val(capitalized);
            });

            // Add smooth focus effects
            $('.form-control').focus(function() {
                $(this).parent().addClass('focused');
            }).blur(function() {
                $(this).parent().removeClass('focused');
            });
        });

        // Add focus animation styles
        const style = document.createElement('style');
        style.textContent = `
            .input-group.focused .input-icon {
                color: var(--secondary-color);
                opacity: 1;
                transform: translateY(-50%) scale(1.1);
            }
            
            .form-control:focus + .input-icon {
                color: var(--secondary-color);
                opacity: 1;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>