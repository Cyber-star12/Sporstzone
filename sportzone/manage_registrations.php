<?php
/**
 * Admin Registration Management
 *
 * Features:
 * - View all registrations with filtering
 * - Approve/Reject registrations
 * - Bulk actions
 * - Search and pagination
 * - Activity logging
 */

require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

// ============================================
// Check if approval system columns exist
// ============================================
$approval_exists = false;
try {
    $pdo->query("SELECT approval_status FROM registrations LIMIT 1");
    $approval_exists = true;
} catch (PDOException $e) {
    $approval_exists = false;
}

// ============================================
// Calculate Statistics
// ============================================
if ($approval_exists) {
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'pending'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'approved'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'rejected'")->fetchColumn(),
        'cancelled' => $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'cancelled'")->fetchColumn(),
        'total' => $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn()
    ];
} else {
    $total = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
    $stats = [
        'pending' => $total,
        'approved' => 0,
        'rejected' => 0,
        'cancelled' => 0,
        'total' => $total
    ];
}

// ============================================
// Build Query with Filters
// ============================================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$event_filter = $_GET['event'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;

// Build WHERE clause
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(u.name LIKE ? OR u.usn LIKE ? OR u.email LIKE ? OR e.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($approval_exists && $status_filter !== 'all') {
    $conditions[] = "r.approval_status = ?";
    $params[] = $status_filter;
}

if ($event_filter !== 'all') {
    $conditions[] = "r.event_id = ?";
    $params[] = (int)$event_filter;
}

$where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM registrations r JOIN users u ON r.user_id = u.id JOIN events e ON r.event_id = e.id $where";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get registrations with pagination
$offset = ($page - 1) * $per_page;

if ($approval_exists) {
    $sql = "
        SELECT
            r.id as registration_id,
            r.approval_status,
            r.rejection_reason,
            r.registered_at,
            r.approved_at,
            u.id as user_id,
            u.name as student_name,
            u.usn as student_usn,
            u.email as student_email,
            e.id as event_id,
            e.name as event_name,
            e.sport_type,
            e.event_date,
            e.venue,
            e.max_slots,
            (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id AND r2.approval_status = 'approved') as approved_count
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        $where
        ORDER BY
            CASE r.approval_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'cancelled' THEN 4
            END,
            r.registered_at DESC
        LIMIT $per_page OFFSET $offset
    ";
} else {
    // Fallback query without approval columns
    $sql = "
        SELECT
            r.id as registration_id,
            'pending' as approval_status,
            '' as rejection_reason,
            r.registered_at,
            r.registered_at as approved_at,
            u.id as user_id,
            u.name as student_name,
            u.usn as student_usn,
            u.email as student_email,
            e.id as event_id,
            e.name as event_name,
            e.sport_type,
            e.event_date,
            e.venue,
            e.max_slots,
            (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id) as approved_count
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        JOIN events e ON r.event_id = e.id
        $where
        ORDER BY r.registered_at DESC
        LIMIT $per_page OFFSET $offset
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get events for filter dropdown
$events = $pdo->query("SELECT id, name, sport_type FROM events ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <?php showFlash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-title mb-1">Manage Registrations</h2>
            <p class="text-muted">Review and manage student event registrations</p>
        </div>
        <div>
            <a href="export_registrations.php" class="btn btn-outline-success me-2">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
            <a href="admin_dashboard.php" class="btn btn-outline-dark">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-warning mb-1"><?= (int)$stats['pending'] ?></h3>
                <p class="small text-muted mb-0">Pending</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-success mb-1"><?= (int)$stats['approved'] ?></h3>
                <p class="small text-muted mb-0">Approved</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-danger mb-1"><?= (int)$stats['rejected'] ?></h3>
                <p class="small text-muted mb-0">Rejected</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-secondary mb-1"><?= (int)$stats['cancelled'] ?></h3>
                <p class="small text-muted mb-0">Cancelled</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="panel-card p-4 mb-4">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search student name, USN, email..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <?php if ($approval_exists): ?>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Event</label>
                <select name="event" class="form-select">
                    <option value="all" <?= $event_filter === 'all' ? 'selected' : '' ?>>All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= (int)$event['id'] ?>" <?= (string)$event_filter === (string)$event['id'] ? 'selected' : '' ?>>
                            <?= e($event['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sport w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="panel-card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted">Showing <?= count($registrations) ?> of <?= (int)$total_count ?> registrations</span>
            <?php if ($approval_exists): ?>
            <div>
                <button class="btn btn-sm btn-success" onclick="bulkAction('approve')" id="bulkApproveBtn" disabled>
                    <i class="bi bi-check-circle me-1"></i> Bulk Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="bulkAction('reject')" id="bulkRejectBtn" disabled>
                    <i class="bi bi-x-circle me-1"></i> Bulk Reject
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Registrations Table -->
    <div class="panel-card p-4">
        <div class="table-responsive">
            <table class="table table-hover" id="registrationsTable">
                <thead>
                    <tr>
                        <?php if ($approval_exists): ?>
                        <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                        <?php endif; ?>
                        <th>Student</th>
                        <th>Event</th>
                        <th>Sport</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($registrations) > 0): ?>
                        <?php foreach ($registrations as $reg): ?>
                            <?php $slots_available = (int)$reg['max_slots'] > (int)$reg['approved_count']; ?>
                            <tr data-registration-id="<?= (int)$reg['registration_id'] ?>">
                                <?php if ($approval_exists): ?>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" value="<?= (int)$reg['registration_id'] ?>"
                                        class="registration-checkbox"
                                        <?= $reg['approval_status'] !== 'pending' ? 'disabled' : '' ?>>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= e($reg['student_name']) ?></strong><br>
                                    <small class="text-muted"><?= e($reg['student_usn']) ?></small><br>
                                    <small class="text-muted"><?= e($reg['student_email']) ?></small>
                                </td>
                                <td><?= e($reg['event_name']) ?></td>
                                <td><?= e($reg['sport_type']) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($reg['event_date'])) ?>
                                    <br><small class="text-muted">Reg: <?= date('M d', strtotime($reg['registered_at'])) ?></small>
                                </td>
                                <td><?= e($reg['venue']) ?></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'pending' => 'bg-warning text-dark',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'cancelled' => 'bg-secondary'
                                    ];
                                    ?>
                                    <span class="badge <?= $status_class[$reg['approval_status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($reg['approval_status']) ?>
                                    </span>
                                    <?php if ($reg['approval_status'] === 'approved'): ?>
                                        <br><small class="text-success"><?= (int)$reg['approved_count'] ?>/<?= (int)$reg['max_slots'] ?> slots</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary"
                                                onclick="viewDetails(<?= (int)$reg['registration_id'] ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($approval_exists && $reg['approval_status'] === 'pending'): ?>
                                            <button class="btn btn-outline-success btn-action-approve"
                                                    data-id="<?= (int)$reg['registration_id'] ?>"
                                                    data-name="<?= e($reg['student_name']) ?>"
                                                    data-event="<?= e($reg['event_name']) ?>"
                                                    data-has-slots="<?= $slots_available ? '1' : '0' ?>"
                                                    title="Approve"
                                                    <?= !$slots_available ? 'disabled' : '' ?>>
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-action-reject"
                                                    data-id="<?= (int)$reg['registration_id'] ?>"
                                                    data-name="<?= e($reg['student_name']) ?>"
                                                    data-event="<?= e($reg['event_name']) ?>"
                                                    title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php elseif ($approval_exists && $reg['approval_status'] === 'approved'): ?>
                                            <button class="btn btn-outline-warning btn-action-cancel"
                                                    data-id="<?= (int)$reg['registration_id'] ?>"
                                                    data-name="<?= e($reg['student_name']) ?>"
                                                    data-event="<?= e($reg['event_name']) ?>"
                                                    title="Cancel Approval">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php elseif ($approval_exists && $reg['approval_status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php elseif ($approval_exists && $reg['approval_status'] === 'cancelled'): ?>
                                            <span class="badge bg-secondary">Cancelled</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $approval_exists ? 8 : 7 ?>" class="text-center py-5">
                                <p class="text-muted mb-0">No registrations found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $query_params = http_build_query(array_filter([
                        'search' => $search,
                        'status' => $status_filter,
                        'event' => $event_filter
                    ]));
                    ?>
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $query_params ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $query_params ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $query_params ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== MAIN DETAILS MODAL ==================== -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-info-circle text-primary me-2"></i>
                    Registration Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="detailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading registration details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== APPROVE CONFIRMATION MODAL ==================== -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-check-circle-fill me-2"></i> Approve Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="display-4 text-success mb-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h5>Confirm Approval</h5>
                <p class="text-muted">Are you sure you want to approve this registration?</p>
                <div class="bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Student</small>
                            <p class="fw-bold mb-0" id="approveStudent"></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Event</small>
                            <p class="fw-bold mb-0" id="approveEvent"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="process_registration.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="registration_id" id="approveId">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ==================== REJECT CONFIRMATION MODAL ==================== -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-x-circle-fill me-2"></i> Reject Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="process_registration.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="registration_id" id="rejectId">
                <div class="modal-body text-center py-4">
                    <div class="display-4 text-danger mb-3">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h5>Confirm Rejection</h5>
                    <p class="text-muted">Are you sure you want to reject this registration?</p>
                    <div class="bg-light p-3 rounded mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Student</small>
                                <p class="fw-bold mb-0" id="rejectStudent"></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Event</small>
                                <p class="fw-bold mb-0" id="rejectEvent"></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-start">
                        <label class="form-label fw-bold">Rejection Reason <span class="text-muted">(Optional)</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== CANCEL APPROVAL MODAL ==================== -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-x-circle-fill me-2"></i> Cancel Approval
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="display-4 text-warning mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5>Cancel Approval?</h5>
                <p class="text-muted">This will free up a slot for another student.</p>
                <div class="bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Student</small>
                            <p class="fw-bold mb-0" id="cancelStudent"></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Event</small>
                            <p class="fw-bold mb-0" id="cancelEvent"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Approval</button>
                <form method="post" action="process_registration.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="registration_id" id="cancelId">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-x-lg me-1"></i> Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Reject Registrations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="process_registration.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_reject">
                <input type="hidden" name="registration_ids" id="bulkRejectIds">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason (Optional)</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject All Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================
// DEBUGGING - Cancel Approval Flow
// ============================================
console.log('========================================');
console.log('SPORTZONE DEBUG - Cancel Approval Fix');
console.log('========================================');

// ============================================
// CSRF Token
// ============================================
const csrfToken = '<?= csrf_token() ?>';
console.log('Page loaded, CSRF token:', csrfToken ? 'Token present (' + csrfToken.length + ' chars)' : 'EMPTY!');
if (!csrfToken) {
    alert('WARNING: CSRF token is empty! This will cause authentication failures.');
}

// ============================================
// Debug: Monitor all clicks on cancel-related elements
// ============================================
document.addEventListener('click', function(e) {
    var target = e.target;
    // Check if clicked element or parent is a cancel button
    var cancelBtn = target.closest('[data-action="cancel"]');
    var cancelConfirmBtn = target.closest('#cancelModal .modal-footer button.btn-warning');

    if (cancelBtn) {
        console.log('DEBUG: Cancel button clicked', {
            id: cancelBtn.getAttribute('data-id'),
            student: cancelBtn.getAttribute('data-student'),
            event: cancelBtn.getAttribute('data-event')
        });
    }

    if (cancelConfirmBtn) {
        console.log('DEBUG: Cancel confirm button clicked');
    }
}, true);

// ============================================
// Toast Notification System
// ============================================
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    const iconMap = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill'
    };

    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${iconMap[type]} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// ============================================
// Toggle select all checkboxes
// ============================================
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.registration-checkbox:not([disabled])');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkButtons();
}

function updateBulkButtons() {
    const checked = document.querySelectorAll('.registration-checkbox:checked');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    if (bulkApproveBtn) bulkApproveBtn.disabled = checked.length === 0;
    if (bulkRejectBtn) bulkRejectBtn.disabled = checked.length === 0;
}

// ============================================
// View registration details
// ============================================
function viewDetails(id) {
    document.getElementById('detailsContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading registration details...</p></div>';
    var detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    detailsModal.show();

    fetch('get_registration_details.php?id=' + id)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.text();
        })
        .then(html => {
            document.getElementById('detailsContent').innerHTML = html;
            setupActionButtons(id);
        })
        .catch(error => {
            console.error('Error loading details:', error);
            document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle me-2"></i>Error loading registration details. Please try again.</div>';
        });
}

function setupActionButtons(id) {
    var approveBtn = document.querySelector('[data-action="approve"]');
    var rejectBtn = document.querySelector('[data-action="reject"]');
    var cancelBtn = document.querySelector('[data-action="cancel"]');

    var studentEl = document.querySelector('#detailsContent .registration-details h5.fw-bold');
    var eventEl = document.querySelector('#detailsContent .registration-details .text-primary');

    var studentName = studentEl ? studentEl.textContent.trim() : 'Student';
    var eventName = eventEl ? eventEl.textContent.trim() : 'Event';

    if (approveBtn) {
        approveBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
            setTimeout(function() {
                document.getElementById('approveStudent').textContent = studentName;
                document.getElementById('approveEvent').textContent = eventName;
                document.getElementById('approveId').value = id;
                new bootstrap.Modal(document.getElementById('approveModal')).show();
            }, 300);
        });
    }

    if (rejectBtn) {
        rejectBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
            setTimeout(function() {
                document.getElementById('rejectStudent').textContent = studentName;
                document.getElementById('rejectEvent').textContent = eventName;
                document.getElementById('rejectId').value = id;
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
            }, 300);
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            console.log('DEBUG: Cancel button in details modal clicked, id:', id);

            // Safely hide details modal if it exists
            const detailsModalEl = document.getElementById('detailsModal');
            if (detailsModalEl) {
                const detailsModal = bootstrap.Modal.getInstance(detailsModalEl);
                if (detailsModal) {
                    detailsModal.hide();
                }
            }

            // Set the registration ID in the hidden input
            document.getElementById('cancelId').value = id;
            document.getElementById('cancelStudent').textContent = studentName;
            document.getElementById('cancelEvent').textContent = eventName;

            console.log('DEBUG: cancelId value set to:', document.getElementById('cancelId').value);

            // Open the cancel modal
            const cancelModalEl = document.getElementById('cancelModal');
            if (cancelModalEl) {
                const cancelModal = new bootstrap.Modal(cancelModalEl);
                cancelModal.show();
                console.log('DEBUG: Cancel modal opened');
            } else {
                console.error('DEBUG: Cancel modal element not found!');
            }
        });
    }
}

