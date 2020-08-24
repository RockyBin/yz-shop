<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * CRM授权记录模型
 * Class CrmAuthModel
 * @package App\Modules\Model
 */
class CrmAuthModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_crm_auth';
    protected $fillable = [
        'openid',
        'auth_type',
        'app_id',
        'site_id',
        'admin_id',
        'created_at',
        'lastlogin'
    ];

    public function __construct(array $attributes = array())
    {
        if(!$attributes['created_at']) $attributes['created_at'] = date('Y-m-d H:i:s');
        parent::__construct($attributes);
    }
}