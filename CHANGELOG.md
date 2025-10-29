# Changelog

All notable changes to `laravel-iam-client` will be documented in this file.

## [1.1.0] - 2025-10-29

### Changed
- **BREAKING**: Removed automatic user synchronization to local database
- IAMUserProvider now creates virtual user instances instead of database records
- `retrieveById()` now retrieves user from session data instead of database
- `retrieveByCredentials()` creates virtual IAMUser without database interaction
- `retrieveByIAMToken()` creates virtual IAMUser without database interaction

### Improved
- Session-based user retrieval for better performance (no IAM API call on every request)
- Complete stateless architecture - no local user data storage required
- Applications can now be 100% reliant on IAM service for user management
- Reduced database queries during authentication flow

### Migration Guide
To upgrade from v1.0.0 to v1.1.0:
1. Create an `IAMUser` model that implements `Authenticatable` interface (not Eloquent)
2. Remove foreign key constraints on user_id columns
3. Drop the `users` table if you want complete statelessness
4. Update `config/auth.php` to use IAMUser model
5. Ensure session stores `iam_user`, `iam_token`, `iam_permissions`, and `iam_roles`

See the updated README for implementation details.

## [1.0.0] - 2025-09-30

### Added
- Initial release
- IAM Guard implementation for Laravel authentication
- IAM User Provider for user management
- IAM Service for API communication with IAM system
- Two middleware options: `iam.auth` and `iam.authenticate`
- IAM Auth Controller with login, logout, and permission checking
- Automatic user synchronization from IAM to local database
- Session-based token storage with caching
- Permission and role checking with session caching
- Installation command for easy setup
- Pre-built Inertia.js + React login component
- Automatic route registration
- Configuration publishing
- Comprehensive documentation
- Auto-discovery for Laravel

### Features
- JWT token-based authentication
- Centralized user management via IAM service
- Local user record synchronization
- Permission checking (session cache + API fallback)
- Role checking (session cache + API fallback)
- Token refresh capability
- Logout from current session
- Logout from all sessions
- Request-level caching to prevent multiple API calls
- 1-minute token verification cache
- Inertia.js integration
- Guest middleware protection

### Security
- Session regeneration on login
- Encrypted session storage for tokens
- HTTPS support with SSL verification
- Bearer token authentication
- Placeholder passwords for IAM-managed users