-- =====================================================
-- SPORTZONE Complete Registration Fix
-- Run this in phpMyAdmin SQL tab
-- =====================================================

-- Step 1: Add student_name column if not exists
-- (MySQL doesn't support IF NOT EXISTS for ADD COLUMN, so we use a workaround)
-- Run this only if student_name column is missing

-- To add student_name:
-- ALTER TABLE registrations ADD COLUMN student_name VARCHAR(100) NOT NULL AFTER user_id;

-- Step 2: Add usn_number column
-- ALTER TABLE registrations ADD COLUMN usn_number VARCHAR(20) NOT NULL;

-- Step 3: Add contact_number column
-- ALTER TABLE registrations ADD COLUMN contact_number VARCHAR(15) NOT NULL;

-- Step 4: Add course column
-- ALTER TABLE registrations ADD COLUMN course VARCHAR(50) NOT NULL;

-- Step 5: Add approval_status column
-- ALTER TABLE registrations ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending';

-- Step 6: Add approval columns
-- ALTER TABLE registrations ADD COLUMN approved_by INT NULL;
-- ALTER TABLE registrations ADD COLUMN approved_at TIMESTAMP NULL;
-- ALTER TABLE registrations ADD COLUMN rejection_reason TEXT NULL;

-- =====================================================
-- SAFE VERSION - Run each statement individually
-- =====================================================

-- Check if columns exist and add if missing
-- Note: Run these one at a time in phpMyAdmin

/*
-- Add student_name
ALTER TABLE registrations ADD COLUMN student_name VARCHAR(100) NOT NULL;

-- Add usn_number
ALTER TABLE registrations ADD COLUMN usn_number VARCHAR(20) NOT NULL;

-- Add contact_number
ALTER TABLE registrations ADD COLUMN contact_number VARCHAR(15) NOT NULL;

-- Add course
ALTER TABLE registrations ADD COLUMN course VARCHAR(50) NOT NULL;

-- Add approval_status
ALTER TABLE registrations ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending';

-- Add approved_by
ALTER TABLE registrations ADD COLUMN approved_by INT NULL;

-- Add approved_at
ALTER TABLE registrations ADD COLUMN approved_at TIMESTAMP NULL;

-- Add rejection_reason
ALTER TABLE registrations ADD COLUMN rejection_reason TEXT NULL;

-- Add unique index
ALTER TABLE registrations ADD UNIQUE INDEX idx_user_event (user_id, event_id);

-- Add usn index
ALTER TABLE registrations ADD INDEX idx_usn_event (usn_number, event_id);
*/