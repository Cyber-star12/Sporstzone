CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  usn VARCHAR(20) NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sport_type VARCHAR(100) NOT NULL,
  event_date DATE NOT NULL,
  event_time TIME NOT NULL,
  venue VARCHAR(150) NOT NULL,
  max_slots INT NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_id INT NOT NULL,
  student_name VARCHAR(100) NOT NULL,
  usn_number VARCHAR(20) NOT NULL,
  contact_number VARCHAR(15) NOT NULL,
  course VARCHAR(50) NOT NULL,
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_registration (user_id, event_id),
  CONSTRAINT fk_reg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- NOTE: To create an admin, register with the email defined in config/admin_config.php
-- The system will automatically assign admin role based on ADMIN_EMAIL setting

-- Sample events
INSERT INTO events (name, sport_type, event_date, event_time, venue, max_slots, description) VALUES
('Badminton Singles', 'Badminton', '2026-05-18', '11:00:00', 'Indoor Stadium', 16, 'Singles badminton tournament.'),
('100m Sprint', 'Athletics', '2026-05-20', '08:30:00', 'Athletics Track', 20, 'Fast-paced athletics sprint event.');