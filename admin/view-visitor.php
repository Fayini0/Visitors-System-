<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_login();
require_admin();

$visitor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$visitor_id) {
    header('Location: manage-visitors.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch visitor details
$query = "SELECT * FROM visitors WHERE visitor_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$visitor_id]);
$visitor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visitor) {
    header('Location: manage-visitors.php');
    exit();
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $phone = sanitize_input($_POST['phone']);
    $email = sanitize_input($_POST['email']);

    $update_query = "UPDATE visitors SET first_name = ?, last_name = ?, phone = ?, email = ? WHERE visitor_id = ?";
    $update_stmt = $db->prepare($update_query);
    if ($update_stmt->execute([$first_name, $last_name, $phone, $email, $visitor_id])) {
        $message = 'Visitor information updated successfully.';
        // Refresh visitor data
        $stmt->execute([$visitor_id]);
        $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $message = 'Error updating visitor information.';
    }
}

// Fetch recent visits
$visits_query = "SELECT v.*, r.room_number FROM visits v LEFT JOIN rooms r ON v.room_id = r.room_id WHERE v.visitor_id = ? ORDER BY v.created_at DESC LIMIT 10";
$visits_stmt = $db->prepare($visits_query);
$visits_stmt->execute([$visitor_id]);
$visits = $visits_stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>View Visitor</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="manage-visitors.php">Manage Visitors</a></li>
                    <li class="breadcrumb-item active">View Visitor</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Visitor Details</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($visitor['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($visitor['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="id_number">ID Number</label>
                                <input type="text" class="form-control" id="id_number" value="<?php echo htmlspecialchars($visitor['id_number']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($visitor['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($visitor['email'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Update Information</button>
                            <a href="manage-visitors.php" class="btn btn-secondary">Back to List</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Visitor Summary</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Total Visits:</strong> <span class="badge badge-primary"><?php echo $visitor['visit_count']; ?></span></p>
                        <p><strong>Last Visit:</strong> <?php echo $visitor['last_visit_date'] ? format_date($visitor['last_visit_date'], 'M j, Y g:i A') : 'Never'; ?></p>
                        <p><strong>Status:</strong> <span class="badge <?php echo $visitor['is_blocked'] ? 'badge-danger' : 'badge-success'; ?>"><?php echo $visitor['is_blocked'] ? 'Blocked' : 'Active'; ?></span></p>
                        <p><strong>Created:</strong> <?php echo format_date($visitor['created_at'], 'M j, Y'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Visits</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Host</th>
                                    <th>Room</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($visits)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No visits recorded.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($visits as $visit): ?>
                                        <tr>
                                            <td><?php echo format_date($visit['created_at'], 'M j, Y'); ?></td>
                                            <td><?php echo htmlspecialchars($visit['host_name']); ?></td>
                                            <td><?php echo htmlspecialchars($visit['room_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($visit['purpose'])); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $visit['visit_status']))); ?></span></td>
                                            <td><?php echo $visit['actual_checkin'] ? format_date($visit['actual_checkin'], 'g:i A') : 'N/A'; ?></td>
                                            <td><?php echo $visit['actual_checkout'] ? format_date($visit['actual_checkout'], 'g:i A') : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
