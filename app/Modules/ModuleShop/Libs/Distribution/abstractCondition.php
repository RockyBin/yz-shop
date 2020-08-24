<?php
/**
 * 分销商升级条件抽象类
 * User: liyaohui
 * Date: 2019/10/21
 * Time: 16:25
 */

namespace App\Modules\ModuleShop\Libs\Distribution;


use App\Modules\ModuleShop\Libs\Model\DistributionSettingModel;
use YZ\Core\Site\Site;

abstract class abstractCondition implements IUpgradeCondition
{
    protected $onlyDistributor = false; // 是否只允许分销商升级
    protected $name = ''; // 条件名称
    protected $value = ''; // 条件的值
    protected $productIds = []; // 指定的商品id
    /**
     * 获取此升级条件的类型名称
     * @return string
     */
    public function getTypeName()
    {
        return $this->name;
    }

    /**
     * 检查当前会员的身份是否可以升级此条件
     * @param mixed $member 会员信息
     * @return bool
     */
    public function checkIdentity($member)
    {
        if ($this->onlyDistributor) {
            if ($member && $member['is_distributor']) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * @param array $params
     * @return bool|mixed
     */
    public function getDistributionLevel($params = [])
    {
        if (isset($params['setting_level'])) {
            return $params['setting_level'];
        } else {
            return DistributionSettingModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->value('level');
        }
    }

    /**
     * 判断是否满足升级条件之前 先检测前置条件 目前只有是否是分销商的检测
     * @param array $params
     * @return bool
     */
    public function beforeCheckUpgrade($params = [])
    {
        return $this->checkIdentity($params);
    }
}