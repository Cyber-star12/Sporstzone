<?php
/**
 * Registration Details - AJAX Modal Handler
 * Returns professional HTML for registration details modal
 */

require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

header('Content-Type: text/html; charset=UTF-8');

$registration_id = (int)($_GET['id'] ?? 0);

// ============================================
// Validation
// ============================================
if ($registration_id <= 0) {
    echo '<div class="alert alert-danger m-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error:</strong> Invalid registration ID.
    </div>';
    exit;
}

// ============================================
// Check if approval columns exist
// ============================================
$approval_exists = false;
$approval_status = 'pending';

try {
    $pdo->query("SELECT approval_status FROM registrations LIMIT 1");
    $approval_exists = true;
} catch (PDOException $e) {
    $approval_exists = false;
}

// ============================================
// Get Registration Details
// ============================================
if ($approval_exists) {
    $sql = "SELECT
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
        e.event_time,
        e.venue,
        e.max_slots,
        (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id AND r2.approval_status = 'approved') as approved_count,
        a.name as approved_by_name
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN events e ON r.event_id = e.id
    LEFT JOIN users a ON r.approved_by = a.id
    WHERE r.id = ?";
} else {
    // Fallback - treat all as pending
    $sql = "SELECT
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
        e.event_time,
        e.venue,
        e.max_slots,
        (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id) as approved_count,
        '' as approved_by_name
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN events e ON r.event_id = e.id
    WHERE r.id = ?";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$registration_id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reg) {
    echo '<div class="alert alert-warning m-3">
        <i class="bi bi-exclamation-circle-fill me-2"></i>
        <strong>Not Found:</strong> Registration not found. It may have been deleted.
    </div>';
    exit;
}

