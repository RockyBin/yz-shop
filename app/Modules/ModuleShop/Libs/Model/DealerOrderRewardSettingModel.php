<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Model;

use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardSettingEntity;
use ReflectionException;
use YZ\Core\Model\BaseModel;

class DealerOrderRewardSettingModel extends BaseModel
{
    protected $table = 'tbl_dealer_order_reward_setting';
    protected $primaryKey = 'site_id';
    public $incrementing = false;
    protected $fillable = [
        'site_id',
        'enable',
        'payer',
        'payee',
        'auto_check',
        'reward_rule'
    ];
    public static $rules = array(
        'site_id' => 'required',
    );

    /**
     * @param int $siteId
     * @return DealerOrderRewardSettingEntity|null
     * @throws ReflectionException
     */
    public function getSingleBySiteId(int $siteId)
    {
        $model = $this->newQuery()->where(DealerOrderRewardSettingEntity::SITE_ID, $siteId)->first();
        return is_null($model) ? null : new DealerOrderRewardSettingEntity($model);
    }

    /**
     * @param int $siteId
     * @return bool
     */
    public function checkExistBySiteId(int $siteId)
    {
        return $this->newQuery()->where(DealerOrderRewardSettingEntity::SITE_ID, $siteId)->limit(1)->count() > 0;
    }
}