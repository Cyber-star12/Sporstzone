<?php
/**
 * Auto Database Fix - Safe Version
 * This script adds missing columns one by one
 * Run this in browser: http://yoursite.com/auto_fix.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Auto Fix - SportZone</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>
    <style>
        body { background: #f5f5f5; }
        .result-card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class='container py-5'>
<h2><i class='bi bi-wrench me-2'></i>Auto Database Fix</h2>
<p class='text-muted'>This will add missing columns to the registrations table.</p>";

$results = [];

// Function to check if column exists
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return in_array($column, $columns);
}

// Function to safely add column
function addColumn($pdo, $name, $definition, &$results) {
    try {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN $name $definition");
        $results[] = ['success' => true, 'message' => "Added column: $name"];
        return true;
    } catch (PDOException $e) {
        $results[] = ['success' => false, 'message' => "Failed to add $name: " . $e->getMessage()];
        return false;
    }
}

// Get current columns
echo "<div class='result-card'>";
echo "<h5>Current Columns:</h5>";
$stmt = $pdo->query("DESCRIBE registrations");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>" . implode("\n", $cols) . "</pre>";
echo "</div>";

// Columns to add
$columns_to_add = [
    'student_name' => 'VARCHAR(100) NOT NULL',
    'usn_number' => 'VARCHAR(20) NOT NULL',
    'contact_number' => 'VARCHAR(15) NOT NULL',
    'course' => 'VARCHAR(50) NOT NULL',
    'approval_status' => "ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'",
    'approved_by' => 'INT NULL',
    'approved_at' => 'TIMESTAMP NULL',
    'rejection_reason' => 'TEXT NULL'
];

echo "<div class='result-card'>";
echo "<h5>Adding Missing Columns:</h5>";

foreach ($columns_to_add as $col => $def) {
    if (!columnExists($pdo, 'registrations', $col)) {
        addColumn($pdo, $col, $def, $results);
    } else {
        $results[] = ['success' => true, 'message' => "Column '$col' already exists - skipped"];
    }
}

echo "</div>";

// Show results
echo "<div class='result-card'>";
echo "<h5>Results:</h5>";
foreach ($results as $r) {
    $icon = $r['success'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
    echo "<p>$icon {$r['message']}</p>";
}
echo "</div>";

// Add indexes
echo "<div class='result-card'>";
echo "<h5>Adding Indexes:</h5>";

try {
    $pdo->exec("ALTER TABLE registrations ADD UNIQUE INDEX idx_user_event (user_id, event_id)");
    echo "<p><i class='bi bi-check-circle text-success'></i> Added unique index on (user_id, event_id)</p>";
} catch (PDOException $e) {
    echo "<p><i class='bi bi-info-circle text-info'></i> Index (user_id, event_id): " . $e->getMessage() . "</p>";
}

try {
    $pdo->exec("ALTER TABLE registrations ADD INDEX idx_usn_event (usn_number, event_id)");
    echo "<p><i class='bi bi-check-circle text-success'></i> Added index on (usn_number, event_id)</p>";
} catch (PDOException $e) {
    echo "<p><i class='bi bi-info-circle text-info'></i> Index (usn_number, event_id): " . $e->getMessage() . "</p>";
}

echo "</div>";

// Test insert
echo "<div class='result-card'>";
echo "<h5>Testing Insert:</h5>";

try {
    $pdo->beginTransaction();

    $user = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    $event = $pdo->query("SELECT id FROM events LIMIT 1")->fetchColumn();

    if ($user && $event) {
        $test_usn = 'TEST' . time();
        $stmt = $pdo->prepare("INSERT INTO registrations (user_id, event_id, student_name, usn_number, contact_number, course, approval_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user, $event, 'Test', $test_usn, '1234567890', 'BCA']);
        $id = $pdo->lastInsertId();

        $pdo->exec("DELETE FROM registrations WHERE id = $id");
        $pdo->rollBack();

        echo "<p><i class='bi bi-check-circle text-success'></i> Test INSERT successful! (ID: $id, rolled back)</p>";
    } else {
        echo "<p><i class='bi bi-exclamation-circle text-warning'></i> No test user/event found</p>";
    }
} catch (PDOException $e) {
    echo "<p><i class='bi bi-x-circle text-danger'></i> Test INSERT failed: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Final verification
echo "<div class='result-card'>";
echo "<h5>Final Column Check:</h5>";
$stmt = $pdo->query("DESCRIBE registrations");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<pre>" . implode("\n", $cols) . "</pre>";

$required = ['id', 'user_id', 'event_id', 'student_name', 'usn_number', 'contact_number', 'course', 'approval_status'];
$all_present = true;
foreach ($required as $r) {
    if (!in_array($r, $cols)) {
        $all_present = false;
        echo "<p class='text-danger'>Missing: $r</p>";
    }
}

if ($all_present) {
    echo "<p class='text-success'><i class='bi bi-check-circle-fill'></i> All required columns are present!</p>";
}
echo "</div>";

echo "<a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>";
echo " <a href='register_event.php?id=1' class='btn btn-success'>Test Registration</a>";

echo "</div></body></html>";
?>