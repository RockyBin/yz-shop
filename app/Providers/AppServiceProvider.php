<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use YZ\Core\FileUpload\FileLogic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        error_reporting((E_ALL & ~E_NOTICE & ~E_STRICT) | E_DEPRECATED);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
