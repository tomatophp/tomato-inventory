<?php

namespace TomatoPHP\TomatoInventory;

use Illuminate\Support\ServiceProvider;
use TomatoPHP\TomatoAdmin\Facade\TomatoMenu;
use TomatoPHP\TomatoAdmin\Services\Contracts\Menu;


class TomatoInventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //Register generate command
        $this->commands([
           \TomatoPHP\TomatoInventory\Console\TomatoInventoryInstall::class,
        ]);

        //Register Config file
        $this->mergeConfigFrom(__DIR__.'/../config/tomato-inventory.php', 'tomato-inventory');

        //Publish Config
        $this->publishes([
           __DIR__.'/../config/tomato-inventory.php' => config_path('tomato-inventory.php'),
        ], 'tomato-inventory-config');

        //Register Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        //Publish Migrations
        $this->publishes([
           __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'tomato-inventory-migrations');
        //Register views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tomato-inventory');

        //Publish Views
        $this->publishes([
           __DIR__.'/../resources/views' => resource_path('views/vendor/tomato-inventory'),
        ], 'tomato-inventory-views');

        //Register Langs
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tomato-inventory');

        //Publish Lang
        $this->publishes([
           __DIR__.'/../resources/lang' => base_path('lang/vendor/tomato-inventory'),
        ], 'tomato-inventory-lang');

        //Register Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

    }

    public function boot(): void
    {
        TomatoMenu::register([
            Menu::make()
                ->group(__('Inventory'))
                ->label(__('Inventory'))
                ->route('admin.inventories.index')
                ->icon('bx bxs-building-house'),
            Menu::make()
                ->group(__('Inventory'))
                ->label(__('Refunds'))
                ->route('admin.refunds.index')
                ->icon('bx bx-refresh')
        ]);
    }
}
