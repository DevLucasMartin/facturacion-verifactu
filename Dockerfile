# ============================================================================
#  Sistema de Gestión de Facturas — PHP 8.2 + Apache
#  Incluye las extensiones necesarias para Verifactu / QR / Excel / PDF.
# ============================================================================
FROM php:8.2-apache

# --- Dependencias del sistema para compilar las extensiones PHP ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# --- Extensiones PHP ---
# (dom y openssl ya vienen habilitadas de serie en la imagen oficial)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        soap \
        intl

# --- Apache: mod_rewrite + mod_headers y AllowOverride All (.htaccess) ---
RUN a2enmod rewrite headers
COPY docker/apache/app.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app

# --- Ajustes de PHP (subida de certificados / generación de PDF) ---
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini

# La aplicación tiene rutas fijas bajo /SistemaGestionFacturas/.
# Se monta ahí mediante volumen en docker-compose (ver docker-compose.yml).
WORKDIR /var/www/html/SistemaGestionFacturas
