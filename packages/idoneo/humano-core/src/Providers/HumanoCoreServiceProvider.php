<?php

namespace Idoneo\HumanoCore\Providers;

use Idoneo\HumanoCore\Console\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HumanoCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('humano-core')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations(['create_modules_table', 'create_categories_table'])
            ->hasRoutes('web')
            ->hasCommand(InstallCommand::class);
    }

    public function packageBooted(): void
    {
        // Cargar traducciones si existen
        if (file_exists($this->package->basePath('/../resources/lang')))
        {
            $this->loadTranslationsFrom($this->package->basePath('/../resources/lang'), 'humano-core');
        }
    }
}
