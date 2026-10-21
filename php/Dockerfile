FROM php:8.4-fpm

# 使用国内源
RUN sed -i 's|http://deb.debian.org/debian|https://mirrors.tuna.tsinghua.edu.cn/debian|g' /etc/apt/sources.list.d/debian.sources 

RUN sed -i 's|http://security.debian.org/debian-security|https://mirrors.tuna.tsinghua.edu.cn/debian-security|g' /etc/apt/sources.list.d/debian.sources 

# 安装必要的扩展和工具
RUN apt-get update && apt-get install -y \
    procps \
    libzip-dev \
    unzip \
    libonig-dev \ 
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev


RUN docker-php-ext-install \
    mbstring \
    pdo_mysql \
    zip \
    pdo \
    gd \
    opcache \
    fileinfo

RUN    rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis

# 安装 Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 配置 Composer 国内镜像（阿里云镜像）
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

USER root

# 设置工作目录
WORKDIR /var/www/html