// ============================================
// AJAX-based Action Handlers (Instant UI Update)
// ============================================

// Generic AJAX action handler
function performAction(action, registrationId, extraData = {}) {
    console.log('performAction called:', action, registrationId);
    console.log('CSRF Token being sent:', csrfToken);

    const formData = new FormData();
    formData.append('action', action);
    formData.append('registration_id', registrationId);
    formData.append('csrf_token', csrfToken);
    formData.append('ajax', '1'); // Explicit AJAX flag for backend

    for (const [key, value] of Object.entries(extraData)) {
        formData.append(key, value);
    }

    // Create abort controller for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        console.log('Request timeout - aborting');
        controller.abort();
    }, 10000); // 10 second timeout

    return fetch('process_registration.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        signal: controller.signal
    })
    .then(res => {
        clearTimeout(timeoutId);
        console.log('Response status:', res.status, res.statusText);
        if (!res.ok) {
            throw new Error('Server returned ' + res.status + ': ' + res.statusText);
        }
        return res.text();
    })
    .then(text => {
        console.log('Raw response:', text.substring(0, 500));
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Invalid server response');
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        if (error.name === 'AbortError') {
            throw new Error('Request timed out. Please try again.');
        }
        throw error;
    });
}

// Find row by registration ID
function getRowById(id) {
    // First try data attribute (preferred)
    const row = document.querySelector(`tr[data-registration-id="${id}"]`);
    if (row) return row;

    // Fallback: find by checkbox value
    return Array.from(document.querySelectorAll('#registrationsTable tbody tr')).find(row => {
        const checkbox = row.querySelector(`input[value="${id}"]`);
        return checkbox !== null;
    });
}

