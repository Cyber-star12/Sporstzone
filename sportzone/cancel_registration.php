<?php
/**
 * Cancel Registration Handler
 *
 * Improved version with:
 * - User ownership verification
 * - Transaction handling for database consistency
 * - Better error handling
 * - Prevents cancellation after event date
 */

require 'config/db.php';
require 'includes/auth.php';
requireLogin();

// ============================================
// STEP 1: Get Registration ID
// ============================================
$registration_id = 0;

// Check GET parameter (preferred method)
if (isset($_GET['cancel_id'])) {
    $cancel_id_raw = $_GET['cancel_id'];

    // Validate: must be numeric
    if (!is_numeric($cancel_id_raw)) {
        flash('danger', 'Invalid registration ID format.');
        header('Location: my_registrations.php');
        exit;
    }

    $registration_id = (int)$cancel_id_raw;

    // Verify CSRF token
    if (!isset($_GET['token']) || !verify_csrf($_GET['token'])) {
        flash('danger', 'Invalid security token. Please access cancel from your registrations page.');
        header('Location: my_registrations.php');
        exit;
    }
}
// Fallback to POST
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id'])) {
    $registration_id = (int)$_POST['registration_id'];

    // Verify CSRF token for POST
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid security token. Please try again.');
        header('Location: my_registrations.php');
        exit;
    }
}

// ============================================
// STEP 2: Validate Registration ID
// ============================================
if ($registration_id <= 0) {
    flash('danger', 'Invalid registration. No cancellation request received.');
    header('Location: my_registrations.php');
    exit;
}

try {
    // ============================================
    // STEP 3: Begin Transaction
    // ============================================
    $pdo->beginTransaction();

    // ============================================
    // STEP 4: Verify Registration Exists and Get Details
    // ============================================
    $check_stmt = $pdo->prepare('
        SELECT r.id, r.user_id, r.event_id, e.event_date
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = ?
    ');
    $check_stmt->execute([$registration_id]);
    $registration = $check_stmt->fetch(PDO::FETCH_ASSOC);

    // Check if registration exists
    if (!$registration) {
        $pdo->rollBack();
        flash('danger', 'Registration not found. It may have already been cancelled.');
        header('Location: my_registrations.php');
        exit;
    }

    // ============================================
    // STEP 5: Security Check - Verify Ownership
    // ============================================
    $current_user_id = (int)$_SESSION['user_id'];
    $registration_user_id = (int)$registration['user_id'];

    if ($registration_user_id !== $current_user_id) {
        // Log potential security violation
        $pdo->rollBack();
        error_log("Security: User $current_user_id attempted to cancel registration $registration_id owned by user $registration_user_id");
        flash('danger', 'You can only cancel your own registrations.');
        header('Location: my_registrations.php');
        exit;
    }

    // ============================================
    // STEP 6: Check if Event Date has Passed
    // ============================================
    $event_date = strtotime($registration['event_date']);
    $today = strtotime(date('Y-m-d'));

    if ($event_date < $today) {
        $pdo->rollBack();
        flash('danger', 'Cannot cancel registration for past events.');
        header('Location: my_registrations.php');
        exit;
    }

    // ============================================
    // STEP 7: Delete the Registration
    // ============================================
    $delete_stmt = $pdo->prepare('
        DELETE FROM registrations
        WHERE id = ? AND user_id = ?
    ');
    $delete_stmt->execute([$registration_id, $current_user_id]);

    // ============================================
    // STEP 8: Commit Transaction
    // ============================================
    $pdo->commit();

    // Check if deletion was successful
    if ($delete_stmt->rowCount() > 0) {
        flash('success', 'Registration cancelled successfully. Slot has been freed.');
    } else {
        flash('danger', 'Failed to cancel registration. Please try again.');
    }

} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error for debugging
    error_log("Cancel registration error: " . $e->getMessage());
    flash('danger', 'An error occurred. Please try again later.');
}

// ============================================
// STEP 9: Redirect Back
// ============================================
header('Location: my_registrations.php');
exit;
?>