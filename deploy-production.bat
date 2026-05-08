@echo off
echo.
echo ============================================
echo   REPETITIVEDOCS — PRODUCTION DEPLOY
echo   Target: https://repetitivedocs.com
echo ============================================
echo.

cd /d "C:\Users\paulg\OneDrive\Desktop\repetitivedocs.com"

echo [1/3] Committing and pushing to GitHub...
git add .
git diff --cached --quiet && echo No changes to commit. || git commit -m "Deploy %date% %time%"
git push origin main

echo.
echo [2/3] Deploying to production server...
ssh root@139.162.61.79 "cd /var/www/repetitivedocs && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction --no-progress && npm ci && npm run build && php artisan view:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && chown -R www-data:www-data storage bootstrap/cache && systemctl reload php8.3-fpm && supervisorctl restart repetitivedocs-worker: && echo DONE"

echo.
echo [3/3] Done!
echo Site is live at: https://repetitivedocs.com
echo.
pause
