<?php
require 'config/db.php';
require 'config/admin_config.php';
require 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Track field errors for inline display
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Invalid request. Please try again.');
        header('Location: register.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $usn = trim($_POST['usn'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $errors = [];

    // ============================================
    // 1. NAME VALIDATION (Backend)
    // ============================================
    // Requirements: Only alphabets and spaces, 3-50 characters
    $namePattern = '/^[a-zA-Z\s]{3,50}$/';

    if (empty($name)) {
        $errors[] = 'Full Name is required.';
        $field_errors['name'] = 'Full Name is required.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Name must be at least 3 characters.';
        $field_errors['name'] = 'Name must be at least 3 characters.';
    } elseif (strlen($name) > 50) {
        $errors[] = 'Name must not exceed 50 characters.';
        $field_errors['name'] = 'Name must not exceed 50 characters.';
    } elseif (!preg_match($namePattern, $name)) {
        $errors[] = 'Name should contain only alphabets and spaces.';
        $field_errors['name'] = 'Name should contain only alphabets and spaces.';
    }

    // ============================================
    // 2. USN VALIDATION (Backend)
    // ============================================
    $usnPattern = '/^U\d{2}[A-Z]{2,3}\d{2}[A-Z]?\d{3,4}$/i';

    if (empty($usn)) {
        $errors[] = 'USN is required.';
        $field_errors['usn'] = 'USN is required.';
    } elseif (!preg_match($usnPattern, $usn)) {
        $errors[] = 'Invalid USN format. Use format: U15BM23S0113';
        $field_errors['usn'] = 'Invalid USN format. Use format: U15BM23S0113';
    }

    // ============================================
    // 3. EMAIL VALIDATION (Backend)
    // ============================================
    // Allow only @gmail.com and @.edu.in domains
    $emailPattern = '/^[a-zA-Z0-9._%+-]+@gmail\.com$/i';
    $eduPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.edu\.in$/i';

    if (empty($email)) {
        $errors[] = 'Email is required.';
        $field_errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
        $field_errors['email'] = 'Please enter a valid email address.';
    } elseif (!preg_match($emailPattern, $email) && !preg_match($eduPattern, $email)) {
        $errors[] = 'Only @gmail.com and @edu.in email addresses are allowed.';
        $field_errors['email'] = 'Only @gmail.com and @edu.in email addresses are allowed.';
    }

    // ============================================
    // 4. PASSWORD VALIDATION (Backend)
    // ============================================
    // Requirements:
    // - Minimum 8 characters
    // - Must start with uppercase letter
    // - Must contain lowercase letters
    // - Must contain numbers
    // - Must contain special characters

    $passwordErrors = [];

    if (empty($pass)) {
        $passwordErrors[] = 'Password is required.';
    } else {
        if (strlen($pass) < 8) {
            $passwordErrors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/^[A-Z]/', $pass)) {
            $passwordErrors[] = 'Password must start with an uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $pass)) {
            $passwordErrors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $pass)) {
            $passwordErrors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $pass)) {
            $passwordErrors[] = 'Password must contain at least one special character.';
        }
    }

    if (!empty($passwordErrors)) {
        $errors = array_merge($errors, $passwordErrors);
        $field_errors['password'] = implode(' ', $passwordErrors);
    }

    // ============================================
    // INSERT USER (if no errors)
    // ============================================
    if (empty($errors)) {
        try {
            // Check if email matches admin email - assign admin role
            $role = (strtolower($email) === strtolower(ADMIN_EMAIL)) ? 'admin' : 'student';

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users(name, usn, email, password, role) VALUES(?, ?, ?, ?, ?)');
            $stmt->execute([$name, $usn, $email, $hash, $role]);

            if ($role === 'admin') {
                flash('success', 'Registration successful! You are registered as Admin.');
            } else {
                flash('success', 'Registration successful! Please login with your Name and USN.');
            }
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                flash('danger', 'Email or USN already exists.');
            } else {
                flash('danger', 'Registration failed. Please try again.');
            }
        }
    }
}

include 'includes/header.php';
?>

<style>
.register-page {
    min-height: 85vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    position: relative;
}
.register-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,%3Csvg width=\"100\" height=\"100\" viewBox=\"0 0 100 100\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cpath d=\"M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z\" fill=\"%23ffffff\" fill-opacity=\"0.03\" fill-rule=\"evenodd\"/%3E%3C/svg%3E');
}

/* Form Control Enhancements */
.register-page .form-control-lg {
    padding: 14px 18px;
    font-size: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
}
.register-page .form-control-lg:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    outline: none;
}
.register-page .form-control-lg.is-valid {
    border-color: #22c55e;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2322c55e' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 20px 20px;
    padding-right: 44px;
}
.register-page .form-control-lg.is-invalid {
    border-color: #ef4444;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.3 5.3l1.4 1.4M8.3 5.3l-1.4 1.4'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 20px 20px;
    padding-right: 44px;
}

