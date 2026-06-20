-- Migration: Add registration form fields to registrations table
-- Run this file to update your existing database

ALTER TABLE registrations
ADD COLUMN student_name VARCHAR(100) NOT NULL AFTER user_id,
ADD COLUMN usn_number VARCHAR(20) NOT NULL,
ADD COLUMN contact_number VARCHAR(15) NOT NULL,
ADD COLUMN course VARCHAR(50) NOT NULL;

-- Add sample data with the new fields (optional - for testing)
-- UPDATE registrations SET student_name = 'Test Student', usn_number = 'USN001', contact_number = '1234567890', course = 'BCA' WHERE student_name IS NULL;