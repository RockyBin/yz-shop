<?php
/**
 * 经销商等级模型
 * User: liyaohui
 * Date: 2019/11/28
 * Time: 16:38
 */

namespace App\Modules\ModuleShop\Libs\Model;


use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use YZ\Core\Entities\Utils\EntityCollection;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;

class DealerLevelModel extends BaseModel
{
    protected $table = 'tbl_dealer_level';
    protected $primaryKey = 'id';
    protected $fillable = [
        'site_id',
        'name',
        'weight',
        'status',
        'has_hide',
        'parent_id',
        'min_purchase_num',
        'min_purchase_num_first',
        'min_purchase_money',
        'min_purchase_money_first',
        'min_take_delivery_num',
        'initial_fee',
        'discount',
        'upgrade_condition',
        'auto_upgrade'
    ];

    /**
     * 已经是经销商的会员 主等级
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dealers()
    {
        return $this->hasMany(MemberModel::class, 'dealer_level', 'id');
    }

    /**
     * 已经是经销商的会员 隐藏等级
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dealersHide()
    {
        return $this->hasMany(MemberModel::class, 'dealer_hide_level', 'id');
    }

    /**
     * 获取申请的经销商
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function applyingDealers()
    {
        return $this->hasMany(DealerModel::class, 'dealer_apply_level', 'id');
    }


    /**
     * 获取申请的经销商
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(get_class($this), 'parent_id', 'id');
    }

    /**
     * @param int $siteId
     * @return EntityCollection
     * @throws \Exception
     */
    public function getListBySiteId(int $siteId): EntityCollection
    {
        $dealerLevelCollection = EntityCollection::createInstance(DealerLevelEntity::class);
        $dealerLevelCollection->loadData($this->newQuery()->where(DealerLevelEntity::SITE_ID, '=', $siteId)->get());
        return $dealerLevelCollection;
    }
}