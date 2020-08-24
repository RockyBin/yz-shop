<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Entities\CloudstockPurchaseOrderEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardSettingEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\FinanceEntity;
use App\Modules\ModuleShop\Libs\Entities\MemberEntity;
use App\Modules\ModuleShop\Libs\Entities\QueryParameters\DealerOrderRewardQueryParameter;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerOrderRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerOrderRewardSettingModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use Exception;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Common\Runtime;
use YZ\Core\Entities\Utils\EntityCollection;
use YZ\Core\Entities\Utils\EntityExecutionOptions;
use YZ\Core\Entities\Utils\EntityExecutionPresets;
use YZ\Core\Entities\Utils\PaginationEntity;
use YZ\Core\Entities\Utils\RelatedDataPresetEvent;
use YZ\Core\Finance\FinanceHelper;
use \YZ\Core\Constants as CoreConstants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Services\BaseService;
use YZ\Core\Traits\InjectTrait;

class DealerOrderRewardService extends BaseService
{
    use InjectTrait;

    /**
     * @var DealerRewardModel
     */
    private $dealerRewardModel;
    /**
     * @var DealerOrderRewardModel
     */
    private $dealerOrderRewardModel;
    /**
     * @var DealerOrderRewardSettingModel
     */
    private $dealerOrderRewardSettingModel;
    /**
     * @var CloudStockPurchaseOrderModel
     */
    private $cloudStockPurchaseOrderModel;
    /**
     * @var FinanceModel
     */
    private $financeModel;

    /**
     * DealerOrderRewardService constructor.
     * @param DealerRewardModel $dealerRewardModel
     * @param DealerOrderRewardModel $dealerOrderRewardModel
     * @param DealerOrderRewardSettingModel $dealerOrderRewardSettingModel
     * @param CloudStockPurchaseOrderModel $cloudStockPurchaseOrderModel
     * @param FinanceModel $financeModel
     * @throws Exception
     */
    public function __construct(DealerRewardModel $dealerRewardModel,  DealerOrderRewardModel $dealerOrderRewardModel,
                                DealerOrderRewardSettingModel $dealerOrderRewardSettingModel, CloudStockPurchaseOrderModel $cloudStockPurchaseOrderModel,
                                FinanceModel $financeModel)
    {
        parent::__construct();
        $this->initialize();
        $this->inject(get_defined_vars());
    }

    /**
     * Service初始化工作封装
     */
    private function initialize()
    {
    }

    /**
     * 管理端获取订单返现奖分页数据
     * @param PaginationEntity $paginationEntity 分页信息
     * @param DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter 查询信息
     * @return array
     * @throws Exception
     */
    public function getPaginationByAdmin(PaginationEntity $paginationEntity, DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter)
    {
        $collection = $this->dealerRewardModel->getOrderRewardPaginationByAdmin($paginationEntity, $dealerOrderRewardQueryParameter);
        return EntityCollection::createReturnValue($collection, $paginationEntity);
    }

