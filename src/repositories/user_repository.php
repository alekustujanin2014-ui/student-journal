<?php
// repositories/user_repository.php

function user_find_by_email(PDO $pdo, string $email, array $logger): ?array
{
    try {
        return db_fetch_one($pdo, "select users.*, roles.name as role from users left join roles on users.role_id = roles.id where users.email = :email", ['email' => $email]);
    } catch (PDOException $e) {
        log_error($logger, "Error finding user by email", ['error' => $e->getMessage(), 'email' => $email]);
        return null;
    }
}

function user_find_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $user = db_fetch_one($pdo, "
            SELECT users.*,
            universities.name as university,
            faculties.name as faculty,
            groups_students.name as group_name
            FROM users
            LEFT JOIN universities on users.university_id  = universities.id 
            LEFT JOIN faculties on users.faculty_id = faculties.id
            LEFT JOIN groups_students on users.group_id = groups_students.id
            WHERE users.id = :id",
        ['id' => $id]);

        if ($user){
            $stats = get_user_homework_stats($pdo, $id, $logger);
            $user['homework_stats'] = $stats;
        }
        return $user ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error finding user by id", ['error' => $e->getMessage(), 'id' => $id]);
        return null;
    }
}

function user_create(PDO $pdo, array $data, array $logger): int
{
    try {
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (email, password_hash, name, university, faculty, group_name, role, created_at) 
                VALUES (:email, :password, :name, :university, :faculty, :group_name, :role, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => $data['email'],
            'password' => $password_hash,
            'name' => $data['name'],
            'university' => $data['university'] ?? '',
            'faculty' => $data['faculty'] ?? '',
            'group_name' => $data['group_name'] ?? '',
            'role' => 'user'
        ]);
        
        $user_id = (int) $pdo->lastInsertId();
        
        // Сохраняем дополнительные поля, если они есть
        $update_fields = [];
        $update_params = ['id' => $user_id];
        
        if (!empty($data['student_id'])) {
            $update_fields[] = "student_id = :student_id";
            $update_params['student_id'] = $data['student_id'];
        }
        
        if (!empty($data['birth_date'])) {
            $update_fields[] = "birth_date = :birth_date";
            $update_params['birth_date'] = $data['birth_date'];
        }
        
        if (!empty($data['gender'])) {
            $update_fields[] = "gender = :gender";
            $update_params['gender'] = $data['gender'];
        }
        
        if (!empty($data['phone'])) {
            $update_fields[] = "phone = :phone";
            $update_params['phone'] = $data['phone'];
        }
        
        if (!empty($data['city'])) {
            $update_fields[] = "city = :city";
            $update_params['city'] = $data['city'];
        }
        
        if (!empty($data['avatar'])) {
            $update_fields[] = "avatar = :avatar";
            $update_params['avatar'] = $data['avatar'];
        }
        
        if (!empty($update_fields)) {
            $update_sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($update_params);
        }
        
        // Сохраняем интересы
        if (!empty($data['interests'])) {
            user_update_interests($pdo, $user_id, $data['interests'], $logger);
        }
        
        log_info($logger, "User created successfully", ['user_id' => $user_id, 'email' => $data['email']]);
        
        return $user_id;
        
    } catch (PDOException $e) {
        log_error($logger, "Error creating user", ['error' => $e->getMessage(), 'email' => $data['email']]);
        throw $e;
    }
}

function user_delete(PDO $pdo, int $id, array $logger): bool
{
    try {
        $result = db_delete($pdo, 'users', ['id' => $id]) > 0;
        if ($result) {
            log_info($logger, "User deleted successfully", ['user_id' => $id]);
        }
        return $result;
    } catch (PDOException $e) {
        log_error($logger, "Error deleting user", ['error' => $e->getMessage(), 'id' => $id]);
        return false;
    }
}

function user_verify_password(PDO $pdo, string $email, string $password, array $logger): ?array
{
    try {
        $user = user_find_by_email($pdo, $email, $logger);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            log_info($logger, "Password verified successfully", ['email' => $email]);
            return $user;
        }
        
        log_warning($logger, "Password verification failed", ['email' => $email]);
        return null;
        
    } catch (PDOException $e) {
        log_error($logger, "Error verifying password", ['error' => $e->getMessage(), 'email' => $email]);
        return null;
    }
}

