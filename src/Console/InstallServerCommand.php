<?php

namespace Adamus\LaravelIamClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallServerCommand extends Command
{
    protected $signature = 'iam:install-server {--force : Overwrite existing files}';

    protected $description = 'Install IAM Server implementation (for running the IAM service)';

    public function handle(): void
    {
        $this->info('Installing Adamus IAM Server...');
        $this->newLine();

        // Publish migrations
        $this->publishMigrations();

        // Publish models
        $this->publishModels();

        // Publish controllers
        $this->publishControllers();

        // Publish routes
        $this->publishRoutes();

        // Publish config
        $this->publishConfig();

        // Install dependencies
        $this->installDependencies();

        // Setup environment
        $this->setupEnvironment();

        $this->newLine();
        $this->info('✓ IAM Server installed successfully!');
        $this->newLine();

        $this->displayNextSteps();
    }

    protected function publishMigrations(): void
    {
        $this->info('Publishing migrations...');

        $stubPath = __DIR__ . '/../../stubs/server/Migrations';
        $targetPath = database_path('migrations');

        if (!File::exists($stubPath)) {
            $this->error("Migrations stub directory not found: $stubPath");
            return;
        }

        $files = File::files($stubPath);

        foreach ($files as $file) {
            $targetFile = $targetPath . '/' . $file->getFilename();

            if (File::exists($targetFile) && !$this->option('force')) {
                $this->warn("  - Migration already exists: {$file->getFilename()}");
                continue;
            }

            File::copy($file->getPathname(), $targetFile);
            $this->line("  ✓ Published: {$file->getFilename()}");
        }
    }

    protected function publishModels(): void
    {
        $this->info('Publishing models...');

        $stubPath = __DIR__ . '/../../stubs/server/Models';
        $targetPath = app_path('Models');

        if (!File::exists($stubPath)) {
            $this->error("Models stub directory not found: $stubPath");
            return;
        }

        File::ensureDirectoryExists($targetPath);

        $files = File::files($stubPath);

        foreach ($files as $file) {
            $targetFile = $targetPath . '/' . $file->getFilename();

            if (File::exists($targetFile) && !$this->option('force')) {
                if ($this->confirm("  Model {$file->getFilename()} already exists. Overwrite?")) {
                    File::copy($file->getPathname(), $targetFile);
                    $this->line("  ✓ Overwritten: {$file->getFilename()}");
                } else {
                    $this->warn("  - Skipped: {$file->getFilename()}");
                }
                continue;
            }

            File::copy($file->getPathname(), $targetFile);
            $this->line("  ✓ Published: {$file->getFilename()}");
        }
    }

    protected function publishControllers(): void
    {
        $this->info('Publishing controllers...');

        $stubPath = __DIR__ . '/../../stubs/server/Controllers';
        $targetPath = app_path('Http/Controllers');

        if (!File::exists($stubPath)) {
            $this->error("Controllers stub directory not found: $stubPath");
            return;
        }

        // Copy Api controllers
        if (File::exists($stubPath . '/Api')) {
            File::ensureDirectoryExists($targetPath . '/Api');

            $files = File::files($stubPath . '/Api');

            foreach ($files as $file) {
                $targetFile = $targetPath . '/Api/' . $file->getFilename();

                if (File::exists($targetFile) && !$this->option('force')) {
                    $this->warn("  - Controller already exists: Api/{$file->getFilename()}");
                    continue;
                }

                File::copy($file->getPathname(), $targetFile);
                $this->line("  ✓ Published: Api/{$file->getFilename()}");
            }
        }
    }

    protected function publishRoutes(): void
    {
        $this->info('Publishing routes...');

        $stubPath = __DIR__ . '/../../stubs/server/routes/api.php';
        $targetPath = base_path('routes/api.php');

        if (!File::exists($stubPath)) {
            $this->error("Routes stub file not found: $stubPath");
            return;
        }

        if (File::exists($targetPath)) {
            if ($this->confirm('  routes/api.php already exists. Do you want to append IAM routes to it?')) {
                $existingContent = File::get($targetPath);
                $iamRoutes = File::get($stubPath);

                // Check if routes are already added
                if (str_contains($existingContent, '// IAM Server Routes')) {
                    $this->warn('  - IAM routes already exist in api.php');
                } else {
                    File::append($targetPath, "\n\n// IAM Server Routes\n" . $iamRoutes);
                    $this->line('  ✓ IAM routes appended to routes/api.php');
                }
            } else {
                $this->warn('  - Skipped routes/api.php');
            }
        } else {
            File::copy($stubPath, $targetPath);
            $this->line('  ✓ Published: routes/api.php');
        }
    }

    protected function publishConfig(): void
    {
        $this->info('Publishing configuration...');

        $stubPath = __DIR__ . '/../../stubs/server/config/iam-server.php';
        $targetPath = config_path('iam-server.php');

        if (!File::exists($stubPath)) {
            $this->error("Config stub file not found: $stubPath");
            return;
        }

        if (File::exists($targetPath) && !$this->option('force')) {
            $this->warn('  - Config file already exists: config/iam-server.php');
        } else {
            File::copy($stubPath, $targetPath);
            $this->line('  ✓ Published: config/iam-server.php');
        }
    }

    protected function installDependencies(): void
    {
        $this->info('Checking dependencies...');

        $requiredPackages = [
            'spatie/laravel-permission',
            'tymon/jwt-auth',
        ];

        $composerFile = base_path('composer.json');

        if (!File::exists($composerFile)) {
            $this->warn('  - composer.json not found. Please install required packages manually.');
            return;
        }

        $composer = json_decode(File::get($composerFile), true);
        $missingPackages = [];

        foreach ($requiredPackages as $package) {
            if (!isset($composer['require'][$package])) {
                $missingPackages[] = $package;
            }
        }

        if (empty($missingPackages)) {
            $this->line('  ✓ All required packages are installed');
        } else {
            $this->warn('  Missing packages: ' . implode(', ', $missingPackages));
            $this->newLine();
            $this->comment('  Please run:');
            $this->comment('  composer require ' . implode(' ', $missingPackages));
        }
    }

    protected function setupEnvironment(): void
    {
        $this->info('Environment configuration...');

        $envFile = base_path('.env');

        if (!File::exists($envFile)) {
            $this->warn('  - .env file not found');
            return;
        }

        $envContent = File::get($envFile);
        $updates = [];

        // Check JWT_SECRET
        if (!str_contains($envContent, 'JWT_SECRET=')) {
            $updates[] = 'JWT_SECRET=' . bin2hex(random_bytes(32));
        }

        // Check IAM mode
        if (!str_contains($envContent, 'IAM_MODE=')) {
            $updates[] = 'IAM_MODE=server';
        }

        if (!empty($updates)) {
            File::append($envFile, "\n# IAM Server Configuration\n" . implode("\n", $updates) . "\n");
            $this->line('  ✓ Environment variables added to .env');
        } else {
            $this->line('  ✓ Environment already configured');
        }
    }

    protected function displayNextSteps(): void
    {
        $this->comment('Next steps:');
        $this->line('  1. Run migrations: php artisan migrate');
        $this->line('  2. Publish Spatie Permission config: php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"');
        $this->line('  3. Create an admin user: php artisan tinker');
        $this->line('     User::create([\'name\' => \'Admin\', \'email\' => \'admin@example.com\', \'password\' => Hash::make(\'password\')])');
        $this->newLine();
        $this->comment('Your IAM server is ready to accept authentication requests!');
    }
}
