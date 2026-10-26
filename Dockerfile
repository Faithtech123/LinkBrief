FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/linkbrief-entrypoint
COPY backend/ /var/www/html/
RUN chmod +x /usr/local/bin/linkbrief-entrypoint \
    && chown -R www-data:www-data /var/www/html
EXPOSE 10000
ENTRYPOINT ["linkbrief-entrypoint"]
CMD ["apache2-foreground"]