<?php
// migrate.php - скрипт для выполнения миграций базы данных

require_once ROOT_PATH . '/core/database.php';
require_once ROOT_PATH . '/core/logger.php';

// Настройки подключения к БД (без выбора базы данных)
$config = [
    'host' => getenv('DB_HOST') ?: 'mysql',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_DATABASE') ?: 'app_db',
    'username' => getenv('DB_USERNAME') ?: 'app_user',
    'password' => getenv('DB_PASSWORD') ?: 'app_password'
];

$logger = [];

// Цвета для вывода в консоль
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

function printMessage(string $message, $type = 'info') {
    global $colors;
    switch ($type) {
        case 'success':
            echo $colors['green'] . "✅ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'error':
            echo $colors['red'] . "❌ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'warning':
            echo $colors['yellow'] . "⚠️  " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'info':
            echo $colors['blue'] . "ℹ️  " . $message . $colors['reset'] . PHP_EOL;
            break;
        default:
            echo $message . PHP_EOL;
    }
}

function executeSQL(PDO $pdo, string $sql, string $description) {
    try {
        $pdo->exec($sql);
        printMessage($description . ' - выполнено', 'success');
        return true;
    } catch (PDOException $e) {
        printMessage($description . ' - ошибка: ' . $e->getMessage(), 'error');
        return false;
    }
}

function executeSQLFile(PDO $pdo, string $filePath, string $description) {
    try {
        if (!file_exists($filePath)) {
            printMessage("Файл не найден: " . $filePath, 'error');
            return false;
        }
        
        $sql = file_get_contents($filePath);
        
        // Разделяем SQL на отдельные запросы (по точке с запятой)
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        $success = true;
        foreach ($queries as $query) {
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                } catch (PDOException $e) {
                    // Игнорируем ошибки "already exists" для таблиц
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        printMessage("Ошибка в запросе: " . substr($query, 0, 100) . "...", 'warning');
                    }
                }
            }
        }
        
        printMessage($description . ' - выполнено', 'success');
        return true;
    } catch (Exception $e) {
        printMessage($description . ' - ошибка: ' . $e->getMessage(), 'error');
        return false;
    }
}

