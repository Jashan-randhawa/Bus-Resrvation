FROM php:8.1-apache

# Install MySQLi extension (required by the app)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite (useful for redirects)
RUN a2enmod rewrite

# Enable output buffering in PHP to prevent 'headers already sent' issues
RUN echo "output_buffering = 4096" > /usr/local/etc/php/conf.d/output-buffering.ini

# Copy project files into Apache's document root
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache listens on port 80 by default; Render injects PORT env var.
# Configure Apache to listen on the PORT env var instead of 80.
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

# Expose port (Render will assign dynamically)
EXPOSE 10000

# Start Apache in the foreground
CMD ["apache2-foreground"]
