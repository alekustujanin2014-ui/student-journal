FROM php:8.1-fpm-alpine

# Установка зависимостей
RUN apk update && apk add --no-cache \
    mysql-client \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        opcache

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка sendmail для Mailhog
RUN echo 'sendmail_path = "/usr/sbin/sendmail -t -i"' > /usr/local/etc/php/conf.d/mail.ini

# Настройка PHP для разработки
RUN echo 'display_errors = On' > /usr/local/etc/php/conf.d/custom.ini && \
    echo 'error_reporting = E_ALL' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'log_errors = On' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'session.save_path = /var/lib/php/sessions' >> /usr/local/etc/php/conf.d/custom.ini && \
    echo 'date.timezone = Europe/Moscow' >> /usr/local/etc/php/conf.d/custom.ini

# Создание необходимых директорий
RUN mkdir -p /var/lib/php/sessions \
    && chmod 777 /var/lib/php/sessions

WORKDIR /var/www/html

# Копируем файлы проекта
COPY src/ /var/www/html/

# Устанавливаем права (запускаем от root)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/html/cache \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/homework \
    && chmod -R 777 /var/www/html/homework/tasks

# Запускаем от root (FPM сам переключится на www-data)
EXPOSE 9000

CMD ["php-fpm"]
