#!/bin/sh
set -e

APP_DIR=/var/www/html
CONFIG_DIR="$APP_DIR/config"

# Only two modes are supported by SLiMS. Anything else falls back to production.
case "${ENV}" in
    development) ;;
    *) ENV=production ;;
esac
export ENV

# Write env.php and database.php from environment variables. Values are encoded
# with var_export so quotes or backslashes in a password cannot break the file.
# Nothing from these files is printed to the log.
umask 027
php <<'PHP'
<?php
$configDir = '/var/www/html/config';
$env = getenv('ENV');

$envFile = "<?php\n"
    . '$env = ' . var_export($env, true) . ";\n"
    . '$conditional_environment = ' . var_export($env, true) . ";\n"
    . "\$based_on_ip = false;\n"
    . "\$range_ip = [''];\n";
file_put_contents($configDir . '/env.php', $envFile);

$database = [
    'default_profile' => 'SLiMS',
    'proxy' => false,
    'nodes' => [
        'SLiMS' => [
            'host' => (string) getenv('DB_HOST'),
            'database' => (string) getenv('DB_NAME'),
            'port' => (string) (getenv('DB_PORT') ?: '3306'),
            'username' => (string) getenv('DB_USER'),
            'password' => (string) getenv('DB_PASS'),
            'options' => [
                'storage_engine' => 'MyISAM'
            ]
        ]
    ]
];
file_put_contents($configDir . '/database.php', "<?php\nreturn " . var_export($database, true) . ";\n");
PHP
umask 022
chown root:www-data "$CONFIG_DIR/env.php" "$CONFIG_DIR/database.php"
chmod 0640 "$CONFIG_DIR/env.php" "$CONFIG_DIR/database.php"
echo "Configuration written: config/env.php (ENV=${ENV}), config/database.php"

# SLiMS creates these from the samples at runtime, but config/ is not writable
# by www-data, which turns every request into HTTP 500. Create them here.
for name in csp auth; do
    if [ ! -f "$CONFIG_DIR/$name.php" ] && [ -f "$CONFIG_DIR/$name.sample.php" ]; then
        cp "$CONFIG_DIR/$name.sample.php" "$CONFIG_DIR/$name.php"
        echo "Configuration created from sample: config/$name.php"
    fi
done

# Wait for the database, but not forever.
DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-120}"
echo "Waiting for database (timeout ${DB_WAIT_TIMEOUT}s)..."
waited=0
until php -r "
mysqli_report(MYSQLI_REPORT_OFF);
\$conn = @new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'), (int)(getenv('DB_PORT') ?: 3306));
exit(\$conn->connect_error ? 1 : 0);
" 2>/dev/null; do
    if [ "$waited" -ge "$DB_WAIT_TIMEOUT" ]; then
        echo "ERROR: database not reachable after ${DB_WAIT_TIMEOUT}s. Check DB_HOST, DB_USER, DB_PASS and DB_NAME." >&2
        exit 1
    fi
    sleep 2
    waited=$((waited + 2))
done
echo "Database is ready."

# Randomize admin password on first startup only
INIT_FLAG="$APP_DIR/files/.admin_initialized"
if [ ! -f "$INIT_FLAG" ]; then
    ADMIN_PASS=$(openssl rand -base64 12 | tr -dc 'A-Za-z0-9' | head -c 16)
    export ADMIN_PASS
    ADMIN_HASH=$(php -r "echo password_hash(getenv('ADMIN_PASS'), PASSWORD_BCRYPT);")
    export ADMIN_HASH

    php -r "
\$conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'), (int)(getenv('DB_PORT') ?: 3306));
if (\$conn->connect_error) {
    echo 'DB connection failed: ' . \$conn->connect_error . PHP_EOL;
    exit(1);
}
\$stmt = \$conn->prepare('UPDATE user SET passwd=? WHERE username=?');
\$hash = getenv('ADMIN_HASH');
\$user = 'admin';
\$stmt->bind_param('ss', \$hash, \$user);
\$stmt->execute();
if (\$stmt->affected_rows > 0) {
    echo 'Admin password updated successfully.' . PHP_EOL;
} else {
    echo 'Warning: admin user not found or password unchanged.' . PHP_EOL;
}
\$stmt->close();
\$conn->close();
"

    touch "$INIT_FLAG"
    unset ADMIN_HASH

    echo ""
    echo "========================================================"
    echo "  SLIMS FIRST-TIME SETUP - ADMIN CREDENTIALS"
    echo "  Username : admin"
    echo "  Password : ${ADMIN_PASS}"
    echo "  Please change your password immediately after login!"
    echo "========================================================"
    echo ""

    unset ADMIN_PASS
else
    echo "Admin password already initialized, skipping."
fi

# Start the PHP application
exec "$@"
