-- =====================================================
-- DATABASE SETUP FOR TRAVEL MEMORY APPLICATION
-- =====================================================

-- Step 1: Create Database
CREATE DATABASE IF NOT EXISTS travel_memory_db;

-- Step 2: Use the database
USE travel_memory_db;

-- Step 3: Create table for storing travel memories
CREATE TABLE IF NOT EXISTS travel_memories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    memory TEXT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Verify table creation
SHOW TABLES;

-- Step 5: Check table structure
DESCRIBE travel_memories;

-- =====================================================
-- USEFUL QUERIES FOR TESTING
-- =====================================================

-- View all records
-- SELECT * FROM travel_memories ORDER BY created_at DESC;

-- Count total records
-- SELECT COUNT(*) as total_memories FROM travel_memories;

-- View recent 10 records
-- SELECT id, name, location, created_at FROM travel_memories ORDER BY created_at DESC LIMIT 10;

-- Search by location
-- SELECT * FROM travel_memories WHERE location LIKE '%Paris%';

-- Delete all records (if needed for testing)
-- TRUNCATE TABLE travel_memories;

-- Drop table (if you want to recreate)
-- DROP TABLE IF EXISTS travel_memories;

-- Drop database (use with caution!)
-- DROP DATABASE IF EXISTS travel_memory_db;