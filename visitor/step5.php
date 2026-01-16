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
} catch (PDOException $e) {
    die("Query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sophen - Welcome Back</title>
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
            background: var(--success-color);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
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

        .search-section {
            margin: 30px 0;
        }

        .search-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .search-group {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 50px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--success-color);
            box-shadow: 0 0 0 0.2rem rgba(39, 174, 96, 0.25);
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
            opacity: 0.7;
        }

        .search-button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--success-color);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background: #229954;
            transform: translateY(-50%) scale(1.05);
        }

        .search-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: translateY(-50%);
        }

        .visitor-details {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            display: none;
        }

        .visitor-details.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        .detail-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .detail-title i {
            margin-right: 8px;
            color: var(--success-color);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .detail-value {
            color: var(--primary-color);
        }

        .edit-details-section {
            margin: 20px 0;
            padding: 20px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            display: none;
        }

        .edit-details-section.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--success-color);
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
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
            background: var(--success-color);
            color: white;
            flex: 2;
            opacity: 0.5;
            pointer-events: none;
        }

        .continue-button.active {
            opacity: 1;
            pointer-events: all;
        }

        .continue-button.active:hover {
            background: #229954;
            transform: translateX(3px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .no-visitor-found {
            text-align: center;
            padding: 30px;
            color: var(--dark-gray);
            display: none;
        }

        .no-visitor-found.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        .no-visitor-found i {
            font-size: 3rem;
            color: var(--warning-color);
            margin-bottom: 15px;
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
                gap: 0;
            }

            .navigation-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .nav-button {
                width: 100%;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
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

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="visitor-card animate-entrance">
            <div class="header-section">
                <div class="system-logo">
                    <i class="fas fa-user-check"></i>
                </div>
                <h1 class="page-title">Welcome Back!</h1>
                <p class="page-subtitle">Let's find your information</p>
            </div>

            <div class="search-section">
                <label for="searchInput" class="search-label">Search for your previous visit:</label>
                <div class="search-group">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" 
                           placeholder="Enter your name or ID number">
                    <button class="search-button" id="searchButton">
                        <span class="button-text">Search</span>
                        <div class="loading-spinner" style="display: none;"></div>
                    </button>
                </div>
            </div>

            <!-- Visitor Details Display -->
            <div class="visitor-details" id="visitorDetails">
                <div class="detail-title">
                    <i class="fas fa-user"></i>
                    Visitor Information
                </div>
                <div class="detail-row">
                    <span class="detail-label">Full Name:</span>
                    <span class="detail-value" id="displayName">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ID Number:</span>
                    <span class="detail-value" id="displayId">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value" id="displayEmail">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Last Visit:</span>
                    <span class="detail-value" id="displayLastVisit">-</span>
                </div>
            </div>

            <!-- Edit Details Section -->
            <div class="edit-details-section" id="editDetailsSection">
                <form id="updateVisitorForm" method="POST" action="step7.php">
                    <input type="hidden" id="visitorId" name="visitor_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="roomNumber" class="form-label">Room Number</label>
                            <select class="form-control" id="roomNumber" name="room_number" required>
                                <option value="">Select Room Number</option>
                                <?php foreach($rooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['room_number']); ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="hostName" class="form-label">Who are you visiting?</label>
                            <select class="form-control" id="hostName" name="host_name" required disabled>
                                <option value="">Select Room First</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="+27 123 456 789">
                    </div>
                </form>
            </div>

            <!-- No Visitor Found -->
            <div class="no-visitor-found" id="noVisitorFound">
                <i class="fas fa-search"></i>
                <h5>No previous visits found</h5>
                <p>We couldn't find any previous visits with that information. 
                   You might want to <a href="step4.php">register as a new visitor</a>.</p>
            </div>

            <div class="navigation-buttons">
                <a href="step3.php" class="nav-button back-button">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back
                </a>
                <button type="button" class="nav-button continue-button" id="continueButton">
                    Continue
                    <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            let visitorFound = false;



            // Search functionality
            $('#searchButton').click(function() {
                performSearch();
            });

            $('#searchInput').keypress(function(e) {
                if (e.which === 13) {
                    performSearch();
                }
            });

            function performSearch() {
                const searchTerm = $('#searchInput').val().trim();

                if (!searchTerm) {
                    alert('Please enter a name or ID number to search');
                    return;
                }

                // Show loading state
                const button = $('#searchButton');
                const buttonText = button.find('.button-text');
                const spinner = button.find('.loading-spinner');

                button.prop('disabled', true);
                buttonText.hide();
                spinner.show();

                // Hide previous results
                $('#visitorDetails, #editDetailsSection, #noVisitorFound').removeClass('show');

                // Make AJAX call to check_visitor.php
                $.ajax({
                    url: 'process/check_visitor.php',
                    type: 'POST',
                    data: { id_number: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.exists && !response.blocked) {
                            displayVisitorDetails(response.visitor);
                            visitorFound = true;
                            $('#continueButton').addClass('active');
                        } else if (response.blocked) {
                            alert('This visitor is currently blocked from visiting: ' + response.message);
                            $('#noVisitorFound').addClass('show');
                            visitorFound = false;
                            $('#continueButton').removeClass('active');
                        } else {
                            $('#noVisitorFound').addClass('show');
                            visitorFound = false;
                            $('#continueButton').removeClass('active');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Search error:', error);
                        alert('An error occurred while searching. Please try again.');
                        $('#noVisitorFound').addClass('show');
                        visitorFound = false;
                        $('#continueButton').removeClass('active');
                    },
                    complete: function() {
                        // Reset button state
                        button.prop('disabled', false);
                        buttonText.show();
                        spinner.hide();
                    }
                });
            }



            function displayVisitorDetails(visitor) {
                $('#displayName').text(visitor.full_name);
                $('#displayId').text(visitor.id_number);
                $('#displayEmail').text(visitor.email);
                $('#displayLastVisit').text(formatDateTime(visitor.last_visit_date));

                // Populate form fields
                $('#visitorId').val(visitor.visitor_id);
                $('#phone').val(visitor.phone || '');

                $('#visitorDetails, #editDetailsSection').addClass('show');
            }

            function formatDateTime(dateString) {
                const date = new Date(dateString);
                return date.toLocaleString('en-ZA', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            // Continue button click
            $('#continueButton').click(function() {
                if (!visitorFound) return;

                if (!$('#roomNumber').val() || !$('#hostName').val()) {
                    alert('Please fill in the room number and who you are visiting');
                    return;
                }

                $('#updateVisitorForm').submit();
            });

            // Form validation
            $('#roomNumber, #hostName').on('input', function() {
                const roomNumber = $('#roomNumber').val().trim();
                const hostName = $('#hostName').val().trim();
                
                if (roomNumber && hostName && visitorFound) {
                    $('#continueButton').addClass('active');
                } else {
                    $('#continueButton').removeClass('active');
                }
            });

            // Filter residents based on selected room
            $('#roomNumber').on('change', function() {
                const roomNumber = $(this).val();
                const hostSelect = $('#hostName');

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
            });

            // Phone number formatting
            $('#phone').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.startsWith('27')) {
                        value = '+27 ' + value.substring(2);
                    } else if (!value.startsWith('+')) {
                        value = '+27 ' + value;
                    }
                    value = value.replace(/(\+27)(\d{3})(\d{3})(\d{3})/, '$1 $2 $3 $4');
                }
                $(this).val(value);
            });
        });
    </script>
</body>
</html>