/* Form Label */
.register-page .form-label {
    color: #374151;
    margin-bottom: 8px;
}

/* Password Requirements Badges */
.register-page .password-requirements {
    opacity: 0;
    transition: opacity 0.3s ease;
}
.register-page .password-requirements.show {
    opacity: 1;
}
.register-page .password-requirements .badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    transition: all 0.3s ease;
}
.register-page .password-requirements .badge.bg-success {
    color: white;
}
.register-page .password-requirements .badge.bg-success i::before {
    content: "\\2713";
}

/* Submit Button */
.register-page .btn-sport {
    padding: 14px 24px;
    font-size: 1.1rem;
}
</style>

<div class="register-page position-relative py-5">
    <div class="container py-5 position-relative">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="auth-box card border-0 shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-trophy-fill text-warning fs-1"></i>
                            <h2 class="fw-bold mt-3 mb-1">Join SportsZone</h2>
                            <p class="text-muted">Create your athlete account</p>
                        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" id="registrationForm" novalidate>
            <?= csrf_field() ?>

            <!-- Full Name -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Full Name *</label>
                <input type="text"
                       name="name"
                       id="name"
                       class="form-control form-control-lg <?= isset($field_errors['name']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       placeholder="Enter your full name"
                       autocomplete="name"
                       required>
                <div id="nameError" class="invalid-feedback"><?= $field_errors['name'] ?? '' ?></div>
            </div>

            <!-- USN Number -->
            <div class="mb-4">
                <label class="form-label fw-semibold">USN Number *</label>
                <input type="text"
                       name="usn"
                       id="usn"
                       class="form-control form-control-lg <?= isset($field_errors['usn']) ? 'is-invalid' : '' ?>"
                       placeholder="Enter your USN number"
                       value="<?= htmlspecialchars($_POST['usn'] ?? '') ?>"
                       autocomplete="off"
                       required>
                <div id="usnError" class="invalid-feedback"><?= $field_errors['usn'] ?? '' ?></div>
            </div>

            <!-- Email -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Email *</label>
                <input type="email"
                       name="email"
                       id="email"
                       class="form-control form-control-lg <?= isset($field_errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="Enter your email address"
                       autocomplete="email"
                       required>
                <div id="emailError" class="invalid-feedback"><?= $field_errors['email'] ?? '' ?></div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Password *</label>
                <input type="password"
                       name="password"
                       id="password"
                       class="form-control form-control-lg <?= isset($field_errors['password']) ? 'is-invalid' : '' ?>"
                       placeholder="Create a strong password"
                       autocomplete="new-password"
                       required>
                <div id="passwordError" class="invalid-feedback"><?= $field_errors['password'] ?? '' ?></div>

                <!-- Password Requirements - Show on focus -->
                <div class="mt-2 password-requirements" id="passwordRequirements">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-light text-muted" id="req-length">
                            <i class="bi bi-dash-lg me-1"></i>8+ chars
                        </span>
                        <span class="badge bg-light text-muted" id="req-uppercase">
                            <i class="bi bi-dash-lg me-1"></i>Uppercase
                        </span>
                        <span class="badge bg-light text-muted" id="req-lowercase">
                            <i class="bi bi-dash-lg me-1"></i>Lowercase
                        </span>
                        <span class="badge bg-light text-muted" id="req-number">
                            <i class="bi bi-dash-lg me-1"></i>Number
                        </span>
                        <span class="badge bg-light text-muted" id="req-special">
                            <i class="bi bi-dash-lg me-1"></i>Special
                        </span>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-sport w-100 py-2" id="submitBtn">Register</button>

            <p class="mt-3 text-center">Already have account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- JAVASCRIPT VALIDATION -->
<!-- ============================================ -->
<script>
// ============================================
// REGEX PATTERNS
// ============================================
const namePattern = /^[a-zA-Z\s]{3,50}$/;
const usnPattern = /^U\d{2}[A-Z]{2,3}\d{2}[A-Z]?\d{3,4}$/i;
const emailPattern = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;
const eduPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.edu\.in$/i;
const simpleUsnPattern = /^[A-Za-z0-9]{3,20}$/;

// ============================================
// VALIDATION FUNCTIONS
// ============================================

function validateName() {
    const field = document.getElementById('name');
    const errorDiv = document.getElementById('nameError');
    const value = field.value.trim();
    let error = '';

    if (value === '') {
        return true; // Don't show error on empty
    } else if (value.length < 3) {
        error = 'Name must be at least 3 characters.';
    } else if (value.length > 50) {
        error = 'Name must not exceed 50 characters.';
    } else if (!namePattern.test(value)) {
        error = 'Name should contain only alphabets and spaces.';
    }

    if (error) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        errorDiv.textContent = error;
        return false;
    } else {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        errorDiv.textContent = '';
        return true;
    }
}

