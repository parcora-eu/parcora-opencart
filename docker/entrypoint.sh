#!/bin/sh
set -e

# Install OpenCart once; the install directory is removed afterwards.
if [ -d /var/www/html/install ]; then
    until mysqladmin ping -h db -uopencart -popencart --silent --skip-ssl; do sleep 1; done

    php /var/www/html/install/cli_install.php install \
        --username admin \
        --email admin@example.test \
        --password admin12345 \
        --http_server "${OPENCART_URL}" \
        --db_driver mysqli \
        --db_hostname db \
        --db_username opencart \
        --db_password opencart \
        --db_database opencart \
        --db_port 3306 \
        --db_prefix oc_

    rm -rf /var/www/html/install
    chown -R www-data:www-data /var/www/html
fi

exec docker-php-entrypoint "$@"
