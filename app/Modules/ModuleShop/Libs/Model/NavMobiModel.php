<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 手机端底部导航数据库模型
 */
class NavMobiModel extends BaseModel {
    protected $table = 'tbl_nav_mobi';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'device_type',
        'background',
        'normal_color',
        'active_color',
        'items',
    ];

    public function __construct(array $attributes = array())
    {
        // 默认菜单
        $defaultItems = [
            [
                "id" => 1,
                "icon_type" => 0,
                "icon" => "icon-shouye",
                "image" => null,
                "image_active" => null,
                "name" => "首页",
                "link_type" => "home",
                "link_data" => null,
                "link_url" => "#/",
                "link_desc" => "链接到 店铺主页"
            ],
            [
                "id" => 2,
                "icon_type" => 0,
                "icon" => "icon-fenlei",
                "image" => null,
                "image_active" => null,
                "name" => "分类",
                "link_type" => "product_class",
                "link_data" => null,
                "link_url" => "#/product/product-class",
                "link_desc" => "链接到 商品分类"
            ],
            [
                "id" => 3,
                "icon_type" => 0,
                "icon" => "icon-gouwuche",
                "image" => null,
                "image_active" => null,
                "name" => "购物车",
                "link_type" => "shopping_cart",
                "link_data" => null,
                "link_url" => "#/product/shopping-cart",
                "link_desc" => "链接到 购物车"
            ],
            [
                "id" => 4,
                "icon_type" => 0,
                "icon" => "icon-wode",
                "image" => null,
                "image_active" => null,
                "name" => "我的",
                "link_type" => "user_center",
                "link_data" => null,
                "link_url" => "#/member/member-center",
                "link_desc" => "链接到 个人中心"
            ]
        ];

        $defaultBigScreenItems = [
            [
                "id" => 1,
                "icon_type" => 0,
                "icon" => "icon-shouye",
                "image" => null,
                "image_active" => null,
                "name" => "商城首页",
                "link_type" => "home",
                "link_data" => null,
                "link_url" => "#/",
                "link_desc" => "链接到 商城首页"
            ],
            [
                "id" => 2,
                "icon_type" => 0,
                "icon" => "icon-fenlei",
                "image" => null,
                "image_active" => null,
                "name" => "商品分类",
                "link_type" => "product_class",
                "link_data" => null,
                "link_url" => "#/product/product-class",
                "link_desc" => "链接到 商品分类"
            ],
            [
                "id" => 3,
                "icon_type" => 0,
                "icon" => "icon-fenlei1",
                "image" => null,
                "image_active" => null,
                "name" => "商品列表",
                "link_type" => "product_list",
                "link_data" => null,
                "link_url" => "#/product/product-list",
                "link_desc" => "链接到 商品列表"
            ],
            [
                "id" => 4,
                "icon_type" => 0,
                "icon" => "icon-youhuiquan",
                "image" => null,
                "image_active" => null,
                "name" => "优惠券",
                "link_type" => "coupon_center",
                "link_data" => null,
                "link_url" => "#/member/coupon-center",
                "link_desc" => "链接到 领券中心"
            ]
        ];

        if(!array_key_exists('background',$attributes)){
            $attributes['background'] = '#fff';
        }
        if(!array_key_exists('normal_color',$attributes)){
            $attributes['normal_color'] = '#333333';
        }
        if(!array_key_exists('active_color',$attributes)){
            $attributes['active_color'] = '#000000';
        }
        if(!array_key_exists('device_type',$attributes)){
            $attributes['device_type'] = 1;
        }
        if(!array_key_exists('items',$attributes)){
            if (intval($attributes['device_type']) === 2) $attributes['items'] = json_encode($defaultBigScreenItems);
            else $attributes['items'] = json_encode($defaultItems);
        }
        parent::__construct($attributes);
    }
}