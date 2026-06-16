# Студенческий журнал

Веб-приложение для автоматизации учета успеваемости и посещаемости студентов.

## Технологии

- PHP 8.1
- MySQL 8.0
- jQuery
- Twig
- Docker

## Быстрый старт

```bash
# Клонирование
git clone <repository-url>
cd student-journal

# Создаем все папки разом
mkdir -p src/logs src/cache src/uploads src/homework/tasks

# Выполните в корне проекта, где лежит ваш docker-compose.yml
sudo chown -R port:port src/
# Выполните из корня проекта
sudo chown -R 777 src/logs src/cache src/uploads src/homework

# Запуск
docker-compose up -d

# Миграция БД
docker-compose exec php php migrate.php
