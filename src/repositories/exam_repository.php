<?php

function get_all_exams(PDO $pdo, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT e.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM exams e
            JOIN groups_students g ON e.group_id = g.id
            JOIN subjects sub ON e.subject_id = sub.id
            LEFT JOIN teachers t ON e.teacher_id = t.id
            LEFT JOIN rooms r ON e.room_id = r.id
            ORDER BY e.exam_date ASC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting all exams", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_exams_by_group(PDO $pdo, int $group_id, array $logger): array
{
    try {
        $sql = "
            SELECT e.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM exams e
            JOIN groups_students g ON e.group_id = g.id
            JOIN subjects sub ON e.subject_id = sub.id
            LEFT JOIN teachers t ON e.teacher_id = t.id
            LEFT JOIN rooms r ON e.room_id = r.id
            WHERE e.group_id = :group_id
            ORDER BY e.exam_date ASC, e.exam_time ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group_id' => $group_id]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting exams by group", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_exam_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $sql = "
            SELECT e.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM exams e
            JOIN groups_students g ON e.group_id = g.id
            JOIN subjects sub ON e.subject_id = sub.id
            LEFT JOIN teachers t ON e.teacher_id = t.id
            LEFT JOIN rooms r ON e.room_id = r.id
            WHERE e.id = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting exam by id", ['error' => $e->getMessage()]);
        return null;
    }
}
function search_exams(PDO $pdo, string $search, ?int $group_id = null, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT e.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM exams e
            JOIN groups_students g ON e.group_id = g.id
            JOIN subjects sub ON e.subject_id = sub.id
            LEFT JOIN teachers t ON e.teacher_id = t.id
            LEFT JOIN rooms r ON e.room_id = r.id
            WHERE (g.name LIKE '%{$search}%'
               OR sub.name LIKE '%{$search}%'
               OR sub.short_name LIKE '%{$search}%'
               OR t.last_name LIKE '%{$search}%'
               OR t.name LIKE '%{$search}%')
        ";
        
        $params = [];
        
        if ($group_id) {
            $sql .= " AND e.group_id = :group_id";
            $params[':group_id'] = $group_id;
        }
        
        $sql .= " ORDER BY e.exam_date ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error searching exams", ['error' => $e->getMessage()]);
        return [];
    }
}

function count_search_exams(PDO $pdo, string $search, ?int $group_id = null, array $logger): int
{
    try {
        $sql = "
            SELECT COUNT(*) as total
            FROM exams e
            JOIN groups_students g ON e.group_id = g.id
            JOIN subjects sub ON e.subject_id = sub.id
            LEFT JOIN teachers t ON e.teacher_id = t.id
            WHERE (g.name LIKE '%{$search}%' 
               OR sub.name LIKE '%{$search}%' 
               OR sub.short_name LIKE '%{$search}%'
               OR t.last_name LIKE '%{$search}%'
               OR t.name LIKE '%{$search}%')
        ";
        
        $params = [];
        
        if ($group_id) {
            $sql .= " AND e.group_id = :group_id";
            $params[':group_id'] = $group_id;
        }
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
        
    } catch (PDOException $e) {
        log_error($logger, "Error counting search exams", ['error' => $e->getMessage()]);
        return 0;
    }
}

function create_exam(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO exams (group_id, subject_id, teacher_id, room_id, exam_date, exam_time, exam_type, description) 
                VALUES (:group_id, :subject_id, :teacher_id, :room_id, :exam_date, :exam_time, :exam_type, :description)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'group_id' => $data['group_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => !empty($data['teacher_id']) ? $data['teacher_id'] : null,
            'room_id' => !empty($data['room_id']) ? $data['room_id'] : null,
            'exam_date' => $data['exam_date'],
            'exam_time' => !empty($data['exam_time']) ? $data['exam_time'] : null,
            'exam_type' => $data['exam_type'] ?? 'exam',
            'description' => $data['description'] ?? null
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating exam", ['error' => $e->getMessage()]);
        return null;
    }
}

function update_exam(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['subject_id', 'teacher_id', 'room_id', 'exam_date', 'exam_time', 'exam_type', 'description'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE exams SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating exam", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_exam(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM exams WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting exam", ['error' => $e->getMessage()]);
        return false;
    }
}

function get_exams_count(PDO $pdo, array $logger): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM exams");
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        log_error($logger, "Error getting exams count", ['error' => $e->getMessage()]);
        return 0;
    }
}