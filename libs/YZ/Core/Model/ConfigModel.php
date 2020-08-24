<?php

namespace YZ\Core\Model;

/**
 * 站点的配置信息表，如站点的支付设置，短信设置等很多配置信息都在这里
 * Class ConfigModel
 * @package YZ\Core\Model
 */
class ConfigModel extends BaseModel
{
    protected $table = 'tbl_config';
    protected $primaryKey = 'site_id';
    public $incrementing = false;
    protected $fillable = [
        'qq_appid',
        'qq_appsecret',
        'member_account_flag',
        'product_video_page',
        'product_list_style',
        'product_list_show_sale_num',
        'product_comment_status',
        'product_comment_check_way',
        'product_comment_auto_day',
        'show_code',
        'show_code_pages',
        'retail_status',
        'balance_give_status',
        'balance_give_target',
        'service3rd_status',
        'service3rd_code',
        'service3rd_pages',
        'small_shop_status',
        'small_shop_optional_product_status',
        'copyright',
        'bind_invite_time'
    ];

    public function __construct()
    {
        parent::__construct();
    }
}