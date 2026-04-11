# --- PHP + Apache + SteamCMD --------------------------------------------------
FROM php:8.2-apache

LABEL maintainer="Game Server Manager"
LABEL description="Steam Game Server Manager - PHP Edition"

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies
RUN dpkg --add-architecture i386 && \
    apt-get update && \
    apt-get install -y \
        ca-certificates \
        wget \
        curl \
        lib32gcc-s1 \
        git \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# SteamCMD
RUN mkdir -p /opt/steamcmd && \
    cd /opt/steamcmd && \
    wget -q https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz && \
    tar xf steamcmd_linux.tar.gz && \
    rm steamcmd_linux.tar.gz && \
    chmod +x /opt/steamcmd/steamcmd.sh

# Apache
RUN a2enmod rewrite
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# PHP config
RUN echo 'upload_max_filesize = 10M' > /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'post_max_size = 10M' >> /usr/local/etc/php/conf.d/uploads.ini

# Application
WORKDIR /var/www/html
COPY . .

# Directories
RUN mkdir -p /app/data/logs /app/data/uploads /opt/servers && \
    chown -R www-data:www-data /var/www/html /app /opt/servers /opt/steamcmd && \
    chmod -R 755 /var/www/html

ENV DATA_DIR=/app/data

EXPOSE 80
