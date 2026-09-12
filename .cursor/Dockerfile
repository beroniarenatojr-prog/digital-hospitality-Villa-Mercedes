FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# System packages: PHP 8.3 + the extensions Laravel 12 needs, MySQL 8, and build tooling.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip gnupg sudo \
        php8.3-cli php8.3-common php8.3-mbstring php8.3-xml php8.3-mysql \
        php8.3-sqlite3 php8.3-gd php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl \
        mysql-server \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 (Vite 6 / Tailwind build toolchain).
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer.
RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php

# Passwordless sudo for the agent user so install/start can manage the MySQL service.
RUN usermod -aG sudo ubuntu \
    && echo 'ubuntu ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/90-ubuntu \
    && chmod 0440 /etc/sudoers.d/90-ubuntu

USER ubuntu
