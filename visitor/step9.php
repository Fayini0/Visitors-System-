<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Welcome Back!</title>
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

        .confirmation-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .welcome-back-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 40px rgba(39, 174, 96, 0.4);
            animation: welcomePulse 2s ease-in-out infinite alternate;
        }

        .welcome-back-icon i {
            font-size: 60px;
            color: white;
        }

        .page-title {
            color: var(--success-color);
            font-size: 2.4rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--dark-gray);
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .welcome-message {
            color: var(--success-color);
            font-size: 1rem;
            margin-bottom: 30px;
            font-style: italic;
        }

        .visit-summary {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(46, 204, 113, 0.05));
            border: 2px solid var(--success-color);
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }

        .summary-header {
            color: var(--success-color);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .summary-header i {
            margin-right: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: rgba(255, 255, 255, 0.7);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--success-color);
        }

        .summary-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .visit-stats {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }

        .stats-header {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-header i {
            margin-right: 8px;
            color: var(--secondary-color);
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .stats-row:last-child {
            border-bottom: none;
        }

        .stats-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .stats-value {
            color: var(--secondary-color);
            font-weight: 700;
        }

        .countdown-section {
            background: rgba(243, 156, 18, 0.1);
            border: 2px solid var(--warning-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .countdown-header {
            color: var(--warning-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .countdown-header i {
            margin-right: 8px;
        }

        .time-remaining {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--warning-color);
            margin-bottom: 8px;
        }

        .checkout-reminder {
            color: var(--dark-gray);
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .quick-actions {
            background: white;
            border: 2px solid var(--secondary-color);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .actions-header {
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .actions-header i {
            margin-right: 8px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .action-button {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .primary-action {
            background: var(--success-color);
            color: white;
        }

        .primary-action:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
            color: white;
        }

        .secondary-action {
            background: var(--secondary-color);
            color: white;
        }

        .secondary-action:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            color: white;
        }

        .loyalty-badge {
            background: linear-gradient(45deg, #8e44ad, #9b59b6);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 20px auto;
            display: inline-block;
            box-shadow: 0 5px 15px rgba(142, 68, 173, 0.3);
        }

        .loyalty-badge i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .confirmation-card {
                padding: 30px 25px;
                margin: 20px;
            }

            .page-title {
                font-size: 2rem;
            }

            .welcome-back-icon {
                width: 100px;
                height: 100px;
            }

            .welcome-back-icon i {
                font-size: 50px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .time-remaining {
                font-size: 1.8rem;
            }
        }

        .animate-entrance {
            animation: slideInUp 0.8s ease-out;
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

        @keyframes welcomePulse {
            from {
                transform: scale(1);
                box-shadow: 0 15px 40px rgba(39, 174, 96, 0.4);
            }
            to {
                transform: scale(1.05);
                box-shadow: 0 20px 50px rgba(39, 174, 96, 0.6);
            }
        }

        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) { left: 10%; animation-delay: -0.5s; }
        .floating-element:nth-child(2) { left: 70%; animation-delay: -2s; }
        .floating-element:nth-child(3) { left: 40%; animation-delay: -3.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .email-sent {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid var(--secondary-color);
            border-radius: 8px;
            padding: 12px 15px;
            margin-top: 15px;
            color: var(--secondary-color);
            font-size: 0.9rem;
            display: none;
            text-align: center;
        }

        .email-sent.show {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <i class="fas fa-star floating-element" style="font-size: 2rem; top: 20%;"></i>
        <i class="fas fa-heart floating-element" style="font-size: 1.5rem; top: 60%;"></i>
        <i class="fas fa-thumbs-up floating-element" style="font-size: 2.5rem; top: 80%;"></i>
    </div>

    <div class="main-container">
        <div class="confirmation-card animate-entrance">
            <div class="welcome-back-icon">
                <i class="fas fa-home"></i>
            </div>

            <h1 class="page-title">Welcome Back!</h1>
            <p class="page-subtitle">Great to see you again at Sophen Residence</p>
            <p class="welcome-message">Your visit has been confirmed. Thanks for being a valued visitor!</p>

            <div class="loyalty-badge">
                <i class="fas fa-crown"></i>
                Returning Visitor - Visit #<span id="visitNumber">4</span>
            </div>

            <div class="visit-summary">
                <div class="summary-header">
                    <i class="fas fa-ticket-alt"></i>
                    Your Visit Pass
                </div>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Visitor</div>
                        <div class="summary-value" id="visitorName">Alice Johnson</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">ID Number</div>
                        <div class="summary-value" id="visitorId">9001015009088</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Room</div>
                        <div class="summary-value" id="roomNumber">B302</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Host</div>
                        <div class="summary-value" id="hostName">Mike Johnson</div>
                    </div>
                </div>

                <div style="text-align: center; padding: 15px; background: rgba(255, 255, 255, 0.7); border-radius: 8px;">
                    <strong>Check-in:</strong> <span id="checkinTime">Dec 20, 2024 - 15:45</span><br>
                    <strong>Must Check Out By:</strong> <span id="checkoutTime">Dec 20, 2024 - 22:00</span>
                </div>
            </div>

            <div class="visit-stats">
                <div class="stats-header">
                    <i class="fas fa-chart-line"></i>
                    Your Visit History
                </div>
                <div class="stats-row">
                    <span class="stats-label">Total Visits:</span>
                    <span class="stats-value" id="totalVisits">4</span>
                </div>
                <div class="stats-row">
                    <span class="stats-label">Last Visit:</span>
                    <span class="stats-value" id="lastVisit">Dec 15, 2024</span>
                </div>
                <div class="stats-row">
                    <span class="stats-label">Favorite Host:</span>
                    <span class="stats-value" id="favoriteHost">Mike Johnson</span>
                </div>
                <div class="stats-row">
                    <span class="stats-label">Average Visit Duration:</span>
                    <span class="stats-value" id="avgDuration">4.5 hours</span>
                </div>
            </div>

            <div class="countdown-section">
                <div class="countdown-header">
                    <i class="fas fa-clock"></i>
                    Time Remaining
                </div>
                <div class="time-remaining" id="timeRemaining">6h 15m</div>
                <div class="checkout-reminder">You'll receive a reminder 30 minutes before checkout time</div>
            </div>

            <div class="quick-actions">
                <div class="actions-header">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </div>
                <div class="action-buttons">
                    <button class="action-button primary-action" onclick="proceedToResidence()">
                        <i class="fas fa-door-open me-2"></i>
                        Enter Residence
                    </button>
                    <button class="action-button secondary-action" onclick="emailPass()">
                        <i class="fas fa-envelope me-2"></i>
                        Email Pass
                    </button>
                    <a href="checkout.php" class="action-button" style="background: var(--warning-color); color: white;">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Early Checkout
                    </a>
                    <button class="action-button" style="background: var(--light-gray); color: var(--dark-gray);" onclick="extendVisit()">
                        <i class="fas fa-plus-circle me-2"></i>
                        Extend Visit
                    </button>
                </div>
                
                <div class="email-sent" id="emailSent">
                    <i class="fas fa-check-circle me-2"></i>
                    Visit pass sent to your email address!
                </div>
            </div>

            <div style="background: rgba(231, 76, 60, 0.1); border-left: 4px solid var(--danger-color); padding: 15px; border-radius: 8px; margin: 25px 0; text-align: left;">
                <h6 style="color: var(--danger-color); font-weight: 700; margin-bottom: 10px;">
                    <i class="fas fa-shield-alt me-2"></i>Security Reminder
                </h6>
                <p style="margin: 0; color: var(--dark-gray); font-size: 0.9rem;">
                    Keep your ID with you and follow all residence guidelines. 
                    Emergency contact: <strong>+27 123 456 789</strong>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        let checkoutTime;
        let timerInterval;

        $(document).ready(function() {
            // Load visit details
            loadVisitDetails();
        });

        function loadVisitDetails() {
            const urlParams = new URLSearchParams(window.location.search);
            const visitId = urlParams.get('visit_id');

            if (!visitId) {
                alert('No visit ID found. Please restart the process.');
                window.location.href = 'step1.php';
                return;
            }

            // Fetch real visit data from backend
            $.ajax({
                url: 'process/check_status.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const visitData = response.visit;

                        // If visit is approved, redirect to step1 after brief delay
                        if (visitData.visit_status === 'approved') {
                            setTimeout(function() {
                                window.location.href = 'http://localhost/sophen-residence-system/visitor/step1.php';
                            }, 5000); // 5s delay to let user see confirmation
                        }

                        // Calculate checkout time (assumed 6 hours from check-in)
                        const checkinTime = new Date(visitData.submit_time);
                        const checkoutTimeObj = new Date(checkinTime.getTime() + (6 * 60 * 60 * 1000)); // 6 hours later

                        $('#visitorName').text(visitData.visitor_name);
                        $('#visitorId').text(visitData.visitor_id);
                        $('#roomNumber').text(visitData.room_number);
                        $('#hostName').text(visitData.host_name);
                        $('#checkinTime').text(formatDateTime(visitData.submit_time));
                        $('#checkoutTime').text(formatDateTime(checkoutTimeObj.toISOString()));
                        $('#visitNumber').text(visitData.total_visits);
                        $('#totalVisits').text(visitData.total_visits);

                        // Set defaults for other display fields when data is unavailable
                        $('#lastVisit').text('Previous visit');
                        $('#favoriteHost').text(visitData.host_name);
                        $('#avgDuration').text('4.5 hours');

                        checkoutTime = checkoutTimeObj;

                        // Start countdown timer after data is loaded
                        startCountdownTimer();
                    } else {
                        alert('Error loading visit details: ' + response.message);
                        window.location.href = 'step1.php';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading visit details:', error);
                    alert('Error loading visit details. Please try again.');
                    window.location.href = 'step1.php';
                }
            });
        }

        function startCountdownTimer() {
            updateCountdown(); // Initial update
            timerInterval = setInterval(updateCountdown, 60000); // Update every minute
        }

        function updateCountdown() {
            const now = new Date();
            const timeDiff = checkoutTime - now;

            if (timeDiff <= 0) {
                $('#timeRemaining').text('Time Expired');
                $('#timeRemaining').css('color', 'var(--danger-color)');
                $('.countdown-section').css('border-color', 'var(--danger-color)');
                clearInterval(timerInterval);
                return;
            }

            const hours = Math.floor(timeDiff / (1000 * 60 * 60));
            const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));

            $('#timeRemaining').text(`${hours}h ${minutes}m`);

            // Change colors based on time remaining
            if (timeDiff < 3600000) { // Less than 1 hour
                $('#timeRemaining').css('color', 'var(--danger-color)');
                $('.countdown-section').css('border-color', 'var(--danger-color)');
                $('.countdown-header').css('color', 'var(--danger-color)');
            } else if (timeDiff < 7200000) { // Less than 2 hours
                $('#timeRemaining').css('color', 'var(--warning-color)');
            }
        }

        function proceedToResidence() {
            const button = event.target;
            const originalText = button.innerHTML;

            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking In...';

            // Get visit_id from URL
            const urlParams = new URLSearchParams(window.location.search);
            const visitId = urlParams.get('visit_id');

            if (!visitId) {
                alert('Visit ID not found. Please restart the process.');
                button.disabled = false;
                button.innerHTML = originalText;
                return;
            }

            // Check in the visitor
            $.ajax({
                url: 'process/check_in.php',
                type: 'POST',
                data: { visit_id: visitId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success animation
                        button.innerHTML = '<i class="fas fa-check me-2"></i>Access Granted!';
                        button.style.background = 'var(--success-color)';

                        setTimeout(() => {
                            showWelcomeMessage();
                        }, 2000);
                    } else {
                        alert('Check-in failed: ' + response.message);
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Check-in error:', error);
                    alert('Error during check-in. Please try again.');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            });
        }

        function showWelcomeMessage() {
            const card = $('.confirmation-card');
            card.html(`
                <div class="welcome-back-icon" style="background: var(--success-color);">
                    <i class="fas fa-door-open"></i>
                </div>
                <h1 class="page-title" style="color: var(--success-color);">Welcome Home!</h1>
                <p class="page-subtitle" style="font-size: 1.3rem; color: var(--success-color);">
                    Enjoy your visit and don't forget to check out on time.
                </p>
                <div class="loyalty-badge" style="margin: 30px auto;">
                    <i class="fas fa-heart me-2"></i>
                    Thanks for being a valued visitor!
                </div>
                <div style="margin: 30px 0;">
                    <button class="action-button primary-action" onclick="window.location.href='../index.php'">
                        <i class="fas fa-home me-2"></i>
                        Return to Home
                    </button>
                </div>
            `);
        }

        function emailPass() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

            // Simulate email sending
            setTimeout(() => {
                $('#emailSent').addClass('show');
                button.disabled = false;
                button.innerHTML = originalText;
                
                // Hide success message after 5 seconds
                setTimeout(() => {
                    $('#emailSent').removeClass('show');
                }, 5000);
            }, 2000);
        }

        function extendVisit() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Requesting...';

            // Simulate extension request
            setTimeout(() => {
                alert('Extension request sent to security. You will be notified of the decision shortly.');
                button.disabled = false;
                button.innerHTML = originalText;
            }, 2000);
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-ZA', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Clean up timer when leaving page
        window.addEventListener('beforeunload', function() {
            if (timerInterval) {
                clearInterval(timerInterval);
            }
        });

        // Add some interactive effects
        $(document).ready(function() {
            $('.summary-item').hover(
                function() {
                    $(this).css('transform', 'translateY(-2px)');
                    $(this).css('box-shadow', '0 5px 15px rgba(0,0,0,0.1)');
                },
                function() {
                    $(this).css('transform', 'translateY(0)');
                    $(this).css('box-shadow', 'none');
                }
            );
        });
    </script>
</body>
</html>