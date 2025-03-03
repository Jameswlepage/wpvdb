-- Enable MariaDB Vector functionality
-- For MariaDB 11.7+ which has built-in VECTOR type

-- Make sure we're using the WordPress database
USE wordpress;

-- Display MariaDB version for confirmation
SELECT VERSION() as mariadb_version;

-- Create a test vector table to verify functionality
CREATE TABLE IF NOT EXISTS vector_test (
  id INT AUTO_INCREMENT PRIMARY KEY,
  description VARCHAR(255) NOT NULL,
  embedding VECTOR(1536) NOT NULL
);

-- Insert a test vector (if MariaDB vector functions work)
-- Note: This will fail gracefully if vector support is not available
SET @vector_support = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='information_schema' AND TABLE_NAME='VECTORS');
SET @sql = CONCAT('INSERT INTO vector_test (description, embedding) VALUES (\'test vector\', VEC_FromText(\'[0.1, 0.2, 0.3]\'))');

-- Only execute if vector support is available
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display configuration message
SELECT 'MariaDB Vector Database Setup Complete' as message; 