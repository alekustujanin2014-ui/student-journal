<?php
// database/migrate_docker.php

$host = getenv('DB_HOST') ?: 'mysql';
$dbname = getenv('DB_DATABASE') ?: 'app_db';
$username = getenv('DB_USERNAME') ?: 'app_user';
$password = getenv('DB_PASSWORD') ?: 'app_password';

try {
    // Подключаемся к MySQL
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Создаем базу данных если не существует
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    
    // SQL для создания таблицы users
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        university VARCHAR(255),
        faculty VARCHAR(255),
        group_name VARCHAR(100),
        phone VARCHAR(50),
        city VARCHAR(100),
        birth_date DATE,
        gender ENUM('male', 'female', 'other') DEFAULT 'other',
        role_id int,
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_role_id (role_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Таблица users создана успешно!\n";
    
    // Добавляем тестового пользователя
    $password_hash = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (email, password_hash, name, role_id) 
        VALUES (:email, :password, :name, :role_id)
    ");
    
    $stmt->execute([
        'email' => 'test@example2.com',
        'password' => $password_hash,
        'name' => 'Тестовый Пользователь',
        'role_id' => 2 // admin
    ]);
    
    echo "✅ Тестовый пользователь добавлен (test@example.com / {$password_hash})\n";
    
} catch (PDOException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

