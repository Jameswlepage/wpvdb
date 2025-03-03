-- Enable MySQL 9.0+ vector functionality
-- This file sets up native VECTOR type support

-- Make sure we're using the WordPress database
USE wordpress;

-- Display MySQL version for confirmation
SELECT VERSION() as mysql_version;

-- Create a test vector table to verify functionality 
CREATE TABLE IF NOT EXISTS vector_test (
  id INT AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  embedding VECTOR(1536) NULL
);

-- MySQL 9.0+ has native VECTOR type - verify it works
-- This will insert a test vector or fail gracefully if vector support isn't available
SET @mysql_version = (SELECT VERSION());
SET @has_vector_support = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                           WHERE TABLE_SCHEMA='wordpress' AND TABLE_NAME='vector_test' 
                           AND COLUMN_NAME='embedding' AND DATA_TYPE='vector');

-- Only insert test data if vector support is available
SET @sql = IF(@has_vector_support > 0, 
              'INSERT INTO vector_test (description, embedding) VALUES (\'test vector\', VECTOR[0.1, 0.2, 0.3])',
              'SELECT \'Vector support not available\' as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display configuration message
SELECT 'MySQL Vector Database Setup Complete' as message; 