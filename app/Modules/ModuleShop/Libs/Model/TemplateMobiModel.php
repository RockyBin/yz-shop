<?php
namespace App\Modules\ModuleShop\Libs\Model;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Site\Site;

/**
 * 移动端模板表
 * Class TemplateMobiModel
 * @package App\Modules\Model
 */
class TemplateMobiModel extends  \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_template_mobi';
    protected $fillable = ['id','name','description','created_at','updated_at','site_id','page_id','industry_id','status','image','demo_url','is_blank','device_type'];

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}