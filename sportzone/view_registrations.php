<?php
require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

// Get all events for filter dropdown
$events = $pdo->query('SELECT id, name, sport_type FROM events ORDER BY name')->fetchAll();

// Filter by event if selected
$event_filter = (int)($_GET['event_id'] ?? 0);

if ($event_filter > 0) {
    $stmt = $pdo->prepare('SELECT r.id, r.event_id, r.student_name, r.usn_number, r.contact_number, r.course, r.registered_at, u.name user_name, u.email, e.name event_name, e.sport_type, e.event_date FROM registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id WHERE r.event_id = ? ORDER BY r.registered_at DESC');
    $stmt->execute([$event_filter]);
} else {
    $stmt = $pdo->query('SELECT r.id, r.event_id, r.student_name, r.usn_number, r.contact_number, r.course, r.registered_at, u.name user_name, u.email, e.name event_name, e.sport_type, e.event_date FROM registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id ORDER BY r.registered_at DESC');
}
$rows = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <h2 class="section-title">All Registrations</h2>
    <p class="text-muted">Students registered for sports events.</p>

    <!-- Filter Form -->
    <div class="mb-4">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label">Filter by Event</label>
                <select name="event_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Events</option>
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $event_filter == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['sport_type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($event_filter > 0): ?>
                <div class="col-auto">
                    <a href="view_registrations.php" class="btn btn-outline-secondary">Clear Filter</a>
                </div>
            <?php endif; ?>
            <div class="col-auto ms-auto">
                <a href="export_registrations.php<?= $event_filter > 0 ? '?event_id=' . $event_filter : '' ?>" class="btn btn-success">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </form>
    </div>

    <?php if (count($rows) > 0): ?>
        <div class="table-card table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>USN</th>
                        <th>Contact</th>
                        <th>Course</th>
                        <th>Email</th>
                        <th>Event</th>
                        <th>Sport</th>
                        <th>Date</th>
                        <th>Registered At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr id="reg-row-<?= (int)$r['id'] ?>">
                            <td><?= htmlspecialchars($r['student_name']) ?></td>
                            <td><?= htmlspecialchars($r['usn_number']) ?></td>
                            <td><?= htmlspecialchars($r['contact_number']) ?></td>
                            <td><?= htmlspecialchars($r['course']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= htmlspecialchars($r['event_name']) ?></td>
                            <td><?= htmlspecialchars($r['sport_type']) ?></td>
                            <td><?= htmlspecialchars($r['event_date']) ?></td>
                            <td><?= htmlspecialchars($r['registered_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-id="<?= (int)$r['id'] ?>"
                                        data-name="<?= htmlspecialchars($r['student_name']) ?>"
                                        data-event="<?= htmlspecialchars($r['event_name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No registrations found<?= $event_filter > 0 ? ' for this event' : '' ?>.</div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Delete Registration
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="display-4 text-danger mb-3">
                    <i class="bi bi-trash"></i>
                </div>
                <h5>Are you sure you want to delete this registration?</h5>
                <p class="text-muted">This action cannot be undone.</p>
                <div class="bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Student</small>
                            <p class="fw-bold mb-0" id="deleteStudentName"></p>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Event</small>
                            <p class="fw-bold mb-0" id="deleteEventName"></p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    A slot will be freed for this event.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<script>
// CSRF Token
var csrfToken = '<?php echo csrf_token(); ?>';

// Show Toast Message
function showToast(message, type) {
    var toastContainer = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-' + type + ' border-0';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    var iconMap = {
        'success': 'bi-check-circle-fill',
        'danger': 'bi-exclamation-triangle-fill',
        'warning': 'bi-exclamation-circle-fill',
        'info': 'bi-info-circle-fill'
    };

    toast.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + iconMap[type] + ' me-2"></i>' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';

    toastContainer.appendChild(toast);
    var bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Delete Modal - Set up details when modal opens
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var name = button.getAttribute('data-name');
    var eventName = button.getAttribute('data-event');

    document.getElementById('deleteStudentName').textContent = name;
    document.getElementById('deleteEventName').textContent = eventName;
    document.getElementById('deleteModal').dataset.registrationId = id;
});

// Handle Delete Confirmation
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    var modal = document.getElementById('deleteModal');
    var registrationId = modal.dataset.registrationId;
    var btn = this;

    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

    // Make AJAX request
    var formData = new FormData();
    formData.append('action', 'delete');
    formData.append('registration_id', registrationId);
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
        return res.text();
    })
    .then(function(text) {
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error('Invalid response');
        }
    })
    .then(function(data) {
        // Hide modal
        var deleteModal = bootstrap.Modal.getInstance(modal);
        deleteModal.hide();

        if (data.success) {
            // Remove row from table
            var row = document.getElementById('reg-row-' + registrationId);
            if (row) {
                row.remove();
            }
            showToast(data.message || 'Registration deleted successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to delete registration', 'danger');
        }
    })
    .catch(function(error) {
        showToast('Error: ' + error.message, 'danger');
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash me-1"></i> Delete';
    });
});
</script>

<?php include 'includes/footer.php'; ?>