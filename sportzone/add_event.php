<?php
require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid request. Please try again.');
        header('Location: add_event.php');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO events(name, sport_type, event_date, event_time, venue, max_slots, description) VALUES(?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['sport_type']),
        $_POST['event_date'],
        $_POST['event_time'],
        trim($_POST['venue']),
        (int)$_POST['max_slots'],
        trim($_POST['description'])
    ]);
    flash('success', 'Event added successfully.');
    header('Location: manage_events.php');
    exit;
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="auth-box">
        <h2 class="fw-bold">Add New Event</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input class="form-control mb-3" name="name" placeholder="Event name" required>
            <input class="form-control mb-3" name="sport_type" placeholder="Sport type" required>
            <input class="form-control mb-3" type="date" name="event_date" required>
            <input class="form-control mb-3" type="time" name="event_time" required>
            <input class="form-control mb-3" name="venue" placeholder="Venue" required>
            <input class="form-control mb-3" type="number" name="max_slots" placeholder="Max slots" min="1" required>
            <textarea class="form-control mb-3" name="description" placeholder="Description"></textarea>
            <button class="btn btn-sport w-100">Save Event</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>