<?php

namespace Adamus\LaravelIamClient\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'iam:install {--mode= : Installation mode (client or server)}';

    protected $description = 'Install Adamus IAM package (prompts for client or server mode)';

    public function handle(): int
    {
        $mode = $this->option('mode');

        if (!$mode) {
            $mode = $this->choice(
                'How do you want to install IAM?',
                [
                    'client' => 'Client - For applications that authenticate against an IAM server',
                    'server' => 'Server - For running the IAM authentication service',
                ],
                'client'
            );
        }

        $mode = strtolower($mode);

        if ($mode === 'client') {
            return $this->call('iam:install-client', [
                '--force' => $this->option('force') ?? false,
            ]);
        }

        if ($mode === 'server') {
            return $this->call('iam:install-server', [
                '--force' => $this->option('force') ?? false,
            ]);
        }

        $this->error('Invalid mode. Please choose either "client" or "server"');
        return self::FAILURE;
    }
}
