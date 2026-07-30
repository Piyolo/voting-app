-- =========================================================
-- School Voting System - PostgreSQL Schema (for Neon)
-- =========================================================
-- Run this once against your Neon database, e.g.:
--   psql "$DATABASE_URL" -f schema.sql
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,      -- store as password_hash()
    department VARCHAR(50),              -- e.g. 'FICT'
    year_level VARCHAR(20),              -- e.g. 'BSIT 1st year'
    has_voted INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS positions (
    id SERIAL PRIMARY KEY,
    title VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS candidates (
    id SERIAL PRIMARY KEY,
    position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE CASCADE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    photo VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS votes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    candidate_id INTEGER NOT NULL REFERENCES candidates(id) ON DELETE CASCADE,
    position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE CASCADE,
    voted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, position_id)   -- prevents voting twice for the same position
);

-- Helpful index for results page lookups.
CREATE INDEX IF NOT EXISTS idx_votes_candidate ON votes (candidate_id);
CREATE INDEX IF NOT EXISTS idx_candidates_position ON candidates (position_id);

-- =========================================================
-- Seed data
-- =========================================================

-- Positions
INSERT INTO positions (title) VALUES
    ('SSG Governor'),
    ('DSG Governor')
ON CONFLICT DO NOTHING;

-- Candidates (2 per position). Uses subqueries so IDs don't need to be
-- hardcoded (safe to re-run against a fresh database).
INSERT INTO candidates (position_id, first_name, last_name)
SELECT id, 'Maria', 'Santos' FROM positions WHERE title = 'SSG Governor'
ON CONFLICT DO NOTHING;

INSERT INTO candidates (position_id, first_name, last_name)
SELECT id, 'Juan', 'Dela Cruz' FROM positions WHERE title = 'SSG Governor'
ON CONFLICT DO NOTHING;

INSERT INTO candidates (position_id, first_name, last_name)
SELECT id, 'Angela', 'Reyes' FROM positions WHERE title = 'DSG Governor'
ON CONFLICT DO NOTHING;

INSERT INTO candidates (position_id, first_name, last_name)
SELECT id, 'Mark', 'Villanueva' FROM positions WHERE title = 'DSG Governor'
ON CONFLICT DO NOTHING;

-- Test user
-- Student ID:  987654321
-- Password:    password123
-- The hash below was generated with PHP's password_hash() (bcrypt) and is
-- verified working with password_verify('password123', ...).
INSERT INTO users (student_id, name, password, department, year_level, has_voted)
VALUES (
    '987654321',
    'Test Student',
    '$2b$10$JOCgvrU0FxenUOwzb8801e10VHHaUhQHm90mhGvspJiiPcKXN4CBa',
    'FICT',
    'BSIT 1st year',
    0
)
ON CONFLICT (student_id) DO NOTHING;

-- =========================================================
-- To add more students later, generate a hash with:
--   php -r "echo password_hash('theirpassword', PASSWORD_DEFAULT), PHP_EOL;"
-- and insert:
--   INSERT INTO users (student_id, name, password, department, year_level)
--   VALUES ('STUDENT_ID', 'Full Name', 'PASTE_HASH_HERE', 'FICT', 'BSIT 2nd year');
-- =========================================================