function validateUsn() {
    const field = document.getElementById('usn');
    const errorDiv = document.getElementById('usnError');
    const value = field.value.trim();
    let error = '';

    if (value === '') {
        return true;
    } else if (!usnPattern.test(value) && !simpleUsnPattern.test(value)) {
        error = 'Please enter a valid USN format.';
    }

    if (error) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        errorDiv.textContent = error;
        return false;
    } else {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        errorDiv.textContent = '';
        return true;
    }
}

function validateEmail() {
    const field = document.getElementById('email');
    const errorDiv = document.getElementById('emailError');
    const value = field.value.trim();
    let error = '';

    if (value === '') {
        return true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        error = 'Please enter a valid email address.';
    } else if (!emailPattern.test(value) && !eduPattern.test(value)) {
        error = 'Only @gmail.com and @edu.in email addresses are allowed.';
    }

    if (error) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        errorDiv.textContent = error;
        return false;
    } else {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        errorDiv.textContent = '';
        return true;
    }
}

// Password Requirements Badge Elements
const passwordReqs = {
    length: document.getElementById('req-length'),
    uppercase: document.getElementById('req-uppercase'),
    lowercase: document.getElementById('req-lowercase'),
    number: document.getElementById('req-number'),
    special: document.getElementById('req-special')
};

function updatePasswordBadge(badge, isValid) {
    if (isValid) {
        badge.classList.remove('bg-light', 'text-muted');
        badge.classList.add('bg-success', 'text-white');
    } else {
        badge.classList.remove('bg-success', 'text-white');
        badge.classList.add('bg-light', 'text-muted');
    }
}

// Validate Password
function validatePassword() {
    const field = document.getElementById('password');
    const errorDiv = document.getElementById('passwordError');
    const value = field.value;
    let error = '';

    if (value === '') {
        return true;
    }

    const isValidLength = value.length >= 8;
    const isValidUppercase = /^[A-Z]/.test(value);
    const isValidLowercase = /[a-z]/.test(value);
    const isValidNumber = /[0-9]/.test(value);
    const isValidSpecial = /[^a-zA-Z0-9]/.test(value);

    updatePasswordBadge(passwordReqs.length, isValidLength);
    updatePasswordBadge(passwordReqs.uppercase, isValidUppercase);
    updatePasswordBadge(passwordReqs.lowercase, isValidLowercase);
    updatePasswordBadge(passwordReqs.number, isValidNumber);
    updatePasswordBadge(passwordReqs.special, isValidSpecial);

    if (value.length < 8) {
        error = 'Password must be at least 8 characters.';
    } else if (!/^[A-Z]/.test(value)) {
        error = 'Password must start with an uppercase letter.';
    } else if (!/[a-z]/.test(value)) {
        error = 'Password must contain at least one lowercase letter.';
    } else if (!/[0-9]/.test(value)) {
        error = 'Password must contain at least one number.';
    } else if (!/[^a-zA-Z0-9]/.test(value)) {
        error = 'Password must contain at least one special character.';
    }

    if (error) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        errorDiv.textContent = error;
        return false;
    } else {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        errorDiv.textContent = '';
        return true;
    }
}

// ============================================
// REAL-TIME VALIDATION (on input/blur)
// ============================================

// Name - validate on blur and input (after first blur)
let nameBlurred = false;
document.getElementById('name').addEventListener('blur', function() {
    nameBlurred = true;
    validateName();
});
document.getElementById('name').addEventListener('input', function() {
    if (nameBlurred) validateName();
});

// USN - validate on blur
document.getElementById('usn').addEventListener('blur', function() {
    validateUsn();
});

// Email - validate on blur
document.getElementById('email').addEventListener('blur', function() {
    validateEmail();
});

// Password - Show requirements on focus, validate on input
const passwordField = document.getElementById('password');
const passwordReqContainer = document.getElementById('passwordRequirements');

passwordField.addEventListener('focus', function() {
    passwordReqContainer.classList.add('show');
});

passwordField.addEventListener('blur', function() {
    if (passwordField.value === '') {
        passwordReqContainer.classList.remove('show');
    }
});

passwordField.addEventListener('input', function() {
    validatePassword();
});

// ============================================
// FORM SUBMISSION VALIDATION
// ============================================
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    // Mark all fields as blurred
    nameBlurred = true;

    // Validate all fields
    const nameValid = validateName();
    const usnValid = validateUsn();
    const emailValid = validateEmail();
    const passwordValid = validatePassword();

    // If any field is invalid, prevent submission
    if (!nameValid || !usnValid || !emailValid || !passwordValid) {
        e.preventDefault();

        // Focus first invalid field
        if (!nameValid) {
            document.getElementById('name').focus();
        } else if (!usnValid) {
            document.getElementById('usn').focus();
        } else if (!emailValid) {
            document.getElementById('email').focus();
        } else if (!passwordValid) {
            document.getElementById('password').focus();
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>