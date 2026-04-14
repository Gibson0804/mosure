###############################################################################
# Mosure Production Dockerfile
# 构建流程：
# 1. vendor 阶段安装 PHP 依赖
# 2. frontend 阶段构建前端资源
# 3. app 阶段生成 PHP-FPM 镜像
# 4. nginx 阶段生成静态资源和 Nginx 配置
###############################################################################

# ---------- Composer / PHP 依赖 ----------
FROM php:8.2-cli AS vendor
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-progress \
    --no-interaction

COPY . .

# ---------- PHP-FPM 运行镜像 ----------
FROM php:8.2-fpm AS app

ARG WWWGROUP=www-data
ENV TZ=Asia/Shanghai

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        libicu-dev \
        libsqlite3-dev \
        sqlite3 \
        vim \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        gd \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /var/www/html /var/www/html
# 直接复制本地已构建的前端资源，跳过前端构建阶段
COPY public/build /var/www/html/public/build

# 增加 PHP 上传限制
RUN echo "upload_max_filesize = 128M" > /usr/local/etc/php/conf.d/upload.ini \
    && echo "post_max_size = 128M" >> /usr/local/etc/php/conf.d/upload.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/upload.ini

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R ${WWWGROUP}:${WWWGROUP} storage bootstrap/cache Plugins database \
    && chmod -R 775 Plugins \
    && chmod -R 775 database

VOLUME ["/var/www/html/storage", "/var/www/html/bootstrap/cache"]

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# ---------- Nginx 镜像 ----------
FROM nginx:1.25-alpine AS nginx

WORKDIR /var/www/html

COPY --from=app /var/www/html /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# 创建 storage 符号链接（Nginx 容器也需要）
RUN ln -sf /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80
