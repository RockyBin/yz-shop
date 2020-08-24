<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\VerifyLog;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use \YZ\Core\Constants as CoreConstants;

class DealerRewardVerifyLog extends AbstractVerifyLog
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param int $type
     * @param 具体model $model
     * @return bool|void
     */
    static function save(int $type, $model)
    {
        // 公司审核的 不用去处理
        if (!$model->pay_member_id) {
            return false;
        }
        $verifyLogModel = VerifyLogModel::query()
            ->where('site_id', self::getSiteId())
            ->where('foreign_id', $model['id'])
            ->where('type', $type)
            ->first();
        // 没有查询到记录的，一律视为添加，否则就是审核
        if ($verifyLogModel) {
            // 编辑的时候 不是通过就是拒绝
            if ($model->status == Constants::DealerRewardStatus_Active) {
                $verifyLogModel->status = 1;
            } else {
                $verifyLogModel->status = -1;
                $verifyLogModel->reject_reason = $model->reason;
            }
            return $verifyLogModel->save();
        } else {
            $model->about = json_decode($model->about, true);
            $model->reward_money_yuan = moneyCent2Yuan($model->reward_money);
            $info = $model->toArray();
            $params['site_id'] = self::getSiteId();
            $params['type'] = $type;
            // 需要审核的人
            $params['member_id'] = $info['pay_member_id'];
            // 自动审核的直接是生效状态
            $params['status'] = $model->sataus == Constants::DealerRewardStatus_Active ? 1 : 0;
            $params['info'] = json_encode($info, JSON_UNESCAPED_UNICODE);
            $params['foreign_id'] = $model->id;
            // 来自于哪个会员
            $params['from_member_id'] = $info['member_id'];
            return self::saveAct($params);
        }
    }
}