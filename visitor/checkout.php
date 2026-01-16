<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Check Out</title>
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

        .checkout-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .header-section {
            margin-bottom: 30px;
        }

        .system-logo {
            width: 80px;
            height: 80px;
            background: var(--danger-color);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .system-logo i {
            font-size: 40px;
            color: white;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 30px;
        }

        .checkout-form {
            margin: 30px 0;
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
            opacity: 0.7;
            z-index: 10;
        }

        .checkout-input {
            width: 100%;
            padding: 18px 60px 18px 60px;
            border: 3px solid #e9ecef;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }

        .checkout-input:focus {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 0.3rem rgba(231, 76, 60, 0.25);
            outline: none;
        }

        .checkout-input::placeholder {
            color: #aaa;
        }

        .checkout-button {
            width: 100%;
            padding: 18px 30px;
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .checkout-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.4);
        }

        .checkout-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .info-section {
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--danger-color);
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
        }

        .info-section h5 {
            color: var(--danger-color);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .info-section p {
            margin: 0;
            color: var(--dark-gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .back-link {
            margin-top: 30px;
        }

        .back-link a {
            color: var(--dark-gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
        }

        .back-link a:hover {
            color: var(--secondary-color);
        }

        .visitor-found {
            background: rgba(39, 174, 96, 0.1);
            border: 2px solid var(--success-color);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            display: none;
        }

        .visitor-found.show {
            display: block;
            animation: slideDown 0.4s ease-out;
        }

        .visitor-info {
            text-align: left;
        }

        .visitor-info h5 {
            color: var(--success-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .visitor-info h5 i {
            margin-right: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .info-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .checkout-success {
            background: rgba(39, 174, 96, 0.1);
            border: 2px solid var(--success-color);
            border-radius: 12px;
            padding: 30px;
            margin: 25px 0;
            display: none;
            text-align: center;
        }

        .checkout-success.show {
            display: block;
            animation: slideDown 0.4s ease-out;
        }

        .success-icon {
            width: 60px;
            height: 60px;
            background: var(--success-color);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-icon i {
            font-size: 30px;
            color: white;
        }

        .error-message {
            background: rgba(231, 76, 60, 0.1);
            border: 2px solid var(--danger-color);
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: var(--danger-color);
            font-weight: 500;
            display: none;
        }

        .error-message.show {
            display: block;
            animation: shake 0.5s ease-in-out;
        }

        @media (max-width: 768px) {
            .checkout-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 1.6rem;
            }

            .checkout-input {
                padding: 15px 50px;
                font-size: 1rem;
            }

            .checkout-button {
                padding: 15px 25px;
                font-size: 1.1rem;
            }

            .info-row {
                flex-direction: column;
                gap: 2px;
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        .visitors-list {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid var(--secondary-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .visitors-list h5 {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .visitors-list h5 i {
            margin-right: 8px;
        }

        .visitors-container {
            max-height: 300px;
            overflow-y: auto;
        }

        .loading-visitors {
            text-align: center;
            color: var(--dark-gray);
            padding: 20px;
        }

        .visitor-item {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .visitor-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .visitor-details {
            flex: 1;
        }

        .visitor-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        .visitor-info {
            font-size: 0.9rem;
            color: var(--dark-gray);
        }

        .visitor-id {
            font-weight: 500;
            color: var(--secondary-color);
        }

        .no-visitors {
            text-align: center;
            color: var(--dark-gray);
            padding: 20px;
            font-style: italic;
        }

        .action-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .btn-checkout {
            background: var(--danger-color);
        }

        .btn-checkout:hover {
            background: #c0392b;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="checkout-card animate-entrance">
            <div class="header-section">
                <div class="system-logo">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h1 class="page-title">Check Out</h1>
                <p class="page-subtitle">Thank you for visiting us today</p>
            </div>

            <div class="info-section">
                <h5><i class="fas fa-info-circle me-2"></i>Important Information</h5>
                <p>Please enter your ID number to complete your checkout process.
                   This helps us maintain accurate security records.</p>
            </div>



            <form id="checkoutForm" class="checkout-form">
                <div class="input-group">
                    <i class="fas fa-id-card input-icon"></i>
                    <input type="text" class="checkout-input" id="visitorId" name="visitor_id"
                           placeholder="Enter Your ID Number">
                </div>

                <button type="submit" class="checkout-button" id="checkoutBtn">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Check Out
                </button>
            </form>

            <!-- Error Message -->
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span id="errorText">Visitor not found. Please check your ID number.</span>
            </div>

            <!-- Visitor Found Display -->
            <div class="visitor-found" id="visitorFound">
                <div class="visitor-info">
                    <h5><i class="fas fa-user-check"></i>Visitor Details</h5>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value" id="visitorName">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-in Time:</span>
                        <span class="info-value" id="checkinTime">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Room Visited:</span>
                        <span class="info-value" id="roomVisited">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Host:</span>
                        <span class="info-value" id="hostVisited">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration:</span>
                        <span class="info-value" id="visitDuration">-</span>
                    </div>
                </div>
            </div>

            <!-- Success Message -->
            <div class="checkout-success" id="checkoutSuccess">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h4 style="color: var(--success-color); margin-bottom: 10px;">Successfully Checked Out!</h4>
                <p style="color: var(--dark-gray); margin: 0;">
                    Thank you for visiting. Have a safe journey!<br>
                    <small>A confirmation email has been sent to you.</small>
                </p>
            </div>

            <div class="back-link">
                <a href="step1.php">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#checkoutForm').submit(function(e) {
                e.preventDefault();
                processCheckout();
            });

            function processCheckout() {
                const visitorId = $('#visitorId').val().trim();

                if (!visitorId) {
                    showError('Please enter your ID number');
                    return;
                }

                // Show loading state
                const btn = $('#checkoutBtn');
                const originalContent = btn.html();
                btn.prop('disabled', true)
                   .html('<div class="loading-spinner"></div>Processing...');

                // Hide previous messages
                hideAllMessages();

                // Make AJAX call to backend
                $.ajax({
                    url: 'process/process_checkout.php',
                    type: 'POST',
                    data: {
                        visitor_id: visitorId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displayVisitorInfo(response.visitor);
                            setTimeout(() => {
                                performCheckout(response.visitor);
                            }, 2000);
                        } else {
                            showError(response.error || 'An error occurred during checkout');
                            btn.prop('disabled', false).html(originalContent);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Checkout error:', error);
                        showError('Failed to process checkout. Please try again.');
                        btn.prop('disabled', false).html(originalContent);
                    }
                });
            }

            function displayVisitorInfo(visitor) {
                $('#visitorName').text(visitor.name);
                $('#checkinTime').text(visitor.checkin_time);
                $('#roomVisited').text(visitor.room_number);
                $('#hostVisited').text(visitor.host_name);
                $('#visitDuration').text(visitor.duration + ' minutes');

                $('#visitorFound').addClass('show');
            }

            function performCheckout(visitor) {
                $('#visitorFound').removeClass('show');
                $('#checkoutSuccess').addClass('show');

                // Reset form after success
                setTimeout(() => {
                    resetForm();
                }, 5000);
            }

            function showError(message) {
                $('#errorText').text(message);
                $('#errorMessage').addClass('show');

                setTimeout(() => {
                    $('#errorMessage').removeClass('show');
                }, 5000);
            }

            function hideAllMessages() {
                $('#errorMessage, #visitorFound, #checkoutSuccess').removeClass('show');
            }

            function resetForm() {
                $('#visitorId').val('');
                $('#checkoutBtn').prop('disabled', false)
                                .html('<i class="fas fa-sign-out-alt me-2"></i>Check Out');
                hideAllMessages();
            }

            // Auto-format ID input
            $('#visitorId').on('input', function() {
                // Remove any non-alphanumeric characters for cleaner input
                let value = $(this).val().replace(/[^a-zA-Z0-9]/g, '');
                $(this).val(value);
            });

            // Enter key support
            $('#visitorId').keypress(function(e) {
                if (e.which === 13 && !$('#checkoutBtn').is(':disabled')) {
                    $('#checkoutForm').submit();
                }
            });
        });
    </script>
</body>
</html>