<?php
require 'config/db.php';
require 'includes/auth.php';
requireLogin();

if (isAdmin()) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$sport_filter = $_GET['sport'] ?? '';
$date_filter = $_GET['date'] ?? '';
$venue_filter = $_GET['venue'] ?? '';

// Get unique sport types for filter dropdown
$sports = $pdo->query('SELECT DISTINCT sport_type FROM events ORDER BY sport_type')->fetchAll(PDO::FETCH_COLUMN);

// Get unique venues for filter dropdown
$venues = $pdo->query('SELECT DISTINCT venue FROM events ORDER BY venue')->fetchAll(PDO::FETCH_COLUMN);

// Build query with filters
$sql = "SELECT e.*, COUNT(r.id) registered FROM events e LEFT JOIN registrations r ON e.id=r.event_id";
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(e.name LIKE ? OR e.sport_type LIKE ? OR e.venue LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($sport_filter) {
    $conditions[] = "e.sport_type = ?";
    $params[] = $sport_filter;
}

if ($date_filter) {
    if ($date_filter === 'upcoming') {
        $conditions[] = "e.event_date >= CURDATE()";
    } elseif ($date_filter === 'today') {
        $conditions[] = "e.event_date = CURDATE()";
    } elseif ($date_filter === 'past') {
        $conditions[] = "e.event_date < CURDATE()";
    }
}

if ($venue_filter) {
    $conditions[] = "e.venue = ?";
    $params[] = $venue_filter;
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " GROUP BY e.id ORDER BY e.event_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <?php showFlash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-title mb-1">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</h2>
            <p class="text-muted mb-0">Discover upcoming events and register to compete.</p>
        </div>
        <div>
            <a href="my_registrations.php" class="btn btn-outline-primary">
                <i class="bi bi-calendar-check me-1"></i> My Registrations
            </a>
        </div>
    </div>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="sport" class="form-select">
                        <option value="">All Sports</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?= htmlspecialchars($sport) ?>" <?= $sport_filter === $sport ? 'selected' : '' ?>><?= htmlspecialchars($sport) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="date" class="form-select">
                        <option value="">All Dates</option>
                        <option value="upcoming" <?= $date_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="past" <?= $date_filter === 'past' ? 'selected' : '' ?>>Past</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="venue" class="form-select">
                        <option value="">All Venues</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?= htmlspecialchars($venue) ?>" <?= $venue_filter === $venue ? 'selected' : '' ?>><?= htmlspecialchars($venue) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">Filter</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (count($events) > 0): ?>
        <div class="row g-4">
            <?php foreach ($events as $e): ?>
                <?php $remaining = $e['max_slots'] - $e['registered']; ?>
                <div class="col-md-6 col-lg-4">
                    <div class="event-card p-4 h-100">
                        <span class="badge badge-soft mb-2"><?= htmlspecialchars($e['sport_type']) ?></span>
                        <h4 class="fw-bold"><?= htmlspecialchars($e['name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($e['description']) ?></p>
                        <p><i class="bi bi-calendar"></i> <?= htmlspecialchars($e['event_date']) ?> at <?= substr($e['event_time'], 0, 5) ?></p>
                        <p><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($e['venue']) ?></p>
                        <span class="slot-pill <?= $remaining > 0 ? 'bg-success text-white' : 'bg-danger text-white' ?>"><?= $remaining > 0 ? $remaining . ' slots left' : 'Event Full' ?></span>
                        <div class="mt-3">
                            <?php if ($remaining > 0): ?>
                                <a class="btn btn-sport w-100" href="register_event.php?id=<?= $e['id'] ?>">Register Event</a>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>Full</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No events found matching your criteria. <a href="dashboard.php">Clear filters</a></div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>