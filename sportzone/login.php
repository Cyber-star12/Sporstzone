<?php
/**
 * Login Handler - Fixed Admin & Student Authentication
 */

ob_start();

require 'config/db.php';
require 'config/admin_config.php';
require 'includes/auth.php';

// Debug logging
error_log("=== LOGIN PAGE LOADED ===");
error_log("POST data: " . json_encode($_POST));

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $redirect = isAdmin() ? 'admin_dashboard.php' : 'dashboard.php';
    header("Location: $redirect");
    exit;
}

$errors = [];
$success = false;

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrf_token)) {
        flash('danger', 'Security token expired. Please refresh the page and try again.');
    } else {
        $login_type = $_POST['login_type'] ?? 'student';

        // ============================================
        // ADMIN LOGIN
        // ============================================
        if ($login_type === 'admin') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            error_log("Admin login attempt - Email: $email");

            // Validate input
            if (empty($email)) {
                flash('danger', 'Please enter your email address.');
            } elseif (empty($password)) {
                flash('danger', 'Please enter your password.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('danger', 'Invalid email format. Please enter a valid email.');
            } else {
                // Check if email matches ADMIN_EMAIL
                if (strtolower($email) !== strtolower(ADMIN_EMAIL)) {
                    error_log("Admin login failed - Email not matched");
                    flash('danger', 'Invalid admin email. Only registered admin emails can access.');
                } elseif ($password !== ADMIN_PASSWORD) {
                    error_log("Admin login failed - Password incorrect");
                    flash('danger', 'Incorrect password. Please try again.');
                } else {
                    // SUCCESS - Admin credentials are correct
                    error_log("Admin login SUCCESS for: $email");

                    // Create or get admin user in database
                    try {
                        // Check if admin user exists in database
                        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = "admin" LIMIT 1');
                        $stmt->execute([$email]);
                        $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$adminUser) {
                            // Create admin user if not exists
                            $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, usn) VALUES (?, ?, ?, "admin", "ADMIN001")');
                            $stmt->execute(['Admin', $email, $hash]);

                            // Get the newly created user
                            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                            $stmt->execute([$email]);
                            $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        // Set session
                        setUserSession($adminUser);
                        flash('success', 'Admin login successful! Welcome, ' . htmlspecialchars($adminUser['name']));
                        header('Location: admin_dashboard.php');
                        exit;

                    } catch (PDOException $e) {
                        error_log("Admin login DB error: " . $e->getMessage());
                        flash('danger', 'Database error occurred. Please try again later.');
                    }
                }
            }
        }

        // ============================================
        // STUDENT LOGIN
        // ============================================
        else {
            $name = trim($_POST['name'] ?? '');
            $usn = trim($_POST['usn'] ?? '');

            error_log("Student login attempt - Name: $name, USN: $usn");

            // Validate input
            if (empty($name)) {
                flash('danger', 'Please enter your full name.');
            } elseif (empty($usn)) {
                flash('danger', 'Please enter your USN/Roll Number.');
            } elseif (strlen($name) < 2) {
                flash('danger', 'Name must be at least 2 characters.');
            } elseif (strlen($usn) < 3) {
                flash('danger', 'USN must be at least 3 characters.');
            } else {
                try {
                    // Find student user by name and USN
                    $stmt = $pdo->prepare('SELECT id, name, usn, email, role FROM users WHERE name = ? AND usn = ? AND role = "student" LIMIT 1');
                    $stmt->execute([$name, $usn]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        error_log("Student login failed - User not found");
                        flash('danger', 'Student not found. Please check your Name and USN are correct.<br><small>If you haven\'t registered, please <a href="register.php">register here</a></small>');
                    } else {
                        // Success - Set session and redirect
                        setUserSession($user);
                        error_log("Student login SUCCESS: " . $user['name']);

                        // Handle remember me
                        if (!empty($_POST['remember_me'])) {
                            $remember_data = base64_encode(json_encode([
                                'type' => 'student',
                                'name' => $name,
                                'usn' => $usn
                            ]));
                            setcookie('sportzone_remember', $remember_data, time() + (7 * 24 * 60 * 60), '/', '', false, true);
                        }

                        flash('success', 'Login successful! Welcome, ' . htmlspecialchars($user['name']));
                        header('Location: dashboard.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Student login error: " . $e->getMessage());
                    flash('danger', 'Database error occurred. Please try again later.');
                }
            }
        }
    }
}

