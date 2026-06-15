<?php
function get_all_homework(PDO $pdo, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT h.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   (SELECT COUNT(*) FROM homework_submissions WHERE homework_id = h.id) as submissions_count,
                   (SELECT AVG(score) FROM homework_submissions WHERE homework_id = h.id AND status = 'approved') as avg_score
            FROM homework h
            JOIN groups_students g ON h.group_id = g.id
            JOIN subjects sub ON h.subject_id = sub.id
            LEFT JOIN teachers t ON h.teacher_id = t.id
            ORDER BY h.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting all homework", ['error' => $e->getMessage()]);
        return [];
    }
}

// function get_homework_by_group(PDO $pdo, int $group_id, array $logger): array
// {
//     try {
//         $sql = "
//             SELECT h.*,
//                    sub.name as subject_name,
//                    sub.short_name as subject_short,
//                    t.name as teacher_name,
//                    t.last_name as teacher_last_name,
//                    (SELECT COUNT(*) FROM homework_submissions WHERE homework_id = h.id AND user_id IN (SELECT id FROM users WHERE group_id = :group_id)) as submissions_count
//             FROM homework h
//             JOIN subjects sub ON h.subject_id = sub.id
//             LEFT JOIN teachers t ON h.teacher_id = t.id
//             WHERE h.group_id = :group_id
//             ORDER BY h.deadline ASC, h.created_at DESC
//         ";
        
//         $stmt = $pdo->prepare($sql);
//         $stmt->execute(['group_id' => $group_id]);
        
//         return $stmt->fetchAll();
//     } catch (PDOException $e) {
//         log_error($logger, "Error getting homework by group", ['error' => $e->getMessage()]);
//         return [];
//     }
// }

function get_homework_by_group(PDO $pdo, int $group_id, array $logger): array
{
    try {
        $sql = "
            SELECT h.*,
                    g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   (SELECT COUNT(*) FROM homework_submissions s 
                    WHERE s.homework_id = h.id 
                    AND s.user_id IN (SELECT u.id FROM users u WHERE u.group_id = :group_id_param)) as submissions_count
            FROM homework h
            JOIN groups_students g ON h.group_id = g.id
            JOIN subjects sub ON h.subject_id = sub.id
            LEFT JOIN teachers t ON h.teacher_id = t.id
            WHERE h.group_id = :group_id_where
            ORDER BY h.deadline ASC, h.created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':group_id_param', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id_where', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting homework by group", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_homework_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $sql = "
            SELECT h.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name
            FROM homework h
            JOIN groups_students g ON h.group_id = g.id
            JOIN subjects sub ON h.subject_id = sub.id
            LEFT JOIN teachers t ON h.teacher_id = t.id
            WHERE h.id = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting homework by id", ['error' => $e->getMessage()]);
        return null;
    }
}

function get_submission_by_homework_and_user(PDO $pdo, int $homework_id, int $user_id, array $logger): ?array
{
    try {
        $sql = "
            SELECT * FROM homework_submissions 
            WHERE homework_id = :homework_id AND user_id = :user_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'homework_id' => $homework_id,
            'user_id' => $user_id
        ]);
        
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting submission", ['error' => $e->getMessage()]);
        return null;
    }
}

