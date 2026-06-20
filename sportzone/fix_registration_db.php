<?php
/**
 * Complete Registration Database Fix
 * This script:
 * 1. Checks database structure
 * 2. Adds missing columns automatically
 * 3. Fixes any column mismatches
 * 4. Tests the registration flow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>SportZone - Database Fix</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; }
        .fix-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container py-5'>
<div class='row justify-content-center'>
<div class='col-lg-8'>

<h2 class='text-white mb-4'><i class='bi bi-wrench-adjustable me-2'></i>Registration Database Fix</h2>";

$changes_made = [];
$errors = [];

// ============================================
// STEP 1: Check Current Table Structure
// ============================================
echo "<div class='fix-card card mb-4'>
<div class='card-header bg-primary text-white'>
    <h5 class='mb-0'><i class='bi bi-database me-2'></i>Step 1: Checking Current Structure</h5>
</div>
<div class='card-body'>";

try {
    $stmt = $pdo->query("DESCRIBE registrations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    echo "Current registrations table structure:\n";

    $existing_cols = [];
    foreach ($columns as $col) {
        echo "  {$col['Field']} - {$col['Type']} ";
        if ($col['Key'] === 'PRI') echo "[PRIMARY KEY]";
        if ($col['Key'] === 'UNI') echo "[UNIQUE]";
        echo "\n";
        $existing_cols[] = $col['Field'];
    }
    echo "</pre>";

} catch (PDOException $e) {
    $errors[] = "Cannot read registrations table: " . $e->getMessage();
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

echo "</div></div>";

// ============================================
// STEP 2: Add Missing Columns
// ============================================
echo "<div class='fix-card card mb-4'>
<div class='card-header bg-warning'>
    <h5 class='mb-0'><i class='bi bi-plus-circle me-2'></i>Step 2: Adding Missing Columns</h5>
</div>
<div class='card-body'>";

$required_columns = [
    'student_name' => 'VARCHAR(100) NOT NULL',
    'usn_number' => 'VARCHAR(20) NOT NULL',
    'contact_number' => 'VARCHAR(15) NOT NULL',
    'course' => 'VARCHAR(50) NOT NULL',
    'approval_status' => "ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'",
    'approved_by' => 'INT NULL',
    'approved_at' => 'TIMESTAMP NULL',
    'rejection_reason' => 'TEXT NULL'
];

foreach ($required_columns as $col_name => $col_def) {
    if (!in_array($col_name, $existing_cols)) {
        try {
            $pdo->exec("ALTER TABLE registrations ADD COLUMN $col_name $col_def");
            $changes_made[] = "Added column: $col_name";
            echo "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Added column: <strong>$col_name</strong></div>";
        } catch (PDOException $e) {
            $errors[] = "Failed to add $col_name: " . $e->getMessage();
            echo "<div class='alert alert-danger'>Error adding $col_name: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='alert alert-info'><i class='bi bi-check-circle-fill me-2'></i>Column <strong>$col_name</strong> already exists</div>";
    }
}

echo "</div></div>";

// ============================================
// STEP 3: Fix Indexes
// ============================================
echo "<div class='fix-card card mb-4'>
<div class='card-header bg-info text-white'>
    <h5 class='mb-0'><i class='bi bi-list-check me-2'></i>Step 3: Fixing Indexes</h5>
</div>
<div class='card-body'>";

// Add unique index for user+event if not exists
try {
    $pdo->exec("ALTER TABLE registrations ADD UNIQUE INDEX idx_user_event (user_id, event_id)");
    $changes_made[] = "Added unique index: user_id + event_id";
    echo "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Added unique index for (user_id, event_id)</div>";
} catch (PDOException $e) {
    // Index might already exist
    echo "<div class='alert alert-info'><i class='bi bi-info-circle me-2'></i>Index (user_id, event_id): " . $e->getMessage() . "</div>";
}

// Add index for usn_number if not exists (for duplicate check)
try {
    $pdo->exec("ALTER TABLE registrations ADD INDEX idx_usn_event (usn_number, event_id)");
    $changes_made[] = "Added index: usn_number + event_id";
    echo "<div class='alert alert-success'><i class='bi bi-check-circle me-2'></i>Added index for (usn_number, event_id)</div>";
} catch (PDOException $e) {
    echo "<div class='alert alert-info'><i class='bi bi-info-circle me-2'></i>Index (usn_number, event_id): Already exists or not needed</div>";
}

echo "</div></div>";

// ============================================
// STEP 4: Test Registration Insert
// ============================================
echo "<div class='fix-card card mb-4'>
<div class='card-header bg-success text-white'>
    <h5 class='mb-0'><i class='bi bi-play-circle me-2'></i>Step 4: Testing Registration Insert</h5>
</div>
<div class='card-body'>";

try {
    $pdo->beginTransaction();

    // Get a test user and event
    $user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    $event_id = $pdo->query("SELECT id FROM events LIMIT 1")->fetchColumn();

    if ($user_id && $event_id) {
        $test_usn = 'TEST' . time();
        $test_name = 'Test Student';
        $test_contact = '1234567890';
        $test_course = 'BCA';

        $stmt = $pdo->prepare("
            INSERT INTO registrations
            (user_id, event_id, student_name, usn_number, contact_number, course, approval_status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user_id, $event_id, $test_name, $test_usn, $test_contact, $test_course]);
        $insert_id = $pdo->lastInsertId();

        // Delete the test record
        $pdo->exec("DELETE FROM registrations WHERE id = $insert_id");
        $pdo->rollBack();

        echo "<div class='alert alert-success'>
            <i class='bi bi-check-circle-fill me-2'></i>
            <strong>Test INSERT successful!</strong><br>
            Test data: user_id=$user_id, event_id=$event_id, usn=$test_usn<br>
            Inserted ID: $insert_id (rolled back)
        </div>";

        $registration_works = true;
    } else {
        echo "<div class='alert alert-warning'>
            <i class='bi bi-exclamation-triangle me-2'></i>
            Cannot test: No users or events found in database.
        </div>";
        $registration_works = false;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>
        <i class='bi bi-x-circle-fill me-2'></i>
        <strong>Test INSERT FAILED!</strong><br>
        Error: " . $e->getMessage() . "<br>
        SQL State: " . $e->getCode() . "
    </div>";
    $registration_works = false;
    $errors[] = "Registration insert test failed: " . $e->getMessage();
}

echo "</div></div>";

// ============================================
// STEP 5: Final Verification
// ============================================
echo "<div class='fix-card card mb-4'>
<div class='card-header bg-primary text-white'>
    <h5 class='mb-0'><i class='bi bi-shield-check me-2'></i>Step 5: Final Verification</h5>
</div>
<div class='card-body'>";

try {
    $stmt = $pdo->query("DESCRIBE registrations");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $required = ['id', 'user_id', 'event_id', 'student_name', 'usn_number', 'contact_number', 'course', 'approval_status'];
    $missing = [];

    foreach ($required as $req) {
        if (!in_array($req, $columns)) {
            $missing[] = $req;
        }
    }

    if (empty($missing)) {
        echo "<div class='alert alert-success'>
            <i class='bi bi-check-circle-fill me-2'></i>
            <strong>All required columns present!</strong><br>
            Columns: " . implode(', ', $columns) . "
        </div>";
    } else {
        echo "<div class='alert alert-danger'>
            <i class='bi bi-x-circle-fill me-2'></i>
            <strong>Missing columns: </strong>" . implode(', ', $missing) . "
        </div>";
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Verification failed: " . $e->getMessage() . "</div>";
}

echo "</div></div>";

// ============================================
// SUMMARY
// ============================================
echo "<div class='card'>";

if (empty($errors) && $registration_works) {
    echo "<div class='card-body bg-success text-white'>
        <h4><i class='bi bi-party-fill me-2'></i>All Fixed!</h4>
        <p class='mb-0'>The registration system is now ready to use.</p>
    </div>";
} else {
    echo "<div class='card-body bg-warning'>
        <h4><i class='bi bi-exclamation-triangle me-2'></i>Some Issues Remain</h4>";

    if (!empty($errors)) {
        echo "<ul>";
        foreach ($errors as $err) {
            echo "<li>$err</li>";
        }
        echo "</ul>";
    }

    echo "</div>";
}

echo "</div>";

echo "<div class='mt-4 text-center'>
    <a href='dashboard.php' class='btn btn-primary btn-lg me-2'>
        <i class='bi bi-house me-2'></i>Go to Dashboard
    </a>
    <a href='register_event.php?id=1' class='btn btn-success btn-lg'>
        <i class='bi bi-calendar-plus me-2'></i>Test Registration
    </a>
</div>";

echo "</div></div></body></html>";

if ($registration_works) {
    exit(0);
} else {
    exit(1);
}
?>