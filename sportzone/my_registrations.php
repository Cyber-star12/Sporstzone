<?php
/**
 * My Registrations - Student View
 * Shows student's event registrations with approval status
 */

require 'config/db.php';
require 'includes/auth.php';
requireLogin();

// Check if approval_status column exists
$approval_exists = false;
try {
    $pdo->query("SELECT approval_status FROM registrations LIMIT 1");
    $approval_exists = true;
} catch (PDOException $e) {
    $approval_exists = false;
}

// Get user's registrations with event details
if ($approval_exists) {
    $stmt = $pdo->prepare("
        SELECT
            r.id as registration_id,
            r.approval_status,
            r.rejection_reason,
            r.registered_at,
            r.approved_at,
            e.id as event_id,
            e.name as event_name,
            e.sport_type,
            e.event_date,
            e.event_time,
            e.venue,
            e.max_slots,
            (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id AND r2.approval_status = 'approved') as approved_count
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = ?
        ORDER BY
            CASE r.approval_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'cancelled' THEN 4
            END,
            r.registered_at DESC
    ");
} else {
    // Fallback for when columns don't exist
    $stmt = $pdo->prepare("
        SELECT
            r.id as registration_id,
            'pending' as approval_status,
            '' as rejection_reason,
            r.registered_at,
            r.registered_at as approved_at,
            e.id as event_id,
            e.name as event_name,
            e.sport_type,
            e.event_date,
            e.event_time,
            e.venue,
            e.max_slots,
            (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id) as approved_count
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = ?
        ORDER BY r.registered_at DESC
    ");
}
$stmt->execute([$_SESSION['user_id']]);
$rows = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <h2 class="section-title">My Registered Events</h2>
    <p class="text-muted">Your participation history and registration status.</p>

    <?php showFlash(); ?>

    <?php if (count($rows) > 0): ?>
        <div class="table-card table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Sport</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php $can_cancel = $r['event_date'] >= date('Y-m-d') && $r['approval_status'] !== 'cancelled'; ?>
                        <tr>
                            <td><?= e($r['event_name']) ?></td>
                            <td><?= e($r['sport_type']) ?></td>
                            <td>
                                <?= e($r['event_date']) ?><br>
                                <small class="text-muted"><?= substr($r['event_time'], 0, 5) ?></small>
                            </td>
                            <td><?= e($r['venue']) ?></td>
                            <td><?= date('M d, Y', strtotime($r['registered_at'])) ?></td>
                            <td>
                                <?php
                                $status_class = [
                                    'pending' => 'bg-warning text-dark',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'cancelled' => 'bg-secondary'
                                ];
                                $status_text = [
                                    'pending' => 'Pending Approval',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                    'cancelled' => 'Cancelled'
                                ];
                                ?>
                                <span class="badge <?= $status_class[$r['approval_status']] ?? 'bg-secondary' ?>">
                                    <?= $status_text[$r['approval_status']] ?? e($r['approval_status']) ?>
                                </span>

                                <?php if ($r['approval_status'] === 'approved'): ?>
                                    <br><small class="text-success">
                                        <?= (int)$r['approved_count'] ?>/<?= (int)$r['max_slots'] ?> slots
                                    </small>
                                <?php endif; ?>

                                <?php if ($r['approval_status'] === 'rejected' && !empty($r['rejection_reason'])): ?>
                                    <br><small class="text-danger" title="<?= e($r['rejection_reason']) ?>">
                                        <i class="bi bi-info-circle"></i> Reason
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($can_cancel): ?>
                                    <a href="cancel_registration.php?cancel_id=<?= (int)$r['registration_id'] ?>&token=<?= csrf_token() ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Are you sure you want to cancel this registration?');">
                                        Cancel
                                    </a>
                                <?php elseif ($r['approval_status'] === 'cancelled'): ?>
                                    <span class="text-muted">Cancelled</span>
                                <?php elseif ($r['event_date'] < date('Y-m-d')): ?>
                                    <span class="text-muted">Event Past</span>
                                <?php elseif ($r['approval_status'] === 'rejected'): ?>
                                    <span class="text-muted">Rejected</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Status Legend -->
        <div class="mt-3 p-3 bg-light rounded">
            <small class="text-muted">
                <strong>Status Guide:</strong>
                <span class="badge bg-warning text-dark ms-2">Pending</span> Awaiting admin approval
                <span class="badge bg-success ms-2">Approved</span> Registration confirmed
                <span class="badge bg-danger ms-2">Rejected</span> Registration declined
                <span class="badge bg-secondary ms-2">Cancelled</span> Registration cancelled
            </small>
        </div>
    <?php else: ?>
        <div class="alert alert-info">You haven't registered for any events yet. <a href="dashboard.php">Browse events</a></div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>