<?php

function get_attendance_by_user(PDO $pdo, int $user_id, ?string $start_date = null, ?string $end_date = null, array $logger): array
{
    try {
        $sql = "
            SELECT a.*,
                   s.subject_name,
                   s.day_of_week,
                   s.lesson_number,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name
            FROM attendance a
            JOIN schedule s ON a.schedule_id = s.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE a.user_id = :user_id
        ";
        
        $params = [':user_id' => $user_id];
        
        if ($start_date) {
            $sql .= " AND a.date >= :start_date";
            $params[':start_date'] = $start_date;
        }
        
        if ($end_date) {
            $sql .= " AND a.date <= :end_date";
            $params[':end_date'] = $end_date;
        }
        
        $sql .= " ORDER BY a.date DESC, s.day_of_week, s.lesson_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting attendance by user", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_attendance_by_group_and_date(PDO $pdo, int $group_id, string $date, array $logger): array
{
    try {
        // Получаем день недели (1 = понедельник, 7 = воскресенье)
        $day_of_week = date('N', strtotime($date));
        
        // Получаем номер недели в году для проверки четности
        $week_number = (int)date('W', strtotime($date));
        $is_even_week = ($week_number % 2 == 0) ? 1 : 0;
        
        log_info($logger, "Attendance query params", [
            'group_id' => $group_id,
            'date' => $date,
            'day_of_week' => $day_of_week,
            'week_number' => $week_number,
            'is_even_week' => $is_even_week
        ]);
        
        $sql = "
            SELECT 
                u.id as user_id,
                u.name as user_name,
                u.email as user_email,
                a.id as attendance_id,
                a.status,
                a.comment,
                a.date,
                s.id as schedule_id,
                sub.name as subject_name,
                s.lesson_number,
                s.day_of_week
            FROM users u
            CROSS JOIN schedule s
            JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN attendance a ON a.user_id = u.id AND a.schedule_id = s.id AND a.date = :date1
            WHERE u.group_id = :group_id1
              AND s.group_id = :group_id2
              AND s.day_of_week = :day_of_week
              AND (s.week_start <= :date2 OR s.week_start IS NULL)
              AND (s.week_end >= :date3 OR s.week_end IS NULL)
              AND (s.is_even_week IS NULL OR s.is_even_week = :is_even_week)
            ORDER BY u.name, s.lesson_number
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':group_id1' => $group_id,
            ':group_id2' => $group_id,
            ':date1' => $date,
            ':date2' => $date,
            ':date3' => $date,
            
            ':day_of_week' => $day_of_week,
            ':is_even_week' => $is_even_week
        ]);
        
        $results = $stmt->fetchAll();
        
        log_info($logger, "Attendance query results", ['count' => count($results)]);
        
        return $results;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting attendance by group and date", [
            'error' => $e->getMessage(),
            'group_id' => $group_id,
            'date' => $date
        ]);
        return [];
    }
}

function get_attendance_stats_by_user(PDO $pdo, int $user_id, array $logger): array
{
    try {
        // Получаем текущую группу пользователя
        $stmt = $pdo->prepare("
            SELECT u.group_id, g.name as group_name
            FROM users u
            LEFT JOIN groups_students g ON u.group_id = g.id
            WHERE u.id = :user_id
        ");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['group_id']) {
            return [
                'total_lessons' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'late_count' => 0,
                'excused_count' => 0,
                'attendance_percent' => 0
            ];
        }
        
        $group_id = $user['group_id'];
        
        // Получаем статистику посещаемости только по занятиям текущей группы
        $sql = "
            SELECT 
                COUNT(a.id) as total_lessons,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                ROUND(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id) * 100, 1) as attendance_percent
            FROM attendance a
            JOIN schedule s ON a.schedule_id = s.id
            WHERE a.user_id = :user_id AND s.group_id = :group_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':group_id' => $group_id
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['total_lessons'] == 0) {
            return [
                'total_lessons' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'late_count' => 0,
                'excused_count' => 0,
                'attendance_percent' => 0
            ];
        }
        
        return $result;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting attendance stats", ['error' => $e->getMessage()]);
        return [];
    }
}
function get_group_attendance_stats(PDO $pdo, int $group_id, array $logger): array
{
    try {
        $sql = "
            SELECT 
                u.id as user_id,
                u.name as user_name,
                COUNT(a.id) as total_marks,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                ROUND(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.id) * 100, 1) as attendance_percent
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id
            WHERE u.group_id = :group_id
            GROUP BY u.id, u.name
            ORDER BY attendance_percent DESC, user_name
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':group_id' => $group_id]);
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting group attendance stats", ['error' => $e->getMessage()]);
        return [];
    }
}

function set_attendance(PDO $pdo, int $user_id, int $schedule_id, string $date, string $status, ?string $comment, int $marked_by, array $logger): bool
{
    try {
        $sql = "
            INSERT INTO attendance (user_id, schedule_id, date, status, comment, marked_by) 
            VALUES (:user_id, :schedule_id, :date, :status, :comment, :marked_by)
            ON DUPLICATE KEY UPDATE 
                status = :status_update,
                comment = :comment_update,
                marked_by = :marked_by_update,
                updated_at = NOW()
        ";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':schedule_id' => $schedule_id,
            ':date' => $date,
            ':status' => $status,
            ':comment' => $comment,
            ':marked_by' => $marked_by,
            ':status_update' => $status,
            ':comment_update' => $comment,
            ':marked_by_update' => $marked_by
        ]);
    } catch (PDOException $e) {
        log_error($logger, "Error setting attendance", ['error' => $e->getMessage()]);
        return false;
    }
}

function bulk_set_attendance(PDO $pdo, array $attendance_data, int $marked_by, array $logger): bool
{
    try {
        $pdo->beginTransaction();
        
        $sql = "
            INSERT INTO attendance (user_id, schedule_id, date, status, comment, marked_by) 
            VALUES (:user_id, :schedule_id, :date, :status, :comment, :marked_by)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                comment = VALUES(comment),
                marked_by = VALUES(marked_by),
                updated_at = NOW()
        ";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($attendance_data as $data) {
            $stmt->execute([
                ':user_id' => $data['user_id'],
                ':schedule_id' => $data['schedule_id'],
                ':date' => $data['date'],
                ':status' => $data['status'],
                ':comment' => $data['comment'] ?? null,
                ':marked_by' => $marked_by
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        log_error($logger, "Error bulk setting attendance", ['error' => $e->getMessage()]);
        return false;
    }
}