@echo off
echo.
echo ============================================
echo   REPETITIVEDOCS — LOCAL DEPLOY (Staging)
echo   Target: http://139.162.61.79:8080
echo   Visible to: You only
echo ============================================
echo.

cd /d "C:\Users\paulg\OneDrive\Desktop\repetitivedocs.com"

echo [1/2] Pushing code to GitHub (main branch)...
git add .
git diff --cached --quiet && echo No changes to commit. || git commit -m "Staging %date% %time%"
git push origin main

echo.
echo [2/2] Deploying to staging server...
ssh root@139.162.61.79 "cd /var/www/repetitivedocs-staging && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction --no-progress && npm ci && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && chown -R www-data:www-data storage bootstrap/cache && supervisorctl restart repetitivedocs-staging-worker: && echo DONE"

echo.
echo Staging is live at: http://139.162.61.79:8080
echo (Only visible to you)
echo.
pause
