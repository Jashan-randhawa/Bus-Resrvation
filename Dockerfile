# Dockerfile
FROM php:8.4-apache

ENV PORT=80

RUN docker-php-ext-install mysqli \
 && a2enmod rewrite headers deflate expires \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php-extra.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/apache-security.conf /etc/apache2/conf-available/security-extra.conf

# Listen on the PORT variable (Render sets it; 80 locally) and enable the security config
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername security-extra \
 && sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's#<VirtualHost \*:80>#<VirtualHost *:${PORT}>#' /etc/apache2/sites-available/000-default.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --chown=www-data:www-data . /var/www/html/

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
 CMD curl -fsS -o /dev/null "http://localhost:${PORT}/index.php" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
