-- Enable MySQL 9.2+ vector functionality
-- This file sets up native VECTOR type support without fallbacks

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

-- MySQL 9.2 has native VECTOR type - insert a test vector
INSERT INTO vector_test (description, embedding) 
VALUES ('test vector', STRING_TO_VECTOR('[0.1, 0.2, 0.3]'));

-- Test vector distance functions
SELECT DISTANCE(
  STRING_TO_VECTOR('[1.0, 2.0, 3.0]'),
  STRING_TO_VECTOR('[1.0, 2.0, 3.0]'),
  'COSINE'
) as exact_match_distance;

SELECT DISTANCE(
  STRING_TO_VECTOR('[1.0, 2.0, 3.0]'),
  STRING_TO_VECTOR('[2.0, 3.0, 4.0]'),
  'COSINE'
) as similar_vectors_distance;

-- Display configuration message
SELECT 'MySQL Vector Database Setup Complete' as message; 