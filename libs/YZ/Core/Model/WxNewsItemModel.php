<?php

namespace YZ\Core\Model;

use YZ\Core\Site\Site;

/**
 * Class WxNewsItemModel 公众号图文消息条目
 * @package YZ\Core\Model
 */
class WxNewsItemModel extends BaseModel
{
    protected $table = 'tbl_wx_news_item';
    protected $fillable = [
        'news_id',
        'site_id',
        'title',
        'image',
        'image_wx',
        'image_media_id',
        'author',
        'content',
        'digest',
        'url',
        'url_wx',
        'created_at',
        'updated_at',
        'comment_open',
        'comment_only_fans',
    ];

    /**
     * 获得拥有此条目的父记录
     */
    public function parent()
    {
        return $this->belongsTo('YZ\Core\Model\WxNewsModel', 'news_id');
    }

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            static::onBeforeSave($model);
        });
        static::saved(function ($model) {
            static::onSaved($model);
        });
    }

    public static function onBeforeSave($model)
    {
        // 没有id，表示是新建记录时，自动添加时间
        if (!$model->id) {
            $model->created_at = date('Y-m-d H:i:s');
        }
    }

    public static function onSaved($model)
    {
        // 更新父记录的标题和封面图
        $item = WxNewsItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('news_id', $model->news_id)
            ->orderBy('id', 'asc')
            ->first();
        // 图片数量
        $itemTotal = WxNewsItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('news_id', $model->news_id)
            ->count();
        if ($item) {
            $parent = $model->parent;
            if ($parent) {
                $parent->title = $item->title;
                $parent->image = $item->image;
                $parent->updated_at = $item->updated_at;
                $parent->item_total = $itemTotal;
                $parent->save();
            }
        }
    }
}