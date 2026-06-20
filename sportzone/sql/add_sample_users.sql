-- =====================================================
-- SAMPLE USERS FOR TESTING
-- Run this SQL to create test users
-- =====================================================

-- Clear existing test users (optional)
-- DELETE FROM users WHERE email IN ('teacher@gmail.com', 'student@test.com');

-- =====================================================
-- ADMIN USER
-- Email: teacher@gmail.com
-- Password: admin123
-- =====================================================
INSERT INTO users (name, email, usn, password, role) VALUES
('Teacher Admin', 'teacher@gmail.com', 'ADMIN001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE name = 'Teacher Admin';

-- =====================================================
-- STUDENT USERS
-- =====================================================
INSERT INTO users (name, email, usn, password, role) VALUES
('John Smith', 'john@test.com', 'PU2024001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Jane Doe', 'jane@test.com', 'PU2024002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Mike Johnson', 'mike@test.com', 'PU2024003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =====================================================
-- VERIFY USERS
-- =====================================================
SELECT 'Admin User:' as Info, name, email, role FROM users WHERE role = 'admin';
SELECT 'Student Users:' as Info, name, usn, role FROM users WHERE role = 'student';

-- =====================================================
-- PASSWORD FOR ALL TEST USERS IS: admin123
-- =====================================================