    /**
     * 创建订单返现奖
     * @transaction 事务注解
     * @param CloudstockPurchaseOrderEntity $orderEntity
     * @throws Exception
     */
    public function createDealerOrderReward(CloudstockPurchaseOrderEntity $orderEntity)
    {
        $settingEntity = $this->dealerOrderRewardSettingModel->getSingleBySiteId($orderEntity->site_id);
        if (!$settingEntity->enable) return; // 不启用订单返现奖时返回

        $memberEntity = $orderEntity->getMemberEntity();
        // 会员不是经销商时返回
        if ($memberEntity->dealer_level === 0) return;

        $rewardMoney = $this->calculateReward($orderEntity, $settingEntity);
        if ($rewardMoney === 0) return; // 奖金为0时返回

        $now = date('Y-m-d H:i:s');

        // 奖励主信息
        $dealerRewardEntity = new DealerRewardEntity();
        $dealerRewardEntity->site_id = $orderEntity->site_id;
        $dealerRewardEntity->member_id = $orderEntity->member_id;
        $dealerRewardEntity->type = 4;
        $dealerRewardEntity->status = 0;
        $dealerRewardEntity->pay_member_id = $settingEntity->payer;
        $dealerRewardEntity->created_at = $now;
        $dealerRewardEntity->updated_at = $now;
        $dealerRewardEntity->setTypeText();
        $dealerRewardEntity->about = $this->createAbout($memberEntity, $dealerRewardEntity, $orderEntity);
        $dealerRewardEntity->reward_money = $rewardMoney;

        // 订单返现奖信息
        $dealerOrderRewardEntity = new DealerOrderRewardEntity();
        $dealerOrderRewardEntity->site_id = $orderEntity->site_id;
        $dealerOrderRewardEntity->member_id = $orderEntity->member_id;
        $dealerOrderRewardEntity->order_id = $orderEntity->id;
        $dealerOrderRewardEntity->order_money = $orderEntity->total_money;
        $dealerOrderRewardEntity->order_created_at = $orderEntity->created_at;
        $dealerOrderRewardEntity->member_dealer_level = $memberEntity->dealer_level;
        $dealerOrderRewardEntity->member_dealer_hide_level = $memberEntity->dealer_hide_level;
        $dealerOrderRewardEntity->reward_money = $rewardMoney;

        $dealerRewardEntity->id = $this->dealerRewardModel->addSingle($dealerRewardEntity);
        $dealerOrderRewardEntity->reward_id = $dealerRewardEntity->id;
        $this->dealerOrderRewardModel->addSingle($dealerOrderRewardEntity);
    }

    /**
     * @transaction 事务注解
     * @param int $dealerRewardId
     * @throws Exception
     */
    public function exchange(int $dealerRewardId)
    {
        $dealerRewardEntity = $this->dealerRewardModel->getSingleById($dealerRewardId, null,
            new EntityExecutionPresets(null, new RelatedDataPresetEvent(DealerRewardEntity::class, 'handlePresetRelatedData', 4)
                , null, null), null);
        $settingEntity = $this->dealerOrderRewardSettingModel->getSingleBySiteId($dealerRewardEntity->site_id);

        $dealerRewardEntity->status = Constants::DealerRewardStatus_WaitReview;
        $this->dealerRewardModel->updateSingle($dealerRewardEntity);

        // 如设置了自动审核及审核者为公司才可以执行自动审核
        if ($settingEntity->auto_check && $dealerRewardEntity->pay_member_id === 0) {
            $this->pass($dealerRewardEntity->id, $dealerRewardEntity);
        }
    }

    /**
     * @transaction 事务注解
     * @param int $dealerRewardId
     * @param DealerRewardEntity|null $dealerRewardEntity
     * @return bool|int
     * @throws Exception
     */
    public function pass(int $dealerRewardId, DealerRewardEntity $dealerRewardEntity = null)
    {
        if (is_null($dealerRewardEntity)) $dealerRewardEntity = $this->dealerRewardModel->getSingleById($dealerRewardId, null,
            new EntityExecutionPresets(null, new RelatedDataPresetEvent(DealerRewardEntity::class, 'handlePresetRelatedData', Constants::DealerRewardType_Order)
                , null, null), null);
        if (is_null($dealerRewardEntity)) throw new Exception('没有这项奖励记录。');

        if ($dealerRewardEntity->status === Constants::DealerRewardStatus_WaitReview) {
            // 上级支付
            if ($dealerRewardEntity->pay_member_id > 0) {
                if (FinanceHelper::getMemberBalance($dealerRewardEntity->pay_member_id) < $dealerRewardEntity->reward_money) {
                    throw new Exception('您的余额不足，不可以通过审核！请充值后再进行审核。');
                }
                $this->financeModel->addSingle($this->createFinanceEntity($dealerRewardEntity, true));
            }
            $this->financeModel->addSingle($this->createFinanceEntity($dealerRewardEntity, false));

            $dealerRewardEntity->status = Constants::DealerRewardStatus_Active;
            return $this->dealerRewardModel->updateSingle($dealerRewardEntity);
        }

        return false;
    }