// ============================================
// Prepare Display Data
// ============================================
$status_class = [
    'pending' => 'bg-warning text-dark',
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

$status_labels = [
    'pending' => 'Pending Approval',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled'
];

$slots_available = (int)$reg['max_slots'] - (int)$reg['approved_count'];
$can_approve = $approval_exists && $reg['approval_status'] === 'pending' && $slots_available > 0;
?>

<!-- ==================== REGISTRATION DETAILS ==================== -->
<div class="registration-details">

    <!-- Header with Status Badge -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-person-badge me-2 text-primary"></i>
                <?= htmlspecialchars($reg['student_name']) ?>
            </h5>
            <small class="text-muted"><?= htmlspecialchars($reg['student_usn'] ?? 'N/A') ?></small>
        </div>
        <span class="badge <?= $status_class[$reg['approval_status']] ?? 'bg-secondary' ?> fs-6 px-3 py-2">
            <?= $status_labels[$reg['approval_status']] ?? ucfirst($reg['approval_status']) ?>
        </span>
    </div>

    <!-- Details Body -->
    <div class="p-3">

        <!-- Student Info Section -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">
                <i class="bi bi-person-lines-fill me-1"></i> Student Information
            </h6>
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">USN / Student ID</small>
                        <strong><?= htmlspecialchars($reg['student_usn'] ?? 'N/A') ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Email</small>
                        <strong><?= htmlspecialchars($reg['student_email'] ?? 'N/A') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Info Section -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">
                <i class="bi bi-calendar-event me-1"></i> Event Details
            </h6>
            <div class="bg-white rounded border p-3">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Event Name</small>
                        <strong class="text-primary"><?= htmlspecialchars($reg['event_name']) ?></strong>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Sport Type</small>
                        <strong><span class="badge bg-info"><?= htmlspecialchars($reg['sport_type']) ?></span></strong>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Venue</small>
                        <strong><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($reg['venue']) ?></strong>
                    </div>
                    <div class="col-md-6 mb-2">
                        <small class="text-muted d-block">Date & Time</small>
                        <strong><?= date('M d, Y', strtotime($reg['event_date'])) ?> at <?= date('h:i A', strtotime($reg['event_time'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Info Section -->
        <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">
                <i class="bi bi-clipboard-check me-1"></i> Registration Info
            </h6>
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Registered On</small>
                        <strong><?= date('M d, Y h:i A', strtotime($reg['registered_at'])) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 bg-white rounded border">
                        <small class="text-muted d-block">Slots Status</small>
                        <?php if ($slots_available > 0): ?>
                            <strong class="text-success"><?= $slots_available ?> available</strong>
                        <?php else: ?>
                            <strong class="text-danger">Event Full</strong>
                        <?php endif; ?>
                        <small class="text-muted d-block">(<?= (int)$reg['approved_count'] ?>/<?= (int)$reg['max_slots'] ?>)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Info (if approved/rejected) -->
        <?php if ($reg['approval_status'] === 'approved' && !empty($reg['approved_by_name'])): ?>
        <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">
                <i class="bi bi-check2-circle me-1"></i> Approval Details
            </h6>
            <div class="row g-2">
                <div class="col-6">
                    <div class="p-2 bg-success bg-opacity-10 rounded border border-success">
                        <small class="text-success d-block">Approved By</small>
                        <strong class="text-success"><?= htmlspecialchars($reg['approved_by_name']) ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 bg-success bg-opacity-10 rounded border border-success">
                        <small class="text-success d-block">Approved At</small>
                        <strong class="text-success"><?= date('M d, Y h:i A', strtotime($reg['approved_at'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rejection Reason (if rejected) -->
        <?php if ($reg['approval_status'] === 'rejected' && !empty($reg['rejection_reason'])): ?>
        <div class="mb-4">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">
                <i class="bi bi-x-circle me-1"></i> Rejection Details
            </h6>
            <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger">
                <small class="text-danger d-block">Rejection Reason</small>
                <strong class="text-danger"><?= htmlspecialchars($reg['rejection_reason']) ?></strong>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ==================== ACTION BUTTONS ==================== -->
    <div class="p-3 bg-light border-top">

        <?php if ($approval_exists && $reg['approval_status'] === 'pending'): ?>

            <!-- Pending Status - Show Approve/Reject Buttons -->
            <div class="action-buttons">

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted">
                        <i class="bi bi-gear me-1"></i>
                        <strong>Admin Actions</strong>
                    </span>
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-clock me-1"></i> Pending Review
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center">

                    <!-- Approve Button -->
                    <?php if ($slots_available > 0): ?>
                        <button type="button" class="btn btn-success btn-lg px-4"
                                data-action="approve"
                                data-id="<?= (int)$reg['registration_id'] ?>"
                                data-student="<?= htmlspecialchars($reg['student_name']) ?>"
                                data-event="<?= htmlspecialchars($reg['event_name']) ?>">
                            <i class="bi bi-check-circle-fill me-2"></i> Approve
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg px-4" disabled>
                            <i class="bi bi-x-circle me-2"></i> No Slots Available
                        </button>
                    <?php endif; ?>

                    <!-- Reject Button -->
                    <button type="button" class="btn btn-danger btn-lg px-4"
                            data-action="reject"
                            data-id="<?= (int)$reg['registration_id'] ?>"
                            data-student="<?= htmlspecialchars($reg['student_name']) ?>"
                            data-event="<?= htmlspecialchars($reg['event_name']) ?>">
                        <i class="bi bi-x-circle-fill me-2"></i> Reject
                    </button>

                </div>

            </div>

        <?php elseif ($reg['approval_status'] === 'approved'): ?>

            <!-- Already Approved - Show Cancel Button -->
            <div class="text-center">
                <span class="badge bg-success fs-6 mb-2">
                    <i class="bi bi-check-circle me-1"></i> Registration Approved
                </span>
                <br>
                <button type="button" class="btn btn-outline-warning"
                        data-action="cancel"
                        data-id="<?= (int)$reg['registration_id'] ?>"
                        data-student="<?= htmlspecialchars($reg['student_name']) ?>"
                        data-event="<?= htmlspecialchars($reg['event_name']) ?>">
                    <i class="bi bi-x-circle-fill me-2"></i> Cancel Approval
                </button>
            </div>

        <?php elseif ($reg['approval_status'] === 'rejected'): ?>

            <!-- Already Rejected -->
            <div class="text-center">
                <span class="badge bg-danger fs-6">
                    <i class="bi bi-x-circle me-1"></i> Registration Rejected
                </span>
            </div>

        <?php elseif ($reg['approval_status'] === 'cancelled'): ?>

            <!-- Cancelled -->
            <div class="text-center">
                <span class="badge bg-secondary fs-6">
                    <i class="bi bi-dash-circle me-1"></i> Registration Cancelled
                </span>
            </div>

        <?php else: ?>

            <!-- Default Pending Status (when approval system is enabled but no status set) -->
            <div class="text-center">
                <span class="badge bg-warning fs-6">
                    <i class="bi bi-clock me-1"></i> Pending Review
                </span>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ==================== APPROVE CONFIRMATION MODAL ==================== -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle-fill me-2"></i> Approve Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="display-1 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <p class="text-center">Are you sure you want to <strong>approve</strong> this registration?</p>
                <div class="bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Student</small>
                            <p class="mb-0 fw-bold" id="approveStudentName"></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Event</small>
                            <p class="mb-0 fw-bold" id="approveEventName"></p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mb-0 mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    A slot will be reserved for this student.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Cancel
                </button>
                <form method="post" action="process_registration.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="registration_id" id="approveRegistrationId">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Confirm Approval
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
                <h5 class="modal-title">
                    <i class="bi bi-x-circle-fill me-2"></i> Reject Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="process_registration.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="registration_id" id="rejectRegistrationId">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="display-1 text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                    <p class="text-center">Are you sure you want to <strong>reject</strong> this registration?</p>
                    <div class="bg-light p-3 rounded mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Student</small>
                                <p class="mb-0 fw-bold" id="rejectStudentName"></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Event</small>
                                <p class="mb-0 fw-bold" id="rejectEventName"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rejection Reason <span class="text-muted">(Optional)</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3"
                                  placeholder="Enter reason for rejection (optional)..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </button>
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
                <h5 class="modal-title">
                    <i class="bi bi-x-circle-fill me-2"></i> Cancel Approval
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="display-1 text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
                <p class="text-center">Are you sure you want to <strong>cancel</strong> this approval?</p>
                <div class="bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Student</small>
                            <p class="mb-0 fw-bold" id="cancelStudentName"></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Event</small>
                            <p class="mb-0 fw-bold" id="cancelEventName"></p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mb-0 mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    The slot will be freed and available for other students.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i> Keep Approval
                </button>
                <form method="post" action="process_registration.php" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="registration_id" id="cancelRegistrationId">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-x-lg me-1"></i> Confirm Cancellation
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ==================== JAVASCRIPT FOR MODALS ==================== -->
<script>
// Approve Modal
document.getElementById('approveModal').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var student = button.getAttribute('data-student');
    var event_name = button.getAttribute('data-event');

    document.getElementById('approveRegistrationId').value = id;
    document.getElementById('approveStudentName').textContent = student;
    document.getElementById('approveEventName').textContent = event_name;
});

// Reject Modal
document.getElementById('rejectModal').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var student = button.getAttribute('data-student');
    var event_name = button.getAttribute('data-event');

    document.getElementById('rejectRegistrationId').value = id;
    document.getElementById('rejectStudentName').textContent = student;
    document.getElementById('rejectEventName').textContent = event_name;
});

// Cancel Modal
document.getElementById('cancelModal').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var student = button.getAttribute('data-student');
    var event_name = button.getAttribute('data-event');

    document.getElementById('cancelRegistrationId').value = id;
    document.getElementById('cancelStudentName').textContent = student;
    document.getElementById('cancelEventName').textContent = event_name;
});
</script>