// Update row status display
function updateRowStatus(row, newStatus, reason = '') {
    if (!row) return;

    const statusCell = row.querySelector('td:nth-child(7)');
    if (!statusCell) return;

    const statusClasses = {
        'pending': 'bg-warning text-dark',
        'approved': 'bg-success',
        'rejected': 'bg-danger',
        'cancelled': 'bg-secondary'
    };

    const statusLabels = {
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected',
        'cancelled': 'Cancelled'
    };

    // Update badge
    let badgeHtml = `<span class="badge ${statusClasses[newStatus]}">${statusLabels[newStatus]}</span>`;

    if (newStatus === 'rejected' && reason) {
        badgeHtml += `<br><small class="text-danger" title="${reason.replace(/"/g, '&quot;')}">Reason provided</small>`;
    }

    statusCell.innerHTML = badgeHtml;

    // Remove action buttons and add status badge
    const actionsCell = row.querySelector('td:nth-child(8)');
    if (actionsCell) {
        if (newStatus === 'rejected') {
            actionsCell.innerHTML = '<span class="badge bg-danger">Rejected</span>';
        } else if (newStatus === 'cancelled') {
            actionsCell.innerHTML = '<span class="badge bg-secondary">Cancelled</span>';
        } else if (newStatus === 'approved') {
            actionsCell.innerHTML = '<span class="badge bg-success">Approved</span>';
        }
    }

    // Disable checkbox if exists
    const checkbox = row.querySelector('.registration-checkbox');
    if (checkbox) checkbox.disabled = true;
}

