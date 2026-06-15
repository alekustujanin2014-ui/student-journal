<?php

/**
 * Получить все предметы
 */
function get_all_subjects(PDO $pdo, array $logger, bool $only_active = false): array
{
    try {
        $sql = "SELECT * FROM subjects";
        if ($only_active) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting subjects", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Получить предмет по ID
 */
function get_subject_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting subject", ['error' => $e->getMessage()]);
        return null;
    }
}
function search_subjects(PDO $pdo, string $search, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT * FROM subjects 
            WHERE name LIKE '%{$search}%'
               OR code LIKE '%{$search}%'
               OR short_name LIKE '%{$search}%'
            ORDER BY name 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error searching subjects", ['error' => $e->getMessage(), 'search' => $search]);
        return [];
    }
}
/**
 * Создать предмет
 */
function create_subject(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO subjects (code, name, short_name, description, hours, semester, specialty, is_active) 
                VALUES (:code, :name, :short_name, :description, :hours, :semester, :specialty, :is_active)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'description' => $data['description'] ?? null,
            'hours' => $data['hours'] ?? 0,
            'semester' => $data['semester'] ?? 1,
            'specialty' => $data['specialty'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating subject", ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Обновить предмет
 */
function update_subject(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['code', 'name', 'short_name', 'description', 'hours', 'semester', 'specialty', 'is_active'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE subjects SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating subject", ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Удалить предмет
 */
function delete_subject(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting subject", ['error' => $e->getMessage()]);
        return false;
    }
}