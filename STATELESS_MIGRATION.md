# Stateless Migration Guide - Laravel IAM Client v1.1.0

## Overview

Version 1.1.0 introduces a **stateless architecture** where user data is no longer synchronized to a local database. Instead, the package creates virtual `IAMUser` instances that exist only in memory and session.

## What Changed

### 1. No More Database Synchronization

**Before (v1.0.0):**
```php
// Users were synced to local database
$user = User::updateOrCreate(
    ['email' => $iamUser['email']],
    ['name' => $iamUser['name'], ...]
);
```

**After (v1.1.0):**
```php
// Virtual user instance created (no database)
$user = new IAMUser([
    'id' => $iamUser['id'],
    'name' => $iamUser['name'],
    ...
]);
```

### 2. Session-Based User Storage

**Before:**
- User data stored in `users` table
- Session stored only token and ID
- `retrieveById()` queried database

**After:**
- User data stored in session as `iam_user` array
- Session stores complete user info, token, permissions, roles
- `retrieveById()` reads from session only

### 3. Virtual User Model

Applications must now implement an `IAMUser` model that:
- Implements `Authenticatable` interface
- Does NOT extend Eloquent
- Stores data in memory only
- Provides helper methods for permissions/roles

## Migration Steps

### Step 1: Create IAMUser Model

Create `app/Models/IAMUser.php`:

```php
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;

class IAMUser implements Authenticatable
{
    use Notifiable;

    protected array $attributes = [];

    public function __construct(array $iamData)
    {
        $this->attributes = $iamData;
    }

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->attributes['id'] ?? null; }
    public function getAuthPassword(): string { return ''; }
    public function getAuthPasswordName() { return 'password'; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value) { }
    public function getRememberTokenName() { return null; }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    // IAM-specific helper methods
    public function hasIAMPermission(string $permission): bool
    {
        return in_array($permission, $this->attributes['permissions'] ?? []);
    }

    public function hasIAMRole(string $role): bool
    {
        $roles = $this->attributes['roles'] ?? [];
        foreach ($roles as $userRole) {
            if ((is_array($userRole) ? $userRole['name'] : $userRole) === $role) {
                return true;
            }
        }
        return false;
    }
}
```

### Step 2: Update Environment Variables

Update `.env`:

```env
IAM_USER_MODEL=App\Models\IAMUser
```

### Step 3: Update Auth Configuration

Update `config/auth.php`:

```php
'providers' => [
    'iam_users' => [
        'driver' => 'iam',
        'model' => App\Models\IAMUser::class,
    ],
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'iam_users',
    ],
    'iam' => [
        'driver' => 'iam',
        'provider' => 'iam_users',
    ],
],
```

### Step 4: Remove Foreign Key Constraints (Optional)

If you have tables with foreign keys to `users`:

```php
Schema::table('forms', function (Blueprint $table) {
    $table->dropForeign(['created_by']);
});

Schema::table('form_submissions', function (Blueprint $table) {
    $table->dropForeign(['user_id']);
});

// Keep the UUID columns, just remove the foreign key constraint
```

### Step 5: Drop Users Table (Optional)

If you want complete statelessness:

```php
Schema::dropIfExists('users');
Schema::dropIfExists('password_reset_tokens');
```

**Note:** Keep UUID columns in your tables that reference users. They will store IAM user IDs directly.

### Step 6: Update Policies

Change policy type hints from `User` to `Authenticatable`:

```php
// Before
use App\Models\User;

public function view(User $user, Form $form): bool
{
    return $form->created_by === $user->id;
}

// After
use Illuminate\Contracts\Auth\Authenticatable;

public function view(Authenticatable $user, Form $form): bool
{
    return $form->created_by === $user->getAuthIdentifier();
}
```

### Step 7: Update Model Relationships

Convert Eloquent relationships to virtual methods:

```php
// Before
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}

// After
public function creator(): ?IAMUser
{
    if (!$this->created_by) return null;

    $lookupService = app(\App\Services\IAMUserLookupService::class);
    return $lookupService->getUserById($this->created_by);
}
```

### Step 8: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## Benefits

1. **No Database Dependency**: Application doesn't need a `users` table
2. **Simplified Architecture**: Single source of truth (IAM service)
3. **Better Performance**: No database queries for user retrieval
4. **Easier Scaling**: Stateless applications scale horizontally better
5. **Reduced Sync Issues**: No stale user data in local database

## Session Structure

After login, the session contains:

```php
[
    'iam_user' => [
        'id' => 'uuid',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '0123456789',
        'department_id' => 'uuid',
        'position_id' => 'uuid',
        'status' => 'active',
    ],
    'iam_token' => 'jwt-token-here',
    'iam_permissions' => ['forms.create', 'forms.view', ...],
    'iam_roles' => ['admin', 'manager', ...],
]
```

## Breaking Changes

1. **User Model**: Must implement `Authenticatable` instead of extending Eloquent
2. **Database**: `users` table no longer used
3. **User Retrieval**: Must use session or IAM API, not database queries
4. **Policies**: Must use `Authenticatable` interface
5. **Relationships**: Must be converted to virtual methods

## Support for Existing Applications

If you need to maintain backward compatibility:

1. Keep the `users` table
2. Use a custom user provider that checks both session and database
3. Gradually migrate to stateless architecture

## Testing

After migration, test:

1. Login flow (email/password and phone/OTP)
2. Session persistence across requests
3. Permission and role checking
4. User data access in controllers/views
5. Policy authorization
6. Logout functionality

## Troubleshooting

### "Undefined table: users" Error

- Ensure all `updateOrCreate()` calls are removed
- Update to v1.1.0 of the package
- Clear all caches
- Verify `config/auth.php` uses `iam_users` provider

### User Not Found After Login

- Check session contains `iam_user` array
- Verify IAMUser model is correctly implemented
- Ensure `config/iam.php` has correct `user_model`

### Policies Not Working

- Update policy type hints to `Authenticatable`
- Use `getAuthIdentifier()` instead of `$user->id`
- Verify IAMUser has permission checking methods

## Version History

- **v1.0.0**: Database synchronization, local user records
- **v1.1.0**: Stateless architecture, virtual user instances
