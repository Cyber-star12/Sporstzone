<?php
/**
 * Event Registration Handler
 */

require 'config/db.php';
require 'includes/auth.php';
requireLogin();

$event_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

// ============================================
// STEP 1: Check Database Columns
// ============================================
$db_columns = [];
$approval_exists = false;
$form_fields_exist = false;

try {
    $stmt = $pdo->query("DESCRIBE registrations");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $db_columns = $columns;

    $approval_exists = in_array('approval_status', $columns);
    $form_fields_exist = in_array('student_name', $columns) &&
                         in_array('usn_number', $columns) &&
                         in_array('contact_number', $columns) &&
                         in_array('course', $columns);

} catch (PDOException $e) {
    error_log("DB Describe Error: " . $e->getMessage());
}

// ============================================
// STEP 2: Redirect if database not ready
// ============================================
$db_setup_error = '';

if (!$form_fields_exist) {
    $db_setup_error = 'Database setup incomplete. Please run the fix script.';
}

// ============================================
// STEP 3: Get Event Details
// ============================================
if ($approval_exists) {
    $stmt = $pdo->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND approval_status = 'approved') as registered
        FROM events e
        WHERE e.id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registered
        FROM events e
        WHERE e.id = ?
    ");
}
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================
// STEP 4: Validate Event
// ============================================
if (!$event) {
    flash('danger', 'Event not found.');
    header('Location: dashboard.php');
    exit;
}

$event_date = strtotime($event['event_date']);
$today = strtotime(date('Y-m-d'));

if ($event_date < $today) {
    flash('danger', 'Cannot register for past events.');
    header('Location: dashboard.php');
    exit;
}

// ============================================
// STEP 5: Check Existing Registration
// ============================================
$check = $pdo->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ?');
$check->execute([$user_id, $event_id]);
$existing_registration = $check->fetch(PDO::FETCH_ASSOC);

if ($existing_registration) {
    flash('warning', 'You have already registered for this event.');
    header('Location: dashboard.php');
    exit;
}

// ============================================
// STEP 6: Check Slots
// ============================================
$remaining = (int)$event['max_slots'] - (int)$event['registered'];

if ($remaining <= 0) {
    flash('danger', 'This event is full.');
    header('Location: dashboard.php');
    exit;
}

