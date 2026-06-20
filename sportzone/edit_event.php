<?php
require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    flash('danger', 'Event not found.');
    header('Location: manage_events.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid request. Please try again.');
        header('Location: edit_event.php?id=' . $id);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE events SET name = ?, sport_type = ?, event_date = ?, event_time = ?, venue = ?, max_slots = ?, description = ? WHERE id = ?');
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['sport_type']),
        $_POST['event_date'],
        $_POST['event_time'],
        trim($_POST['venue']),
        (int)$_POST['max_slots'],
        trim($_POST['description']),
        $id
    ]);
    flash('success', 'Event updated.');
    header('Location: manage_events.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="auth-box">
        <h2 class="fw-bold">Edit Event</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input class="form-control mb-3" name="name" value="<?= htmlspecialchars($event['name']) ?>" required>
            <input class="form-control mb-3" name="sport_type" value="<?= htmlspecialchars($event['sport_type']) ?>" required>
            <input class="form-control mb-3" type="date" name="event_date" value="<?= $event['event_date'] ?>" required>
            <input class="form-control mb-3" type="time" name="event_time" value="<?= $event['event_time'] ?>" required>
            <input class="form-control mb-3" name="venue" value="<?= htmlspecialchars($event['venue']) ?>" required>
            <input class="form-control mb-3" type="number" name="max_slots" value="<?= $event['max_slots'] ?>" min="1" required>
            <textarea class="form-control mb-3" name="description"><?= htmlspecialchars($event['description']) ?></textarea>
            <button class="btn btn-sport w-100">Update Event</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>