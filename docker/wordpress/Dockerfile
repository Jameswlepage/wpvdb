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

# Set up WordPress initialization scripts
COPY entrypoint.sh /usr/local/bin/wpvdb-entrypoint.sh
RUN chmod +x /usr/local/bin/wpvdb-entrypoint.sh

# Download and install Action Scheduler
RUN mkdir -p /usr/src/action-scheduler \
    && cd /usr/src/action-scheduler \
    && curl -L https://github.com/woocommerce/action-scheduler/archive/refs/tags/3.5.4.zip -o action-scheduler.zip \
    && unzip action-scheduler.zip \
    && rm action-scheduler.zip \
    && mv action-scheduler-3.5.4 action-scheduler

# Extend the entrypoint to run our setup script
ENTRYPOINT ["wpvdb-entrypoint.sh"]
CMD ["apache2-foreground"] 