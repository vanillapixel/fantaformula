# Fantasy Formula 1 - Docker Setup
FROM php:8.3-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Add centralized Apache CORS config
COPY backend/apache/cors.conf /etc/apache2/conf-available/cors.conf
RUN a2enconf cors

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p backend/database \
    && chown -R www-data:www-data backend/database \
    && chmod 775 backend/database

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/backend/database/info.php || exit 1

# Start Apache
CMD ["apache2-foreground"]
