<?php

namespace YZ\Core\Model;

use YZ\Core\Site\Site;

/**
 * Class WxNewsModel 公众号图文消息
 * @package YZ\Core\Model
 */
class WxNewsModel extends BaseModel
{
    protected $table = 'tbl_wx_news';

    public function items()
    {
        return $this->hasMany('YZ\Core\Model\WxNewsItemModel', 'news_id');
    }

    public static function boot()
    {
        parent::boot();
        static::deleted(function ($model) {
            static::onDeleted($model);
        });
    }

    public static function onDeleted($model)
    {
        WxNewsItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('news_id', $model->id)
            ->delete();
    }
}