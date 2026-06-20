<?php
/**
 * Admin Activity Log
 * Shows history of admin actions
 */

require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

// Get activity logs
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count
$total_count = $pdo->query("SELECT COUNT(*) FROM admin_activity_log")->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get logs
$logs = $pdo->query("
    SELECT * FROM admin_activity_log
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
")->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <?php showFlash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-title mb-1">Admin Activity Log</h2>
            <p class="text-muted">History of admin actions and approvals</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-dark">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="panel-card p-4">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $action_class = [
                                'approve' => 'text-success',
                                'reject' => 'text-danger',
                                'cancel' => 'text-warning',
                                'bulk_approve' => 'text-success',
                                'bulk_reject' => 'text-danger'
                            ];
                            ?>
                            <tr>
                                <td>
                                    <small><?= date('M d, Y', strtotime($log['created_at'])) ?></small><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td><?= e($log['admin_name']) ?></td>
                                <td>
                                    <span class="<?= $action_class[$log['action_type']] ?? '' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $log['action_type'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['target_id']): ?>
                                        <?= e($log['target_type']) ?> #<?= (int)$log['target_id'] ?>
                                    <?php else: ?>
                                        <span class="text-muted">Bulk</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= e($log['description']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No activity logged yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>