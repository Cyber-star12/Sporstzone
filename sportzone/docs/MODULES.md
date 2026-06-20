# SportZone - Project Modules Documentation

**Project:** Sports Event Management System  
**Total Modules:** 9  
**Date Generated:** 2026-06-03

---

## Module 1: Authentication Module

**Purpose:** Handle user login, registration, and logout functionality.

| File | Description |
|------|-------------|
| `login.php` | User login page with email/password authentication |
| `register.php` | New user registration form |
| `logout.php` | Destroy session and redirect to login |

---

## Module 2: User Dashboard Module

**Purpose:** Display user-specific information and quick actions.

| File | Description |
|------|-------------|
| `dashboard.php` | User dashboard showing upcoming events, registered events, and quick stats |
| `my_registrations.php` | List of all events user has registered for with status |

---

## Module 3: Event Management Module (Admin)

**Purpose:** CRUD operations for sports events.

| File | Description |
|------|-------------|
| `add_event.php` | Form to create new sports events |
| `edit_event.php` | Edit existing event details |
| `manage_events.php` | List/manage all events (admin view) |
| `delete_event.php` | Delete events with confirmation |

---

## Module 4: Registration Module

**Purpose:** Handle event registrations, approvals, and tracking.

| File | Description |
|------|-------------|
| `register_event.php` | User registration form for specific event |
| `process_registration.php` | Backend processing of registration data |
| `manage_registrations.php` | Admin view to manage all registrations |
| `view_registrations.php` | View registration details |
| `cancel_registration.php` | Allow users to cancel their registration |
| `get_registration_details.php` | AJAX endpoint for registration details |
| `export_registrations.php` | Export registrations to CSV format |

---

## Module 5: Admin Module

**Purpose:** Administrative functions and monitoring.

| File | Description |
|------|-------------|
| `admin_dashboard.php` | Admin dashboard with statistics and management shortcuts |
| `admin_activity.php` | Log/track admin activities |

---

## Module 6: Utility/Fix Scripts Module

**Purpose:** Database maintenance and auto-correction scripts.

| File | Description |
|------|-------------|
| `auto_fix.php` | Automated database fixes |
| `fix_registration_db.php` | Registration-specific database corrections |

---

## Module 7: Configuration Module

**Purpose:** Application configuration and database connection.

| File | Description |
|------|-------------|
| `config/db.php` | Database connection (PDO) - InfinityFree hosting |
| `config/admin_config.php` | Admin-specific configurations |

---

## Module 8: Includes Module

**Purpose:** Reusable components included across pages.

| File | Description |
|------|-------------|
| `includes/auth.php` | Authentication checks and session management |
| `includes/header.php` | Common HTML header, navigation menu |
| `includes/footer.php` | Common footer with copyright |

---

## Module 9: Home Module

**Purpose:** Entry point and landing page.

| File | Description |
|------|-------------|
| `index.php` | Landing page with featured events and announcements |

---

## Asset Files

| Path | Description |
|------|-------------|
| `assets/css/style.css` | Global stylesheet |
| `assets/js/main.js` | JavaScript functions |

---

## SQL Files

| File | Description |
|------|-------------|
| `sql/sportzone.sql` | Main database schema |
| `sql/update_registrations.sql` | Registration system updates |
| `sql/update_approval_system.sql` | Approval workflow updates |
| `sql/add_indexes.sql` | Database performance indexes |
| `sql/complete_fix.sql` | Comprehensive database fixes |

---

## Summary

```
SportZone Project Structure
├── 9 Core Modules
│   ├── Authentication (3 files)
│   ├── User Dashboard (2 files)
│   ├── Event Management (4 files)
│   ├── Registration (7 files)
│   ├── Admin (2 files)
│   ├── Utility/Fix Scripts (2 files)
│   ├── Configuration (2 files)
│   ├── Includes (3 files)
│   └── Home (1 file)
├── Assets (CSS + JS)
└── SQL (Database Scripts)
```

---

*Generated for SportZone BCA Project*