function get_submissions_by_homework(PDO $pdo, int $homework_id, array $logger): array
{
    try {
        $sql = "
            SELECT s.*,
                   u.name as user_name,
                   u.email as user_email
            FROM homework_submissions s
            JOIN users u ON s.user_id = u.id
            WHERE s.homework_id = :homework_id
            ORDER BY s.submitted_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['homework_id' => $homework_id]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting submissions", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_user_submissions(PDO $pdo, int $user_id, array $logger): array
{
    try {
        $sql = "
            SELECT s.*,
                   h.title as homework_title,
                   h.max_score,
                   sub.name as subject_name
            FROM homework_submissions s
            JOIN homework h ON s.homework_id = h.id
            JOIN subjects sub ON h.subject_id = sub.id
            WHERE s.user_id = :user_id
            ORDER BY s.submitted_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting user submissions", ['error' => $e->getMessage()]);
        return [];
    }
}

// function get_user_homework_stats(PDO $pdo, int $user_id, array $logger): array
// {
//     try {
//         $sql = "
//             SELECT 
//                 (SELECT COUNT(*) FROM homework h 
//                  WHERE h.group_id = (SELECT group_id FROM users WHERE id = :user_id1)) as total_homework,
//                 (SELECT COUNT(*) FROM homework_submissions s 
//                  WHERE s.user_id = :user_id2) as submitted_count,
//                 (SELECT COUNT(*) FROM homework_submissions s 
//                  WHERE s.user_id = :user_id3 AND s.status = 'approved') as approved_count,
//                 (SELECT COALESCE(SUM(score), 0) FROM homework_submissions s 
//                  WHERE s.user_id = :user_id4 AND s.status = 'approved') as total_score,
//                 (SELECT COALESCE(SUM(max_score), 0) FROM homework h 
//                  WHERE h.group_id = (SELECT group_id FROM users WHERE id = :user_id5)) as max_possible_score,
//                 (SELECT ROUND(COALESCE(AVG(score), 0)) FROM homework_submissions s 
//                  WHERE s.user_id = :user_id6 AND s.status = 'approved') as avg_score
//         ";
        
//         $stmt = $pdo->prepare($sql);
//         $stmt->bindValue(':user_id1', $user_id, PDO::PARAM_INT);
//         $stmt->bindValue(':user_id2', $user_id, PDO::PARAM_INT);
//         $stmt->bindValue(':user_id3', $user_id, PDO::PARAM_INT);
//         $stmt->bindValue(':user_id4', $user_id, PDO::PARAM_INT);
//         $stmt->bindValue(':user_id5', $user_id, PDO::PARAM_INT);
//         $stmt->bindValue(':user_id6', $user_id, PDO::PARAM_INT);
//         $stmt->execute();
        
//         $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         // Вычисляем процент
//         if ($result && $result['max_possible_score'] > 0) {
//             $result['average_percent'] = round(($result['total_score'] / $result['max_possible_score']) * 100, 1);
//         } else {
//             $result['average_percent'] = 0;
//         }
        
//         return $result ?: [];
//     } catch (PDOException $e) {
//         log_error($logger, "Error getting user homework stats", ['error' => $e->getMessage()]);
//         return [];
//     }
// }
function get_user_homework_stats(PDO $pdo, int $user_id, array $logger): array
{
    try {
        // Получаем текущую группу пользователя
        $stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['group_id']) {
            return [
                'total_homework' => 0,
                'submitted_count' => 0,
                'approved_count' => 0,
                'total_score' => 0,
                'max_possible_score' => 0,
                'avg_score' => 0,
                'average_percent' => 0
            ];
        }
        
        $group_id = $user['group_id'];
        
        // Статистика по домашним заданиям текущей группы
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM homework WHERE group_id = :group_id1) as total_homework,
                (SELECT COUNT(*) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE s.user_id = :user_id1 AND h.group_id = :group_id2) as submitted_count,
                (SELECT COUNT(*) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE s.user_id = :user_id2 AND s.status = 'approved' AND h.group_id = :group_id3) as approved_count,
                (SELECT COALESCE(SUM(s.score), 0) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE s.user_id = :user_id3 AND s.status = 'approved' AND h.group_id = :group_id4) as total_score,
                (SELECT COALESCE(SUM(max_score), 0) FROM homework WHERE group_id = :group_id5) as max_possible_score,
                (SELECT ROUND(COALESCE(AVG(s.score), 0)) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE s.user_id = :user_id4 AND s.status = 'approved' AND h.group_id = :group_id6) as avg_score
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':group_id1', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id2', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id3', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id4', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id5', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':group_id6', $group_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id1', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id2', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id3', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id4', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Вычисляем процент
        if ($result && $result['max_possible_score'] > 0) {
            $result['average_percent'] = round(($result['total_score'] / $result['max_possible_score']) * 100, 1);
        } else {
            $result['average_percent'] = 0;
        }
        
        return $result ?: [];
    } catch (PDOException $e) {
        log_error($logger, "Error getting user homework stats", ['error' => $e->getMessage()]);
        return [];
    }
}
function get_group_homework_stats(PDO $pdo, int $group_id, array $logger): array
{
    try {
        $sql = "
            SELECT 
                COUNT(*) as total_homework,
                (SELECT COUNT(*) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE h.group_id = :group_id) as total_submissions,
                (SELECT COUNT(*) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE h.group_id = :group_id AND s.status = 'approved') as approved_count,
                (SELECT AVG(score) FROM homework_submissions s 
                 JOIN homework h ON s.homework_id = h.id 
                 WHERE h.group_id = :group_id AND s.status = 'approved') as avg_score
            FROM homework h
            WHERE h.group_id = :group_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group_id' => $group_id]);
        
        return $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        log_error($logger, "Error getting group homework stats", ['error' => $e->getMessage()]);
        return [];
    }
}

