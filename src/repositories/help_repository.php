<?php
/**
 * Получить все университеты для выпадающего списка
 */

function get_all_universities(PDO $pdo, array $logger): array
{
    try {
        $stmt = $pdo->query("SELECT id, name, short_name FROM universities ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting universities", ['error' => $e->getMessage()]);
        return [];
    }
}
function get_university_by_id(PDO $pdo, int $id, array $logger): array {
    try {
        //code...
        $stmt = $pdo->prepare("SELECT name, short_name FROM universities 
        WHERE universities.id = :id 
        ORDER BY name");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        log_error($logger, "Error getting university by id", ['error' => $e->getMessage()]);
        return [];
    }
}
/**
 * Получить факультеты по ID университета
 */
function get_faculties_by_university(PDO $pdo, int $university_id, array $logger): array
{
    try {
        $stmt = $pdo->prepare("SELECT id, name, short_name FROM faculties WHERE university_id = :university_id ORDER BY name");
        $stmt->execute(['university_id' => $university_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting faculties", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Получить группы по ID факультета
 */
function get_groups_by_faculty(PDO $pdo, int $faculty_id, array $logger): array
{
    try {
        $stmt = $pdo->prepare("SELECT id, name, course FROM groups_students WHERE faculty_id = :faculty_id ORDER BY course, name");
        $stmt->execute(['faculty_id' => $faculty_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting groups", ['error' => $e->getMessage()]);
        return [];
    }
}