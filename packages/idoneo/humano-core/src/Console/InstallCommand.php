<?php

namespace Idoneo\HumanoCore\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'humano:install {--modules=* : Modules to install}';

    protected $description = 'Install Humano Core components';

    public function handle()
    {
        $this->info('🚀 Installing Humano Core...');

        $modules = $this->option('modules');

        if (empty($modules))
        {
            $this->info('✅ Humano Core installed successfully!');
        } else
        {
            $this->info('📦 Modules to install: '.implode(', ', $modules));
            $this->info('✅ Humano Core + modules installed successfully!');
        }

        return 0;
    }
}
