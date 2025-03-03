FROM wordpress:latest

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install required PHP extensions and MySQL client tools
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache for WordPress properly
RUN a2enmod rewrite
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '  AllowOverride All'; \
    echo '  Options -Indexes +FollowSymLinks'; \
    echo '  ServerSignature Off'; \
    echo '  UseCanonicalName Off'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/wordpress.conf && \
a2enconf wordpress

# Set up WordPress initialization scripts
COPY entrypoint.sh /usr/local/bin/wpvdb-entrypoint.sh
RUN chmod +x /usr/local/bin/wpvdb-entrypoint.sh

# Copy .htaccess file
COPY htaccess /var/www/html/.htaccess

# Extend the entrypoint to run our setup script
ENTRYPOINT ["wpvdb-entrypoint.sh"]
CMD ["apache2-foreground"] 