// ============================================
// STEP 7: Handle Form Submission
// ============================================
$errors = [];
$field_errors = [];
$loading = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check database setup first
    if (!empty($db_setup_error)) {
        $errors[] = $db_setup_error;
    } else {
        // Verify CSRF token
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please refresh the page and try again.';
        } else {
            // Get form inputs
            $student_name = trim($_POST['student_name'] ?? '');
            $usn_number = trim($_POST['usn_number'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $course = trim($_POST['course'] ?? '');

            // Validate Student Name
            if (empty($student_name)) {
                $errors[] = 'Student Name is required.';
                $field_errors['student_name'] = 'Please enter your name.';
            } elseif (strlen($student_name) < 2) {
                $errors[] = 'Student Name must be at least 2 characters.';
                $field_errors['student_name'] = 'Name is too short.';
            } elseif (!preg_match('/^[a-zA-Z\s\'.-]+$/', $student_name)) {
                $errors[] = 'Student Name can only contain letters, spaces, dots, and hyphens.';
                $field_errors['student_name'] = 'Name contains invalid characters.';
            }

            // Validate USN
            if (empty($usn_number)) {
                $errors[] = 'USN Number is required.';
                $field_errors['usn_number'] = 'Please enter your USN.';
            } elseif (strlen($usn_number) < 3) {
                $errors[] = 'USN Number is too short.';
                $field_errors['usn_number'] = 'USN is too short.';
            }

            // Validate Contact Number
            if (empty($contact_number)) {
                $errors[] = 'Contact Number is required.';
                $field_errors['contact_number'] = 'Please enter your contact number.';
            } elseif (!preg_match('/^[0-9]+$/', $contact_number)) {
                $errors[] = 'Contact number must contain only digits.';
                $field_errors['contact_number'] = 'Only numbers allowed.';
            } elseif (strlen($contact_number) !== 10) {
                $errors[] = 'Contact number must be exactly 10 digits.';
                $field_errors['contact_number'] = 'Must be 10 digits.';
            }

            // Validate Course
            $valid_courses = ['BCA', 'BBA', 'BCom', 'BSc', 'BA', 'MA', 'MBA', 'MCA', 'Other'];
            if (empty($course)) {
                $errors[] = 'Please select a Course.';
                $field_errors['course'] = 'Please select a course.';
            } elseif (!in_array($course, $valid_courses)) {
                $errors[] = 'Please select a valid Course.';
                $field_errors['course'] = 'Invalid course selected.';
            }

            // Check Duplicate USN for Same Event
            if (empty($errors)) {
                try {
                    $usn_check = $pdo->prepare('
                        SELECT id, student_name
                        FROM registrations
                        WHERE usn_number = ? AND event_id = ?
                    ');
                    $usn_check->execute([$usn_number, $event_id]);
                    $existing_usn = $usn_check->fetch(PDO::FETCH_ASSOC);

                    if ($existing_usn) {
                        $errors[] = 'This USN has already registered for this event.';
                        $field_errors['usn_number'] = 'This USN is already registered.';
                    }
                } catch (PDOException $e) {
                    // Continue even if this check fails
                }
            }

            // ============================================
            // Insert Registration
            // ============================================
            if (empty($errors)) {
                // Re-check slot availability
                if ($approval_exists) {
                    $slot_check = $pdo->prepare("
                        SELECT e.max_slots,
                               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND approval_status = 'approved') as approved_count
                        FROM events e WHERE e.id = ?
                    ");
                } else {
                    $slot_check = $pdo->prepare("
                        SELECT e.max_slots,
                               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as approved_count
                        FROM events e WHERE e.id = ?
                    ");
                }
                $slot_check->execute([$event_id]);
                $slot_info = $slot_check->fetch(PDO::FETCH_ASSOC);

                $available_slots = (int)$slot_info['max_slots'] - (int)$slot_info['approved_count'];

                if ($available_slots <= 0) {
                    $errors[] = 'Sorry! All slots have been filled.';
                } else {
                    // Insert the registration
                    try {
                        if ($approval_exists) {
                            $stmt = $pdo->prepare("
                                INSERT INTO registrations
                                (user_id, event_id, student_name, usn_number, contact_number, course, approval_status, registered_at)
                                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                            ");
                            $stmt->execute([
                                $user_id,
                                $event_id,
                                $student_name,
                                $usn_number,
                                $contact_number,
                                $course
                            ]);
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO registrations
                                (user_id, event_id, student_name, usn_number, contact_number, course, registered_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $user_id,
                                $event_id,
                                $student_name,
                                $usn_number,
                                $contact_number,
                                $course
                            ]);
                        }

                        $reg_id = $pdo->lastInsertId();

                        $msg = 'Registration submitted for ' . htmlspecialchars($event['name']) . '!';
                        if ($approval_exists) {
                            $msg .= ' Your registration is pending admin approval.';
                        }
                        flash('success', $msg);
                        header('Location: my_registrations.php');
                        exit;

                    } catch (PDOException $e) {
                        $error_msg = $e->getMessage();
                        if ($e->getCode() == '23000' || strpos($error_msg, 'Duplicate') !== false) {
                            $errors[] = 'You have already registered for this event.';
                        } else {
                            $errors[] = 'Registration failed: ' . htmlspecialchars($error_msg);
                        }
                    }
                }
            }
        }
    }

    $loading = true;
}

include 'includes/header.php';
?>

<style>
.register-event-page {
    min-height: 85vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, #0a0e27 0%, #121a35 50%, #1a2744 100%);
    position: relative;
}

.register-event-page::before {
    content: '';
    position: absolute;
    top: -10%;
    right: -5%;
    width: 40%;
    height: 100%;
    background: radial-gradient(ellipse, rgba(255, 183, 3, 0.08) 0%, transparent 60%);
    pointer-events: none;
}

.register-event-page .auth-box {
    background: #fff;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.2);
}

.register-event-page .form-control-lg,
.register-event-page .form-select {
    padding: 14px 18px;
    font-size: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.register-event-page .form-control-lg:focus,
.register-event-page .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    outline: none;
}

.register-event-page .form-control-lg.is-valid {
    border-color: #22c55e;
}

.register-event-page .form-control-lg.is-invalid {
    border-color: #ef4444;
}

.register-event-page .form-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.event-details {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 28px;
}

.event-details h5 {
    color: #0a0e27;
    margin-bottom: 16px;
}

.event-details p {
    color: #64748b;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.event-details p strong {
    color: #374151;
}

.event-details .badge {
    font-size: 0.8rem;
    padding: 6px 12px;
}

.register-event-page .btn-sport {
    padding: 16px 24px;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 12px;
}

@media (max-width: 576px) {
    .register-event-page .auth-box {
        padding: 24px;
        margin: 20px;
    }
}
</style>

<div class="register-event-page position-relative py-5">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-box">
                    <h2 class="fw-bold mb-4" style="color: #0a0e27;">Register for Event</h2>

                    <?php if (!empty($db_setup_error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Database Setup Required!</strong>
                            <p class="mb-0 mt-2"><?= htmlspecialchars($db_setup_error) ?></p>
                            <a href="fix_registration_db.php" class="btn btn-warning btn-sm mt-2">
                                <i class="bi bi-wrench me-1"></i>Fix Database Now
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="event-details">
                        <h5 class="fw-bold">
                            <i class="bi bi-calendar-event me-2 text-warning"></i>
                            <?= htmlspecialchars($event['name']) ?>
                        </h5>
                        <p class="mb-2">
                            <strong>Sport:</strong> <?= htmlspecialchars($event['sport_type']) ?>
                        </p>
                        <p class="mb-2">
                            <strong>Date:</strong> <?= htmlspecialchars($event['event_date']) ?> at <?= substr($event['event_time'], 0, 5) ?>
                        </p>
                        <p class="mb-2">
                            <strong>Venue:</strong> <?= htmlspecialchars($event['venue']) ?>
                        </p>
                        <p class="mb-0">
                            <strong>Slots:</strong>
                            <span class="badge <?= $remaining > 5 ? 'bg-success' : ($remaining > 0 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                <?= $remaining ?> of <?= (int)$event['max_slots'] ?> available
                            </span>
                        </p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <strong>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="registrationForm" <?= !empty($db_setup_error) ? 'style="display:none;"' : '' ?> novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label class="form-label">Student Name *</label>
                            <input type="text"
                                   name="student_name"
                                   id="student_name"
                                   class="form-control form-control-lg <?= isset($field_errors['student_name']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($_POST['student_name'] ?? '') ?>"
                                   placeholder="Enter your full name"
                                   required
                                   minlength="2"
                                   maxlength="100">
                            <?php if (isset($field_errors['student_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($field_errors['student_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">USN Number *</label>
                            <input type="text"
                                   name="usn_number"
                                   id="usn_number"
                                   class="form-control form-control-lg <?= isset($field_errors['usn_number']) ? 'is-invalid' : '' ?>"
                                   placeholder="Enter your USN number"
                                   value="<?= htmlspecialchars($_POST['usn_number'] ?? '') ?>"
                                   required
                                   minlength="3"
                                   maxlength="20">
                            <?php if (isset($field_errors['usn_number'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($field_errors['usn_number']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Contact Number *</label>
                            <input type="text"
                                   name="contact_number"
                                   id="contact_number"
                                   class="form-control form-control-lg <?= isset($field_errors['contact_number']) ? 'is-invalid' : '' ?>"
                                   placeholder="Enter your contact number"
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                   required
                                   inputmode="numeric"
                                   maxlength="10">
                            <?php if (isset($field_errors['contact_number'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($field_errors['contact_number']) ?></div>
                            <?php else: ?>
                                <small class="text-muted d-block mt-1">Enter exactly 10 digits</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Course *</label>
                            <select name="course"
                                    id="course"
                                    class="form-select form-control-lg <?= isset($field_errors['course']) ? 'is-invalid' : '' ?>"
                                    required>
                                <option value="">Select Course</option>
                                <option value="BCA" <?= ($_POST['course'] ?? '') === 'BCA' ? 'selected' : '' ?>>BCA</option>
                                <option value="BBA" <?= ($_POST['course'] ?? '') === 'BBA' ? 'selected' : '' ?>>BBA</option>
                                <option value="BCom" <?= ($_POST['course'] ?? '') === 'BCom' ? 'selected' : '' ?>>BCom</option>
                                <option value="BSc" <?= ($_POST['course'] ?? '') === 'BSc' ? 'selected' : '' ?>>BSc</option>
                                <option value="BA" <?= ($_POST['course'] ?? '') === 'BA' ? 'selected' : '' ?>>BA</option>
                                <option value="MA" <?= ($_POST['course'] ?? '') === 'MA' ? 'selected' : '' ?>>MA</option>
                                <option value="MBA" <?= ($_POST['course'] ?? '') === 'MBA' ? 'selected' : '' ?>>MBA</option>
                                <option value="MCA" <?= ($_POST['course'] ?? '') === 'MCA' ? 'selected' : '' ?>>MCA</option>
                                <option value="Other" <?= ($_POST['course'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <?php if (isset($field_errors['course'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($field_errors['course']) ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit"
                                class="btn btn-sport w-100"
                                id="submitBtn"
                                <?= $loading ? 'disabled' : '' ?>>
                            <?php if ($loading): ?>
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Registering...
                            <?php else: ?>
                                <i class="bi bi-check-circle me-2"></i>Submit Registration
                            <?php endif; ?>
                        </button>

                        <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-3">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactInput = document.getElementById('contact_number');
    const usnInput = document.getElementById('usn_number');
    const nameInput = document.getElementById('student_name');
    const form = document.getElementById('registrationForm');

    // Contact Number Validation
    contactInput.addEventListener('input', function(e) {
        let value = this.value.replace(/[^0-9]/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        this.value = value;
        this.classList.remove('is-valid', 'is-invalid');
        if (value.length > 0) {
            if (value.length === 10) {
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
            }
        }
    });

    contactInput.addEventListener('paste', function(e) {
        e.preventDefault();
        let pastedData = e.clipboardData.getData('text');
        let numericOnly = pastedData.replace(/[^0-9]/g, '');
        if (numericOnly.length > 10) {
            numericOnly = numericOnly.substring(0, 10);
        }
        this.value = numericOnly;
        this.dispatchEvent(new Event('input'));
    });

    // USN Validation
    usnInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9\/]/g, '');
        this.classList.remove('is-valid', 'is-invalid');
        if (this.value.length >= 3) {
            this.classList.add('is-valid');
        } else if (this.value.length > 0) {
            this.classList.add('is-invalid');
        }
    });

    // Name Validation
    nameInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s\'.-]/g, '');
        this.classList.remove('is-valid', 'is-invalid');
        if (this.value.length >= 2) {
            this.classList.add('is-valid');
        } else if (this.value.length > 0) {
            this.classList.add('is-invalid');
        }
    });

    // Form Submission
    form.addEventListener('submit', function(e) {
        let isValid = true;

        if (nameInput.value.trim().length < 2) {
            nameInput.classList.add('is-invalid');
            isValid = false;
        }

        if (usnInput.value.trim().length < 3) {
            usnInput.classList.add('is-invalid');
            isValid = false;
        }

        const contactValue = contactInput.value.trim();
        if (contactValue.length !== 10 || !/^\d{10}$/.test(contactValue)) {
            contactInput.classList.add('is-invalid');
            isValid = false;
        }

        const courseSelect = document.getElementById('course');
        if (courseSelect.value === '') {
            courseSelect.classList.add('is-invalid');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        } else {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Registering...';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>