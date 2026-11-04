# Skuul V3 on Render

This is a deployment overlay for the official Skuul source in this repository.

## Included
- PHP 8.5 / Apache Docker image
- Node 24 Vite build
- Redis PHP extension
- Supervisor-managed web server, queue worker, and scheduler loop
- Render Blueprint
- Flysystem S3 adapter for Backblaze B2

## External services required
- MySQL 8-compatible database (Skuul requires MySQL; Supabase PostgreSQL is not a drop-in replacement)
- Redis endpoint
- Backblaze B2 bucket and bucket-scoped S3 application key

## Render setup
1. In Render, choose New > Blueprint and select this repository.
2. Review the `skuul` web service.
3. Fill all environment variables marked as needing a value.
4. Set `APP_KEY` to a generated Laravel key. Generate locally with:
   `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'`
5. Set `APP_URL` to the HTTPS URL Render assigns.
6. Set the database host, port, database, username and password exactly as provided by your MySQL host.
7. Set `REDIS_URL` to the Redis provider's TLS URL if supplied.
8. For Backblaze B2, use:
   - AWS_ACCESS_KEY_ID: B2 application key ID
   - AWS_SECRET_ACCESS_KEY: B2 application key
   - AWS_DEFAULT_REGION: bucket region (for example, us-west-004)
   - AWS_BUCKET: bucket name
   - AWS_ENDPOINT: https://s3.<region>.backblazeb2.com
   - AWS_USE_PATH_STYLE_ENDPOINT: true

Create a bucket-scoped B2 application key, not the account master key. Keep all credentials in Render environment variables; never commit them.

## First database initialization
After the first successful deployment, use the Render Shell. Confirm the database is empty and backed up before running:
```sh
php artisan migrate --force --no-interaction
php artisan db:seed --class=WorldSeeder --force --no-interaction
php artisan storage:link --no-interaction
```
Do not run these commands against an existing database without a verified backup. Do not run the default database seeder on a real school database.

## Important limitations
- Render's free web service can sleep while idle. Its queue worker and scheduler stop while the service sleeps.
- Render free services do not include persistent disks. User uploads must go to B2, and database data must live in the external MySQL service.
- `FILESYSTEM_DISK=s3` selects Laravel's S3 disk by default. Verify a test upload, download, and deletion. Any code that explicitly selects the `public` disk may still write to local storage and needs separate testing.
- This is a test deployment, not a production-ready host for real student records. Verify backups, restore, permissions, email, and privacy controls first.
