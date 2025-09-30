# Quick Installation Guide

## Step 1: Add Package to Project

Add the package repository to your project's `composer.json`:

```bash
cd /path/to/your/laravel/project
```

Edit `composer.json` and add:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/adamus/laravel-iam-client"
        }
    ]
}
```

## Step 2: Require the Package

```bash
composer require adamus/laravel-iam-client
```

## Step 3: Run Installation

```bash
php artisan iam:install
```

## Step 4: Configure Environment

Add to your `.env`:

```env
IAM_BASE_URL=http://localhost:8002/api/v1
AUTH_GUARD=iam
IAM_TIMEOUT=10
IAM_VERIFY_SSL=true
```

## Step 5: Verify Installation

Check that these files were created:
- `config/iam.php` - Configuration file
- `resources/js/pages/auth/login.tsx` - Login component

Check that `config/auth.php` now includes IAM guard and provider.

## Step 6: Test Login

Start your Laravel server and navigate to `/login`. You should see the login page and be able to authenticate using your IAM credentials.

## Troubleshooting

### Package not found
Make sure the `url` in the repository configuration points to the correct path relative to your project.

### Autoload error
Run `composer dump-autoload` after requiring the package.

### Routes not registered
Clear your route cache: `php artisan route:clear`

### Login redirects to wrong URL
Check that `AUTH_GUARD=iam` is set in your `.env` file.

## Next Steps

Protect your routes with the `iam.auth` middleware:

```php
Route::middleware('iam.auth')->group(function () {
    Route::get('/dashboard', ...)->name('dashboard');
});
```

See README.md for complete documentation.