// Set button loading state
function setButtonLoading(button, isLoading, originalHtml) {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalHtml || originalHtml;
    }
}

// ============================================
// Approve Registration (AJAX)
// ============================================
function handleApproveAction(button) {
    console.log('handleApproveAction called, id:', button.dataset.id);
    const id = parseInt(button.dataset.id);
    const name = button.dataset.name;
    const eventName = button.dataset.event;
    const hasSlots = button.dataset.hasSlots === '1';

    if (!hasSlots) {
        showToast('No slots available for this event!', 'warning');
        return;
    }

    // Show confirmation modal
    document.getElementById('approveStudent').textContent = name;
    document.getElementById('approveEvent').textContent = eventName;
    document.getElementById('approveId').value = id;

    const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
    approveModal.show();

    // Handle form submission
    const confirmBtn = document.querySelector('#approveModal .modal-footer button.btn-success');

    // Prevent default form submission
    const approveForm = document.querySelector('#approveModal form');
    if (approveForm) approveForm.action = 'javascript:void(0)';

    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        setButtonLoading(confirmBtn, true);

        performAction('approve', id)
        .then(data => {
            approveModal.hide();

            if (data.success) {
                const row = getRowById(id);
                updateRowStatus(row, 'approved');
                showToast(data.message || 'Registration approved successfully!', 'success');
            } else {
                showToast(data.message || 'Failed to approve registration', 'danger');
            }
        })
        .catch(error => {
            console.error('Approve error:', error);
            showToast('Error: ' + error.message, 'danger');
        })
        .finally(() => {
            setButtonLoading(confirmBtn, false, '<i class="bi bi-check-lg me-1"></i> Confirm');
        });
    });
}

