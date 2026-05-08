#!/bin/bash
set -e

echo "=== RepetitiveDocs Server Setup ==="

# ── System update ─────────────────────────────────────────────
apt-get update -qq && apt-get upgrade -y -qq

# ── nginx ─────────────────────────────────────────────────────
apt-get install -y nginx

# ── PHP 8.3 + extensions ──────────────────────────────────────
apt-get install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt-get update -qq
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
    php8.3-tokenizer php8.3-ctype php8.3-fileinfo \
    php8.3-openssl php8.3-intl php8.3-readline \
    php8.3-gd php8.3-imagick

# ── Composer ──────────────────────────────────────────────────
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── Node.js 20 ────────────────────────────────────────────────
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# ── Git ───────────────────────────────────────────────────────
apt-get install -y git

# ── LibreOffice (for PDF → DOCX conversion) ───────────────────
apt-get install -y libreoffice libreoffice-writer

# ── Supervisor (for queue workers) ────────────────────────────
apt-get install -y supervisor

# ── Clone repo ────────────────────────────────────────────────
mkdir -p /var/www
cd /var/www
git clone https://github.com/PaulEspinas1989/repetitivedocs.git repetitivedocs
cd /var/www/repetitivedocs

# ── Environment file ──────────────────────────────────────────
cat > /var/www/repetitivedocs/.env << 'ENVEOF'
APP_NAME=RepetitiveDocs
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://repetitivedocs.com

DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.YOUR_SUPABASE_PROJECT_REF
DB_PASSWORD=YOUR_SUPABASE_DB_PASSWORD
DB_SSLMODE=require

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
LOG_LEVEL=error

FILESYSTEM_DISK=local
STORAGE_DISK=documents

BCRYPT_ROUNDS=12

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS="hello@repetitivedocs.com"
MAIL_FROM_NAME="RepetitiveDocs"

ANTHROPIC_API_KEY=YOUR_ANTHROPIC_API_KEY

PAYMONGO_PUBLIC_KEY=YOUR_PAYMONGO_PUBLIC_KEY
PAYMONGO_SECRET_KEY=YOUR_PAYMONGO_SECRET_KEY
PAYMONGO_WEBHOOK_SECRET=YOUR_PAYMONGO_WEBHOOK_SECRET

SANCTUM_STATEFUL_DOMAINS=repetitivedocs.com,www.repetitivedocs.com
ENVEOF

# ── Generate app key ──────────────────────────────────────────
php artisan key:generate

# ── Install dependencies ──────────────────────────────────────
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --prefer-offline
npm run build

# ── Laravel setup ─────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link --force

# ── Storage directories ───────────────────────────────────────
mkdir -p /var/www/repetitivedocs/storage/app/documents

# ── Permissions ───────────────────────────────────────────────
chown -R www-data:www-data /var/www/repetitivedocs
chmod -R 755 /var/www/repetitivedocs
chmod -R 775 /var/www/repetitivedocs/storage
chmod -R 775 /var/www/repetitivedocs/bootstrap/cache

# ── nginx config (production) ─────────────────────────────────
cat > /etc/nginx/sites-available/repetitivedocs << 'NGINXEOF'
server {
    listen 80;
    server_name repetitivedocs.com www.repetitivedocs.com;
    root /var/www/repetitivedocs/public;
    index index.php;

    client_max_body_size 256M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

# ── nginx config (staging on port 8080) ───────────────────────
cat > /etc/nginx/sites-available/repetitivedocs-staging << 'NGINXEOF2'
server {
    listen 8080;
    server_name _;
    root /var/www/repetitivedocs-staging/public;
    index index.php;

    client_max_body_size 256M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF2

ln -sf /etc/nginx/sites-available/repetitivedocs /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/repetitivedocs-staging /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── Supervisor: queue worker (production) ─────────────────────
cat > /etc/supervisor/conf.d/repetitivedocs-worker.conf << 'SUPEOF'
[program:repetitivedocs-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/repetitivedocs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/repetitivedocs/storage/logs/worker.log
stopwaitsecs=3600
SUPEOF

# ── Supervisor: queue worker (staging) ────────────────────────
cat > /etc/supervisor/conf.d/repetitivedocs-staging-worker.conf << 'SUPEOF2'
[program:repetitivedocs-staging-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/repetitivedocs-staging/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/repetitivedocs-staging/storage/logs/worker.log
stopwaitsecs=3600
SUPEOF2

supervisorctl reread
supervisorctl update
supervisorctl start repetitivedocs-worker:

# ── Auto-deploy script ────────────────────────────────────────
cat > /usr/local/bin/deploy-repetitivedocs.sh << 'DEPLOYEOF'
#!/bin/bash
cd /var/www/repetitivedocs
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --prefer-offline
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
chown -R www-data:www-data /var/www/repetitivedocs/storage
chown -R www-data:www-data /var/www/repetitivedocs/bootstrap/cache
supervisorctl restart repetitivedocs-worker:
echo "Deployed at $(date)"
DEPLOYEOF
chmod +x /usr/local/bin/deploy-repetitivedocs.sh

echo ""
echo "=== Setup Complete ==="
echo "Site is live at: http://YOUR_LINODE_IP"
echo "Staging at:      http://YOUR_LINODE_IP:8080"
echo ""
echo "Next steps:"
echo "  1. Point repetitivedocs.com DNS A record to this server IP in GoDaddy"
echo "  2. Fill in real credentials in /var/www/repetitivedocs/.env"
echo "  3. Run: certbot --nginx -d repetitivedocs.com -d www.repetitivedocs.com"
echo "  4. To redeploy: run /usr/local/bin/deploy-repetitivedocs.sh"