    /**
     * @transaction 事务注解
     * @param int $dealerRewardId
     * @param DealerRewardEntity|null $dealerRewardEntity
     * @param string $reason
     * @return bool|int
     * @throws Exception
     */
    public function reject(int $dealerRewardId, DealerRewardEntity $dealerRewardEntity = null, string $reason = '')
    {
        if (is_null($dealerRewardEntity)) $dealerRewardEntity = $this->dealerRewardModel->getSingleById($dealerRewardId, EntityExecutionOptions::createNotWorkingInstance());
        if (is_null($dealerRewardEntity)) throw new Exception('没有这项奖励记录。');

        if ($dealerRewardEntity->status === Constants::DealerRewardStatus_WaitReview) {
            $dealerRewardEntity->status = Constants::DealerRewardStatus_RejectReview;
            $dealerRewardEntity->reason = $reason;
            return $this->dealerRewardModel->updateSingle($dealerRewardEntity);
        }
        return false;
    }

    /**
     * @param DealerRewardEntity $dealerRewardEntity
     * @param bool $expenditure
     * @return FinanceEntity
     * @throws Exception
     */
    public function createFinanceEntity(DealerRewardEntity $dealerRewardEntity, bool $expenditure)
    {
        $now = time();

        $memberEntity = $dealerRewardEntity->getMemberEntity(); // 受款人
        $payMemberEntity = $dealerRewardEntity->getPayMemberEntity(); // 付款人

        $financeEntity = new FinanceEntity();

        $financeEntity->site_id = $dealerRewardEntity->site_id;
        $financeEntity->status = CoreConstants::FinanceStatus_Active;
        $financeEntity->order_id = "JXSJHFXJ_{$dealerRewardEntity->member_id}_{$dealerRewardEntity->getDealerRewardOrderEntity()->order_id}";
        $financeEntity->tradeno = 'JXSJHFXJ_' . date('YmdHis', $now) . '_' . genUuid(8);
        $financeEntity->money = $dealerRewardEntity->reward_money;
        $financeEntity->money_real = $dealerRewardEntity->reward_money;
        $financeEntity->created_at = date('Y-m-d H:i:s', $now);
        $financeEntity->active_at = date('Y-m-d H:i:s', $now);
        $financeEntity->status = CoreConstants::FinanceStatus_Active;

        if ($expenditure) {
            $financeEntity->member_id = $dealerRewardEntity->pay_member_id;
            $financeEntity->type = CoreConstants::FinanceType_Normal;
            $financeEntity->sub_type = CoreConstants::FinanceSubType_DealerCommission_SubSale;
            $financeEntity->in_type = CoreConstants::FinanceInType_Unknow;
            $financeEntity->out_type = CoreConstants::FinanceOutType_DealerOrderReward;
            $financeEntity->pay_type = CoreConstants::PayType_Balance;
            $financeEntity->about = "转现支出-兑换订货返现奖金给【{$memberEntity->nickname}】";
        } else {
            $financeEntity->member_id = $dealerRewardEntity->member_id;
            $financeEntity->type = CoreConstants::FinanceType_CloudStock;
            $financeEntity->sub_type = CoreConstants::FinanceSubType_DealerCommission_Order;
            $financeEntity->in_type = CoreConstants::FinanceInType_Commission;
            $financeEntity->out_type = CoreConstants::FinanceOutType_Unknow;
            $financeEntity->pay_type = CoreConstants::PayType_Commission;
            $financeEntity->about = '经销商订货返现奖';
        }

        return $financeEntity;
    }

