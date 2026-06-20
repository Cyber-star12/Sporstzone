<?php
/**
 * Auto Migration Runner
 * Checks and runs database migrations automatically
 * Run this file once to set up the approval system
 */

require 'config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>SportsZone - Database Migration</title>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' rel='stylesheet'>
    <style>
        body { background: #0a0e27; color: #fff; padding: 50px 0; }
        .result-box { background: rgba(255,255,255,0.1); border-radius: 15px; padding: 30px; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
    </style>
</head>
<body>
<div class='container'>
    <div class='row justify-content-center'>
        <div class='col-md-8'>
            <div class='text-center mb-5'>
                <i class='bi bi-database-gear text-warning' style='font-size: 4rem;'></i>
                <h1 class='mt-3'>SportsZone Database Migration</h1>
                <p class='text-muted'>Setting up approval system...</p>
            </div>";

$migrations = [];
$errors = [];

// ============================================
// CHECK & ADD APPROVAL STATUS COLUMN
// ============================================
try {
    $result = $pdo->query("DESCRIBE registrations");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('approval_status', $columns)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'");
        $migrations[] = "✅ Added 'approval_status' column";
    } else {
        $migrations[] = "✓ 'approval_status' column already exists";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Error adding approval_status: " . $e->getMessage();
}

// ============================================
// CHECK & ADD APPROVED_BY COLUMN
// ============================================
try {
    if (!in_array('approved_by', $columns)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN approved_by INT NULL");
        $migrations[] = "✅ Added 'approved_by' column";
    } else {
        $migrations[] = "✓ 'approved_by' column already exists";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Error adding approved_by: " . $e->getMessage();
}

// ============================================
// CHECK & ADD APPROVED_AT COLUMN
// ============================================
try {
    if (!in_array('approved_at', $columns)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN approved_at TIMESTAMP NULL");
        $migrations[] = "✅ Added 'approved_at' column";
    } else {
        $migrations[] = "✓ 'approved_at' column already exists";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Error adding approved_at: " . $e->getMessage();
}

// ============================================
// CHECK & ADD REJECTION_REASON COLUMN
// ============================================
try {
    if (!in_array('rejection_reason', $columns)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN rejection_reason TEXT NULL");
        $migrations[] = "✅ Added 'rejection_reason' column";
    } else {
        $migrations[] = "✓ 'rejection_reason' column already exists";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Error adding rejection_reason: " . $e->getMessage();
}

// ============================================
// CHECK & ADD UPDATED_AT COLUMN
// ============================================
try {
    if (!in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $migrations[] = "✅ Added 'updated_at' column";
    } else {
        $migrations[] = "✓ 'updated_at' column already exists";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Error adding updated_at: " . $e->getMessage();
}

// ============================================
// UPDATE EXISTING REGISTRATIONS
// ============================================
try {
    $pdo->exec("UPDATE registrations SET approval_status = 'pending' WHERE approval_status IS NULL");
    $migrations[] = "✅ Updated existing registrations to 'pending'";
} catch (PDOException $e) {
    $errors[] = "❌ Error updating registrations: " . $e->getMessage();
}

// ============================================
// CHECK & CREATE ADMIN_ACTIVITY_LOG TABLE
// ============================================
try {
    $pdo->query("SELECT 1 FROM admin_activity_log LIMIT 1");
    $migrations[] = "✓ 'admin_activity_log' table already exists";
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            admin_name VARCHAR(100) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            target_id INT NULL,
            target_type VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_id (admin_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB");
        $migrations[] = "✅ Created 'admin_activity_log' table";
    } catch (PDOException $e2) {
        $errors[] = "❌ Error creating admin_activity_log: " . $e2->getMessage();
    }
}

// ============================================
// CREATE INDEXES
// ============================================
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_approval_status ON registrations(approval_status)");
    $migrations[] = "✅ Created index on approval_status";
} catch (PDOException $e) {
    // Index might already exist, ignore error
}

try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_approval ON registrations(event_id, approval_status)");
    $migrations[] = "✅ Created index on event_id + approval_status";
} catch (PDOException $e) {
    // Index might already exist, ignore error
}

// ============================================
// VERIFY SETUP
// ============================================
try {
    $pending = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'pending'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'approved'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM registrations WHERE approval_status = 'rejected'")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

    $migrations[] = "📊 Registrations: $total total, $pending pending, $approved approved, $rejected rejected";
} catch (PDOException $e) {
    $errors[] = "❌ Error counting registrations: " . $e->getMessage();
}

// ============================================
// OUTPUT RESULTS
// ============================================
echo "<div class='result-box'>";

if (empty($errors)) {
    echo "<div class='alert alert-success'>
        <i class='bi bi-check-circle-fill me-2'></i>
        <strong>Migration Completed Successfully!</strong>
    </div>";
} else {
    echo "<div class='alert alert-warning'>
        <i class='bi bi-exclamation-triangle-fill me-2'></i>
        <strong>Migration completed with some warnings</strong>
    </div>";
}

echo "<h5 class='mt-4'>Migration Results:</h5>";
echo "<ul class='list-unstyled'>";
foreach ($migrations as $msg) {
    echo "<li class='mb-2'><i class='bi bi-check-circle text-success me-2'></i>$msg</li>";
}
foreach ($errors as $err) {
    echo "<li class='mb-2'><i class='bi bi-exclamation-circle text-warning me-2'></i>$err</li>";
}
echo "</ul>";

if (empty($errors)) {
    echo "<div class='text-center mt-4'>
        <a href='manage_registrations.php' class='btn btn-success btn-lg'>
            <i class='bi bi-arrow-right-circle me-2'></i>Go to Registrations
        </a>
    </div>";
}

echo "</div>
</div>
</div>
</body>
</html>";