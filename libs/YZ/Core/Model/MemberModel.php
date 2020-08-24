<?php

namespace YZ\Core\Model;

use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\MemberLabelModel;
use App\Modules\ModuleShop\Libs\Model\MemberRelationLabelModel;
use App\Modules\ModuleShop\Libs\Model\ShoppingCartModel;
use App\Modules\ModuleShop\Libs\Model\StatisticsModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;

class MemberModel extends BaseModel
{

    protected $table = 'tbl_member';
    protected $fillable = [
        'site_id',
        'nickname',
        'headurl',
        'level',
        'name',
        'mobile',
        'email',
        'prov',
        'city',
        'area',
        'age',
        'birthday',
        'sex',
        'password',
        'pay_password',
        'terminal_type',
        'regfrom',
        'invite',
        'created_at',
        'lastlogin',
        'is_distributor',
        'agent_level',
        'agent_parent_id',
        'dealer_level',
        'dealer_hide_level',
        'dealer_parent_id',
        'invite1',
        'invite2',
        'invite3',
        'invite4',
        'invite5',
        'invite6',
        'invite7',
        'invite8',
        'invite9',
        'invite10',
        'has_bind_invite',
        'buy_times',
        'buy_money',
        'deal_times',
        'deal_money',
        'status',
        'admin_id',
        'about',
        'is_area_agent',
        'area_agent_at',
        'is_supplier'
    ];
    public static $rules = array(
        //  'name' => 'required|between:1,200',
        'email' => 'required|between:5,50|email',
    );

    public function __construct()
    {
        $this->email = self::genUuid(10) . '@no.com'; //数据库这个字段时唯一的，要随机生成一个
        $this->mobile = self::genUuid(8) . '@no'; //数据库这个字段时唯一的，要随机生成一个
        $this->created_at = date('Y-m-d H:i:s');
        $this->password = substr(md5(mt_rand()), 0, 6);
        // $this->name = '匿名用户';
        $this->nickname = '匿名用户';
        parent::__construct();
    }

	public function getMobileAttribute($value)
    {
        if(preg_match('/^\d{11}$/',$value)) return $value; 
		else return "--";
    }

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            static::onBeforeSave($model);
        });
        static::deleted(function ($model) {
            static::onDeleted($model);
        });
    }

    public static function onBeforeSave($model)
    {
        if (!$model->id || !preg_match('/^\d+$/', $model->getOriginal('mobile'))) {
            /*if (!$model->name || $model->name == '匿名用户') {
                if (preg_match('/^\d+$/', $model->mobile)) $model->name = substr($model->mobile, 0, 3) . "****" . substr($model->mobile, -4);
                else $model->name = '匿名用户';
            }*/
            if (!$model->nickname || $model->nickname == '匿名用户') {
                if (preg_match('/^\d+$/', $model->mobile)) $model->nickname = substr($model->mobile, 0, 3) . "****" . substr($model->mobile, -4);
                else $model->nickname = '匿名用户';
            }
        }
    }

    public static function onDeleted($model)
    {
        MemberAddressModel::where('member_id', $model->id)->delete();
        MemberParentsModel::where('member_id', $model->id)->delete();
    }

    /**
     * 用户的收货地址，关联 MemberAddressModel
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function delivery_address()
    {
        return $this->hasMany('YZ\Core\Model\MemberAddressModel', 'member_id');
    }

    /**
     * 用户的积分情况，关联 PointModel
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    function point()
    {
        return $this->hasMany('YZ\Core\Model\PointModel', 'member_id');
    }

    /**
     * 用户的财务情况，关联 FinanceModel
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    function finance()
    {
        return $this->hasMany('YZ\Core\Model\FinanceModel', 'member_id');
    }

    /**
     * 用户的授权情况，关联 MemberAuthModel
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    function authList()
    {
        return $this->hasMany('YZ\Core\Model\MemberAuthModel', 'member_id');
    }

    /**
     * 用户的统计数据情况，关联 Statistics
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    function statisticsList()
    {
        return $this->hasMany(StatisticsModel::class, 'member_id');
    }

    /**
     * 用户的上家列表，关联 MemberParentsModel
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    function parents()
    {
        return $this->hasMany('YZ\Core\Model\MemberParentsModel', 'member_id');
    }

    /**
     * 用户的等级情况，关联 MemberLevelModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function memberLevel()
    {
        return $this->hasOne('App\Modules\ModuleShop\Libs\Model\MemberLevelModel', 'id', 'level');
    }

    /**
     * 所在省，关联 DistrictModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function provInfo()
    {
        return $this->hasOne('YZ\Core\Model\DistrictModel', 'id', 'prov');
    }

    /**
     * 所在城市，关联 DistrictModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function cityInfo()
    {
        return $this->hasOne('YZ\Core\Model\DistrictModel', 'id', 'city');
    }

    /**
     * 所在县/区，关联 DistrictModel
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    function areaInfo()
    {
        return $this->hasOne('YZ\Core\Model\DistrictModel', 'id', 'area');
    }

    /**
     * 会员的购物车
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shoppingCart()
    {
        return $this->hasMany(ShoppingCartModel::class, 'member_id');
    }

    /**
     * 关联代理数据
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function agent()
    {
        return $this->hasOne(AgentModel::class, 'member_id');
    }

    /**
     * 经销商等级信息 主等级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dealerLevel()
    {
        return $this->belongsTo(DealerLevelModel::class, 'dealer_level', 'id');
    }

    /**
     * 经销商隐藏等级信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dealerHideLevel()
    {
        return $this->belongsTo(DealerLevelModel::class, 'dealer_hide_level', 'id');
    }


    /**
     * 个人业绩
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dealerCloudStockPurchasePerformance()
    {
        return $this->hasMany(CloudStockPurchaseOrderModel::class, 'member_id', 'id');
    }

    public function label()
    {
        return $this->belongsToMany(MemberLabelModel::class, 'tbl_member_relation_label', 'member_id', 'label_id');
//        return $this->hasMany(MemberRelationLabelModel::class,'member_id','id')->leftJoin('tbl_member_label','tbl_member_label.id','tbl_member_relation_label.label_id');
    }

    /**
     * 供应商
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function supplier()
    {
        return $this->hasOne(SupplierModel::class, 'member_id', 'id');
    }
}
