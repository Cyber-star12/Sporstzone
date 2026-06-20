<?php
require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

$event_id = (int)($_GET['event_id'] ?? 0);

// Get registrations
if ($event_id > 0) {
    $stmt = $pdo->prepare('SELECT r.student_name, r.usn_number, r.contact_number, r.course, r.registered_at, u.email, e.name as event_name, e.sport_type, e.event_date, e.venue FROM registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id WHERE r.event_id = ? ORDER BY r.registered_at DESC');
    $stmt->execute([$event_id]);
    $event = $pdo->prepare('SELECT name FROM events WHERE id = ?');
    $event->execute([$event_id]);
    $event_name = $event->fetch();
    $filename = 'registrations_' . str_replace(' ', '_', $event_name['name']) . '_' . date('Y-m-d') . '.csv';
} else {
    $stmt = $pdo->query('SELECT r.student_name, r.usn_number, r.contact_number, r.course, r.registered_at, u.email, e.name as event_name, e.sport_type, e.event_date, e.venue FROM registrations r JOIN users u ON r.user_id=u.id JOIN events e ON r.event_id=e.id ORDER BY r.registered_at DESC');
    $filename = 'all_registrations_' . date('Y-m-d') . '.csv';
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create CSV file
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($output, ['Student Name', 'USN', 'Contact', 'Course', 'Email', 'Event', 'Sport', 'Date', 'Venue', 'Registered At']);

// Data rows
foreach ($rows as $row) {
    fputcsv($output, [
        $row['student_name'],
        $row['usn_number'],
        $row['contact_number'],
        $row['course'],
        $row['email'],
        $row['event_name'],
        $row['sport_type'],
        $row['event_date'],
        $row['venue'],
        $row['registered_at']
    ]);
}

fclose($output);
exit;
?>