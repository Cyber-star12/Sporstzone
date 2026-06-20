-- Add performance indexes
-- Run this script to improve query performance

USE sportzone;

-- Index on registrations table for faster user lookups
CREATE INDEX idx_registrations_user_id ON registrations(user_id);

-- Index on registrations table for faster event lookups
CREATE INDEX idx_registrations_event_id ON registrations(event_id);

-- Index on events table for faster date-based queries
CREATE INDEX idx_events_event_date ON events(event_date);

-- Index on users table for faster email lookups
CREATE INDEX idx_users_email ON users(email);

-- Index on users table for faster USN lookups
CREATE INDEX idx_users_usn ON users(usn);

-- Composite index for user event registration check
CREATE UNIQUE INDEX idx_unique_user_event ON registrations(user_id, event_id);