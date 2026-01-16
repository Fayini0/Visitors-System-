<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_login();
require_admin();
require_once '../includes/header.php';

// Handle search
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$searchClause = $search ? "WHERE full_name LIKE '%$search%' OR id_number LIKE '%$search%'" : '';

// Get visitors from view
$database = new Database();
$db = $database->getConnection();
$query = "SELECT * FROM visitor_summary $searchClause ORDER BY last_visit_date DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load active block reasons for modal
try {
    $stmtReasons = $db->prepare("SELECT reason_id, reason_description, default_block_days, severity_level FROM block_reasons WHERE is_active = 1 ORDER BY severity_level DESC, reason_description");
    $stmtReasons->execute();
    $blockReasons = $stmtReasons->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blockReasons = [];
}
?>

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Manage Visitors</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-check"></i> Success!</h5>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">All Visitors</h3>
                            <div class="card-tools">
                                <form method="GET" class="form-inline ml-3">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="search" class="form-control" placeholder="Search visitors" value="<?php echo htmlspecialchars($search); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-default">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>ID Number</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Total Visits</th>
                                        <th>Last Visit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($visitors)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                                <p class="text-muted mb-0">No visitors found<?php echo $search ? ' matching your search' : ''; ?>.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($visitors as $visitor): ?>
                                            <tr>
                                                <td><?php echo $visitor['visitor_id']; ?></td>
                                                <td><?php echo htmlspecialchars($visitor['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($visitor['id_number']); ?></td>
                                                <td><?php echo htmlspecialchars($visitor['phone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($visitor['email'] ?? 'N/A'); ?></td>
                                                <td><span class="badge badge-primary"><?php echo $visitor['visit_count']; ?></span></td>
                                                <td>
                                                    <?php if ($visitor['last_visit_date']): ?>
                                                        <small class="text-muted"><?php echo format_date($visitor['last_visit_date'], 'M j, Y g:i A'); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo ($visitor['is_blocked'] || $visitor['currently_blocked']) ? 'badge-danger' : 'badge-success'; ?>">
                                                        <?php echo ($visitor['is_blocked'] || $visitor['currently_blocked']) ? 'Blocked' : 'Active'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view-visitor.php?id=<?php echo $visitor['visitor_id']; ?>" class="btn btn-info" data-bs-toggle="tooltip" title="View Visitor">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if (!$visitor['is_blocked'] && !$visitor['currently_blocked']): ?>
                                                            <button type="button" class="btn btn-warning" data-bs-toggle="tooltip" title="Block Visitor"
                                                                onclick="openBlockModal(<?php echo (int)$visitor['visitor_id']; ?>, '<?php echo addslashes($visitor['full_name']); ?>', '<?php echo addslashes($visitor['id_number']); ?>')">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="process/visitor-actions.php?action=unblock&visitor_id=<?php echo $visitor['visitor_id']; ?>" class="btn btn-success" data-bs-toggle="tooltip" title="Unblock Visitor" onclick="return confirm('Unblock this visitor?')">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="process/visitor-actions.php?action=delete&visitor_id=<?php echo $visitor['visitor_id']; ?>" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete Visitor" onclick="return confirm('Delete this visitor? This cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
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

    <!-- Block Visitor Modal (contextual) -->
    <div class="modal fade" id="blockVisitorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--light-gray); border-bottom: 1px solid #dee2e6;">
                    <h5 class="modal-title"><i class="fas fa-user-slash me-2"></i>Block Visitor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="blockVisitorForm" onsubmit="return false;">
                        <input type="hidden" id="blockVisitorId">
                        <div class="mb-2">
                            <div class="d-flex align-items-center" style="gap:8px;">
                                <i class="fas fa-user text-muted"></i>
                                <div>
                                    <div id="blockVisitorName" class="fw-semibold"></div>
                                    <small class="text-muted">ID: <span id="blockVisitorIdNumber"></span></small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-7">
                                <label for="blockReasonSelect" class="form-label">Reason</label>
                                <select id="blockReasonSelect" class="form-select">
                                    <option value="" selected disabled>Select reason</option>
                                    <?php if (!empty($blockReasons)): ?>
                                        <?php foreach ($blockReasons as $reason): ?>
                                            <option value="<?= (int)$reason['reason_id'] ?>" data-default-days="<?= (int)$reason['default_block_days'] ?>">
                                                <?= htmlspecialchars($reason['reason_description']) ?> (Severity <?= (int)$reason['severity_level'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="1">Default</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="blockDaysInput" class="form-label">Block days</label>
                                <input type="number" id="blockDaysInput" class="form-control" min="1" value="7">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBlockBtn" onclick="confirmBlockVisitor()">
                        <i class="fas fa-ban me-1"></i>Block Visitor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Tooltips initialization after all resources load
    window.addEventListener('load', function() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(function(el){ new bootstrap.Tooltip(el); });
    });

    function openBlockModal(visitorId, fullName, idNumber) {
        document.getElementById('blockVisitorId').value = visitorId;
        document.getElementById('blockVisitorName').textContent = fullName || 'Unknown';
        document.getElementById('blockVisitorIdNumber').textContent = idNumber || '';
        document.getElementById('blockReasonSelect').value = '';
        document.getElementById('blockDaysInput').value = 7;
        const modalEl = document.getElementById('blockVisitorModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Auto-fill block days when reason changes
    document.addEventListener('DOMContentLoaded', function() {
        const reasonSelect = document.getElementById('blockReasonSelect');
        if (reasonSelect) {
            reasonSelect.addEventListener('change', function() {
                const defDays = this.options[this.selectedIndex]?.getAttribute('data-default-days');
                if (defDays && Number(defDays) > 0) {
                    document.getElementById('blockDaysInput').value = defDays;
                }
            });
        }
    });

    function confirmBlockVisitor() {
        const visitorId = parseInt(document.getElementById('blockVisitorId').value, 10);
        const reasonId = document.getElementById('blockReasonSelect').value;
        const blockDays = parseInt(document.getElementById('blockDaysInput').value, 10);

        if (!visitorId) { alert('Invalid visitor'); return; }
        if (!reasonId) { alert('Please select a block reason'); return; }
        if (!blockDays || blockDays < 1) { alert('Please enter a valid number of days'); return; }

        const btn = document.getElementById('confirmBlockBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Blocking...';

        $.ajax({
            url: 'process/visitor-actions.php',
            method: 'POST',
            data: {
                action: 'block_visitor',
                visitor_id: visitorId,
                reason_id: reasonId,
                block_days: blockDays
            },
            dataType: 'json'
        }).done(function(resp) {
            if (resp && resp.success) {
                // Show a quick success and reload
                alert('Visitor blocked successfully');
                setTimeout(function(){ window.location.reload(); }, 500);
            } else {
                alert(resp.message || 'Block request failed');
            }
        }).fail(function(){
            alert('Block request failed');
        }).always(function(){
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-ban me-1"></i>Block Visitor';
        });
    }
    </script>

<?php require_once '../includes/footer.php'; ?>
