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

# Behind Cloudflare Tunnel every request comes from cloudflared. Take the client
# IP from CF-Connecting-IP, but only from the trusted proxy address, so the
# header cannot be spoofed through the published HTTP port.
REMOTEIP_CONF=/etc/apache2/conf-enabled/zz-remoteip.conf
case "${TRUSTED_PROXY}" in
    "") rm -f "$REMOTEIP_CONF" ;;
    *[!0-9A-Fa-f.:/\ ]*)
        echo "ERROR: TRUSTED_PROXY must be IP addresses or CIDR ranges, got: ${TRUSTED_PROXY}" >&2
        exit 1
        ;;
    *)
        printf 'RemoteIPHeader CF-Connecting-IP\nRemoteIPInternalProxy %s\n' "$TRUSTED_PROXY" > "$REMOTEIP_CONF"
        echo "Client IP taken from CF-Connecting-IP when sent by: ${TRUSTED_PROXY}"
        ;;
esac

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

# Randomize the admin password while it still has the public default hash from
# install/senayan.sql. Checked against the database (not a flag file in the files
# volume) so a fresh database volume is always rotated.
ADMIN_PASS=$(openssl rand -base64 12 | tr -dc 'A-Za-z0-9' | head -c 16)
export ADMIN_PASS
rc=0
php -r "
\$conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'), (int)(getenv('DB_PORT') ?: 3306));
\$default = '\$2y\$10\$pG0dqMrd2r39zRTSFSyp8.Z7sMy4cY7s/18UDQsV50Vn0TnR6UORm';
\$stmt = \$conn->prepare('UPDATE user SET passwd=? WHERE username=? AND passwd=?');
\$hash = password_hash(getenv('ADMIN_PASS'), PASSWORD_BCRYPT);
\$user = 'admin';
\$stmt->bind_param('sss', \$hash, \$user, \$default);
\$stmt->execute();
exit(\$stmt->affected_rows > 0 ? 0 : 3);
" || rc=$?

if [ "$rc" -ne 0 ] && [ "$rc" -ne 3 ]; then
    echo "ERROR: admin password check failed (exit $rc). Is the SLiMS schema loaded in $DB_NAME?" >&2
    exit 1
fi

if [ "$rc" -eq 0 ]; then
    echo ""
    echo "========================================================"
    echo "  SLIMS FIRST-TIME SETUP - ADMIN CREDENTIALS"
    echo "  Username : admin"
    echo "  Password : ${ADMIN_PASS}"
    echo "  Please change your password immediately after login!"
    echo "========================================================"
    echo ""
else
    echo "Admin password already initialized, skipping."
fi
unset ADMIN_PASS

# Start the PHP application
exec "$@"
