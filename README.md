# Heart 2 Mind Admin Panel

Heart 2 Mind Admin Panel is the Laravel backend and web dashboard used by the Heart 2 Mind mobile application.

## Requirements

- PHP 8.0 or later
- Composer
- MySQL or MariaDB
- XAMPP, WAMP, Laragon, or any local PHP/MySQL stack

## Project Path

If you are working on Windows with XAMPP, place this project inside:

```text
C:\xampp\htdocs\Maditam-Admin-SourceCode\install\install
```

## Install On Localhost

### 1. Prepare your environment

1. Install XAMPP on the `C:` drive.
2. Start `Apache` and `MySQL` from the XAMPP Control Panel.
3. Install Composer.
4. Extract the project archive.
5. Move the extracted project into your `htdocs` folder.

### 2. Create a database

1. Open `http://localhost/phpmyadmin`.
2. Click the `Databases` tab.
3. Create a new database for Heart 2 Mind.
4. If needed, use `utf8_general_ci` or your preferred UTF-8 collation.

### 3. Configure the application

Copy `.env.example` to `.env`, then update the important values:

```env
APP_NAME="Heart 2 Mind"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=maditam
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com

DEMO_USER=
DEMO_PASS=
FRONTEND_URL=http://127.0.0.1:8000
```

Update the values to match your machine and mail provider.

### 4. Run the installation commands

Open PowerShell or Terminal inside the project folder and run:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

### 5. Open the project

Use either of these URLs in your browser:

- `http://127.0.0.1:8000`
- `http://localhost:8000`

## Install On cPanel Hosting

1. Upload the project files to your hosting account.
2. Extract the archive inside your project directory.
3. Create a MySQL database and database user from cPanel.
4. Update the `.env` file with production database, mail, and app URL values.
5. Run these commands from the cPanel terminal inside the project root:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

6. Make sure your domain points to the Laravel `public` directory.
7. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.

Do not use `php artisan serve` on cPanel production hosting.

## Admin Login

After installation, sign in using the administrator account configured for your package or the account created during setup.

For security:

- change the admin password after first login
- do not keep demo credentials in production
- do not publish passwords in public documentation

## Post-Install Checklist

After the panel is running, review these areas from the dashboard:

1. Web settings
2. Mail configuration
3. Firebase configuration
4. SMS gateway configuration
5. Payment gateway configuration
6. Subscription plans
7. User roles and permissions

## Notes

- The mobile app uses this backend API.
- If you change the backend domain, also update the mobile app API base URL.
- If push notifications are required, upload the Firebase service account JSON from the admin panel.

## Completion

If all steps run successfully, the Heart 2 Mind admin panel is ready to use on your local machine or hosting server.
