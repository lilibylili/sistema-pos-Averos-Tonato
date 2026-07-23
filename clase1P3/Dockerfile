FROM php:8.2-apache

# Instalar extensión pdo_mysql para Aiven
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite por si usas rutas
RUN a2enmod rewrite

# Copiar TODO el contenido de clase1P3 a la raíz web de Apache
COPY . /var/www/html/

# Ajustar permisos para que Apache lea los archivos sin dar 403
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
