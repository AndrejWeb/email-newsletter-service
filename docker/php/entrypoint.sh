#!/bin/sh
set -e

echo "Waiting for PostgreSQL..."
until php -r "
try {
    \$url = getenv('DATABASE_URL') ?: 'postgresql://newsletter_user:newsletter_pass@db:5432/newsletter_db';
    \$parts = parse_url(\$url);
    \$host = \$parts['host'] ?? 'db';
    \$port = \$parts['port'] ?? 5432;
    \$dbname = ltrim(\$parts['path'] ?? '/newsletter_db', '/');
    \$dbname = explode('?', \$dbname)[0];
    \$user = \$parts['user'] ?? 'newsletter_user';
    \$pass = \$parts['pass'] ?? 'newsletter_pass';
    new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$dbname\", \$user, \$pass);
    echo 'OK';
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
  sleep 1
done
echo "PostgreSQL is ready!"

echo "Generating JWT keypair..."
mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction 2>/dev/null || true
fi

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>/dev/null || true

echo "Ensuring schema is up to date..."
php bin/console doctrine:schema:update --force --no-interaction 2>/dev/null || true

echo "Seeding database..."
SUBSCRIBER_COUNT=$(php -r "
try {
    \$url = getenv('DATABASE_URL') ?: 'postgresql://newsletter_user:newsletter_pass@db:5432/newsletter_db';
    \$parts = parse_url(\$url);
    \$host = \$parts['host'] ?? 'db';
    \$port = \$parts['port'] ?? 5432;
    \$dbname = ltrim(\$parts['path'] ?? '/newsletter_db', '/');
    \$dbname = explode('?', \$dbname)[0];
    \$user = \$parts['user'] ?? 'newsletter_user';
    \$pass = \$parts['pass'] ?? 'newsletter_pass';
    \$pdo = new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$dbname\", \$user, \$pass);
    \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM \\\"user\\\"\");
    echo \$stmt->fetchColumn();
} catch (Exception \$e) {
    echo '0';
}
" 2>/dev/null)

if [ "$SUBSCRIBER_COUNT" = "0" ] || [ -z "$SUBSCRIBER_COUNT" ]; then
    php bin/console doctrine:fixtures:load --no-interaction 2>/dev/null || true
else
    echo "Database already seeded ($SUBSCRIBER_COUNT users found), skipping..."
fi

echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-interaction 2>/dev/null || true

echo "Fixing cache permissions..."
mkdir -p /var/www/html/var/cache /var/www/html/var/log
chown -R www-data:www-data /var/www/html/var
chmod -R u+rwX,g+rwX /var/www/html/var

echo "Starting PHP-FPM..."
exec "$@"
