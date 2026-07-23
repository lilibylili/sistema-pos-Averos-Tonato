FROM php:8.2-apache

# Instalar extensión pdo_mysql para conectar con la base de datos
RUN docker-php-ext-install pdo pdo_mysql

# Copiar el código del backend a la carpeta web de Apache
COPY ./backend /var/www/html/

# Exponer el puerto 80
EXPOSE 80
