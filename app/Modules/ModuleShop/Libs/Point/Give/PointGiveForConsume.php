<?php

namespace App\Modules\ModuleShop\Libs\Point\Give;

use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Constants;
use YZ\Core\Site\Site;


/**
 * 消费赠送积分
 * Class PointGiveForConsume
 * @package App\Modules\ModuleShop\Libs\Point\Give
 */
class PointGiveForConsume extends AbstractPointGive
{
    protected $statusColumnName = 'in_consume_status';
    protected $pointColumnName = 'in_consume_point';
    private $orderMoney = 0; // 消费金额，单位：分
    private $orderId = ''; // 订单id
    private $perMoney = 0; // 每多少钱赠送积分
    private $perPoint = 0; // 每钱赠送多少积分

    /**
     * 初始化
     * PointGiveForConsume constructor.
     * @param $memberModal
     * @param $orderId
     */
    public function __construct($memberModal, $orderId)
    {
        parent::__construct($memberModal);
        $order = Order::find($orderId);
        if ($order->checkExist()) {
            $this->orderId = $orderId;
            $orderModel = $order->getModel();
            // 总金额 - 运费
            $money = abs(floatval($orderModel->money)) - abs(floatval($orderModel->freight));
            // - 退款金额
            $refundMoney = 0;
            $afterSaleCount = AfterSaleModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('order_id', $orderId)
                ->where('status', LibsConstants::RefundStatus_Over)
                ->addSelect(DB::Raw('sum(real_money) as real_money'))
                ->addSelect(DB::Raw('sum(total_money) as total_money'))
                ->first();
            if ($afterSaleCount) {
                $refundMoney = $afterSaleCount->real_money ? abs(floatval($afterSaleCount->real_money)) : abs(floatval($afterSaleCount->total_money));
            }

            $this->orderMoney = $money - $refundMoney;
        }

        $this->perPoint = $this->getPoint();
        $pointConfigModel = $this->getPointConfig();
        if ($pointConfigModel) {
            $this->perMoney = abs(intval($pointConfigModel->in_consume_per_price));
        }
    }

    /**
     * 获取当前订单的金额
     * @return float|int
     */
    public function getMoney()
    {
        return $this->orderMoney;
    }

    /**
     * 获取要赠送的积分
     * @return int
     */
    public function getGivePoint()
    {
        if ($this->perMoney > 0) {
            return floor($this->orderMoney / $this->perMoney) * $this->perPoint;
        } else {
            return 0;
        }
    }

    /**
     * 独立设置配置
     * @param $data
     */
    public function setConfig($data)
    {
        $perMoneyTmp = intval($data['in_consume_per_price']);
        $perPointTmp = intval($data['in_consume_point']);
        if ($perMoneyTmp > 0) {
            $this->perMoney = $perMoneyTmp;
        }
        if ($perPointTmp > 0) {
            $this->perPoint = $perPointTmp;
        }
    }

    /**
     * 自定义规则
     * @return bool|mixed
     */
    protected function customRule()
    {
        // 金额数值正常
        if ($this->perPoint <= 0 || $this->perMoney <= 0 || $this->orderMoney <= 0 || !$this->orderId) return false;
        // 验证是否已经赠送过
        $total = $this->point->count([
            'member_id' => $this->member->getMemberId(),
            'in_out_type' => Constants::PointInOutType_Consume,
            'in_id' => $this->orderId
        ]);
        if ($total > 0) return false;

        return true;
    }

    /**
     * 购物赠送积分
     * @return bool|mixed|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected function addPointHandle()
    {
        $pointVal = $this->getGivePoint();
        if ($pointVal > 0) {
            return $this->point->add([
                'member_id' => $this->member->getMemberId(),
                'in_out_type' => Constants::PointInOutType_Consume,
                'in_out_id' => $this->orderId,
                'point' => $pointVal,
                'about' => '购物赠送积分，订单尚未完成',
                'terminal_type' => $this->getTerminalType(),
                'status' => Constants::PointStatus_UnActive,
            ]);
        }
        return false;
    }
}