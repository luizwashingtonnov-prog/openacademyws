FROM php:8.2-apache

# Copia o projeto
COPY . /var/www/html/

# Ajusta permissões para Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
