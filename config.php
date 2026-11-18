<?php

session_start();

$host = 'localhost';
$dbname = 'utilisense_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $needsRebuild = false;
    try {
        $pdo->query("SELECT rate_per_unit FROM utility_types LIMIT 1");
        $pdo->query("SELECT action FROM audit_logs LIMIT 1");
        $pdo->query("SELECT account_number FROM users LIMIT 1");
        $pdo->query("SELECT sub_id FROM consumer_subscriptions LIMIT 1"); // Check for new table
    } catch (PDOException $e) {
        $needsRebuild = true;
    }

    if ($needsRebuild) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS audit_logs, consumer_subscriptions, payments, bills, utility_types, users, roles");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $pdo->exec("CREATE TABLE roles (role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) NOT NULL UNIQUE)");
        $pdo->exec("INSERT INTO roles (role_name) VALUES ('admin'), ('staff'), ('consumer')");

        $pdo->exec("CREATE TABLE users (
            user_id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE, account_number VARCHAR(30) UNIQUE NULL, password_hash VARCHAR(255) NOT NULL,
            account_status ENUM('active', 'inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (role_id) REFERENCES roles(role_id) ON UPDATE CASCADE
        )");

        $pdo->exec("CREATE TABLE utility_types (
            utility_id INT AUTO_INCREMENT PRIMARY KEY, utility_name VARCHAR(50) NOT NULL UNIQUE,
            unit_label VARCHAR(20) NOT NULL, rate_per_unit DECIMAL(10,2) NOT NULL
        )");
        $pdo->exec("INSERT INTO utility_types (utility_name, unit_label, rate_per_unit) VALUES
            ('Electricity', 'kWh', 11.50), ('Water', 'm³', 35.00), ('Internet', 'Mbps Plan', 1500.00)");

        $pdo->exec("CREATE TABLE consumer_subscriptions (
            sub_id INT AUTO_INCREMENT PRIMARY KEY,
            consumer_id INT NOT NULL,
            utility_id INT NOT NULL,
            FOREIGN KEY (consumer_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (utility_id) REFERENCES utility_types(utility_id) ON DELETE CASCADE,
            UNIQUE (consumer_id, utility_id)
        )");

        $pdo->exec("CREATE TABLE bills (
            bill_id INT AUTO_INCREMENT PRIMARY KEY, consumer_id INT NOT NULL, utility_id INT NOT NULL,
            billing_period VARCHAR(50) NOT NULL, consumption_units DECIMAL(10,2) NOT NULL, amount_due DECIMAL(10,2) NOT NULL,
            due_date DATE NOT NULL, status ENUM('unpaid', 'paid') DEFAULT 'unpaid', created_by INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (consumer_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (utility_id) REFERENCES utility_types(utility_id) ON UPDATE CASCADE
        )");

        $pdo->exec("CREATE TABLE payments (
            payment_id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL UNIQUE, consumer_id INT NOT NULL,
            amount_paid DECIMAL(10,2) NOT NULL, payment_method ENUM('GCash', 'Maya', 'Credit/Debit Card', 'Bank Transfer') NOT NULL,
            reference_number VARCHAR(100) NOT NULL UNIQUE, paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE,
            FOREIGN KEY (consumer_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");

        $pdo->exec("CREATE TABLE audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(100) NOT NULL,
            details TEXT NOT NULL, ip_address VARCHAR(45) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
        )");


        $demoHash = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, account_number, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([1, 'UtiliSense Admin', 'admin@utilisense.com', 'ADM-0001', $demoHash]);
        $stmt->execute([2, 'UtiliSense Billing Staff', 'staff@utilisense.com', 'STF-0001', $demoHash]);
        $stmt->execute([3, 'Juan Dela Cruz', 'consumer@utilisense.com', 'UTS-1001', $demoHash]);

        $pdo->exec("INSERT INTO consumer_subscriptions (consumer_id, utility_id) VALUES (3, 1), (3, 2), (3, 3)");

       
        $pdo->exec("INSERT INTO bills (consumer_id, utility_id, billing_period, consumption_units, amount_due, due_date, status, created_by) VALUES
            (3, 1, 'November 2026', 185.50, 2133.25, '2026-11-25', 'unpaid', 2),
            (3, 2, 'November 2026', 24.00, 840.00, '2026-11-25', 'unpaid', 2),
            (3, 3, 'November 2026', 1.00, 1500.00, '2026-11-25', 'unpaid', 2)");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function logActivity($pdo, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, ?, ?, ?)");
        $stmt->execute([$action, $details . " (ID: $user_id)", $ip]);
    }
}

function requireRole($allowed_roles = []) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_name'])) {
        header("Location: login.php"); exit();
    }
    if (!in_array($_SESSION['role_name'], $allowed_roles)) {
        die("403 Access Denied");
    }
}
?>