// ============================================
// REMEMBER ME FUNCTIONALITY
// ============================================
if (!isLoggedIn() && isset($_COOKIE['sportzone_remember'])) {
    $remember_data = json_decode(base64_decode($_COOKIE['sportzone_remember']), true);
    if ($remember_data && isset($remember_data['type']) && $remember_data['type'] === 'student') {
        try {
            $stmt = $pdo->prepare('SELECT id, name, usn, email, role FROM users WHERE name = ? AND usn = ? AND role = "student" LIMIT 1');
            $stmt->execute([$remember_data['name'] ?? '', $remember_data['usn'] ?? '']);
            $user = $stmt->fetch();

            if ($user) {
                setUserSession($user);
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Remember me error: " . $e->getMessage());
        }
    }
    setcookie('sportzone_remember', '', time() - 3600, '/');
}

ob_end_flush();

include 'includes/header.php';
?>

<style>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    position: relative;
    padding: 20px 0;
}
.login-page::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z" fill="%23ffffff" fill-opacity="0.03" fill-rule="evenodd"/%3E%3C/svg%3E');
}

/* AUTH BOX */
.login-page .auth-box {
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    background: #fff;
    border-radius: 20px;
    padding: 36px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.3);
    border-top: 4px solid #ffb703;
}

.login-page .brand-title {
    font-size: 1.8rem;
    font-weight: 800;
    white-space: nowrap;
    color: #0a0e27;
}

