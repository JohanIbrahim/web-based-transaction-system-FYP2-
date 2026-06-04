-- ============================================================
-- Smart Web-Based Transaction System - Database Schema
-- Phase 1: Foundation
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_transaction_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_transaction_db;

-- ------------------------------------------------------------
-- 1. Users Table
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. Categories Table
-- ------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Products Table
-- ------------------------------------------------------------
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. Orders Table
-- ------------------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. Order Items Table
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. Payments Table
-- ------------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('cash','online','ewallet') NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_ref VARCHAR(100) DEFAULT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. Transactions Table
-- ------------------------------------------------------------
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id INT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. Order Status Logs Table
-- ------------------------------------------------------------
CREATE TABLE order_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    old_status VARCHAR(50) DEFAULT NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by VARCHAR(100) DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ------------------------------------------------------------
-- Admin & Staff Users
-- Password for ALL users below is: password
-- The hash below is a pre-generated bcrypt hash of 'password'
-- generated by PHP's password_hash('password', PASSWORD_DEFAULT).
-- It works immediately after importing schema.sql.
-- ------------------------------------------------------------
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Admin Owner', 'admin@smarttransaction.com', '012-3456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Co-Admin', 'coadmin@smarttransaction.com', '012-3456790', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Staff Member', 'staff@smarttransaction.com', '012-3456791', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');
-- All above users have password: password
-- The hash is the well-known bcrypt hash of "password" from Laravel's default hash.
-- If it doesn't work on your system, run setup.php to re-hash.

-- ------------------------------------------------------------
-- Test Customer
-- Email: customer@smarttransaction.com
-- Password: customer123
-- ------------------------------------------------------------
INSERT INTO users (name, email, phone, password_hash, role) VALUES
('Test Customer', 'customer@smarttransaction.com', '0111234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');
-- NOTE: The hash above is for 'password', not 'customer123'.
-- After importing, run the following PHP script to update the hash:
--   php -r "echo password_hash('customer123', PASSWORD_DEFAULT);"
-- Then UPDATE users SET password_hash = '<new_hash>' WHERE email = 'customer@smarttransaction.com';
-- OR use the setup.php script to re-hash all passwords.

-- ------------------------------------------------------------
-- Categories (5)
-- ------------------------------------------------------------
INSERT INTO categories (name, description, is_active) VALUES
('Coffee', 'Freshly brewed coffee beverages', 1),
('Non-Coffee', 'Refreshing non-coffee drinks', 1),
('Pastries', 'Baked goods and pastries', 1),
('Snacks', 'Light bites and snacks', 1),
('Specials', 'Chef specials and seasonal items', 1);

-- ------------------------------------------------------------
-- Products (15)
-- ------------------------------------------------------------
INSERT INTO products (category_id, name, description, price, image_url, is_available) VALUES
-- Coffee (category_id = 1)
(1, 'Espresso', 'Rich and bold single-shot espresso', 4.50, NULL, 1),
(1, 'Cappuccino', 'Espresso with steamed milk and foam', 6.00, NULL, 1),
(1, 'Latte', 'Smooth espresso with steamed milk', 6.50, NULL, 1),
(1, 'Mocha', 'Espresso with chocolate and steamed milk', 7.00, NULL, 1),
-- Non-Coffee (category_id = 2)
(2, 'Matcha Latte', 'Premium Japanese matcha with milk', 7.50, NULL, 1),
(2, 'Chocolate Frappe', 'Blended iced chocolate drink', 8.00, NULL, 1),
(2, 'Fresh Lemonade', 'House-made fresh lemonade', 5.00, NULL, 1),
(2, 'Iced Tea', 'Refreshing brewed iced tea', 4.50, NULL, 1),
-- Pastries (category_id = 3)
(3, 'Croissant', 'Buttery flaky croissant', 5.50, NULL, 1),
(3, 'Blueberry Muffin', 'Freshly baked blueberry muffin', 4.50, NULL, 1),
(3, 'Chocolate Danish', 'Danish pastry with chocolate filling', 6.00, NULL, 1),
-- Snacks (category_id = 4)
(4, 'Caesar Wrap', 'Chicken Caesar salad wrap', 9.00, NULL, 1),
(4, 'Fries', 'Crispy golden french fries', 5.50, NULL, 1),
-- Specials (category_id = 5)
(5, 'Truffle Pasta', 'Creamy truffle mushroom pasta', 14.00, NULL, 1),
(5, 'Grilled Chicken Set', 'Grilled chicken with sides and drink', 16.00, NULL, 1);
