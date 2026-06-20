<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<script>
// Cookie check - run before page loads
if (!navigator.cookieEnabled) {
    document.write('<div style="background:#ffcccc;color:#cc0000;padding:20px;text-align:center;font-family:sans-serif;"><h2>Cookies Disabled</h2><p>Please enable cookies in your browser settings to use this site.</p><p><a href="login.php">Click here to try again</a></p></div>');
    throw new Error('Cookies required');
}
</script>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SportsZone - Sports Event Management System</title>
    <meta name="description" content="SportsZone - Modern sports event registration platform">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Rockwell:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0e27;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: rgba(10, 14, 39, 0.95); backdrop-filter: blur(10px);">
  <div class="container py-2">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
        <span class="bg-warning text-dark rounded me-2" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-trophy-fill fs-6"></i>
        </span>
        <span class="fw-bold">SportsZone</span>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-center gap-2">
        <?php if(isset($_SESSION['user_id'])): ?>
          <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_events.php">Events</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_registrations.php">Registrations</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" style="border-radius: 12px;">
                    <li><a class="dropdown-item px-3" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item px-3 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="my_registrations.php">My Events</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg" style="border-radius: 12px;">
                    <li><a class="dropdown-item px-3" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                    <li><a class="dropdown-item px-3" href="my_registrations.php"><i class="bi bi-calendar-check me-2"></i>My Registrations</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item px-3 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </li>
          <?php endif; ?>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="login.php">Login</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-warning fw-semibold" href="register.php" style="border-radius: 10px;">
                <i class="bi bi-person-plus me-1"></i>Register
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main>