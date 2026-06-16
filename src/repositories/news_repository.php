<?php
// repositories/news_repository.php

/**
 * Получить последние новости
 */

function get_news_by_university(PDO $pdo, ?int $university_id, array $logger, int $limit = 6): array
{
    try {
        $sql = "
            SELECT 
                n.id, 
                n.title, 
                n.content, 
                n.excerpt, 
                n.image, 
                n.views,
                DATE_FORMAT(n.published_at, '%d.%m.%Y') as formatted_date,
                DATE_FORMAT(n.published_at, '%H:%i') as formatted_time,
                n.published_at,
                u.id as university_id,
                u.name as university_name,
                u.short_name as university_short_name
            FROM news n
            JOIN universities u ON n.university_id = u.id
            WHERE n.published = 1 
              AND n.published_at <= NOW()
              AND n.university_id = :university_id
            ORDER BY n.published_at DESC
            LIMIT :limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':university_id', $university_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting news by university", [
            'error' => $e->getMessage(), 
            'university_id' => $university_id
        ]);
        return [];
    }
}

/**
 * Получить новость по ID с защитой от накрутки просмотров
 */
function get_news_by_id(PDO $pdo, int $id, array $logger, ?int $user_id = null): ?array
{
    try {
        // Получаем IP адрес
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Если user_id не передан, берем из сессии
        if ($user_id === null && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['id'])) {
            $user_id = $_SESSION['user']['id'];
        }
        
        // Проверяем, не смотрел ли уже этот пользователь данную новость сегодня
        $has_viewed = false;
        
        if ($user_id) {
            // Проверка по user_id (один просмотр в день от пользователя)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM news_views 
                WHERE news_id = :news_id 
                  AND user_id = :user_id 
                  AND DATE(viewed_at) = CURDATE()
            ");
            $stmt->execute(['news_id' => $id, 'user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $has_viewed = $result && $result['count'] > 0;
        }
        
        // Если пользователь не авторизован, проверяем по IP (ограничение 1 просмотр в сутки)
        if (!$has_viewed && !$user_id) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM news_views 
                WHERE news_id = :news_id 
                  AND ip_address = :ip 
                  AND DATE(viewed_at) = CURDATE()
            ");
            $stmt->execute(['news_id' => $id, 'ip' => $ip_address]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $has_viewed = $result && $result['count'] > 0;
        }
        
        // Если просмотра не было, регистрируем его
        if (!$has_viewed) {
            $stmt = $pdo->prepare("
                INSERT INTO news_views (news_id, user_id, ip_address, user_agent, viewed_at) 
                VALUES (:news_id, :user_id, :ip, :user_agent, NOW())
            ");
            $stmt->execute([
                'news_id' => $id,
                'user_id' => $user_id,
                'ip' => $ip_address,
                'user_agent' => $user_agent
            ]);
            
            // Обновляем общий счетчик просмотров в таблице news
            $stmt = $pdo->prepare("UPDATE news SET views = views + 1 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            log_info($logger, "News view registered", [
                'news_id' => $id,
                'user_id' => $user_id,
                'ip' => $ip_address
            ]);
        }
        
        // Получаем данные новости
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                DATE_FORMAT(n.published_at, '%d.%m.%Y') as formatted_date,
                DATE_FORMAT(n.published_at, '%H:%i') as formatted_time,
                (SELECT COUNT(*) FROM news_views WHERE news_id = n.id) as total_views,
                (SELECT COUNT(DISTINCT user_id) FROM news_views WHERE news_id = n.id AND user_id IS NOT NULL) as unique_user_views,
                (SELECT COUNT(DISTINCT ip_address) FROM news_views WHERE news_id = n.id) as unique_ip_views
            FROM news n
            WHERE n.id = :id AND n.published = 1
        ");
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting news by id", [
            'error' => $e->getMessage(),
            'news_id' => $id
        ]);
        return null;
    }
}

/**
 * Создать новость для университета
 */
function create_news(PDO $pdo, array $data, array $logger): ?int
{
    try {
        $sql = "
            INSERT INTO news (university_id, title, content, excerpt, image, published, published_at)
            VALUES (:university_id, :title, :content, :excerpt, :image, :published, NOW())
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':university_id' => $data['university_id'],
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':excerpt' => $data['excerpt'] ?? mb_substr($data['content'], 0, 150),
            ':image' => $data['image'] ?? null,
            ':published' => $data['published'] ?? 1
        ]);
        
        return (int)$pdo->lastInsertId();
        
    } catch (PDOException $e) {
        log_error($logger, "Error creating news", ['error' => $e->getMessage()]);
        return null;
    }
}

/**
 * Обновить новость
 */
