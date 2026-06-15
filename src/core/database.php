<?php
// core/database.php

function create_database_connection(array $config, array $logger): PDO
{
    $required = ['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($required as $key) {
        if (!isset($config[$key])) {
            throw new RuntimeException("Missing database config: $key");
        }
    }
    
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=utf8mb4",
        $config['DB_HOST'],
        $config['DB_NAME']
    );
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    try {
        $pdo = new PDO(
            $dsn,
            $config['DB_USERNAME'],
            $config['DB_PASSWORD'],
            $options
        );
        
        log_debug($logger, "Database connected", [
            'host' => $config['DB_HOST'],
            'database' => $config['DB_NAME']
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        log_error($logger, "Database connection failed", [
            'error' => $e->getMessage()
        ]);
        throw new RuntimeException("Database connection failed: " . $e->getMessage());
    }
}
function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new RuntimeException("Query failed: " . $e->getMessage());
    }
}
function db_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = db_query($pdo, $sql, $params);
    $result = $stmt->fetch();
    return $result ?: null;
}

function db_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = db_query($pdo, $sql, $params);
    return $stmt->fetchAll();
}

function db_insert(PDO $pdo, string $table, array $data): int
{
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    db_query($pdo, $sql, $data);
    
    return (int) $pdo->lastInsertId();
}

function db_update(PDO $pdo, string $table, array $data, array $where): int
{
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
    $where_clause = implode(' AND ', array_map(fn($k) => "$k = :where_$k", array_keys($where)));
    
    $params = array_merge($data, array_combine(
        array_map(fn($k) => "where_$k", array_keys($where)),
        array_values($where)
    ));
    
    $sql = "UPDATE $table SET $set WHERE $where_clause";
    $stmt = db_query($pdo, $sql, $params);
    
    return $stmt->rowCount();
}

function db_delete(PDO $pdo, string $table, array $where): int
{
    $where_clause = implode(' AND ', array_map(fn($k) => "$k = :$k", array_keys($where)));
    $sql = "DELETE FROM $table WHERE $where_clause";
    
    $stmt = db_query($pdo, $sql, $where);
    return $stmt->rowCount();
}

function db_begin(PDO $pdo): void
{
    $pdo->beginTransaction();
}

function db_commit(PDO $pdo): void
{
    $pdo->commit();
}

function db_rollback(PDO $pdo): void
{
    $pdo->rollBack();
}

function db_transaction(PDO $pdo, callable $callback)
{
    try {
        db_begin($pdo);
        $result = $callback($pdo);
        db_commit($pdo);
        return $result;
    } catch (Exception $e) {
        db_rollback($pdo);
        throw $e;
    }
}