// ============================================
// Reject Registration (AJAX - Main Fix)
// ============================================
function handleRejectAction(button) {
    console.log('handleRejectAction called, id:', button.dataset.id);
    const id = parseInt(button.dataset.id);
    const name = button.dataset.name;
    const eventName = button.dataset.event;

    // Show reject modal with student/event info
    document.getElementById('rejectStudent').textContent = name;
    document.getElementById('rejectEvent').textContent = eventName;
    document.getElementById('rejectId').value = id;
    document.querySelector('#rejectModal textarea[name="rejection_reason"]').value = '';

    const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModal.show();

    // Handle form submission via AJAX
    const rejectForm = document.querySelector('#rejectModal form');
    const confirmBtn = rejectForm.querySelector('button[type="submit"]');

    // Prevent default form submission
    rejectForm.action = 'javascript:void(0)';

    // Reset any previous handlers and add new one
    confirmBtn.onclick = null;

    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const reason = document.querySelector('#rejectModal textarea[name="rejection_reason"]').value;

        setButtonLoading(confirmBtn, true);

        performAction('reject', id, { rejection_reason: reason })
        .then(data => {
            rejectModal.hide();

            if (data.success) {
                const row = getRowById(id);
                updateRowStatus(row, 'rejected', reason);
                showToast(data.message || 'Registration rejected.', 'success');
            } else {
                showToast(data.message || 'Failed to reject registration', 'danger');
            }
        })
        .catch(error => {
            console.error('Reject error:', error);
            showToast('Error: ' + error.message, 'danger');
        })
        .finally(() => {
            setButtonLoading(confirmBtn, false, '<i class="bi bi-x-lg me-1"></i> Confirm Rejection');
        });
    });
}

