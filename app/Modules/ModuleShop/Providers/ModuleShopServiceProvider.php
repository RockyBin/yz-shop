<?php

namespace App\Modules\ModuleShop\Providers;

use App\Modules\ModuleShop\Libs\Dealer\DealerOrderRewardService;
use App\Modules\ModuleShop\Libs\Dealer\IDealerOrderRewardService;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Observer\CloudStockPurchaseOrderObserver;
use App\Modules\ModuleShop\Observer\FinanceObserver;
use App\Modules\ModuleShop\Observer\OrderItemDiscountObserver;
use App\Modules\ModuleShop\Observer\OrderItemObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\OrderConfigModel;
use App\Modules\ModuleShop\Observer\OrderConfigObserver;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use App\Modules\ModuleShop\Observer\ProductSkuValueObserver;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Observer\ProductSkusObserver;

class ModuleShopServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../DbLock/Migrations');
        //添加数据观察者
        OrderConfigModel::observe(OrderConfigObserver::class);
        ProductSkuValueModel::observe(ProductSkuValueObserver::class);
        ProductSkusModel::observe(ProductSkusObserver::class);
        CloudStockPurchaseOrderModel::observe(CloudStockPurchaseOrderObserver::class);
        FinanceModel::observe(FinanceObserver::class);
        OrderItemModel::observe(OrderItemObserver::class);
        OrderItemDiscountModel::observe(OrderItemDiscountObserver::class);
        //添加命令
        $this->commands([
			\App\Modules\ModuleShop\Console\CommandCheckCode::class,
            \App\Modules\ModuleShop\Console\CommandTest::class,
            \App\Modules\ModuleShop\Console\CommandTest2::class,
            \App\Modules\ModuleShop\Console\CommandOrderCloseForNoPay::class,
            \App\Modules\ModuleShop\Console\CommandOrderReceipt::class,
            \App\Modules\ModuleShop\Console\CommandOrderFinish::class,
            \App\Modules\ModuleShop\Console\CommandCouponNoUseForExpire::class,
            \App\Modules\ModuleShop\Console\CommandCouponExpire::class,
            \App\Modules\ModuleShop\Console\CommandOrderNoticeForNoPay::class,
            \App\Modules\ModuleShop\Console\CommandCloudStockOrderNoticeForNoPay::class,
            \App\Modules\ModuleShop\Console\CommandBackupSite::class,
            \App\Modules\ModuleShop\Console\CommandBackupClean::class,
            \App\Modules\ModuleShop\Console\CommandExportSite::class,
            \App\Modules\ModuleShop\Console\CommandOrderAutoComment::class,
            \App\Modules\ModuleShop\Console\CommandResetMemberParents::class,
            \App\Modules\ModuleShop\Console\CommandAgentPerformanceReward::class,
            \App\Modules\ModuleShop\Console\CommandResetOrderMembersHistory::class,
            \App\Modules\ModuleShop\Console\CommandResetSkusName::class,
			\App\Modules\ModuleShop\Console\CommandDealerPerformanceReward::class,    
			\App\Modules\ModuleShop\Console\CommandUpdateData::class,
			\App\Modules\ModuleShop\Console\CommandCheckSslStatus::class,
            \App\Modules\ModuleShop\Console\CommandGroupBuyingCloseForNoTime::class,
            \App\Modules\ModuleShop\Console\CommandSupplierOrderSettle::class
        ]);
		$this->bootCustom();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(EventServiceProvider::class);

//        $this->app->bind(IDealerOrderRewardService::class, DealerOrderRewardService::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('moduleshop.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php', 'moduleshop'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/siteadminperm.php', 'siteadminperm'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/supplieradminperm.php', 'supplieradminperm'
        );
		$this->mergeConfigFrom(
            __DIR__ . '/../Config/jobgrouplimit.php', 'jobgrouplimit'
        );
		$this->mergeConfigFrom(
            __DIR__ . '/../Config/requiremobile.php', 'requiremobile'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/moduleshop');

        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/moduleshop';
        }, \Config::get('view.paths')), [$sourcePath]), 'moduleshop');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/moduleshop');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'moduleshop');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'moduleshop');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (!app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../DbLock/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

	/**
     * 加载定制站配置
     */
    protected function bootCustom()
    {
        $siteId = Site::getCurrentSite()->getSiteId();
		//加载定制站的 ServiceProvider::boot() 事件，要求定制文件中要有一个名为 bootCustomProvider() 的全局函数
        $customFile = __DIR__ . '/Custom/Provider' . $siteId . '.php';
		if(Site::getCurrentSite()->getModel()->custom_dir) {
			$customFile = __DIR__ . '/Custom/' . Site::getCurrentSite()->getModel()->custom_dir . '.php';
		}
        if (file_exists($customFile)) {
            require_once($customFile);
			bootCustomProvider($this);
        }
    }
}