function search_homework(PDO $pdo, string $search, ?int $group_id = null, array $logger, int $limit = 20, int $offset = 0): array
{
    try {
        $sql = "
            SELECT h.*,
                   g.name as group_name,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   (SELECT COUNT(*) FROM homework_submissions WHERE homework_id = h.id) as submissions_count
            FROM homework h
            JOIN groups_students g ON h.group_id = g.id
            JOIN subjects sub ON h.subject_id = sub.id
            LEFT JOIN teachers t ON h.teacher_id = t.id
            WHERE (h.title LIKE '%{$search}%'  
               OR h.description LIKE '%{$search}%' 
               OR sub.name LIKE '%{$search}%' 
               OR g.name LIKE '%{$search}%')
        ";

        $params = [];
        
        if ($group_id) {
            $sql .= " AND h.group_id = :group_id";
            $params[':group_id'] = $group_id;
        }
        
        $sql .= " ORDER BY h.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error searching homework", ['error' => $e->getMessage()]);
        return [];
    }
}

function count_search_homework(PDO $pdo, string $search, ?int $group_id = null, array $logger): int
{
    try {
        $sql = "
            SELECT COUNT(*) as total
            FROM homework h
            JOIN groups_students g ON h.group_id = g.id
            JOIN subjects sub ON h.subject_id = sub.id
            WHERE (h.title LIKE '%{$search}%' 
               OR h.description LIKE '%{$search}%'
               OR sub.name LIKE '%{$search}%'
               OR g.name LIKE '%{$search}%')
        ";
        
        $params = [];
        
        if ($group_id) {
            $sql .= " AND h.group_id = :group_id";
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
        log_error($logger, "Error counting search homework", ['error' => $e->getMessage()]);
        return 0;
    }
}

function create_homework(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO homework (group_id, subject_id, teacher_id, title, description, file_path, deadline, max_score) 
                VALUES (:group_id, :subject_id, :teacher_id, :title, :description, :file_path, :deadline, :max_score)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'group_id' => $data['group_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'max_score' => $data['max_score'] ?? 100
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating homework", ['error' => $e->getMessage()]);
        return null;
    }
}

function submit_homework(PDO $pdo, int $homework_id, int $user_id, string $file_path, string $comment, array $logger): bool
{
    try {
        // Проверяем, есть ли уже отправка
        $existing = get_submission_by_homework_and_user($pdo, $homework_id, $user_id, $logger);
        
        if ($existing) {
            // Обновляем существующую
            $sql = "UPDATE homework_submissions 
                    SET file_path = :file_path, comment = :comment, submitted_at = NOW(), status = 'pending'
                    WHERE homework_id = :homework_id AND user_id = :user_id";
        } else {
            // Создаем новую
            $sql = "INSERT INTO homework_submissions (homework_id, user_id, file_path, comment) 
                    VALUES (:homework_id, :user_id, :file_path, :comment)";
        }
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'homework_id' => $homework_id,
            'user_id' => $user_id,
            'file_path' => $file_path,
            'comment' => $comment
        ]);
    } catch (PDOException $e) {
        log_error($logger, "Error submitting homework", ['error' => $e->getMessage()]);
        return false;
    }
}

function update_homework(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['subject_id', 'teacher_id', 'title', 'description', 'file_path', 'deadline', 'max_score'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE homework SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating homework", ['error' => $e->getMessage()]);
        return false;
    }
}

function grade_submission(PDO $pdo, int $submission_id, int $score, string $status, string $teacher_comment, array $logger): bool
{
    try {
        $sql = "UPDATE homework_submissions 
                SET score = :score, status = :status, teacher_comment = :teacher_comment, graded_at = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'id' => $submission_id,
            'score' => $score,
            'status' => $status,
            'teacher_comment' => $teacher_comment
        ]);
    } catch (PDOException $e) {
        log_error($logger, "Error grading submission", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_homework(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM homework WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting homework", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_submission(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM homework_submissions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting submission", ['error' => $e->getMessage()]);
        return false;
    }
}