function user_update_last_login(PDO $pdo, int $id, array $logger): void
{
    try {
        db_update($pdo, 'users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $id]);
        log_debug($logger, "Last login updated", ['user_id' => $id]);
    } catch (PDOException $e) {
        log_error($logger, "Error updating last login", ['error' => $e->getMessage(), 'id' => $id]);
    }
}

function user_exists(PDO $pdo, string $email, array $logger): bool
{
    try {
        return user_find_by_email($pdo, $email, $logger) !== null;
    } catch (PDOException $e) {
        log_error($logger, "Error checking user exists", ['error' => $e->getMessage(), 'email' => $email]);
        return false;
    }
}

function user_get_all(PDO $pdo, int $limit, int $offset, array $logger): array
{
    try {
        return db_fetch_all($pdo, "SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset", [
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (PDOException $e) {
        log_error($logger, "Error getting all users", ['error' => $e->getMessage()]);
        return [];
    }
}

function user_count(PDO $pdo, array $logger): int
{
    try {
        $result = db_fetch_one($pdo, "SELECT COUNT(*) as count FROM users");
        return (int) $result['count'];
    } catch (PDOException $e) {
        log_error($logger, "Error counting users", ['error' => $e->getMessage()]);
        return 0;
    }
}

function user_update(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        
        $allowed = ['name', 'birth_date', 'gender', 'university', 'faculty', 'group_name', 'phone', 'city'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            log_warning($logger, "No fields to update for user", ['user_id' => $id]);
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            log_info($logger, "User updated successfully", ['user_id' => $id]);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        log_error($logger, "Error updating user", ['error' => $e->getMessage(), 'id' => $id]);
        return false;
    }
}

function user_update_interests(PDO $pdo, int $user_id, array $interests, array $logger): void
{
    try {
        // Удаляем старые интересы
        $stmt = $pdo->prepare("DELETE FROM user_interests WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        // Добавляем новые
        if (!empty($interests)) {
            $stmt = $pdo->prepare("INSERT INTO user_interests (user_id, interest) VALUES (:user_id, :interest)");
            foreach ($interests as $interest) {
                $stmt->execute(['user_id' => $user_id, 'interest' => $interest]);
            }
        }
        
        log_debug($logger, "User interests updated", ['user_id' => $user_id, 'interests_count' => count($interests)]);
        
    } catch (PDOException $e) {
        log_error($logger, "Error updating user interests", ['error' => $e->getMessage(), 'user_id' => $user_id]);
    }
}

function user_get_interests(PDO $pdo, int $user_id, array $logger): array
{
    try {
        $stmt = $pdo->prepare("SELECT interest FROM user_interests WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return array_column($stmt->fetchAll(), 'interest');
    } catch (PDOException $e) {
        log_error($logger, "Error getting user interests", ['error' => $e->getMessage(), 'user_id' => $user_id]);
        return [];
    }
}

function get_total_users(PDO $pdo, array $logger): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        log_error($logger, "Error getting total users", ['error' => $e->getMessage()]);
        return 0;
    }
}

function get_all_users_paginated(PDO $pdo, int $limit, int $offset, array $logger): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
            users.id,
            users.email,
            users.name,
            universities.name as university,
            faculties.name as faculty,
            groups_students.name as group_name,
            users.phone,
            users.city,
            users.created_at FROM users
            LEFT JOIN universities on users.university_id  = universities.id 
            LEFT JOIN faculties on users.faculty_id = faculties.id
            LEFT JOIN groups_students on users.group_id = groups_students.id
            ORDER BY users.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error getting users paginated", ['error' => $e->getMessage()]);
        return [];
    }
}

function search_users(PDO $pdo, string $search, int $limit, int $offset, array $logger): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
            users.id,
            users.email,
            users.name,
            universities.name as university,
            faculties.name as faculty,
            groups_students.name as group_name,
            users.phone,
            users.city,
            users.created_at FROM users
            LEFT JOIN universities on users.university_id = universities.id
            LEFT JOIN faculties on users.faculty_id = faculties.id and faculties.university_id = universities.id
            LEFT JOIN groups_students on users.group_id = groups_students.id and groups_students.faculty_id = faculties.id
            WHERE users.name LIKE '%{$search}%' OR users.email LIKE '%{$search}%' OR universities.name LIKE '%{$search}%'
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error($logger, "Error searching users", ['error' => $e->getMessage(), 'search' => $search]);
        return [];
    }
}

function count_search_users(PDO $pdo, string $search, array $logger): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
            COUNT(*) as count,
            universities.name
            FROM users 
            LEFT JOIN universities ON users.university_id = universities.id
            WHERE users.name LIKE '%{$search}%' OR users.email LIKE '%{$search}%' OR universities.name LIKE '%{$search}%'
            GROUP BY universities.id, universities.name
        ");
        $stmt->execute();
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        log_error($logger, "Error counting search users", ['error' => $e->getMessage(), 'search' => $search]);
        return 0;
    }
}
function create_user_by_admin(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (email, password_hash, name, university_id, faculty_id, group_id, phone, city, created_at) 
                VALUES (:email, :password, :name, :university_id, :faculty_id, :group_id, :phone, :city, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => $data['email'],
            'password' => $password_hash,
            'name' => $data['name'],
            'university_id' => $data['university_id'] ?? '',
            'faculty_id' => $data['faculty_id'] ?? '',
            'group_id' => $data['group_id'] ?? '',
            'phone' => $data['phone'] ?? '',
            'city' => $data['city'] ?? ''
        ]);
        
        $user_id = (int)$pdo->lastInsertId();
        
        log_info($logger, "User created by admin successfully", ['user_id' => $user_id, 'email' => $data['email']]);
        
        return $user_id;
        
    } catch (PDOException $e) {
        log_error($logger, "Error creating user by admin", ['error' => $e->getMessage(), 'email' => $data['email']]);
        return null;
    }
}

function update_user_by_admin(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $fields = [];
        $params = ['id' => $id];
        
        $allowed = ['name', 'email', 'university_id', 'faculty_id', 'group_id', 'phone', 'city'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value === '' ? null : $value;
            }
        }
        
        if (isset($data['password_hash'])) {
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = $data['password_hash'];
        }
        
        if (empty($fields)) {
            log_warning($logger, "No fields to update for user by admin", ['user_id' => $id]);
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            log_info($logger, "User updated by admin successfully", ['user_id' => $id]);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        log_error($logger, "Error updating user by admin", ['error' => $e->getMessage(), 'id' => $id]);
        return false;
    }
}



function get_user_by_id(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error($logger, "Error getting user by id", ['error' => $e->getMessage(), 'id' => $id]);
        return null;
    }
}