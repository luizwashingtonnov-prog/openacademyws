FROM php:8.2-apache

# Copia todo o projeto
COPY . /var/www/html/

# Define a pasta public como raiz do servidor
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Altera a configuração do Apache para usar /public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/!/var/www/html/public/!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

