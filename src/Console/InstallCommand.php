<?php

namespace Adamus\LaravelIamClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'iam:install {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install the Adamus IAM Client package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Adamus IAM Client...');
        $this->newLine();

        // 1. Publish config
        $this->task('Publishing configuration', function () {
            $this->call('vendor:publish', [
                '--tag' => 'iam-config',
                '--force' => $this->option('force'),
            ]);
        });

        // 2. Publish views
        $this->task('Publishing login page component', function () {
            $this->call('vendor:publish', [
                '--tag' => 'iam-views',
                '--force' => $this->option('force'),
            ]);
        });

        // 3. Update auth config
        $this->task('Updating authentication configuration', function () {
            return $this->updateAuthConfig();
        });

        // 4. Check .env file
        $this->newLine();
        $this->checkEnvironmentVariables();

        // 5. Display next steps
        $this->newLine();
        $this->displayNextSteps();

        $this->newLine();
        $this->info('✓ Installation complete!');

        return self::SUCCESS;
    }

    /**
     * Update the authentication configuration
     */
    protected function updateAuthConfig(): bool
    {
        $authConfigPath = config_path('auth.php');

        if (!file_exists($authConfigPath)) {
            $this->warn('auth.php config file not found!');
            return false;
        }

        $content = file_get_contents($authConfigPath);

        // Check if IAM guard already exists
        if (Str::contains($content, "'iam' =>")) {
            $this->info('IAM guard already configured in auth.php');
            return true;
        }

        // Add IAM guard to guards array
        $guardConfig = <<<'PHP'
        'iam' => [
            'driver' => 'iam',
            'provider' => 'iam',
        ],
PHP;

        // Add IAM provider to providers array
        $providerConfig = <<<'PHP'
        'iam' => [
            'driver' => 'iam',
            'model' => App\Models\User::class,
        ],
PHP;

        // Insert guard after 'guards' => [
        $content = Str::replaceFirst(
            "'guards' => [",
            "'guards' => [\n\n" . $guardConfig,
            $content
        );

        // Insert provider after 'providers' => [
        $content = Str::replaceFirst(
            "'providers' => [",
            "'providers' => [\n\n" . $providerConfig,
            $content
        );

        file_put_contents($authConfigPath, $content);

        return true;
    }

    /**
     * Check and prompt for environment variables
     */
    protected function checkEnvironmentVariables(): void
    {
        $this->info('Environment Configuration:');
        $this->newLine();

        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        $variables = [
            'IAM_BASE_URL' => [
                'description' => 'The base URL of your IAM service API',
                'example' => 'http://localhost:8000/api/v1',
                'required' => true,
            ],
            'AUTH_GUARD' => [
                'description' => 'Default authentication guard',
                'example' => 'iam',
                'required' => true,
            ],
            'IAM_TIMEOUT' => [
                'description' => 'API request timeout in seconds',
                'example' => '10',
                'required' => false,
            ],
            'IAM_VERIFY_SSL' => [
                'description' => 'Verify SSL certificates for IAM API calls',
                'example' => 'true',
                'required' => false,
            ],
        ];

        $missing = [];

        foreach ($variables as $key => $config) {
            if (!Str::contains($envContent, $key . '=')) {
                $missing[] = $key;
                $required = $config['required'] ? ' (required)' : ' (optional)';
                $this->warn("⚠ {$key} not found in .env{$required}");
                $this->line("  {$config['description']}");
                $this->line("  Example: {$key}={$config['example']}");
                $this->newLine();
            } else {
                $this->info("✓ {$key} already configured");
            }
        }

        if (!empty($missing)) {
            $this->newLine();
            $this->warn('Please add the missing environment variables to your .env file.');
        }
    }

    /**
     * Display next steps
     */
    protected function displayNextSteps(): void
    {
        $this->info('Next Steps:');
        $this->newLine();

        $this->line('1. Configure your .env file with IAM service URL:');
        $this->line('   IAM_BASE_URL=http://your-iam-service.com/api/v1');
        $this->line('   AUTH_GUARD=iam');
        $this->newLine();

        $this->line('2. Make sure your User model exists at app/Models/User.php');
        $this->newLine();

        $this->line('3. The login route is automatically registered at /login');
        $this->newLine();

        $this->line('4. Protect your routes using the iam.auth middleware:');
        $this->line("   Route::middleware('iam.auth')->group(function () {");
        $this->line("       // Your protected routes here");
        $this->line('   });');
        $this->newLine();

        $this->line('5. Use the IAM guard in your application:');
        $this->line("   Auth::guard('iam')->user()");
        $this->line("   Auth::guard('iam')->check()");
        $this->newLine();

        $this->line('6. Check permissions and roles:');
        $this->line("   Auth::guard('iam')->hasPermission('permission.name')");
        $this->line("   Auth::guard('iam')->hasRole('role.name')");
        $this->newLine();

        $this->line('For more information, see the README.md in the package directory.');
    }
}