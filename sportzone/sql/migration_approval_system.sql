-- =====================================================
-- SPORTZONE Approval System Migration
-- Run this SQL to enable approval features
-- =====================================================

-- Step 1: Add approval system columns to registrations table
ALTER TABLE registrations
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending' AFTER registered_at,
ADD COLUMN IF NOT EXISTS approved_by INT NULL,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Step 2: Create index for faster queries (if not exists)
-- Note: MySQL doesn't support IF NOT EXISTS for indexes, so we'll handle this in PHP

-- Step 3: Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_name VARCHAR(100) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_id INT NULL,
    target_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Step 4: Update existing pending registrations
UPDATE registrations SET approval_status = 'pending' WHERE approval_status IS NULL;

-- Step 5: Create view for approved registrations (for export)
CREATE OR REPLACE VIEW v_approved_registrations AS
SELECT
    r.id as registration_id,
    u.name as student_name,
    u.usn as student_usn,
    u.email as student_email,
    e.name as event_name,
    e.sport_type,
    e.event_date,
    e.venue,
    r.registered_at,
    r.approval_status,
    r.approved_at,
    a.name as approved_by_name
FROM registrations r
JOIN users u ON r.user_id = u.id
JOIN events e ON r.event_id = e.id
LEFT JOIN users a ON r.approved_by = a.id
WHERE r.approval_status = 'approved';

-- Step 6: Create view for registration statistics
CREATE OR REPLACE VIEW v_registration_stats AS
SELECT
    e.id as event_id,
    e.name as event_name,
    e.max_slots,
    COUNT(r.id) as total_registrations,
    SUM(CASE WHEN r.approval_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN r.approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN r.approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN r.approval_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
    (e.max_slots - SUM(CASE WHEN r.approval_status = 'approved' THEN 1 ELSE 0 END)) as available_slots
FROM events e
LEFT JOIN registrations r ON e.id = r.event_id
GROUP BY e.id;

-- Verify migration success
SELECT 'Migration completed!' as status;
SELECT COUNT(*) as total_registrations FROM registrations;
SELECT COUNT(*) as pending_registrations FROM registrations WHERE approval_status = 'pending';