    /**
     * @param MemberEntity $memberEntity
     * @param DealerRewardEntity $dealerRewardEntity
     * @param CloudstockPurchaseOrderEntity $orderEntity
     * @return array
     */
    public function createAbout(MemberEntity $memberEntity, DealerRewardEntity $dealerRewardEntity, CloudstockPurchaseOrderEntity $orderEntity)
    {
        return $about = [
            'member_nickname' => $memberEntity->nickname,
            'member_mobile' => $memberEntity->mobile,
            'member_headurl' => $memberEntity->headurl,
            'type' => $dealerRewardEntity->type,
            'type_text' => $dealerRewardEntity->type_text,
            'member_dealer_level_name' => $memberEntity->dealer_level_name,
            'member_dealer_hide_level_name' => $memberEntity->dealer_hide_level_name,
            'member_dealer_level' => $memberEntity->dealer_level,
            'member_dealer_hide_level' => $memberEntity->dealer_hide_level,
            'order_id' => $orderEntity->id,
            'data_text' => '来自-【' . $memberEntity->nickname . '】的' . $dealerRewardEntity->type_text
        ];
    }

    /**
     * 计算奖金
     * @param CloudstockPurchaseOrderEntity $orderEntity
     * @param DealerOrderRewardSettingEntity $settingEntity
     * @return float|int
     */
    public function calculateReward(CloudstockPurchaseOrderEntity $orderEntity, DealerOrderRewardSettingEntity $settingEntity)
    {
        $rules = $settingEntity->reward_rule;
        $memberEntity = $orderEntity->getMemberEntity();
        $firstOrder = !$this->cloudStockPurchaseOrderModel->hasManyOrderByMemberId($orderEntity->member_id);
        $rewardValue = 0;
        $levelId = 0;

        // 检测会员实际等级
        if ($memberEntity->getDealerLevelEntity()->has_hide) {
            $levelId = $memberEntity->dealer_hide_level;
        } else {
            $levelId = $memberEntity->dealer_level;
        }

        for ($i = count((array)$rules); $i > -1; $i--) {
            $value = $rules[$i];
            if ($levelId === $value->id) {
                $rate = intval($firstOrder ? $value->first_rate : $value->rate);
                if ($rate === 0) break; // $rate等于0时没有设置奖金比例，直接跳出
                $rewardValue = $orderEntity->total_money * $rate / 100;
                break;
            }
        }

        return $rewardValue;
    }

    /**
     * @param PaginationEntity $paginationEntity
     * @param DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter
     * @return Export
     * @throws Exception
     */
    public function exportFileByQuery(PaginationEntity $paginationEntity, DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter)
    {
        $orderRewardPagination = $this->dealerRewardModel->getOrderRewardPaginationByAdmin($paginationEntity, $dealerOrderRewardQueryParameter);

        $exportData = [];
        foreach ($orderRewardPagination as $rewardEntity) {
            /**
             * @var DealerRewardEntity $rewardEntity
             */
            $exportData[] = [
                $rewardEntity->member_id,
                $rewardEntity->member_nickname,
                $rewardEntity->member_name,
                "\t" . $rewardEntity->member_mobile . "\t",
                "\t" . $rewardEntity->order_id . "\t",
                $rewardEntity->order_created_at,
                $rewardEntity->order_money,
                $rewardEntity->reward_money,
                $rewardEntity->pay_member_nickname,
                $rewardEntity->pay_member_mobile ? "\t" . $rewardEntity->pay_member_mobile . "\t" : '--',
                $rewardEntity->status_text,
            ];
        }

        // 表头
        $exportHeadings = [
            '得奖经销商ID',
            '得奖经销商昵称',
            '得奖经销商姓名',
            '得奖经销商手机号',
            '关联订单号',
            '下单时间',
            '订单金额',
            '销售奖金',
            '支付奖金人昵称',
            '支付奖金人手机号',
            '状态',
        ];

        return new Export(new Collection($exportData), 'Dinghuofanxian-' . date("YmdHis") . '.xlsx', $exportHeadings);
    }
}