// ============================================
// Cancel Approved Registration (AJAX)
// ============================================
function handleCancelAction(button) {
    console.log('handleCancelAction called, id:', button.dataset.id);
    const id = parseInt(button.dataset.id);
    const name = button.dataset.name;
    const eventName = button.dataset.event;

    document.getElementById('cancelStudent').textContent = name;
    document.getElementById('cancelEvent').textContent = eventName;
    document.getElementById('cancelId').value = id;

    const cancelModalEl = document.getElementById('cancelModal');
    const cancelModal = new bootstrap.Modal(cancelModalEl);
    cancelModal.show();

    // Manually trigger the show event so our event listener fires
    cancelModalEl.dispatchEvent(new Event('show.bs.modal', { bubbles: true }));
}

// ============================================
// Bulk Action Handlers
// ============================================
function bulkAction(action) {
    const checked = document.querySelectorAll('.registration-checkbox:checked');
    const ids = Array.from(checked).map(cb => parseInt(cb.value));

    if (ids.length === 0) {
        showToast('No registrations selected', 'warning');
        return;
    }

    if (action === 'approve') {
        if (confirm('Approve ' + ids.length + ' selected registrations?')) {
            performBulkApprove(ids);
        }
    } else if (action === 'reject') {
        document.getElementById('bulkRejectIds').value = ids.join(',');
        new bootstrap.Modal(document.getElementById('bulkRejectModal')).show();
    }
}

function performBulkApprove(ids) {
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    setButtonLoading(bulkApproveBtn, true);

    // Process each registration
    const promises = ids.map(id => performAction('approve', id));
    Promise.allSettled(promises)
    .then(results => {
        let approved = 0, failed = 0;

        results.forEach((result, index) => {
            if (result.status === 'fulfilled' && result.value.success) {
                approved++;
                const row = getRowById(ids[index]);
                updateRowStatus(row, 'approved');
            } else {
                failed++;
            }
        });

        if (failed > 0) {
            showToast(`Approved ${approved}, ${failed} failed`, 'warning');
        } else {
            showToast(`Approved ${approved} registrations successfully!`, 'success');
        }

        // Uncheck all
        document.getElementById('selectAll').checked = false;
        updateBulkButtons();
    })
    .catch(error => {
        console.error('Bulk approve error:', error);
        showToast('Error: ' + error.message, 'danger');
    })
    .finally(() => {
        setButtonLoading(bulkApproveBtn, false, '<i class="bi bi-check-circle me-1"></i> Bulk Approve');
    });
}

