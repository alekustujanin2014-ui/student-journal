<?php

function get_all_teachers(PDO $pdo, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "SELECT * FROM teachers ORDER BY last_name LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting teachers", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_total_teachers_count(PDO $pdo, array $logger): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM teachers");
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        log_error($logger, "Error getting teachers count", ['error' => $e->getMessage()]);
        return 0;
    }
}

function search_teachers(PDO $pdo, string $search, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT * FROM teachers 
            WHERE name LIKE '%{$search}%' 
               OR last_name LIKE '%{$search}%' 
               OR patronymic LIKE '%{$search}%'
               OR email LIKE '%{$search}%'
            ORDER BY last_name 
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error searching teachers", ['error' => $e->getMessage()]);
        return [];
    }
}

function count_search_teachers(PDO $pdo, string $search, array $logger): int
{
    try {
        $sql = "
            SELECT COUNT(*) as total FROM teachers 
            WHERE name LIKE '%{$search}%' 
               OR last_name LIKE '%{$search}%' 
               OR patronymic LIKE '%{$search}%'
               OR email LIKE '%{$search}%'
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        log_error($logger, "Error counting search teachers", ['error' => $e->getMessage()]);
        return 0;
    }
}

function get_teacher_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting teacher by id", ['error' => $e->getMessage()]);
        return null;
    }
}

function create_teacher(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO teachers (name, last_name, patronymic, email, phone) 
                VALUES (:name, :last_name, :patronymic, :email, :phone)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'patronymic' => $data['patronymic'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating teacher", ['error' => $e->getMessage()]);
        return null;
    }
}

function update_teacher(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['name', 'last_name', 'patronymic', 'email', 'phone'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE teachers SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating teacher", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_teacher(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting teacher", ['error' => $e->getMessage()]);
        return false;
    }
}