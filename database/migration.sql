-- ============================================================
-- Migration: Add table_number to orders table
-- ============================================================
ALTER TABLE orders 
ADD COLUMN table_number VARCHAR(10) DEFAULT NULL AFTER customer_phone;
