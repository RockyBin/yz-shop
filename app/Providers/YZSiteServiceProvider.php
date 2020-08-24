<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use YZ\Core\Site\Site;

class YZSiteServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        // 在这里初始化 Site 对象，并绑定到容器中，命名为 YZSite
        // 注意要用 singleton 方式进行绑定，避免多次初始化
        $func = isSwoole() ? 'singleton':'bind';
        $this->app->$func('YZSite', function () {
            return new Site();
        });
    }
}