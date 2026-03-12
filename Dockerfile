# Imagen PHP 8.1 + Apache para COMECyT (PostgreSQL)
FROM php:8.1-apache

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    postgresql-client \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    intl \
    opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar configuración PHP personalizada
COPY docker/php.ini /usr/local/etc/php/conf.d/comecyt.ini

# Copiar configuración Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Copiar el proyecto al contenedor
COPY . /var/www/html/

# Crear directorio de uploads y ajustar permisos
RUN mkdir -p /var/www/html/public/uploads/solicitudes \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/public/uploads

EXPOSE 80
CMD ["apache2-foreground"]
