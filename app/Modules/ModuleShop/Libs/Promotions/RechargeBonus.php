<?php
namespace App\Modules\ModuleShop\Libs\Promotions;

use App\Modules\ModuleShop\Libs\Model\RechargeBonusModel;
use Illuminate\Support\Collection;
use YZ\Core\Site\Site;

/**
 * 充值赠送活动
 */
class RechargeBonus
{
    private $_siteId = 0; // 站点ID
    private $_model = null;

    /**
     * 充值赠送优惠类
     */
    public function __construct($siteId = 0)
    {
        if (!$siteId) {
            $siteId = Site::getCurrentSite()->getSiteId();
        }
        $this->_siteId = $siteId;
        $this->_model = RechargeBonusModel::where('site_id',$this->_siteId)->first();
        if (!$this->_model) {
            $this->_model = new RechargeBonusModel();
            $this->_model->status = 0;
            $this->_model->site_id = $this->_siteId;
        }
    }

    /**
     * 获取充值赠送的信息，返回的金额单位都是分，需要在外层使用时看情况调用 toYuan() 进行转换
     *
     * @param int $sort 是否将按充值金额大小进行排序，0=不排序，1=按从小到大排序，2=按从大到小排序
     * @return void
     */
    public function getInfo($sort = 0){
        $info = $this->_model->toArray();
        if ($info['bonus']) {
            $info['bonus'] = json_decode($info['bonus'], true);
            if($sort > 0){
                $coll = new Collection($info['bonus']);
                if($sort === 1) $info['bonus'] = $coll->sortBy('recharge')->values()->toArray();
                elseif($sort === 2) $info['bonus'] = $coll->sortByDesc('recharge')->values()->toArray();
            }
        } else {
            $info['bonus'] = [];
        }
        return $info;
    }

    /**
     * 更新数据，要求金额单位都是分，需要在外层使用时看情况调用 toCent() 进行转换
     *
     * @param array $info
     * @return void
     */
    public function update($info = array()){
        if(key_exists('status',$info)) {
            $this->_model->status = $info['status'];
        }
        if(key_exists('bonus',$info)) {
            if(is_array($info['bonus'])) $info['bonus'] = json_encode($info['bonus']);
            $this->_model->bonus = $info['bonus'];
        }
        $this->_model->save();
    }

    /**
     * 将金额单位转为元
     *
     * @return void
     */
    public function toYuan($info = array()){
        foreach($info['bonus'] as &$item){
            $item['recharge'] = moneyCent2Yuan($item['recharge']);
            $item['bonus'] = moneyCent2Yuan($item['bonus']);
        }
        unset($item);
        return $info;
    }

    /**
     * 将金额单位转为分
     *
     * @return void
     */
    public function toCent($info = array()){
        foreach($info['bonus'] as &$item){
            $item['recharge'] = moneyYuan2Cent($item['recharge']);
            $item['bonus'] = moneyYuan2Cent($item['bonus']);
        }
        unset($item);
        return $info;
    }
}