function update_news(PDO $pdo, int $id, array $data, array $logger): bool
{
    try {
        $sql = "
            UPDATE news 
            SET 
                title = :title,
                content = :content,
                excerpt = :excerpt,
                image = :image,
                published = :published,
                updated_at = NOW()
            WHERE id = :id
        ";
        
        // Если нужно проверить принадлежность к университету
        if (isset($data['university_id'])) {
            $sql .= " AND university_id = :university_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':id' => $id,
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':excerpt' => $data['excerpt'] ?? mb_substr($data['content'], 0, 150),
            ':image' => $data['image'] ?? null,
            ':published' => $data['published'] ?? 1
        ];
        
        if (isset($data['university_id'])) {
            $params[':university_id'] = $data['university_id'];
        }
        
        return $stmt->execute($params);
        
    } catch (PDOException $e) {
        log_error($logger, "Error updating news", ['error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Удалить новость
 */
function delete_news(PDO $pdo, int $id, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = :id");
        return $stmt->execute([':id' => $id]);
        
    } catch (PDOException $e) {
        log_error($logger, "Error deleting news", ['error' => $e->getMessage()]);
        return false;
    }
}


function get_all_news_admin(PDO $pdo, array $logger, ?int $university_id = null, int $limit = 100, int $offset = 0): array
{
    try {
        $sql = "
            SELECT 
                n.id, 
                n.title, 
                n.content,
                n.excerpt, 
                n.image, 
                n.published,
                n.views,
                n.university_id,
                DATE_FORMAT(n.published_at, '%d.%m.%Y %H:%i') as formatted_date,
                n.published_at, 
                n.created_at, 
                n.updated_at,
                u.id as university_id,
                u.name as university_name
            FROM news n
            JOIN universities u ON n.university_id = u.id
            WHERE 1=1
        ";
        
        $params = [];

        if ($university_id) {
            $sql .= " AND n.university_id = :university_id";
            $params[':university_id'] = $university_id;
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting all news", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Получить новость по ID для админки (без проверки published)
 */
function get_news_by_id_admin(PDO $pdo, int $id, array $logger): ?array
{
    try {
        $sql = "
            SELECT 
                n.id, 
                n.title, 
                n.content, 
                n.excerpt, 
                n.image, 
                n.published,
                n.views,
                n.university_id,
                DATE_FORMAT(n.published_at, '%d.%m.%Y %H:%i') as formatted_date,
                n.published_at,
                n.created_at,
                n.updated_at,
                u.name as university_name
            FROM news n
            JOIN universities u ON n.university_id = u.id
            WHERE n.id = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting news by id for admin", [
            'error' => $e->getMessage(),
            'news_id' => $id
        ]);
        return null;
    }
}

/**
 * Получить количество новостей
 */
function get_news_count(PDO $pdo, ?int $university_id = null, bool $only_published = true, array $logger): int
{
    try {
        $sql = "SELECT COUNT(*) FROM news WHERE 1=1";
        $params = [];
        
        if ($only_published) {
            $sql .= " AND published = 1";
        }
        
        if ($university_id) {
            $sql .= " AND university_id = :university_id";
            $params[':university_id'] = $university_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
        
    } catch (PDOException $e) {
        log_error($logger, "Error getting news count", ['error' => $e->getMessage()]);
        return 0;
    }
}

/**
 * Поиск новостей
 */
function search_news(PDO $pdo, string $search, ?int $university_id = null, array $logger, int $limit = 10): array
{
    try {
        $sql = "
            SELECT 
                n.id, 
                n.title, 
                n.excerpt,
                DATE_FORMAT(n.published_at, '%d.%m.%Y') as formatted_date,
                n.views,
                n.published,
                u.name as university_name
            FROM news n
            JOIN universities u ON n.university_id = u.id
            WHERE n.title LIKE '%{$search}%' OR n.content LIKE '%{$search}%'
        ";
        
        $params = [];
        
        if ($university_id) {
            $sql .= " AND n.university_id = :university_id";
            $params[':university_id'] = $university_id;
        }
        
        $sql .= " ORDER BY n.published_at DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        log_error($logger, "Error searching news", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Публикация/скрытие новости
 */
function toggle_news_published(PDO $pdo, int $id, bool $published, array $logger): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE news SET published = :published, updated_at = NOW() WHERE id = :id");
        $result = $stmt->execute([
            'id' => $id,
            'published' => $published ? 1 : 0
        ]);
        
        if ($result) {
            log_info($logger, "News toggled successfully", [
                'news_id' => $id,
                'published' => $published
            ]);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        log_error($logger, "Error toggling news", [
            'error' => $e->getMessage(),
            'news_id' => $id
        ]);
        return false;
    }
}
