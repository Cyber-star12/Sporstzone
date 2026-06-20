<?php
/**
 * Authentication Functions
 * Fixed for XAMPP Local Development
 */

// ============================================
// SESSION CONFIGURATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters for local development
    ini_set('session.use_cookies', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', 0);
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 0);

    session_start();
}

// Debug logging (remove in production)
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// ============================================
// CSRF Protection Functions
// ============================================

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate CSRF hidden form field
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// Session Functions
// ============================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // Check if session has user_id
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }

    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time'])) {
        $timeout = 30 * 60;
        if (time() - $_SESSION['login_time'] > $timeout) {
            logout();
            return false;
        }
        // Update login time to extend session
        $_SESSION['login_time'] = time();
    }

    return true;
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require login - redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require admin access - redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Logout function - completely destroy session
 */
function logout() {
    // Clear session variables
    $_SESSION = [];

    // Destroy session if active
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Clear remember me cookie
    if (isset($_COOKIE['sportzone_remember'])) {
        setcookie('sportzone_remember', '', time() - 3600, '/');
    }

    // Start fresh session for redirect
    session_start();
    session_regenerate_id(true);
}

/**
 * Set session variables after successful login
 */
function setUserSession($user) {
    // Regenerate session ID for security
    session_regenerate_id(true);

    // Set all user data in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();

    // Store USN for students
    if (isset($user['usn'])) {
        $_SESSION['usn'] = $user['usn'];
    }

    error_log("Session set for user: " . $user['name'] . " Role: " . $user['role']);
}

// ============================================
// Flash Message Functions
// ============================================

/**
 * Set flash message
 */
function flash($type, $message) {
    $allowed = ['success', 'danger', 'warning', 'info'];
    $type = in_array($type, $allowed) ? $type : 'info';

    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display and clear flash message
 */
function showFlash() {
    if (isset($_SESSION['flash'])) {
        $type = htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');

        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            'info' => 'bi-info-circle-fill'
        ];
        $icon = $icons[$type] ?? 'bi-info-circle';

        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
            <i class='bi $icon me-2'></i>$message
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";

        unset($_SESSION['flash']);
    }
}

// ============================================
// XSS Prevention Helpers
// ============================================

/**
 * Escape HTML special characters
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Escape for display
 */
function escape($string) {
    return e($string);
}
?>