// Главная функция миграции
function runMigration() {
    global $config, $logger;
    
    printMessage("========================================", 'info');
    printMessage("    ЗАПУСК МИГРАЦИИ БАЗЫ ДАННЫХ", 'info');
    printMessage("========================================", 'info');
    echo PHP_EOL;
    
    // Подключаемся к MySQL без базы данных
    try {
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        printMessage("Подключение к MySQL установлено", 'success');
    } catch (PDOException $e) {
        printMessage("Ошибка подключения к MySQL: " . $e->getMessage(), 'error');
        return false;
    }
    
    // Создаем базу данных если не существует
    $dbName = $config['database'];
    executeSQL($pdo, "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", "Создание базы данных `{$dbName}`");
    executeSQL($pdo, "USE `{$dbName}`", "Переключение на базу данных `{$dbName}`");
    
    echo PHP_EOL;
    printMessage("========================================", 'info');
    printMessage("    СОЗДАНИЕ ТАБЛИЦ", 'info');
    printMessage("========================================", 'info');
    echo PHP_EOL;
    
    // 1. Таблица ролей
    $sql = "
    CREATE TABLE IF NOT EXISTS roles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name ENUM('user', 'admin') DEFAULT 'user'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'roles'");
    
    // 2. Таблица университетов
    $sql = "
    CREATE TABLE IF NOT EXISTS universities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        address VARCHAR(255),
        phone VARCHAR(50),
        email VARCHAR(100),
        website VARCHAR(255),
        logo VARCHAR(255),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'universities'");
    
    // 3. Таблица факультетов
    $sql = "
    CREATE TABLE IF NOT EXISTS faculties (
        id INT PRIMARY KEY AUTO_INCREMENT,
        university_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(100),
        dean_name VARCHAR(255),
        dean_email VARCHAR(100),
        phone VARCHAR(50),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
        INDEX idx_university_id (university_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'faculties'");
    
    // 4. Таблица групп
    $sql = "
    CREATE TABLE IF NOT EXISTS groups_students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        faculty_id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        course INT NOT NULL,
        semester INT NOT NULL,
        year_of_study INT,
        headman_id INT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE,
        INDEX idx_faculty_id (faculty_id),
        INDEX idx_course (course)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'groups_students'");
    
    // 5. Таблица пользователей
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        university_id INT,
        faculty_id INT,
        group_id INT,
        phone VARCHAR(50),
        city VARCHAR(100),
        birth_date DATE,
        gender ENUM('male', 'female', 'other') DEFAULT 'other',
        role_id INT,
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
        FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
        FOREIGN KEY (group_id) REFERENCES groups_students(id) ON DELETE SET NULL,
        INDEX idx_email (email),
        INDEX idx_role_id (role_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'users'");
    
    // 6. Таблица предметов
    $sql = "
    CREATE TABLE IF NOT EXISTS subjects (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        short_name VARCHAR(50),
        description TEXT,
        hours INT DEFAULT 0,
        code VARCHAR(20),
        semester INT DEFAULT 1,
        specialty VARCHAR(100),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'subjects'");
    
    // 7. Таблица преподавателей
    $sql = "
    CREATE TABLE IF NOT EXISTS teachers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        patronymic VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        position VARCHAR(100),
        department VARCHAR(100),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'teachers'");
    
    // 8. Таблица аудиторий
    $sql = "
    CREATE TABLE IF NOT EXISTS rooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        number VARCHAR(20) NOT NULL,
        building VARCHAR(100),
        capacity INT DEFAULT 0,
        type ENUM('lecture', 'lab', 'practice') DEFAULT 'lecture',
        description TEXT,
        has_computer TINYINT(1) DEFAULT 0,
        has_projector TINYINT(1) DEFAULT 0,
        has_board TINYINT(1) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'rooms'");
    
    // 9. Таблица расписания
    $sql = "
    CREATE TABLE IF NOT EXISTS schedule (
        id INT PRIMARY KEY AUTO_INCREMENT,
        group_id INT NOT NULL,
        subject_id INT NOT NULL,
        teacher_id INT,
        room_id INT,
        day_of_week INT NOT NULL COMMENT '1-6: ПН-СБ',
        lesson_number INT NOT NULL COMMENT '1-5: номер пары',
        week_start DATE,
        week_end DATE,
        is_even_week TINYINT(1) COMMENT 'NULL - каждая неделя, 0 - нечетная, 1 - четная',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_students(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
        INDEX idx_group_id (group_id),
        INDEX idx_day_lesson (day_of_week, lesson_number),
        INDEX idx_week_dates (week_start, week_end),
        INDEX idx_even_week (is_even_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'schedule'");
    
    // 10. Таблица новостей
    $sql = "
    CREATE TABLE IF NOT EXISTS news (
        id INT PRIMARY KEY AUTO_INCREMENT,
        university_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        excerpt VARCHAR(500),
        image VARCHAR(255),
        published BOOLEAN DEFAULT TRUE,
        published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE,
        INDEX idx_university_id (university_id),
        INDEX idx_published (published),
        INDEX idx_published_at (published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'news'");
    
    // 11. Таблица просмотров новостей
    $sql = "
    CREATE TABLE IF NOT EXISTS news_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        news_id INT NOT NULL,
        user_id INT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        session_id VARCHAR(128),
        FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_news_id (news_id),
        INDEX idx_user_id (user_id),
        INDEX idx_ip_address (ip_address),
        INDEX idx_viewed_at (viewed_at),
        INDEX idx_news_user_date (news_id, user_id, viewed_at),
        INDEX idx_news_ip_date (news_id, ip_address, viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'news_views'");
    
    // 12. Таблица экзаменов
    $sql = "
    CREATE TABLE IF NOT EXISTS exams (
        id INT PRIMARY KEY AUTO_INCREMENT,
        group_id INT NOT NULL,
        subject_id INT NOT NULL,
        teacher_id INT,
        room_id INT,
        exam_date DATE NOT NULL,
        exam_time TIME,
        exam_type ENUM('exam', 'credit') DEFAULT 'exam',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_students(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
        INDEX idx_group_id (group_id),
        INDEX idx_exam_date (exam_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'exams'");
    
    // 13. Таблица домашних заданий
    $sql = "
    CREATE TABLE IF NOT EXISTS homework (
        id INT PRIMARY KEY AUTO_INCREMENT,
        group_id INT NOT NULL,
        subject_id INT NOT NULL,
        teacher_id INT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path VARCHAR(255),
        deadline DATE,
        max_score INT DEFAULT 100,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups_students(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
        INDEX idx_group_id (group_id),
        INDEX idx_subject_id (subject_id),
        INDEX idx_deadline (deadline)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'homework'");
    
    // 14. Таблица выполненных заданий
    $sql = "
    CREATE TABLE IF NOT EXISTS homework_submissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        homework_id INT NOT NULL,
        user_id INT NOT NULL,
        file_path VARCHAR(255),
        comment TEXT,
        score INT DEFAULT 0,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        graded_at TIMESTAMP NULL,
        teacher_comment TEXT,
        FOREIGN KEY (homework_id) REFERENCES homework(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_submission (homework_id, user_id),
        INDEX idx_homework_id (homework_id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'homework_submissions'");
    
    // 15. Таблица посещаемости
    $sql = "
    CREATE TABLE IF NOT EXISTS attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        schedule_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
        comment TEXT,
        marked_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (schedule_id) REFERENCES schedule(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_attendance (user_id, schedule_id, date),
        INDEX idx_user_id (user_id),
        INDEX idx_schedule_id (schedule_id),
        INDEX idx_date (date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    executeSQL($pdo, $sql, "Таблица 'attendance'");
    
    echo PHP_EOL;
    printMessage("========================================", 'info');
    printMessage("    ЗАПОЛНЕНИЕ ДАННЫХ", 'info');
    printMessage("========================================", 'info');
    echo PHP_EOL;
    
    // Заполнение ролей
    $sql = "INSERT IGNORE INTO roles (name) VALUES ('admin'), ('user')";
    executeSQL($pdo, $sql, "Роли (admin, user)");
    
    // Заполнение университетов
    $sql = "
    INSERT IGNORE INTO universities (name, short_name, address, phone, email, website, description) VALUES
    ('Московский государственный технический университет им. Н.Э. Баумана', 'МГТУ им. Баумана', 'г. Москва, ул. 2-я Бауманская, д. 5', '+7 (495) 263-63-00', 'info@bmstu.ru', 'https://bmstu.ru', 'Ведущий технический вуз России'),
    ('Московский государственный университет им. М.В. Ломоносова', 'МГУ', 'г. Москва, Ленинские горы, д. 1', '+7 (495) 939-10-00', 'info@msu.ru', 'https://msu.ru', 'Классический университет с богатой историей'),
    ('Санкт-Петербургский политехнический университет Петра Великого', 'СПбПУ', 'г. Санкт-Петербург, ул. Политехническая, д. 29', '+7 (812) 552-76-12', 'info@spbstu.ru', 'https://spbstu.ru', 'Крупнейший технический университет на Северо-Западе'),
    ('Казанский федеральный университет', 'КФУ', 'г. Казань, ул. Кремлевская, д. 18', '+7 (843) 233-71-09', 'info@kpfu.ru', 'https://kpfu.ru', 'Один из старейших университетов России'),
    ('Новосибирский государственный университет', 'НГУ', 'г. Новосибирск, ул. Пирогова, д. 2', '+7 (383) 363-40-00', 'info@nsu.ru', 'https://nsu.ru', 'Ведущий научно-образовательный центр Сибири'),
    ('Технический колледж информатики и вычислительной техники', 'ТКИВТ', 'г. Москва, ул. Студенческая, д. 1', '+7 (495) 123-45-67', 'info@tkivt.ru', 'https://tkivt.ru', 'Современный колледж IT-направлений')";
    executeSQL($pdo, $sql, "Университеты");
    
    // Заполнение факультетов
    $sql = "
    INSERT IGNORE INTO faculties (university_id, name, short_name, dean_name, dean_email, phone, description) VALUES
    (1, 'Факультет информатики и систем управления', 'ИУ', 'Иванов Сергей Петрович', 'dean.iu@bmstu.ru', '+7 (495) 263-64-00', 'Подготовка IT-специалистов высшего уровня'),
    (1, 'Факультет радиоэлектроники и лазерной техники', 'РЛ', 'Петров Александр Владимирович', 'dean.rl@bmstu.ru', '+7 (495) 263-65-00', 'Разработка радиоэлектронных систем'),
    (1, 'Факультет фундаментальных наук', 'ФН', 'Сидоров Михаил Игоревич', 'dean.fn@bmstu.ru', '+7 (495) 263-66-00', 'Математическое и естественно-научное образование'),
    (2, 'Факультет вычислительной математики и кибернетики', 'ВМК', 'Кузнецова Елена Владимировна', 'dean.vmk@msu.ru', '+7 (495) 939-35-00', 'Математическое моделирование и компьютерные науки'),
    (2, 'Физический факультет', 'Физфак', 'Смирнов Алексей Дмитриевич', 'dean.physics@msu.ru', '+7 (495) 939-10-00', 'Физика и астрономия'),
    (2, 'Факультет психологии', 'Психфак', 'Новикова Татьяна Сергеевна', 'dean.psy@msu.ru', '+7 (495) 939-57-00', 'Психологические науки'),
    (3, 'Институт компьютерных наук и технологий', 'ИКНТ', 'Васильев Дмитрий Константинович', 'dean.iknt@spbstu.ru', '+7 (812) 552-76-00', 'Разработка программного обеспечения'),
    (3, 'Институт физики', 'Физтех', 'Попов Константин Андреевич', 'dean.physics@spbstu.ru', '+7 (812) 552-76-01', 'Физика и нанотехнологии'),
    (4, 'Институт вычислительной математики и информационных технологий', 'ИВМиИТ', 'Козлова Ирина Михайловна', 'dean.ivmit@kpfu.ru', '+7 (843) 233-71-00', 'Информационные технологии'),
    (5, 'Механико-математический факультет', 'ММФ', 'Морозов Владимир Петрович', 'dean.mmf@nsu.ru', '+7 (383) 363-45-00', 'Математика и механика')";
    executeSQL($pdo, $sql, "Факультеты");
    
    // Заполнение групп
    $sql = "
    INSERT IGNORE INTO groups_students (faculty_id, name, course, semester, year_of_study, description) VALUES
    (1, 'ИУ-21', 3, 6, 2024, 'Группа по направлению \"Информатика и вычислительная техника\"'),
    (1, 'ИУ-22', 3, 6, 2024, 'Группа по направлению \"Программная инженерия\"'),
    (1, 'ИУ-23', 2, 4, 2024, 'Группа по направлению \"Информационные системы\"'),
    (1, 'ИУ-24', 1, 2, 2025, 'Младшая группа по информатике'),
    (1, 'ИУ-25', 4, 8, 2024, 'Выпускная группа по системному анализу'),
    (2, 'РЛ-21', 3, 6, 2024, 'Радиоэлектронные системы и комплексы'),
    (2, 'РЛ-22', 2, 4, 2024, 'Лазерная техника'),
    (3, 'ФН-21', 3, 6, 2024, 'Прикладная математика'),
    (3, 'ФН-22', 2, 4, 2024, 'Математическое моделирование'),
    (4, 'ВМК-21', 3, 6, 2024, 'Вычислительная математика'),
    (4, 'ВМК-22', 2, 4, 2024, 'Кибернетика'),
    (5, 'Физ-21', 3, 6, 2024, 'Фундаментальная физика'),
    (7, 'ИКНТ-21', 3, 6, 2024, 'Программирование'),
    (7, 'ИКНТ-22', 2, 4, 2024, 'IT-технологии'),
    (9, 'ИВМиИТ-21', 3, 6, 2024, 'Информатика'),
    (9, 'ИВМиИТ-22', 2, 4, 2024, 'Математическое обеспечение'),
    (10, 'ММФ-21', 3, 6, 2024, 'Математика'),
    (10, 'ММФ-22', 2, 4, 2024, 'Прикладная математика')";
    executeSQL($pdo, $sql, "Группы");
    
    // Создание тестового администратора (пароль: admin123)
    $password_hash = password_hash('password123', PASSWORD_DEFAULT);
    $sql = "INSERT IGNORE INTO users (email, password_hash, name, role_id, is_active) VALUES 
            ('admin@example.com', '{$password_hash}', 'Администратор', (SELECT id FROM roles WHERE name = 'admin'), 1)";
    executeSQL($pdo, $sql, "Тестовый администратор (admin@example.com / admin123)");
    
    // Создание тестового студента (пароль: student123)
    $password_hash = password_hash('password123', PASSWORD_DEFAULT);
    $sql = "INSERT IGNORE INTO users (email, password_hash, name, role_id, group_id, is_active) VALUES 
            ('student@example.com', '{$password_hash}', 'Иван Петров', (SELECT id FROM roles WHERE name = 'user'), (SELECT id FROM groups_students WHERE name = 'ИУ-21' LIMIT 1), 1)";
    executeSQL($pdo, $sql, "Тестовый студент (student@example.com / student123)");
    
    // Заполнение предметов
    $sql = "
    INSERT IGNORE INTO subjects (name, short_name, hours) VALUES
    ('Высшая математика', 'ВМ', 120),
    ('Программирование', 'ПРОГ', 150),
    ('Базы данных', 'БД', 100),
    ('Web-технологии', 'WEB', 80),
    ('Операционные системы', 'ОС', 90),
    ('Физика', 'ФИЗ', 100),
    ('Английский язык', 'АНГЛ', 60),
    ('Философия', 'ФИЛ', 50),
    ('Дискретная математика', 'ДМ', 80),
    ('Компьютерные сети', 'КС', 90),
    ('Алгоритмы и структуры данных', 'АСД', 100),
    ('Физическая культура', 'ФИЗК', 40)";
    executeSQL($pdo, $sql, "Предметы");
    
    // Заполнение преподавателей
    $sql = "
    INSERT IGNORE INTO teachers (name, last_name, patronymic, email) VALUES
    ('Александр', 'Иванов', 'Алексеевич', 'a.ivanov@college.ru'),
    ('Борис', 'Петров', 'Владимирович', 'b.petrov@college.ru'),
    ('Елена', 'Сидорова', 'Михайловна', 'e.sidorova@college.ru'),
    ('Сергей', 'Козлов', 'Николаевич', 's.kozlov@college.ru'),
    ('Владимир', 'Морозов', 'Константинович', 'v.morozov@college.ru'),
    ('Дмитрий', 'Соколов', 'Петрович', 'd.sokolov@college.ru'),
    ('Наталья', 'Белова', 'Сергеевна', 'n.belova@college.ru'),
    ('Ирина', 'Новикова', 'Александровна', 'i.novikova@college.ru'),
    ('Михаил', 'Кузнецов', 'Игоревич', 'm.kuznetsov@college.ru'),
    ('Ольга', 'Павлова', 'Андреевна', 'o.pavlova@college.ru')";
    executeSQL($pdo, $sql, "Преподаватели");
    
    // Заполнение аудиторий
    $sql = "
    INSERT IGNORE INTO rooms (number, building, capacity, type) VALUES
    ('101', 'Главный корпус', 30, 'lecture'),
    ('102', 'Главный корпус', 25, 'lecture'),
    ('103', 'Главный корпус', 20, 'practice'),
    ('201', 'Главный корпус', 35, 'lecture'),
    ('202', 'Главный корпус', 30, 'lecture'),
    ('301', 'Главный корпус', 25, 'practice'),
    ('401', 'Лабораторный корпус', 20, 'lab'),
    ('402', 'Лабораторный корпус', 15, 'lab'),
    ('403', 'Лабораторный корпус', 25, 'lab'),
    ('404', 'Лабораторный корпус', 20, 'lab'),
    ('501', 'Спортивный корпус', 50, 'practice')";
    executeSQL($pdo, $sql, "Аудитории");
    
    // Заполнение новостей
    $sql = "
    INSERT IGNORE INTO news (university_id, title, content, excerpt, published, published_at) VALUES
    (1, 'Открытие новой лаборатории робототехники', 
     'В МГТУ им. Баумана состоялось торжественное открытие новой лаборатории робототехники. Лаборатория оснащена современным оборудованием и позволит студентам заниматься исследованиями в области робототехники и искусственного интеллекта.', 
     'Новая лаборатория робототехники открыта в МГТУ', 1, NOW()),
    (1, 'Студенты Бауманки победили на международной олимпиаде', 
     'Студенты МГТУ им. Баумана заняли первое место на международной олимпиаде по программированию. Поздравляем наших студентов и преподавателей с блестящей победой!', 
     'Победа студентов Бауманки на олимпиаде', 1, NOW()),
    (2, 'МГУ вошел в топ-100 лучших университетов мира', 
     'Московский государственный университет им. М.В. Ломоносова вошел в топ-100 лучших университетов мира по версии международного рейтинга. Это признание высокого качества образования и научных исследований.', 
     'МГУ вошел в топ-100 мировых университетов', 1, NOW()),
    (2, 'День открытых дверей в МГУ', 
     'Приглашаем абитуриентов и их родителей на День открытых дверей МГУ. Вы сможете познакомиться с факультетами, пообщаться с преподавателями и узнать о правилах поступления.', 
     'День открытых дверей в МГУ', 1, NOW()),
    (3, 'Политех создал инновационный материал', 
     'Ученые Санкт-Петербургского политехнического университета разработали новый композитный материал, который может использоваться в авиастроении и машиностроении.', 
     'Инновационный материал создан в Политехе', 1, NOW())";
    executeSQL($pdo, $sql, "Новости");
    
    echo PHP_EOL;
    printMessage("========================================", 'info');
    printMessage("    МИГРАЦИЯ ЗАВЕРШЕНА УСПЕШНО!", 'success');
    printMessage("========================================", 'info');
    echo PHP_EOL;
    
    printMessage("Тестовые учетные записи:", 'info');
    printMessage("Администратор: admin@example.com / password123", 'success');
    printMessage("Студент: student@example.com / password123", 'success');
    echo PHP_EOL;
    
    return true;
}

// Запуск миграции
if (php_sapi_name() === 'cli') {
    runMigration();
} else {
    echo "Этот скрипт предназначен для запуска из командной строки!";
}
