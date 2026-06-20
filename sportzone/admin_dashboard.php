<?php
require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

/**
 * Admin Dashboard - Statistics and Analytics
 *
 * Fixes applied:
 * - COUNT(DISTINCT user_id) to prevent duplicate counting
 * - Proper JOINs to avoid orphan record issues
 * - Separate counts for clarity
 * - Optimized queries
 */

// ============================================
// STAT 1: Total Registered Students
// Count unique students who have at least one registration
// Uses DISTINCT to prevent duplicate counting from JOINs
// ============================================
$total_students = $pdo->query("
    SELECT COUNT(DISTINCT user_id)
    FROM registrations
")->fetchColumn();

// ============================================
// STAT 2: Total Events
// ============================================
$total_events = $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

// ============================================
// STAT 3: Total Registrations
// Count all registrations
// ============================================
$total_registrations = $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();

// ============================================
// STAT 3b: Registration Status Counts (Approval System)
// ============================================
// Check if approval system columns exist
$approval_columns_exist = false;
try {
    $pdo->query("SELECT approval_status FROM registrations LIMIT 1");
    $approval_columns_exist = true;
} catch (PDOException $e) {
    $approval_columns_exist = false;
}

if ($approval_columns_exist) {
    $pending_count = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'pending'")->fetchColumn();
    $approved_count = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'approved'")->fetchColumn();
    $rejected_count = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'rejected'")->fetchColumn();
    $cancelled_count = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'cancelled'")->fetchColumn();
} else {
    // If columns don't exist yet, set defaults
    $pending_count = 0;
    $approved_count = 0;
    $rejected_count = 0;
    $cancelled_count = 0;
}

// ============================================
// STAT 4: Events with Full Slots
// Events where registration count >= max_slots
// ============================================
$filled_events = $pdo->query("
    SELECT COUNT(*) FROM events e
    WHERE (
        SELECT COUNT(*) FROM registrations r
        WHERE r.event_id = e.id
    ) >= e.max_slots AND e.max_slots > 0
")->fetchColumn();

// ============================================
// STAT 7: Available Slots (Total across all events)
// ============================================
$available_slots = $pdo->query("
    SELECT COALESCE(SUM(e.max_slots), 0) - COALESCE((SELECT COUNT(*) FROM registrations), 0)
    FROM events e
")->fetchColumn();

// ============================================
// CHART 1: Registrations per Event
// Shows registered vs max slots for each event
// ============================================
$event_stats = $pdo->query('
    SELECT e.id, e.name, e.max_slots,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as registered
    FROM events e
    ORDER BY registered DESC
')->fetchAll();

// ============================================
// CHART 2: Registrations by Sport Type
// ============================================
$sport_stats = $pdo->query('
    SELECT e.sport_type,
           COUNT(r.id) as total
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    GROUP BY e.sport_type
    ORDER BY total DESC
')->fetchAll();

// ============================================
// CHART 3: Most Popular Sport (by registrations)
// ============================================
$most_popular_sport = $pdo->query('
    SELECT e.sport_type, COUNT(r.id) as reg_count
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    GROUP BY e.sport_type
    ORDER BY reg_count DESC
    LIMIT 1
')->fetch();

// ============================================
// TABLE: Recent Registrations
// Last 5 registrations with student and event details
// ============================================
$recent_regs = $pdo->query('
    SELECT r.registered_at, e.name as event_name, u.name as student_name, u.usn
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    JOIN users u ON r.user_id = u.id
    ORDER BY r.registered_at DESC
    LIMIT 5
')->fetchAll();

// ============================================
// TABLE: Students with Most Registrations
// ============================================
$top_students = $pdo->query('
    SELECT u.name, u.usn, COUNT(r.id) as reg_count
    FROM users u
    JOIN registrations r ON u.id = r.user_id
    WHERE u.role = \'student\'
    GROUP BY u.id
    ORDER BY reg_count DESC
    LIMIT 5
')->fetchAll();

include 'includes/header.php';
?>

<div class="container py-5">
    <?php showFlash(); ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-title mb-1">Dashboard</h2>
            <p class="text-muted mb-0">Welcome back! Here's what's happening with your events.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add_event.php" class="btn btn-sport">
                <i class="bi bi-plus-lg me-1"></i> New Event
            </a>
        </div>
    </div>

    <!-- Stats Cards Row 1 -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">Total Students</h6>
                        <h1 class="mb-0"><?= (int)$total_students ?></h1>
                    </div>
                    <div class="stat-icon bg-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Unique registered students</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">Total Events</h6>
                        <h1 class="mb-0"><?= (int)$total_events ?></h1>
                    </div>
                    <div class="stat-icon bg-success">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Total events in system</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-muted mb-1">Total Registrations</h6>
                        <h1 class="mb-0"><?= (int)$total_registrations ?></h1>
                    </div>
                    <div class="stat-icon bg-info">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Active registrations</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards Row 2 -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="panel-card p-3">
                <h6 class="text-muted">Full Events</h6>
                <h3 class="text-danger"><?= (int)$filled_events ?></h3>
                <p class="small text-muted mb-0">Events at max capacity</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-card p-3">
                <h6 class="text-muted">Available Slots</h6>
                <h3 class="text-success"><?= (int)$available_slots ?></h3>
                <p class="small text-muted mb-0">Total across all events</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-card p-3">
                <h6 class="text-muted">Avg per Event</h6>
                <h3><?= $total_events > 0 ? round($total_registrations / $total_events, 1) : 0 ?></h3>
                <p class="small text-muted mb-0">Registrations per event</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-card p-3">
                <h6 class="text-muted">Most Popular</h6>
                <h3 class="text-primary"><?= htmlspecialchars($most_popular_sport['sport_type'] ?? 'N/A') ?></h3>
                <p class="small text-muted mb-0"><?= (int)($most_popular_sport['reg_count'] ?? 0) ?> registrations</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="panel-card p-4">
                <h5 class="mb-3">Registrations per Event</h5>
                <canvas id="eventChart" height="200"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel-card p-4" style="min-height: 380px;">
                <h5 class="mb-3 fw-semibold text-secondary">
                    <i class="bi bi-graph-up me-2 text-primary"></i>Registrations Trend by Sport
                </h5>
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="sportChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Registrations and Top Students -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="panel-card p-4">
                <h5 class="mb-3">Recent Registrations</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>USN</th>
                                <th>Event</th>
                                <th>Registered At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_regs) > 0): ?>
                                <?php foreach ($recent_regs as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['student_name']) ?></td>
                                        <td><?= htmlspecialchars($r['usn'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(substr($r['event_name'], 0, 15)) ?></td>
                                        <td><?= date('M d, H:i', strtotime($r['registered_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-muted">No registrations yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel-card p-4">
                <h5 class="mb-3">Most Active Students</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>USN</th>
                                <th>Registrations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($top_students) > 0): ?>
                                <?php foreach ($top_students as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['name']) ?></td>
                                        <td><?= htmlspecialchars($s['usn'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-primary"><?= (int)$s['reg_count'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-muted">No student registrations</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Status Stats -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-warning mb-1"><?= (int)$pending_count ?></h3>
                <p class="small text-muted mb-0">Pending Approval</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-success mb-1"><?= (int)$approved_count ?></h3>
                <p class="small text-muted mb-0">Approved</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-danger mb-1"><?= (int)$rejected_count ?></h3>
                <p class="small text-muted mb-0">Rejected</p>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card p-3 text-center">
                <h3 class="text-secondary mb-1"><?= (int)$cancelled_count ?></h3>
                <p class="small text-muted mb-0">Cancelled</p>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="panel-card p-4">
        <a href="add_event.php" class="btn btn-sport me-2 mb-2">Add Event</a>
        <a href="manage_events.php" class="btn btn-dark me-2 mb-2">Manage Events</a>
        <a href="manage_registrations.php" class="btn btn-primary me-2 mb-2">
            <i class="bi bi-check2-square me-1"></i> Manage Registrations
        </a>
        <a href="view_registrations.php" class="btn btn-outline-dark me-2 mb-2">View Registrations</a>
        <a href="export_registrations.php" class="btn btn-outline-success mb-2">Export CSV</a>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Registrations per Event Chart
    const eventCtx = document.getElementById('eventChart').getContext('2d');
    new Chart(eventCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(function($e) { return substr($e['name'], 0, 15) . (strlen($e['name']) > 15 ? '...' : ''); }, $event_stats)) ?>,
            datasets: [{
                label: 'Registered',
                data: <?= json_encode(array_map(function($e) { return (int)$e['registered']; }, $event_stats)) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }, {
                label: 'Max Slots',
                data: <?= json_encode(array_map(function($e) { return (int)$e['max_slots']; }, $event_stats)) ?>,
                backgroundColor: 'rgba(201, 203, 207, 0.5)',
                borderColor: 'rgba(201, 203, 207, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // ============================================================
    // Registrations by Sport - Professional Line Chart
    // ============================================================
    const sportCtx = document.getElementById('sportChart').getContext('2d');

    // Modern color palette for dark blue dashboard theme
    const sportColors = {
        primary: 'rgba(59, 130, 246, 1)',
        primaryLight: 'rgba(59, 130, 246, 0.1)',
        gradient: [
            'rgba(59, 130, 246, 1)',    // Blue
            'rgba(16, 185, 129, 1)',    // Emerald
            'rgba(245, 158, 11, 1)',    // Amber
            'rgba(139, 92, 246, 1)',    // Purple
            'rgba(236, 72, 153, 1)',    // Pink
            'rgba(14, 165, 233, 1)',    // Sky
            'rgba(34, 197, 94, 1)',     // Green
            'rgba(249, 115, 22, 1)'     // Orange
        ],
        gradientLight: [
            'rgba(59, 130, 246, 0.2)',
            'rgba(16, 185, 129, 0.2)',
            'rgba(245, 158, 11, 0.2)',
            'rgba(139, 92, 246, 0.2)',
            'rgba(236, 72, 153, 0.2)',
            'rgba(14, 165, 233, 0.2)',
            'rgba(34, 197, 94, 0.2)',
            'rgba(249, 115, 22, 0.2)'
        ],
        gridColor: 'rgba(148, 163, 184, 0.15)',
        textColor: 'rgba(148, 163, 184, 0.9)',
        tooltipBg: 'rgba(15, 23, 42, 0.95)'
    };

    // Sport names and registration data from database
    const sportLabels = <?= json_encode(array_map(function($s) { return $s['sport_type']; }, $sport_stats)) ?>;
    const sportData = <?= json_encode(array_map(function($s) { return (int)$s['total']; }, $sport_stats)) ?>;

    // Create gradient fill for the line chart
    const createGradient = (ctx, color) => {
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, color.replace('1)', '0.4)'));
        gradient.addColorStop(1, color.replace('1)', '0.02)'));
        return gradient;
    };

    // Create the professional line chart
    new Chart(sportCtx, {
        type: 'line',
        data: {
            labels: sportLabels,
            datasets: [{
                label: 'Registrations',
                data: sportData,
                // Main line styling
                borderColor: sportColors.primary,
                borderWidth: 3,
                borderCapStyle: 'round',
                borderJoinStyle: 'round',
                // Fill styling
                fill: true,
                backgroundColor: createGradient(sportCtx, sportColors.primary),
                // Point styling - only show points on hover for cleaner look
                pointRadius: 0,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: sportColors.primary,
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: sportColors.primary,
                pointBorderWidth: 2,
                // Smooth curved lines (tension)
                tension: 0.4
            }]
        },
        options: {
            // Responsive configuration
            responsive: true,
            maintainAspectRatio: false,
            // Animation for smooth loading
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                delay: function(context) {
                    return context.dataIndex * 100;
                }
            },
            // Interaction settings
            interaction: {
                mode: 'index',
                intersect: false
            },
            // Plugin configurations
            plugins: {
                // Chart title
                title: {
                    display: true,
                    text: 'Registrations Trend by Sport',
                    color: sportColors.textColor,
                    font: {
                        family: "'Inter', sans-serif",
                        size: 14,
                        weight: '600'
                    },
                    padding: {
                        bottom: 20
                    },
                    align: 'start'
                },
                // Legend
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        color: sportColors.textColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 12,
                            weight: '500'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        boxWidth: 8
                    }
                },
                // Tooltips
                tooltip: {
                    enabled: true,
                    backgroundColor: sportColors.tooltipBg,
                    titleColor: '#ffffff',
                    bodyColor: 'rgba(255, 255, 255, 0.9)',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    cornerRadius: 10,
                    padding: 12,
                    displayColors: true,
                    usePointStyle: true,
                    boxPadding: 6,
                    titleFont: {
                        family: "'Inter', sans-serif",
                        size: 13,
                        weight: '600'
                    },
                    bodyFont: {
                        family: "'Inter', sans-serif",
                        size: 12,
                        weight: '500'
                    },
                    callbacks: {
                        // Custom title format
                        title: function(context) {
                            return context[0].label;
                        },
                        // Custom label format
                        label: function(context) {
                            const value = context.parsed.y;
                            return ` ${value} registration${value !== 1 ? 's' : ''}`;
                        }
                    }
                }
            },
            // Axis configurations
            scales: {
                // X-Axis
                x: {
                    grid: {
                        display: false,
                        drawBorder: false,
                        borderDash: [5, 5]
                    },
                    ticks: {
                        color: sportColors.textColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11,
                            weight: '500'
                        },
                        padding: 10,
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8
                    },
                    border: {
                        display: false
                    }
                },
                // Y-Axis
                y: {
                    beginAtZero: true,
                    grid: {
                        color: sportColors.gridColor,
                        drawBorder: false,
                        borderDash: [5, 5],
                        lineWidth: 1
                    },
                    ticks: {
                        color: sportColors.textColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11,
                            weight: '500'
                        },
                        padding: 12,
                        stepSize: 1,
                        // Include registration count in label
                        callback: function(value) {
                            return value;
                        }
                    },
                    border: {
                        display: false
                    },
                    // Add some padding at top for better visuals
                    suggestedMax: Math.max(...sportData) + 1
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>