<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\VerifyLog;


use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeDealerLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Finance\Recharge;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForRecharge;
use App\Modules\ModuleShop\Libs\Point\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use \YZ\Core\Constants as CoreConstants;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;

class BalanceVerifyLog extends AbstractVerifyLog
{
    use DispatchesJobs;

    public function __construct()
    {
        parent::__construct();
    }

    static function save(int $type, $model)
    {
        try {
            DB::beginTransaction();
            $VerifyLogModel = VerifyLogModel::query()
                ->where('site_id', self::getSiteId())
                ->where('id', $model['log_id'])
                ->where('type', $type)
                ->first();
            // 没有查询到记录的，一律视为添加，否则就是审核
            if ($VerifyLogModel) {
                $info = json_decode($VerifyLogModel->info, true);
                $params['id'] = $VerifyLogModel->id;
                $params['status'] = $model['status'];
                $snapshot = ['voucher' => $info['snapshot'], 'dealer_account' => $info['dealer_account']];
                if ($params['status'] == 1 && $VerifyLogModel->status == 0) {
                    if ($VerifyLogModel->member_id == 0) {
                        if (in_array($info['pay_type'], CoreConstants::getOnlinePayType())) {
                            $about = "向公司充值-在线充值";
                        } elseif (in_array($info['pay_type'], CoreConstants::getOfflinePayType())) {
                            switch ($info['pay_type']) {
                                case $info['pay_type'] == CoreConstants::PayType_WeixinQrcode:
                                    $about = '向公司充值-线下微信收款码';
                                    break;
                                case $info['pay_type'] == CoreConstants::PayType_AlipayQrcode :
                                    $about = '向公司充值-线下支付宝收款码';
                                    break;
                                case $info['pay_type'] == CoreConstants::PayType_AlipayAccount :
                                    $about = '向公司充值-线下支付宝账户';
                                    break;
                                case $info['pay_type'] == CoreConstants::PayType_Bank:
                                    $about = '向公司充值-线下银行账户';
                                    break;
                            }
                        }

                        $finInfo = [
                            'site_id' => self::getSiteId(),
                            'member_id' => $info['member_id'],
                            'type' => $info['type'],
                            'pay_type' => $info['pay_type'],
                            'order_id' => $info['order_id'],
                            'order_type' => 0,
                            'is_real' => CoreConstants::FinanceIsReal_Yes,
                            'in_type' => $info['in_type'],
                            'operator' => '',
                            'snapshot' => json_encode($snapshot),
                            'terminal_type' => $info['terminal_type'],
                            'money' => $info['money'],
                            'money_real' => $info['money'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'about' => $about,
                            'status' => CoreConstants::FinanceStatus_Active,
                            'active_at' => date('Y-m-d H:i:s'),
                        ];
                        // 保存财务记录
                        $fin = new Finance();
                        $finAddResult = $fin->add($finInfo, false);
                        $params['foreign_id'] = $finAddResult['id'];
                    } else {
                        $maxPoint = FinanceHelper::getMemberBalance($VerifyLogModel->member_id);
                        if ($maxPoint < $info['money']) {
                            throw new \Exception(" 您的余额不足，不可通过审核！\n请向上级充值后再进行审核");
                        }
                        $params['foreign_id'] = (FinanceHelper::Give($info['member_id'], $VerifyLogModel->member_id, $info['money'], $snapshot, true))[1];
                    }
                    // 审核通过后触发经销商充值条件的升级
                    UpgradeDealerLevelJob::dispatch($info['member_id'], ['money' => $info['money']]);
                    // 相关分销商升级
                    UpgradeDistributionLevelJob::dispatch($info['member_id'], ['money' => $info['money']]);
                    //相关代理升级
                    UpgradeAgentLevelJob::dispatch($info['member_id'], ['money' => $info['money']]);
                    if ($info['recharge_bonus']) {
                        $finance = FinanceModel::find($params['foreign_id'])->toArray();
                        $bonus = json_decode($info['recharge_bonus'], true);
                        // 充值赠送金额
                        Recharge::processRechargeBonus($finance, $bonus);
                    }
                    if ($info['give_point']) {
                        // 充值赠送积分
                        $pointGive = new PointGiveForRecharge($info['member_id'], $params['foreign_id']);
                        $pointGive->addPoint();
                        // 充值赠送积分
//                        $point = new Point();
//                        $point->add([
//                            'member_id' => $info['member_id'],
//                            'in_out_type' => CoreConstants::PointInOutType_Recharge,
//                            'in_out_id' => $params['foreign_id'],
//                            'point' => $info['give_point'],
//                            'about' => '充值',
//                            'terminal_type' => $info['terminal_type'],
//                            'status' => CoreConstants::PointStatus_Active,
//                        ]);
                    }
                } else {
                    $params['reject_reason'] = $model['reject_reason'];
                }
            } else {
                $pay_type_text = '';
                switch ($model['pay_type']) {
                    case $model['pay_type'] == CoreConstants::PayType_WeixinQrcode:
                        $pay_type_text = 'wechat';
                        break;
                    case $model['pay_type'] == CoreConstants::PayType_AlipayQrcode || $model['pay_type'] == CoreConstants::PayType_AlipayAccount :
                        $pay_type_text = 'alipay';
                        break;
                    case $model['pay_type'] == CoreConstants::PayType_Bank:
                        $pay_type_text = 'bank_account';
                        break;
                }
                //  info的字段
                $member = new Member($model['member_id']);
                $memberModel = $member->getModel();

                $info['member_id'] = $memberModel->id; // 充值人ID
                $info['mobile'] = $memberModel->mobile; // 充值人手机
                $info['nickname'] = $memberModel->nickname; // 充值人昵称
                $info['headurl'] = $memberModel->headurl; // 充值人ID
                $info['order_id'] = $model['order_id'];
                $info['type'] = CoreConstants::FinanceType_Normal;
                $info['pay_type'] = $model['pay_type'];
                $info['pay_type_text'] = $pay_type_text;
                $info['in_type'] = $model['in_type'];
                $info['is_real'] = 0;
                $info['terminal_type'] = $model['terminal_type'];
                $info['money'] = $model['money'];
                $info['money_fee'] = $model['money_fee'];
                $info['money_real'] = $model['money_real'];
                $info['snapshot'] = $model['snapshot'];//快照这个字段装上传的凭证


                // 存储的当时转账的账号
                if ($model['in_type'] == CoreConstants::FinanceInType_Give) {
                    $dealerAccount = new DealerAccount($memberModel->dealer_parent_id);
                    $dealerAccountInfo = $dealerAccount->getAccount($model['pay_type']);
                    $dealer_account['account'] = $dealerAccountInfo['account'];
                    $dealer_account['account_name'] = $dealerAccountInfo['account_name'];
                    $dealer_account['bank'] = $dealerAccountInfo['bank'];
                } else {
                    $payConfig = Finance::getPayConfig(0);
                    if ($payConfig['types']) {
                        foreach ($payConfig['types'] as $item) {
                            if ($item['type'] == $model['pay_type']) {
                                $dealer_account['account'] = $item['account'];
                                $dealer_account['account_name'] = $item['account_name'];
                                $dealer_account['bank'] = $item['bank'] ? $item['bank'] : $item['text'];
                            }
                        }
                    }
                }
                $info['dealer_account'] = $dealer_account ? json_encode($dealer_account) : [];
                $info['recharge_bonus'] = $model['recharge_bonus'];//充值优惠
                $info['give_point'] = $model['give_point'];//积分优惠

                $params['site_id'] = self::getSiteId();
                $params['type'] = $type;
                // 加入intype 是转现，就是余额充值给经销商
                $params['member_id'] = $model['in_type'] == CoreConstants::FinanceInType_Give ? $memberModel->dealer_parent_id : 0;
                $params['status'] = 0;
                $params['info'] = json_encode($info, JSON_UNESCAPED_UNICODE);
                $params['foreign_id'] = 0;
                $params['from_member_id'] = $memberModel->id;
            }
            $verify_id = self::saveAct($params);
            DB::commit();
            return $verify_id;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

    }

    static function getInfo($VerifyLogModel, $memberId)
    {
        $info = json_decode($VerifyLogModel['info'], true);
        $info['snapshot'] = explode(',', $info['snapshot']);
        $info['dealer_account'] = json_decode($info['dealer_account'], true);
        $info['money'] = moneyCent2Yuan($info['money']);
        //上级信息
        $info['parent_review_member'] = $memberId;
        $parent_review_member = (new Member($info['parent_review_member']))->getModel();
        $info['parent_nickname'] = $parent_review_member ? $parent_review_member->nickname : '公司';
        $info['parent_review_status'] = $VerifyLogModel->status;

        //被审人信息
        $member = (new Member($info['member_id']))->getModel();
        $info['nickname'] = $member->nickname;
        $info['headurl'] = $member->headurl;
        if ($info['recharge_bonus']) {
            $recharge_bonus = json_decode($info['recharge_bonus'], true);
            $recharge_bonus['recharge'] = moneyCent2Yuan($recharge_bonus['recharge']);
            $recharge_bonus['bonus'] = moneyCent2Yuan($recharge_bonus['bonus']);
            $info['recharge_bonus'] = $recharge_bonus;
        }
        $VerifyLogModel->info = $info;
        $VerifyLogModel->review_status = $VerifyLogModel->status == 0 ? true : false;
        return $VerifyLogModel;
    }
}