<?php
// repositories/schedule_repository.php

// /**
//  * Получить расписание на текущую неделю
//  */


// function get_exams_for_group(PDO $pdo, string|null $group_name, array $logger): array
// {
//     try {
//         $sql = "
//             SELECT e.*,
//                    sub.name as subject_name,
//                    sub.short_name as subject_short,
//                    t.name as teacher_name,
//                    t.last_name as teacher_last_name,
//                    t.patronymic as teacher_patronymic,
//                    r.number as room_number,
//                    r.building as room_building
//             FROM exams e
//             JOIN subjects sub ON e.subject_id = sub.id
//             LEFT JOIN teachers t ON e.teacher_id = t.id
//             LEFT JOIN rooms r ON e.room_id = r.id
//             WHERE e.group_name = :group_name
//             ORDER BY e.exam_date ASC, e.exam_time ASC
//         ";
        
//         $results = db_fetch_all($pdo, $sql, [':group_name' => $group_name]);
        
//         return $results;
        
//     } catch (PDOException $e) {
//         log_error($logger, "Error getting exams", ['error' => $e->getMessage()]);
//         return [];
//     }
// }



function get_schedule_by_group(PDO $pdo, int $group_id, array $logger): array
{
    try {
        $sql = "
            SELECT s.*,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM schedule s
            JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.group_id = :group_id
            ORDER BY s.day_of_week, s.lesson_number
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['group_id' => $group_id]);
        
        $results = $stmt->fetchAll();
        
        // Группируем по дням недели
        $schedule = [];
        foreach ($results as $row) {
            $day = (int)$row['day_of_week'];
            $lesson = (int)$row['lesson_number'];
            
            if (!isset($schedule[$day])) {
                $schedule[$day] = [];
            }
            
            $schedule[$day][$lesson] = $row;
        }
        
        return $schedule;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting schedule by group", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_schedule_for_group(PDO $pdo, int $group_id, string $week_start, array $logger): array
{
    try {
        // Получаем номер недели и определяем четность
        $week_number = (int)date('W', strtotime($week_start));
        $is_even_week = ($week_number % 2 == 0);
        
        $sql = "
            SELECT s.*,
                   sub.name as subject_name,
                   sub.short_name as subject_short,
                   t.name as teacher_name,
                   t.last_name as teacher_last_name,
                   t.patronymic as teacher_patronymic,
                   r.number as room_number,
                   r.building as room_building
            FROM schedule s
            JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            LEFT JOIN rooms r ON s.room_id = r.id
            WHERE s.group_id = :group_id
              AND (s.week_start IS NULL OR s.week_start <= :week_start1)
              AND (s.week_end IS NULL OR s.week_end >= :week_start2)
              AND (s.is_even_week IS NULL OR s.is_even_week = :is_even_week)
            ORDER BY s.day_of_week, s.lesson_number
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':group_id' => $group_id,
            ':week_start1' => $week_start,
            ':week_start2' => $week_start,
            ':is_even_week' => $is_even_week ? 1 : 0
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Группируем по дням недели и номерам пар
        $schedule = [];
        foreach ($results as $row) {
            $day = (int)$row['day_of_week'];
            $lesson = (int)$row['lesson_number'];
            
            if (!isset($schedule[$day])) {
                $schedule[$day] = [];
            }
            
            $schedule[$day][$lesson] = [
                'id' => $row['id'],
                'group_id' => $row['group_id'],
                'subject_id' => $row['subject_id'],
                'subject_name' => $row['subject_name'],
                'subject_short' => $row['subject_short'],
                'teacher_id' => $row['teacher_id'],
                'teacher_name' => $row['teacher_name'],
                'teacher_last_name' => $row['teacher_last_name'],
                'teacher_patronymic' => $row['teacher_patronymic'],
                'room_id' => $row['room_id'],
                'room_number' => $row['room_number'],
                'room_building' => $row['room_building'],
                'day_of_week' => $row['day_of_week'],
                'lesson_number' => $row['lesson_number'],
                'week_start' => $row['week_start'],
                'week_end' => $row['week_end'],
                'is_even_week' => $row['is_even_week'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        log_info($logger, "Schedule loaded for group", [
            'group_id' => $group_id,
            'week_start' => $week_start,
            'week_number' => $week_number,
            'is_even_week' => $is_even_week,
            'lessons_count' => count($results)
        ]);
        
        return $schedule;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting schedule for group", [
            'error' => $e->getMessage(),
            'group_id' => $group_id,
            'week_start' => $week_start
        ]);
        return [];
    }
}
// function get_schedule_for_week(PDO $pdo, string $group_name, string $week_start, string $week_end, array $logger): array
// {
//     try {
        
//         $week_number = (int)date('W', strtotime($week_start));
//         $is_even_week = ($week_number % 2 == 0);
        
//         // ПРАВИЛЬНЫЙ SQL - используем week_start для проверки, что текущая неделя входит в диапазон
//         $sql = "SELECT s.*, 
//                 sub.name as subject_name,
//                 sub.short_name as subject_short,
//                 t.name as teacher_name,
//                 t.last_name as teacher_last_name,
//                 t.patronymic as teacher_patronymic,
//                 r.number as room_number,
//                 r.building as room_building
//             FROM schedule s
//             JOIN subjects sub ON s.subject_id = sub.id
//             LEFT JOIN teachers t ON s.teacher_id = t.id
//             LEFT JOIN rooms r ON s.room_id = r.id
//             WHERE s.group_name = :group_name
//             AND s.week_start <= :week_start1
//             AND s.week_end >= :week_end
//             AND (s.is_even_week IS NULL OR s.is_even_week = :is_even_week)
//             ORDER BY s.day_of_week, s.lesson_number
//         ";
        
//         $results = db_fetch_all($pdo, $sql, [
//             ':group_name' => $group_name,
//             ':week_start1' => $week_start,
//             ':is_even_week' => $is_even_week ? 1 : 0
//         ]);
        
//         // Логируем количество найденных записей
//         log_info($logger, "Schedule results", ['count' => count($results)]);
        
//         // Группируем по дням недели
//         $schedule = [];
//         foreach ($results as $row) {
//             $day = (int)$row['day_of_week'];
//             if (!isset($schedule[$day])) {
//                 $schedule[$day] = [];
//             }
//             $schedule[$day][] = $row;
//         }
        
//         return $schedule;
        
//     } catch (PDOException $e) {
//         log_error($logger, "Error getting weekly schedule", ['error' => $e->getMessage()]);
//         return [];
//     }
// }

function get_schedule_item_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM schedule WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting schedule item", ['error' => $e->getMessage()]);
        return null;
    }
}

