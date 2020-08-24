<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Finance;

use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForRecharge;
use App\Modules\ModuleShop\Libs\Promotions\RechargeBonus;
use YZ\Core\Constants as YZConstants;
use YZ\Core\Finance\Finance;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;

class Recharge
{
    use DispatchesJobs;

    /**
     * 充值后做的事情（需要保证等幂性）
     * @param $finance
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function afterRecharge($finance)
    {
        try {
            if (!$finance) return false;
            $memberId = $finance['member_id'];
            $financeId = $finance['id'];
            if (!$memberId || !$financeId) return false;
            try {
                $cacheKey = 'recharge_finance_' . $financeId;
                $hasAfterRecharge = \Cache::get($cacheKey);
                if (!$hasAfterRecharge) {
                    \Cache::add($cacheKey, true, 10);
                    // 充值赠送积分
                    $pointGive = new PointGiveForRecharge($memberId, $financeId);
                    $pointGive->addPoint();
                    // 充值赠送金额
                    $financeBonusId = static::processRechargeBonus($finance);
                    // 发送余额变更通知
                    MessageNoticeHelper::sendMessageBalanceChange(FinanceModel::find($financeId));
                    if ($financeBonusId) MessageNoticeHelper::sendMessageBalanceChange(FinanceModel::find($financeBonusId));
                    $financeInfo = FinanceModel::find($financeId);
                    // 相关分销商升级
                    UpgradeDistributionLevelJob::dispatch($memberId, ['money' => $financeInfo->money]);
                    //相关代理升级
                    UpgradeAgentLevelJob::dispatch($memberId, ['money' => $financeInfo->money]);
                }
            } catch (\Exception $ex) {
                Log::writeLog('recharge', 'Error:' . $ex->getMessage());
            }
            // 如果是支付宝，通联充值，应该要跳转到一个页面
            if ($finance['pay_type'] == \YZ\Core\Constants::PayType_Alipay || $finance['pay_type'] == \YZ\Core\Constants::PayType_TongLian) {
                $url = '/shop/front/#/member/balance-home?success=true';
                return redirect($url);
            }
            return true;
        } catch (\Exception $ex) {
            Log::writeLog('recharge', 'Error:' . $ex->getMessage());
            return false;
        }
    }

    /**
     * 处理充值赠送优惠
     *
     * @param array $finance 充值成功时的入帐记录
     * @param $customBonus 自定义规则
     * @return void
     */
    public static function processRechargeBonus($finance, $customBonus = [])
    {
        if (!Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_RECHARGE_BONUS)) return;
        if ($customBonus) {
            $bonus = $customBonus;
        } else {
            $bonus = self::calcRechargeBonus($finance['money']);
        }
        if ($bonus) {
            $finInfo = [
                'site_id' => $finance['site_id'],
                'member_id' => $finance['member_id'],
                'type' => YZConstants::FinanceType_Normal,
                'pay_type' => YZConstants::PayType_Bonus,
                'tradeno' => 'BONUS_' . $finance['tradeno'],
                'order_id' => $finance['order_id'],
                'order_type' => $finance['order_type'],
                'is_real' => YZConstants::FinanceIsReal_No,
                'in_type' => YZConstants::FinanceInType_Bonus,
                'operator' => '',
                'terminal_type' => $finance['terminal_type'],
                'money' => $bonus['bonus'],
                'created_at' => date('Y-m-d H:i:s'),
                'about' => '充值赠送-充' . str_replace('.00', '', moneyCent2Yuan($bonus['recharge'])) . '送' . str_replace('.00', '', moneyCent2Yuan($bonus['bonus'])) . "元",
                'status' => YZConstants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
            ];
            $fin = new Finance();
            return $fin->add($finInfo, false);
        }
    }

    public static function calcRechargeBonus($money)
    {
        if (!Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_RECHARGE_BONUS)) return;
        $bonus = new RechargeBonus();
        $data = $bonus->getInfo(2);

        if ($data['status']) {
            foreach ($data['bonus'] as $item) {
                if ($money >= $item['recharge']) {
                    return $item;
                }
            }
        }
        return [];
    }
}