.login-page .input-group-text {
    transition: all 0.2s ease;
}
.login-page .form-control {
    transition: all 0.3s ease;
    border: 2px solid #e2e8f0;
}
.login-page .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
}
.login-page .input-group:focus-within .input-group-text {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

/* MOBILE STYLES */
@media (max-width: 576px) {
    .login-page {
        padding: 16px;
        align-items: flex-start;
        padding-top: 30px;
    }

    .login-page .auth-box {
        padding: 24px 20px;
        border-radius: 18px;
        max-width: 100%;
    }

    .login-page .brand-title {
        font-size: 1.5rem;
    }

    .login-page .form-control,
    .login-page .form-control-lg {
        font-size: 0.95rem;
        padding: 10px 12px;
    }

    .login-page .btn-lg {
        padding: 12px;
        font-size: 1rem;
    }

    .login-page .nav-link {
        padding: 8px 16px;
        font-size: 0.9rem;
    }

    .login-page .fs-1 {
        font-size: 2.5rem !important;
    }
}

@media (max-width: 400px) {
    .login-page .auth-box {
        padding: 20px 16px;
    }

    .login-page .brand-title {
        font-size: 1.3rem;
    }
}
</style>

<div class="login-page position-relative">
    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                <div class="auth-box">
                    <!-- Logo & Title -->
                    <div class="text-center mb-4">
                        <div class="mb-2">
                            <i class="bi bi-trophy-fill text-warning fs-1"></i>
                        </div>
                        <h3 class="brand-title">SportsZone</h3>
                        <p class="text-muted small mb-0">Presidency University Athletics</p>
                    </div>

                    <!-- Flash Messages -->
                    <?php showFlash(); ?>

                    <!-- Tabs -->
                    <ul class="nav nav-pills mb-4 justify-content-center" id="loginTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($_POST['login_type'] ?? 'student') === 'student' ? 'active' : '' ?> px-4" id="student-tab" data-bs-toggle="pill" data-bs-target="#student-login" type="button" role="tab">
                                <i class="bi bi-person-badge me-1"></i> Student
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($_POST['login_type'] ?? '') === 'admin' ? 'active' : '' ?> px-4" id="admin-tab" data-bs-toggle="pill" data-bs-target="#admin-login" type="button" role="tab">
                                <i class="bi bi-shield-lock me-1"></i> Admin
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="loginTabsContent">

                        <!-- Student Login -->
                        <div class="tab-pane fade <?= ($_POST['login_type'] ?? 'student') === 'student' ? 'show active' : '' ?>" id="student-login" role="tabpanel">
                            <form method="post" class="needs-validation" novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="login_type" value="student">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Full Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-person text-muted"></i>
                                        </span>
                                        <input type="text"
                                               name="name"
                                               class="form-control form-control-lg border-start-0"
                                               placeholder="Enter your full name"
                                               required minlength="2" maxlength="100"
                                               autocomplete="name">
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">USN / Roll Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">
                                            <i class="bi bi-hash text-muted"></i>
                                        </span>
                                        <input type="text"
                                               name="usn"
                                               id="usnInput"
                                               class="form-control form-control-lg border-start-0"
                                               placeholder="Enter your USN or Roll Number"
                                               required minlength="3" maxlength="20"
                                               autocomplete="off">
                                        <div class="invalid-feedback">Please enter your USN or Roll Number.</div>
                                    </div>
                                    <small class="text-muted mt-2 d-block">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Enter your registered USN or Roll Number.
                                    </small>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="remember_me" class="form-check-input" id="rememberStudent">
                                    <label class="form-check-label" for="rememberStudent">Remember me</label>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-sport btn-lg fw-bold">
                                        <i class="bi bi-box-arrow-in-right me-2"></i> Student Login
                                    </button>
                                </div>

                                <p class="text-center mt-3 mb-0">
                                    <span class="text-muted">New student?</span>
                                    <a href="register.php" class="text-decoration-none fw-semibold"> Register here</a>
                                </p>
                            </form>
                        </div>

                        <!-- Admin Login -->
                        <div class="tab-pane fade <?= ($_POST['login_type'] ?? '') === 'admin' ? 'show active' : '' ?>" id="admin-login" role="tabpanel">
                            <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                                <i class="bi bi-shield-check me-2"></i>
                                <div>
                                    <strong>Admin Access</strong><br>
                                    <small>Use admin credentials to manage events and registrations.</small>
                                </div>
                            </div>

                            <form method="post" class="needs-validation" novalidate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="login_type" value="admin">

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Admin Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" name="email"
                                               class="form-control form-control-lg"
                                               placeholder="Enter admin email"
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                               required maxlength="120">
                                        <div class="invalid-feedback">Please enter admin email.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" name="password"
                                               class="form-control form-control-lg"
                                               placeholder="Enter password"
                                               required minlength="6">
                                        <div class="invalid-feedback">Please enter password.</div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-dark btn-lg fw-bold">
                                        <i class="bi bi-shield-lock me-2"></i> Admin Login
                                    </button>
                                </div>
                            </form>

                            <div class="alert alert-warning mt-3 mb-0">
                                <small><i class="bi bi-key me-1"></i> Admin: teacher@gmail.com / admin@123</small>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center text-white mt-4 mb-0 opacity-75">
                    <small>&copy; <?= date('Y') ?> SportsZone. All rights reserved.</small>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Handle tab switching to preserve selected tab
document.addEventListener('DOMContentLoaded', function() {
    // Fix tab persistence after form submission
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'admin') {
        var adminTab = new bootstrap.Tab(document.getElementById('admin-tab'));
        adminTab.show();
    }

    var usnInput = document.getElementById('usnInput');
    if (usnInput) {
        usnInput.addEventListener('input', function() {
            var value = this.value.trim();
            this.classList.remove('is-valid', 'is-invalid');
            if (value.length >= 3) {
                this.classList.add('is-valid');
            } else if (value.length > 0) {
                this.classList.add('is-invalid');
            }
        });
    }

    var nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            var value = this.value.trim();
            this.classList.remove('is-valid', 'is-invalid');
            if (value.length >= 2) {
                this.classList.add('is-valid');
            } else if (value.length > 0) {
                this.classList.add('is-invalid');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>