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

# Figure out the external URL based on environment
if [[ $WORDPRESS_DB_HOST == "mysql" ]]; then
    SITE_URL="http://localhost:8080"
else
    SITE_URL="http://localhost:8081"
fi

echo "WordPress site URL will be: ${SITE_URL}"

# Check if WordPress is already installed
echo "Checking if WordPress is installed..."
if ! $(wp core is-installed --allow-root); then
    echo "Setting up WordPress..."
    
    # Install WordPress
    wp core install \
        --url=$SITE_URL \
        --title="WPVDB Testing Site" \
        --admin_user=admin \
        --admin_password=password \
        --admin_email=admin@example.com \
        --allow-root
    
    # Install and activate required plugins
    echo "Installing Action Scheduler..."
    mkdir -p /var/www/html/wp-content/plugins/action-scheduler
    cp -r /usr/src/action-scheduler/action-scheduler/* /var/www/html/wp-content/plugins/action-scheduler/
    wp plugin activate action-scheduler --allow-root
    
    # Install WordPress Importer
    echo "Installing WordPress Importer..."
    wp plugin install wordpress-importer --activate --allow-root
    
    # Activate our plugin
    echo "Activating WPVDB plugin..."
    wp plugin activate wpvdb --allow-root
    
    # Import test data if available
    if [ -f /var/www/html/wp-content/setup/test-data.xml ]; then
        echo "Importing test data..."
        wp import /var/www/html/wp-content/setup/test-data.xml --authors=create --allow-root
    fi
    
    echo "WordPress setup complete. You can access it at $SITE_URL"
    echo "Admin username: admin"
    echo "Admin password: password"
else
    echo "WordPress is already installed."
fi

# Create a test page to confirm functionality
echo "Creating test page..."
wp post create --post_type=page --post_title="Test Page" --post_content="This is a test page to confirm WordPress is working." --post_status=publish --allow-root

# Keep the container running
wait 