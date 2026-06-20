<?php
/**
 * Registration Processing Handler
 *
 * Handles:
 * - Single approval
 * - Single rejection
 * - Bulk approval
 * - Bulk rejection
 * - Cancellation of approved registrations
 * - Activity logging
 * - JSON response for AJAX calls
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Debug: Log session info
error_log("Session ID: " . session_id() . ", CSRF in session: " . ($_SESSION['csrf_token'] ?? 'NOT_SET'));

require 'config/db.php';
require 'includes/auth.php';
requireAdmin();

// Check if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// JSON response helper
function jsonResponse($success, $message, $data = []) {
    global $isAjax;
    if ($isAjax || !empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }
    // Non-AJAX: redirect with flash message
    if ($success) {
        flash('success', $message);
    } else {
        flash('danger', $message);
    }
    header('Location: manage_registrations.php');
    exit;
}

// Verify CSRF token
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? 'NOT_SET';

error_log("CSRF - Submitted: [" . $submittedToken . "], Session: [" . $sessionToken . "]");

if (!verify_csrf($submittedToken)) {
    jsonResponse(false, 'Invalid security token. Please try again.');
}

$action = $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin';

/**
 * ============================================
 * Log Admin Activity
 * ============================================
 */
function logActivity($pdo, $admin_id, $admin_name, $action_type, $target_id, $target_type, $description) {
    try {
        // Check if table exists first
        $pdo->query("SELECT 1 FROM admin_activity_log LIMIT 1");
        $stmt = $pdo->prepare('
            INSERT INTO admin_activity_log
            (admin_id, admin_name, action_type, target_id, target_type, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$admin_id, $admin_name, $action_type, $target_id, $target_type, $description]);
    } catch (PDOException $e) {
        // Silently fail - don't break the main operation
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * ============================================
 * Process Single Approval
 * ============================================
 */
if ($action === 'approve') {
    $registration_id = (int)($_POST['registration_id'] ?? 0);

    if ($registration_id <= 0) {
        jsonResponse(false, 'Invalid registration ID.');
    }

    try {
        $pdo->beginTransaction();

        // Get registration details with slot check
        $stmt = $pdo->prepare("
            SELECT r.*, e.max_slots,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = r.event_id AND approval_status = 'approved') as approved_count
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE r.id = ?
        ");
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            $pdo->rollBack();
            jsonResponse(false, 'Registration not found.');
        }

        if ($registration['approval_status'] !== 'pending') {
            $pdo->rollBack();
            jsonResponse(false, 'Registration is not pending. Current status: ' . $registration['approval_status']);
        }

        // Check slot availability
        if ((int)$registration['approved_count'] >= (int)$registration['max_slots']) {
            $pdo->rollBack();
            jsonResponse(false, 'No slots available for this event.');
        }

        // Approve the registration
        $stmt = $pdo->prepare("
            UPDATE registrations
            SET approval_status = 'approved',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$admin_id, $registration_id]);

        $pdo->commit();

        // Log activity
        logActivity($pdo, $admin_id, $admin_name, 'approve', $registration_id, 'registration',
            "Approved registration for event ID " . $registration['event_id']);

        jsonResponse(true, 'Registration approved successfully!');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Approval error: " . $e->getMessage());
        jsonResponse(false, 'Failed to approve registration. Please try again.');
    }
}

/**
 * ============================================
 * Process Single Rejection
 * ============================================
 */
