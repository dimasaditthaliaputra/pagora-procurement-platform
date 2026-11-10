web: php artisan optimize && heroku-php-apache2 public/
release: php artisan migrate --force
worker: php artisan queue:work --tries=3 --max-time=3600
