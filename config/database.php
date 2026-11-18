<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' OR strpos($line, '#') === 0 OR strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$host   = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'utilisense_db';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            role_id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB;
    ");

    if ($pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO roles (role_id, role_name) VALUES
            (1, 'Administrator'),
            (2, 'Staff'),
            (3, 'Consumer')
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL DEFAULT 3,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('role_id', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT NOT NULL DEFAULT 3 AFTER user_id");
    if (!in_array('full_name', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NOT NULL DEFAULT 'User' AFTER role_id");
    if (!in_array('password_hash', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL AFTER email");
    if (!in_array('status', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
    if (!in_array('last_login', $columns)) $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_utilities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            utility_type VARCHAR(50) NOT NULL,
            provider_name VARCHAR(100) NOT NULL,
            rate_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_utility (user_id, utility_type),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS utility_bills (
            bill_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            utility_type VARCHAR(50) NOT NULL,
            provider_name VARCHAR(100) NOT NULL,
            previous_reading DECIMAL(10,2) DEFAULT 0.00,
            present_reading DECIMAL(10,2) DEFAULT 0.00,
            consumption DECIMAL(10,2) DEFAULT 0.00,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE NOT NULL,
            status ENUM('PENDING', 'PAID', 'OVERDUE') DEFAULT 'PENDING',
            encoded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action_type VARCHAR(50) NOT NULL DEFAULT 'EVENT',
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    $auditCols = $pdo->query("SHOW COLUMNS FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('action_type', $auditCols)) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN action_type VARCHAR(50) NOT NULL DEFAULT 'EVENT'");
    }
    if (!in_array('description', $auditCols)) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN description TEXT NULL");
    }
    if (!in_array('ip_address', $auditCols)) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL");
    }
    if (!in_array('created_at', $auditCols)) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

} catch (PDOException $e) {
    die("Database Error: " . htmlspecialchars($e->getMessage()));
}
$pdo->exec("
        CREATE TABLE IF NOT EXISTS bill_payments (
            payment_id INT AUTO_INCREMENT PRIMARY KEY,
            bill_id INT NOT NULL,
            user_id INT NOT NULL,
            payment_channel VARCHAR(80) NOT NULL,
            account_identifier VARCHAR(100) NULL,
            reference_no VARCHAR(80) NOT NULL,
            amount_paid DECIMAL(10,2) NOT NULL,
            payment_mode ENUM('ONLINE_INSTANT', 'WALK_IN_BRANCH') DEFAULT 'ONLINE_INSTANT',
            paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bill_id) REFERENCES utility_bills(bill_id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
function log_activity($pdo, $user_id, $action, $description) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action_type, description, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $description, $ip]);
    } catch (Exception $e) {}
}
?>