// ============================================
// Event Listeners Setup
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Checkbox listeners
    document.querySelectorAll('.registration-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkButtons);
    });

    // Direct onclick binding - most reliable approach
    document.querySelectorAll('.btn-action-approve').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleApproveAction(this);
        });
    });

    document.querySelectorAll('.btn-action-reject').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleRejectAction(this);
        });
    });

    document.querySelectorAll('.btn-action-cancel').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleCancelAction(this);
        });
    });

    // ============================================
    // Cancel Modal - Setup confirm button handler when modal is shown
    // FIXED: Get ID from hidden input field instead of event.relatedTarget
    // ============================================
    const cancelModalEl = document.getElementById('cancelModal');
    if (cancelModalEl) {
        cancelModalEl.addEventListener('show.bs.modal', function(event) {
            console.log('Cancel modal showing');

            // Get registration ID from the hidden input field - this is more reliable
            const cancelIdInput = document.getElementById('cancelId');
            const registrationId = cancelIdInput ? cancelIdInput.value : null;

            console.log('Registration ID from hidden field:', registrationId);

            if (registrationId && parseInt(registrationId) > 0) {
                console.log('Setting up cancel for registration:', registrationId);

                // Get the confirm button and set up the handler
                const confirmBtn = document.querySelector('#cancelModal .modal-footer button.btn-warning');
                const cancelForm = document.querySelector('#cancelModal form');
                if (cancelForm) {
                    cancelForm.action = 'javascript:void(0)';
                    cancelForm.addEventListener('submit', function(e) {
                        e.preventDefault(); // Prevent any form submission
                    });
                }

                if (confirmBtn) {
                    // Remove all existing event listeners by cloning
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

                    // Add fresh click handler
                    newConfirmBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        console.log('Confirm button clicked');

                        // Show loading state
                        newConfirmBtn.disabled = true;
                        newConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

                        const id = parseInt(registrationId);
                        console.log('Attempting to cancel registration ID:', id);

                        // Make the AJAX request - create FormData correctly
                        var formData = new FormData();
                        formData.append('action', 'cancel');
                        formData.append('registration_id', id);
                        formData.append('csrf_token', csrfToken);
                        formData.append('ajax', '1');

                        fetch('process_registration.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(function(res) {
                            console.log('Response status:', res.status);
                            if (!res.ok) {
                                throw new Error('Server returned ' + res.status);
                            }
                            return res.text();
                        })
                        .then(function(text) {
                            console.log('Raw response:', text.substring(0, 200));
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                throw new Error('Invalid JSON response');
                            }
                        })
                        .then(function(data) {
                            console.log('Parsed response:', data);

                            // Hide the modal
                            const modal = bootstrap.Modal.getInstance(cancelModalEl);
                            if (modal) modal.hide();

                            if (data.success) {
                                // Show success message
                                showToast(data.message || 'Registration cancelled successfully!', 'success');

                                // Update the table row
                                const row = getRowById(id);
                                if (row) {
                                    updateRowStatus(row, 'cancelled');
                                }

                                // Refresh details modal if still open
                                const detailsModalEl = document.getElementById('detailsModal');
                                if (detailsModalEl && detailsModalEl.classList.contains('show')) {
                                    setTimeout(function() {
                                        viewDetails(id);
                                    }, 500);
                                }
                            } else {
                                showToast(data.message || 'Failed to cancel registration', 'danger');
                            }
                        })
                        .catch(function(error) {
                            console.error('Cancel error:', error);
                            showToast('Error: ' + error.message, 'danger');
                        })
                        .finally(function() {
                            // Reset button state
                            newConfirmBtn.disabled = false;
                            newConfirmBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i> Confirm';
                        });
                    });
                }
            } else {
                console.error('Could not get registration ID!');
                showToast('Error: Could not get registration ID', 'danger');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>