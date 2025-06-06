FROM php:8.4-fpm

# 安装必要的扩展和工具
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libonig-dev \ 
    && docker-php-ext-install \
    mbstring \
    pdo_mysql \
    opcache \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 配置 Composer 国内镜像（阿里云镜像）
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 设置工作目录
WORKDIR /var/www/html