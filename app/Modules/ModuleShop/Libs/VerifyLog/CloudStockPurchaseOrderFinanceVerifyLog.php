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
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;

class CloudStockPurchaseOrderFinanceVerifyLog extends AbstractVerifyLog
{
    public function __construct()
    {
        parent::__construct();
    }

    static function save(int $type, $cloudStockPurchaseOrder)
    {
        if ($cloudStockPurchaseOrder->payee == 0) {
            return false;
        }
        $VerifyLogModel = VerifyLogModel::query()
            ->where('site_id', self::getSiteId())
            ->where('id', $cloudStockPurchaseOrder->verify_log_id)
            ->where('type',$type)
            ->first();
        if ($VerifyLogModel && $cloudStockPurchaseOrder->review_member_id) {
            $params['id'] = $VerifyLogModel->id;
            if ($cloudStockPurchaseOrder->payee != $cloudStockPurchaseOrder->review_member_id) return false;
        }
        $member = new Member($cloudStockPurchaseOrder->member_id);
        $memberModel = $member->getModel();
        $params['site_id'] = self::getSiteId();
        $params['type'] = $type;
        $params['member_id'] = $cloudStockPurchaseOrder->payee;
        $params['status'] = $cloudStockPurchaseOrder->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Refuse ? -1 : $cloudStockPurchaseOrder->payment_status;
        $params['info'] = json_encode(['money' => moneyCent2Yuan($cloudStockPurchaseOrder->total_money), 'nickname' => $memberModel->nickname, 'headurl' => $memberModel->headurl, 'member_id' => $cloudStockPurchaseOrder->member_id, 'created_at' => $cloudStockPurchaseOrder->created_at]);
        $params['foreign_id'] = $cloudStockPurchaseOrder->id;
        $params['from_member_id'] = $cloudStockPurchaseOrder->member_id;
        return  self::saveAct($params);
    }
}