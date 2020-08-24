<?php
namespace App\Modules\ModuleShop\Libs\Model;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Site\Site;

/**
 * 运费模板表
 * Class FreightTemplateModel
 * @package App\Modules\Model
 */
class FreightTemplateModel extends  \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_freight_template';
    protected $fillable = ['id','site_id','template_name','delivery_type','fee_type','delivery_area','status','orderby'];

    /**
     * 全局查询作用域  查询时自动添加site_id 为当前站点id的条件
     */
    public static function boot()
    {
        parent::boot();

        $siteId = Site::getCurrentSite()->getSiteId();
        if ($siteId) {
            static::addGlobalScope('site_id', function (Builder $builder) use ($siteId) {
                //这里不重用 $siteId 变量就因为的 swoole 环境下，$siteId 对象会不准（因为boot()是静态方法导致）
                $builder->where('tbl_freight_template.site_id', Site::getCurrentSite()->getSiteId());
            });
        }
    }
}