function create_schedule_item(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "INSERT INTO schedule (group_id, subject_id, teacher_id, room_id, day_of_week, lesson_number, week_start, week_end, is_even_week) 
                VALUES (:group_id, :subject_id, :teacher_id, :room_id, :day_of_week, :lesson_number, :week_start, :week_end, :is_even_week)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'group_id' => $data['group_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => !empty($data['teacher_id']) ? $data['teacher_id'] : null,
            'room_id' => !empty($data['room_id']) ? $data['room_id'] : null,
            'day_of_week' => $data['day_of_week'],
            'lesson_number' => $data['lesson_number'],
            'week_start' => !empty($data['week_start']) ? $data['week_start'] : null,
            'week_end' => !empty($data['week_end']) ? $data['week_end'] : null,
            'is_even_week' => isset($data['is_even_week']) && $data['is_even_week'] !== '' ? $data['is_even_week'] : null
        ]);
        
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        log_error($logger, "Error creating schedule item", ['error' => $e->getMessage()]);
        return null;
    }
}

function update_schedule_item(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        $allowed = ['subject_id', 'teacher_id', 'room_id', 'day_of_week', 'lesson_number', 'week_start', 'week_end', 'is_even_week'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = ($key === 'week_start' || $key === 'week_end') && empty($value) ? null : $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE schedule SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        log_error($logger, "Error updating schedule item", ['error' => $e->getMessage()]);
        return false;
    }
}

function delete_schedule_item(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error deleting schedule item", ['error' => $e->getMessage()]);
        return false;
    }
}

function get_universities_with_groups(PDO $pdo, array $logger): array
{
    try {
        $sql = "
            SELECT 
                u.id as university_id,
                u.name as university_name,
                f.id as faculty_id,
                f.name as faculty_name,
                g.id as group_id,
                g.name as group_name,
                g.course
            FROM universities u
            JOIN faculties f ON u.id = f.university_id
            JOIN groups_students g ON f.id = g.faculty_id
            ORDER BY u.name, f.name, g.course, g.name
        ";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
        
        // Группируем по университетам
        $universities = [];
        foreach ($results as $row) {
            $uniId = $row['university_id'];
            $facId = $row['faculty_id'];
            
            if (!isset($universities[$uniId])) {
                $universities[$uniId] = [
                    'id' => $row['university_id'],
                    'name' => $row['university_name'],
                    'faculties' => []
                ];
            }
            
            if (!isset($universities[$uniId]['faculties'][$facId])) {
                $universities[$uniId]['faculties'][$facId] = [
                    'id' => $row['faculty_id'],
                    'name' => $row['faculty_name'],
                    'groups' => []
                ];
            }
            
            $universities[$uniId]['faculties'][$facId]['groups'][] = [
                'id' => $row['group_id'],
                'name' => $row['group_name'],
                'course' => $row['course']
            ];
        }
        
        // Преобразуем в обычные массивы
        foreach ($universities as &$uni) {
            $uni['faculties'] = array_values($uni['faculties']);
        }
        
        return array_values($universities);
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting universities with groups", ['error' => $e->getMessage()]);
        return [];
    }
}

function get_schedule_groups(PDO $pdo, array $logger): array
{
    try {
        $sql = "
            SELECT 
                g.id, 
                g.name as group_name, 
                g.course,
                f.name as faculty_name,
                u.name as university_name
            FROM groups_students g
            JOIN faculties f ON g.faculty_id = f.id
            JOIN universities u ON f.university_id = u.id
            ORDER BY u.name, f.name, g.course, g.name
        ";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting schedule groups", ['error' => $e->getMessage()]);
        return [];
    }
}

