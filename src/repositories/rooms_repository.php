<?php

function get_all_rooms(PDO $pdo, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "SELECT * FROM rooms ORDER BY building, number LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting rooms", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_total_rooms_count(PDO $pdo, array $logger): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM rooms");
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        log_error($logger, "Error getting rooms count", ['error' => $e->getMessage()]);
        return 0;
    }
}

function search_rooms(PDO $pdo, string $search, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT * FROM rooms 
            WHERE number LIKE '%{$search}%' 
               OR building LIKE '%{$search}%' 
               OR type LIKE '%{$search}%'
               OR description LIKE '%{$search}%'
            ORDER BY building, number 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error searching rooms", ['error' => $e->getMessage()]);
        return [];
    }
}

function count_search_rooms(PDO $pdo, string $search, array $logger): int
{
    try {
        $sql = "
            SELECT COUNT(*) as total FROM rooms 
            WHERE number LIKE '%{$search}%' 
               OR building LIKE '%{$search}%' 
               OR type LIKE '%{$search}%'
               OR description LIKE '%{$search}%'
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        log_error($logger, "Error counting search rooms", ['error' => $e->getMessage()]);
        return 0;
    }
}

function get_room_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting room by id", ['error' => $e->getMessage()]);
        return null;
    }
}

function create_room(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO rooms (number, building, capacity, type, description, has_computer, has_projector, has_board, is_active) 
                VALUES (:number, :building, :capacity, :type, :description, :has_computer, :has_projector, :has_board, :is_active)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'number' => $data['number'],
            'building' => $data['building'] ?? null,
            'capacity' => $data['capacity'] ?? 0,
            'type' => $data['type'] ?? 'lecture',
            'description' => $data['description'] ?? null,
            'has_computer' => $data['has_computer'] ?? 0,
            'has_projector' => $data['has_projector'] ?? 0,
            'has_board' => $data['has_board'] ?? 1,
            'is_active' => $data['is_active'] ?? 1
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating room", ['error' => $e->getMessage()]);
        return null;
    }
}

function update_room(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['number', 'building', 'capacity', 'type', 'description', 'has_computer', 'has_projector', 'has_board', 'is_active'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE rooms SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating room", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_room(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting room", ['error' => $e->getMessage()]);
        return false;
    }
}