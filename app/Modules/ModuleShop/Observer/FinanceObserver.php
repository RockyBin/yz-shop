<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Jobs\UpgradeDealerLevelJob;
use App\Modules\ModuleShop\Libs\Member\LevelUpgrade\MemberLevelUpgradeHelper;
use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;

/**
 * 财务记录观察者模型
 */
class FinanceObserver {

    /**
     * 产品新建财务记录时，处理余额充值升级
     *
     * @param  FinanceModel $model
     * @return void
     */
    public function created(FinanceModel $model)
    {
        //处理充值的会员升级条件
        if($model->status == Constants::FinanceStatus_Active
            && $model->type == Constants::FinanceType_Normal
            && $model->money_real > 0
            && in_array($model->in_type,[Constants::FinanceInType_Recharge,Constants::FinanceInType_Give,Constants::FinanceInType_Manual])){
            MemberLevelUpgradeHelper::levelUpgrade($model->member_id,[moneyCent2Yuan($model->money_real)]);
            UpgradeDealerLevelJob::dispatch($model->member_id, ['money' => $model->money_real]);
        }
    }
}
