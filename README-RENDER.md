# Skuul V3 on Render

This repository includes a Docker/Render deployment setup for Skuul.

## Included
- PHP 8.5 + Apache container
- Node 24 Vite asset build
- Redis PHP extension
- Supervisor-managed Apache, Laravel queue worker, and scheduler
- Render Blueprint
- Flysystem S3 adapter for Backblaze B2

## Services you need
- A MySQL 8-compatible database. Skuul uses MySQL; Supabase PostgreSQL is not a drop-in replacement.
- A Redis service
- A Backblaze B2 bucket and S3 application key

## Deploy
1. In Render, select **New → Blueprint** and connect this repository.
2. Review the `skuul` web service and deploy it.
3. In the service's Environment settings, fill in every variable marked as needing a value.
4. Generate an app key locally with:
   `php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'`
   Paste the full output into `APP_KEY`.
5. Set `APP_URL` to the HTTPS URL Render gives your service.
6. Enter your MySQL host, port, database name, username, and password.
7. Enter the Redis connection settings required by the app. If your provider gives a Redis URL, use its host, port, and password in the corresponding Redis variables.
8. Configure Backblaze B2:
   - `AWS_ACCESS_KEY_ID`: B2 application key ID
   - `AWS_SECRET_ACCESS_KEY`: B2 application key
   - `AWS_DEFAULT_REGION`: your bucket's region, such as `us-west-004`
   - `AWS_BUCKET`: bucket name
   - `AWS_ENDPOINT`: `https://s3.<region>.backblazeb2.com`
   - `AWS_USE_PATH_STYLE_ENDPOINT`: `true`

Create a bucket-scoped B2 application key. Do not use your account master key, and never commit credentials to GitHub.

## Finish setup in Skuul
1. Open your Render URL. Skuul's installer is at `/install`.
2. Generate the application key if the installer asks.
3. Enter and test the MySQL database connection in the installer, then run its database setup step.
4. Complete the first administrator, organization, and campus setup.
5. Set up SMTP before relying on invitation or password-reset emails.
6. Upload a test file, then confirm it can be opened after a redeploy.

Do not run generic Laravel seed commands for this app's installation. The installer has its own database and world-data setup steps.

## Backblaze storage note
Laravel's default disk is set to S3/B2 in Render. The app also has a separate `public` disk configured as local storage in its source. If any Skuul feature explicitly writes to that `public` disk, those files will still be on Render's temporary filesystem. Test every upload feature before using real records, and we can adjust those paths to B2 if needed.

## Free Render limitations
- A free web service may sleep when idle.
- The queue worker and scheduler run inside the web container, so they also stop while the service sleeps.
- Render's free service does not provide a persistent disk. Keep the database external and file uploads in B2.

Treat this as a test deployment only. Do not store real student records until you have tested access controls, email, backups, restore, and file storage.