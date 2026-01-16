<?php
session_start();

// Check if user is logged in and has security role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header('Location: login.php');
    exit();
}

// Get shift data
$shiftStart = $_SESSION['shift_start'] ?? time();
$shiftDuration = time() - $shiftStart;
$shiftHours = floor($shiftDuration / 3600);
$shiftMinutes = floor(($shiftDuration % 3600) / 60);

// Get today's statistics
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $today = date('Y-m-d');

    // Total check-ins today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(actual_checkin) = ?");
    $stmt->execute([$today]);
    $todayCheckIns = $stmt->fetch()['count'];

    // Total check-outs today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE DATE(actual_checkout) = ?");
    $stmt->execute([$today]);
    $todayCheckOuts = $stmt->fetch()['count'];

    // Total incidents (alerts)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todayIncidents = $stmt->fetch()['count'];

    // Currently checked in visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved')");
    $stmt->execute();
    $currentVisitors = $stmt->fetch()['count'];

    // Active alerts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM security_alerts WHERE alert_status = 'new'");
    $stmt->execute();
    $activeAlerts = $stmt->fetch()['count'];

    // Overdue visitors
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE visit_status IN ('checked_in', 'approved') AND expected_checkout < NOW()");
    $stmt->execute();
    $overdueVisitors = $stmt->fetch()['count'];

} catch (Exception $e) {
    error_log("End shift data error: " . $e->getMessage());
    $todayCheckIns = $todayCheckOuts = $todayIncidents = $currentVisitors = $activeAlerts = $overdueVisitors = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>End Shift - Sophen Security</title>
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
            --security-color: #e67e22;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .end-shift-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .end-shift-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .security-icon {
            width: 80px;
            height: 80px;
            background: var(--security-color);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(230, 126, 34, 0.3);
        }

        .security-icon i {
            font-size: 40px;
            color: white;
        }

        .end-shift-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .end-shift-subtitle {
            color: var(--dark-gray);
            font-size: 1rem;
            opacity: 0.8;
        }

        .shift-summary {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
        }

        .summary-header {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .summary-header i {
            margin-right: 10px;
            color: var(--security-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .summary-value {
            color: var(--primary-color);
            font-weight: 600;
        }

        .warning-section {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }

        .warning-header {
            color: var(--danger-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .warning-header i {
            margin-right: 10px;
        }

        .warning-list {
            margin: 0;
            padding-left: 20px;
            color: var(--dark-gray);
        }

        .warning-list li {
            margin-bottom: 8px;
        }

        .notes-section {
            margin: 25px 0;
        }

        .notes-header {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .notes-header i {
            margin-right: 10px;
            color: var(--security-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--security-color);
            box-shadow: 0 0 0 2px rgba(230, 126, 34, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .action-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .end-shift-btn {
            background: var(--danger-color);
            color: white;
            flex: 1;
        }

        .end-shift-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            color: white;
        }

        .cancel-btn {
            background: var(--light-gray);
            color: var(--dark-gray);
            flex: 1;
        }

        .cancel-btn:hover {
            background: #bdc3c7;
            color: var(--dark-gray);
        }

        .time-display {
            text-align: center;
            margin: 20px 0;
        }

        .current-time {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--security-color);
            margin-bottom: 5px;
        }

        .current-date {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            display: none;
        }

        .success-message.show {
            display: block;
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

        .success-title {
            color: var(--success-color);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .success-text {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .end-shift-container {
                margin: 20px;
                padding: 30px 25px;
            }

            .end-shift-title {
                font-size: 1.5rem;
            }

            .security-icon {
                width: 70px;
                height: 70px;
            }

            .security-icon i {
                font-size: 35px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .summary-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 3px;
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
    <div class="end-shift-container animate-entrance">
        <div class="end-shift-header">
            <div class="security-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h1 class="end-shift-title">End Shift</h1>
            <p class="end-shift-subtitle">Review your shift summary and add notes before ending</p>
        </div>

        <div class="time-display">
            <div class="current-time" id="currentTime"></div>
            <div class="current-date" id="currentDate"></div>
        </div>

        <div class="shift-summary">
            <div class="summary-header">
                <i class="fas fa-clipboard-list"></i>
                Shift Summary
            </div>
            <div class="summary-row">
                <span class="summary-label">Shift Duration:</span>
                <span class="summary-value" id="shiftDuration">
                    <?= sprintf('%02d:%02d', $shiftHours, $shiftMinutes) ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Officer:</span>
                <span class="summary-value" id="officerName">
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Check-ins Today:</span>
                <span class="summary-value" id="todayCheckIns">
                    <?= $todayCheckIns ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Check-outs Today:</span>
                <span class="summary-value" id="todayCheckOuts">
                    <?= $todayCheckOuts ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Incidents Today:</span>
                <span class="summary-value" id="todayIncidents">
                    <?= $todayIncidents ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Currently Inside:</span>
                <span class="summary-value" id="currentVisitors">
                    <?= $currentVisitors ?>
                </span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Active Alerts:</span>
                <span class="summary-value <?= $activeAlerts > 0 ? 'text-danger' : '' ?>" id="activeAlerts">
                    <?= $activeAlerts ?>
                </span>
            </div>
            <?php if ($overdueVisitors > 0): ?>
            <div class="summary-row">
                <span class="summary-label">Overdue Visitors:</span>
                <span class="summary-value text-danger" id="overdueVisitors">
                    <?= $overdueVisitors ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($activeAlerts > 0 || $overdueVisitors > 0): ?>
        <div class="warning-section">
            <div class="warning-header">
                <i class="fas fa-exclamation-triangle"></i>
                Important Notes for Next Shift
            </div>
            <ul class="warning-list">
                <?php if ($activeAlerts > 0): ?>
                <li><strong><?= $activeAlerts ?> pending security alerts</strong> need attention</li>
                <?php endif; ?>
                <?php if ($overdueVisitors > 0): ?>
                <li><strong><?= $overdueVisitors ?> visitors are overdue</strong> for checkout</li>
                <?php endif; ?>
                <li>Please ensure proper handover to the next security officer</li>
                <li>All alerts should be addressed before shift change</li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="notes-section">
            <div class="notes-header">
                <i class="fas fa-sticky-note"></i>
                Shift Notes (Optional)
            </div>
            <div class="form-group">
                <label for="shiftNotes" class="form-label">Add any notes about your shift:</label>
                <textarea
                    class="form-control"
                    id="shiftNotes"
                    name="shift_notes"
                    rows="4"
                    placeholder="Enter any important notes, incidents, or observations from your shift..."
                ></textarea>
            </div>
        </div>

        <div class="success-message" id="successMessage">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3 class="success-title">Shift Ended Successfully!</h3>
            <p class="success-text">Your shift has been recorded and you're being logged out.</p>
        </div>

        <form id="endShiftForm">
            <div class="action-buttons">
                <a href="index.php" class="action-btn cancel-btn">
                    <i class="fas fa-times me-2"></i>
                    Cancel
                </a>
                <button type="submit" class="action-btn end-shift-btn" id="endShiftBtn">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    End Shift
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Update time every second
            updateTime();
            setInterval(updateTime, 1000);

            // Form submission
            $('#endShiftForm').submit(function(e) {
                e.preventDefault();

                const shiftNotes = $('#shiftNotes').val();
                const button = $('#endShiftBtn');
                const originalText = button.html();

                // Show loading state
                button.prop('disabled', true)
                      .html('<div class="loading-spinner"></div>Ending shift...');

                // Make AJAX request
                $.ajax({
                    url: 'process/shift-management.php',
                    method: 'POST',
                    data: {
                        action: 'end_shift',
                        shift_notes: shiftNotes
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $('#successMessage').addClass('show');

                            // Hide form
                            $('#endShiftForm').hide();

                            // Redirect to logout after delay
                            setTimeout(function() {
                                window.location.href = 'logout.php?finalize=1';
                            }, 2000);
                        } else {
                            // Show error message
                            alert('Error: ' + response.message);
                            button.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        alert('An error occurred while ending the shift. Please try again.');
                        button.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Warning if there are pending issues
            <?php if ($activeAlerts > 0 || $overdueVisitors > 0): ?>
            setTimeout(() => {
                if (confirm('You have pending security issues. Would you like to review them before ending your shift?')) {
                    window.location.href = 'alerts.php';
                }
            }, 2000);
            <?php endif; ?>
        });

        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-ZA', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('en-ZA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            $('#currentTime').text(timeString);
            $('#currentDate').text(dateString);
        }

        // Add text-danger class styles
        const style = document.createElement('style');
        style.textContent = `
            .text-danger {
                color: var(--danger-color) !important;
                font-weight: 700;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
