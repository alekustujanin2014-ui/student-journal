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

# Запуск
docker-compose up -d

# Миграция БД
docker-compose exec php php migrate.php
