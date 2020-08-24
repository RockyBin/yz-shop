<?php

namespace App\Modules\ModuleShop\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use YZ\Core\Site\Site;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The root namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $namespace = 'App\Modules\ModuleShop\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapWebCustomRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../Routes/web.php');
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../Routes/api.php');
    }

    /**
     * 加载自定义配置
     */
    protected function mapWebCustomRoutes()
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        if($_COOKIE['CustomSiteID']) $siteId = $_COOKIE['CustomSiteID']; //为方便调试用
        if($_REQUEST['CustomSiteID']) $siteId = $_REQUEST['CustomSiteID']; //为方便调试用
        $customFile = __DIR__ . '/../Routes/Custom/web' . $siteId . '.php';
		if(Site::getCurrentSite()->getModel()->custom_dir) {
			$customFile = __DIR__ . '/../Routes/Custom/' . Site::getCurrentSite()->getModel()->custom_dir . '.php';
		}
        if (file_exists($customFile)) {
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group($customFile);
        }
    }
}