if ($action === 'reject') {
    $registration_id = (int)($_POST['registration_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if ($registration_id <= 0) {
        jsonResponse(false, 'Invalid registration ID.');
    }

    try {
        $pdo->beginTransaction();

        // Get registration details
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = ?');
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            $pdo->rollBack();
            jsonResponse(false, 'Registration not found.');
        }

        // Handle edge case: already rejected
        if ($registration['approval_status'] === 'rejected') {
            $pdo->rollBack();
            jsonResponse(false, 'Registration is already rejected.');
        }

        // Handle edge case: already cancelled
        if ($registration['approval_status'] === 'cancelled') {
            $pdo->rollBack();
            jsonResponse(false, 'Cannot reject a cancelled registration.');
        }

        // Allow reject only from pending or approved status
        if (!in_array($registration['approval_status'], ['pending', 'approved'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Registration cannot be rejected. Current status: ' . $registration['approval_status']);
        }

        // Reject the registration
        $stmt = $pdo->prepare("
            UPDATE registrations
            SET approval_status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$admin_id, $rejection_reason, $registration_id]);

        $pdo->commit();

        // Log activity
        $reason_text = empty($rejection_reason) ? 'Not provided' : $rejection_reason;
        logActivity($pdo, $admin_id, $admin_name, 'reject', $registration_id, 'registration',
            "Rejected registration. Reason: " . $reason_text);

        jsonResponse(true, 'Registration rejected.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Rejection error: " . $e->getMessage());
        jsonResponse(false, 'Failed to reject registration. Please try again.');
    }
}

/**
 * ============================================
 * Process Cancellation (of approved registration)
 * ============================================
 */
if ($action === 'cancel') {
    $registration_id = (int)($_POST['registration_id'] ?? 0);

    if ($registration_id <= 0) {
        jsonResponse(false, 'Invalid registration ID.');
    }

    try {
        $pdo->beginTransaction();

        // Get registration details
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = ?');
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            $pdo->rollBack();
            jsonResponse(false, 'Registration not found.');
        }

        if ($registration['approval_status'] !== 'approved') {
            $pdo->rollBack();
            jsonResponse(false, 'Only approved registrations can be cancelled.');
        }

        // Cancel the registration
        $stmt = $pdo->prepare("
            UPDATE registrations
            SET approval_status = 'cancelled'
            WHERE id = ?
        ");
        $stmt->execute([$registration_id]);

        $pdo->commit();

        // Log activity
        logActivity($pdo, $admin_id, $admin_name, 'cancel', $registration_id, 'registration',
            "Cancelled approved registration");

        jsonResponse(true, 'Registration cancelled. Slot has been freed.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Cancellation error: " . $e->getMessage());
        jsonResponse(false, 'Failed to cancel registration. Please try again.');
    }
}

/**
 * ============================================
 * Process Bulk Approval
 * ============================================
 */
if ($action === 'bulk_approve') {
    $registration_ids = $_POST['registration_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $registration_ids)));

    if (empty($ids)) {
        jsonResponse(false, 'No registrations selected.');
    }

    $approved_count = 0;
    $failed_count = 0;

    try {
        $pdo->beginTransaction();

        foreach ($ids as $registration_id) {
            // Get registration details
            $stmt = $pdo->prepare("
                SELECT r.*, e.max_slots,
                       (SELECT COUNT(*) FROM registrations WHERE event_id = r.event_id AND approval_status = 'approved') as approved_count
                FROM registrations r
                JOIN events e ON r.event_id = e.id
                WHERE r.id = ?
            ");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registration && $registration['approval_status'] === 'pending') {
                if ((int)$registration['approved_count'] < (int)$registration['max_slots']) {
                    $update_stmt = $pdo->prepare("
                        UPDATE registrations
                        SET approval_status = 'approved',
                            approved_by = ?,
                            approved_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$admin_id, $registration_id]);
                    $approved_count++;
                } else {
                    $failed_count++;
                }
            }
        }

        $pdo->commit();

        // Log activity
        logActivity($pdo, $admin_id, $admin_name, 'bulk_approve', 0, 'registration',
            "Bulk approved " . $approved_count . " registrations");

        if ($failed_count > 0) {
            jsonResponse(true, "Approved " . $approved_count . " registrations. " . $failed_count . " failed (no slots).", ['partial' => true]);
        } else {
            jsonResponse(true, "Approved " . $approved_count . " registrations successfully!");
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bulk approval error: " . $e->getMessage());
        jsonResponse(false, 'Failed to process bulk approval. Please try again.');
    }
}

/**
 * ============================================
 * Process Bulk Rejection
 * ============================================
 */
if ($action === 'bulk_reject') {
    $registration_ids = $_POST['registration_ids'] ?? '';
    $ids = array_filter(array_map('intval', explode(',', $registration_ids)));
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($ids)) {
        jsonResponse(false, 'No registrations selected.');
    }

    $rejected_count = 0;

    try {
        $pdo->beginTransaction();

        foreach ($ids as $registration_id) {
            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? AND approval_status = 'pending'");
            $stmt->execute([$registration_id]);

            if ($stmt->fetch()) {
                $update_stmt = $pdo->prepare("
                    UPDATE registrations
                    SET approval_status = 'rejected',
                        approved_by = ?,
                        approved_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $update_stmt->execute([$admin_id, $rejection_reason, $registration_id]);
                $rejected_count++;
            }
        }

        $pdo->commit();

        // Log activity
        $reason_text = empty($rejection_reason) ? 'Not provided' : $rejection_reason;
        logActivity($pdo, $admin_id, $admin_name, 'bulk_reject', 0, 'registration',
            "Bulk rejected " . $rejected_count . " registrations. Reason: " . $reason_text);

        jsonResponse(true, "Rejected " . $rejected_count . " registrations.");
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bulk rejection error: " . $e->getMessage());
        jsonResponse(false, 'Failed to process bulk rejection. Please try again.');
    }
}

// Default: redirect back
/**
 * ============================================
 * Process Delete Registration
 * ============================================
 */
if ($action === 'delete') {
    $registration_id = (int)($_POST['registration_id'] ?? 0);

    if ($registration_id <= 0) {
        jsonResponse(false, 'Invalid registration ID.');
    }

    try {
        $pdo->beginTransaction();

        // Get registration details BEFORE deletion (need event_id for slot update)
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = ?');
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            $pdo->rollBack();
            jsonResponse(false, 'Registration not found.');
        }

        $event_id = $registration['event_id'];

        // Delete the registration
        $stmt = $pdo->prepare('DELETE FROM registrations WHERE id = ?');
        $stmt->execute([$registration_id]);

        $pdo->commit();

        // Log activity
        logActivity($pdo, $admin_id, $admin_name, 'delete', $registration_id, 'registration',
            "Deleted registration for event ID " . $event_id);

        jsonResponse(true, 'Registration deleted successfully. Slot has been freed.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete error: " . $e->getMessage());
        jsonResponse(false, 'Failed to delete registration. Please try again.');
    }
}

// Default: redirect back
jsonResponse(false, 'Invalid action.');
?>