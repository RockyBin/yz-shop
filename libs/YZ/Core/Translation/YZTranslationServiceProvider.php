<?php

namespace YZ\Core\Translation;

use YZ\Core\Site\Site;
use Illuminate\Translation\TranslationServiceProvider as ServiceProvider;

class YZTranslationServiceProvider extends ServiceProvider
{
    protected function registerLoader()
    {
        $func = isSwoole() ? 'singleton':'bind';
        $this->app->$func('translation.loader', function ($app) {
            $site = Site::getCurrentSite();
            $langPath = public_path() . Site::getSiteComdataDir($site->getSiteId()) . '/lang';
            $default_path = $app['path.lang'];
            return new FileLoader($app['files'], $langPath, $default_path);
        });
    }
}