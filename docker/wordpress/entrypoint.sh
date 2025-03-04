#!/bin/bash
set -e

# Output environment variables for debugging (excluding passwords)
echo "ENVIRONMENT VARIABLES:"
env | grep -v PASSWORD | sort

# First run the original WordPress entrypoint
source /usr/local/bin/docker-entrypoint.sh "$@" &

# Test database connectivity
echo "Testing database connectivity..."
DB_HOST=${WORDPRESS_DB_HOST}
DB_USER=${WORDPRESS_DB_USER}
DB_PASS=${WORDPRESS_DB_PASSWORD}
DB_NAME=${WORDPRESS_DB_NAME}
echo "Attempting to connect to database: ${DB_HOST}"

# Wait for database to be available
max_db_retries=30
db_count=0

# Wait for the database using mysqladmin now that we have the MySQL client tools
until mysqladmin ping -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" --silent; do
    if [ ${db_count} -eq ${max_db_retries} ]; then
        echo "Could not connect to database at ${DB_HOST}. Maximum attempts reached."
        exit 1
    fi
    db_count=$((db_count+1))
    sleep 5
    echo "Waiting for database (${db_count}/${max_db_retries})..."
done
echo "Successfully connected to database at ${DB_HOST}"

# Wait for WordPress to be available
echo "Waiting for WordPress to be ready..."
max_retries=30
count=0
until $(curl --output /dev/null --silent --head --fail http://localhost:80); do
    if [ ${count} -eq ${max_retries} ]; then
        echo "Maximum attempts reached. WordPress is not available."
        exit 1
    fi
    count=$((count+1))
    sleep 2
    echo "Waiting for WordPress... (${count}/${max_retries})"
done

# Wait a bit more to ensure WordPress is fully initialized
sleep 5

# Configure WordPress if not already configured
cd /var/www/html

# Set up external URLs for WordPress based on the database host
if [[ $WORDPRESS_DB_HOST == "mysql" ]]; then
    EXTERNAL_URL="http://localhost:9080"
else
    EXTERNAL_URL="http://localhost:9081"
fi

echo "WordPress external URL will be: ${EXTERNAL_URL}"

# Check if WordPress is already installed
echo "Checking if WordPress is installed..."
if ! $(wp core is-installed --allow-root); then
    echo "Setting up WordPress..."
    
    # Install WordPress
    # Use external URL to avoid internal container hostname issues
    wp core install \
        --url="${EXTERNAL_URL}" \
        --title="WPVDB Testing Site" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=admin@example.com \
        --allow-root
    
    # Install and activate required plugins
    echo "Installing Action Scheduler..."
    # We don't need to manually install Action Scheduler as it will be installed via Composer
    
    # Install WordPress Importer
    echo "Installing WordPress Importer..."
    wp plugin install wordpress-importer --activate --allow-root
    
    # Activate our plugin
    echo "Activating WPVDB plugin..."
    wp plugin activate wpvdb --allow-root
    
    # Run composer install for WPVDB plugin if composer.json exists
    if [ -f /var/www/html/wp-content/plugins/wpvdb/composer.json ]; then
        echo "Installing Composer dependencies for WPVDB plugin..."
        cd /var/www/html/wp-content/plugins/wpvdb
        if [ -x "$(command -v composer)" ]; then
            composer install
        else
            echo "Composer not available. Installing Composer..."
            curl -sS https://getcomposer.org/installer | php
            mv composer.phar /usr/local/bin/composer
            composer install
        fi
        cd /var/www/html
    fi
    
    # Import test data if available
    if [ -f /var/www/html/wp-content/setup/test-data.xml ]; then
        echo "Importing test data..."
        wp import /var/www/html/wp-content/setup/test-data.xml --authors=create --allow-root
        
        # Update URLs if needed
        echo "Updating imported URLs..."
        wp search-replace 'http://localhost:80' "${EXTERNAL_URL}" --all-tables --allow-root
        wp search-replace 'http://localhost:8000' "${EXTERNAL_URL}" --all-tables --allow-root
    fi
    
    echo "WordPress setup complete. You can access it at ${EXTERNAL_URL}"
    echo "Admin username: admin"
    echo "Admin password: password"
else
    echo "WordPress is already installed."
    # Update URLs to use external URL
    echo "Updating WordPress URLs to use external URL..."
    wp option update siteurl "${EXTERNAL_URL}" --allow-root
    wp option update home "${EXTERNAL_URL}" --allow-root
fi

# Create a test page to confirm functionality
echo "Creating test page..."
wp post create --post_type=page --post_title="Test Page" --post_content="This is a test page to confirm WordPress is working. You can access the site at ${EXTERNAL_URL}" --post_status=publish --allow-root

# Add configuration to wp-config.php for external URL
if ! grep -q "WPVDB_EXTERNAL_URL" /var/www/html/wp-config.php; then
    echo "Adding external URL reference to wp-config.php"
    echo "
/* WPVDB Docker Setup */
define('WPVDB_EXTERNAL_URL', '${EXTERNAL_URL}');
" >> /var/www/html/wp-config.php
fi

# Keep the container running
wait 