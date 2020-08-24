<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuSettleModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuModel;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use YZ\Core\Finance\FinanceHelper;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Contracts\Bus\Dispatcher;
use YZ\Core\Constants as CoreConstants;
use Carbon\Carbon;
use YZ\Core\Common\DataCache;

/**
 * 会员的云仓
 * Class CloudStock
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class CloudStock
{
    use DispatchesJobs;
    private $_model = null;

    public function __construct($memberIdOrStockModel, $autoCreate = 1)
    {
        if ($memberIdOrStockModel) {
            if (is_numeric($memberIdOrStockModel)) {
                // 将对象放到全局变量中，避免当一个请求中需要多次使用此类时要多读数据库
                $this->_model = DataCache::getData(static::class . $memberIdOrStockModel);
                if (!$this->_model) {
                    $model = CloudStockModel::where('member_id', $memberIdOrStockModel)->where('site_id', Site::getCurrentSite()->getSiteId())->first();
                    if ($model) {
                        $this->_model = $model;
                        DataCache::setData(static::class . $memberIdOrStockModel, $this->_model);
                    }
                }
            } else {
                $this->_model = $memberIdOrStockModel;
            }
        }
        if (!$this->_model && $autoCreate) {
            $this->_model = new CloudStockModel();
            try {
                $model = static::createCloudStock($memberIdOrStockModel);
                if ($model) {
                    $this->_model = $model;
                }
            } catch (\Exception $ex) {
            }
        }
    }

    /**
     * 建立新的云仓
     *
     * @param int $memberId 会员ID
     * @return void
     */
    public static function createCloudStock($memberId)
    {
        DB::beginTransaction();
        $site = Site::getCurrentSite();
        $sn = $site->getSn();

        if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)) {
            $member = new Member($memberId);
            $memberModel = $member->getModel(false);
            if (
                $memberModel
                && ($memberModel->dealer_level || $memberModel->dealer_hide_level)
                && $member->isActive()
            ) {
                $type = 0; //0=代理云仓，1=区域代理云仓
                $model = new CloudStockModel();
                $model->site_id = $site->getSiteId();
                $model->member_id = $memberId;
                $model->status = 1;
                $model->type = $type;
                $model->save();
                DB::commit();
                //新增云仓通知发送
                MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Open, $memberModel);
                //如果有上级需要给上级发送通知 因为云仓现在与经销商是捆绑的，所以不是判断推荐人是判断经销商上级领导人
                if ($memberModel->dealer_parent_id) {
                    MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Member_Add, $memberModel);
                }

                return $model;
            } else {
                throw new \Exception('会员未生效或不是代理，不能增加云仓');
            }
        } else {
            DB::rollBack();
            throw new \Exception('当前版本没有云仓功能');
        }
    }

    /**
     * 建立新的云仓的后置事件
     * @return boolean
     */
    public function createCloudStockAfter($model)
    {

    }

    /**
     * 判断云仓是否生效
     * @return boolean
     */
    public function enable()
    {
        return intval($this->_model->status) === 1;
    }

    /**
     * 获取会员总仓的数据库记录模型
     *
     * @return CloudStockModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 计算云仓用户的产品拿货价格(考虑到商家后台可能会单独为不同的用户设置不同的拿货折扣，所以将计价过程写到这里)
     *
     * @param int $price 产品原价，单位：分
     * @param int $dealerLevel 经销商等级
     * @param int $dealerHideLevel 经销商隐藏等级
     * @param int $cloudStockRule 商品sku的云仓价格规则id
     * @return int
     */
    public function getProductPrice($price, $dealerLevel, $dealerHideLevel, $cloudStockRule = 0)
    {
        // 先确定子等级是否可用
        $levels = DealerLevel::getCachedLevels();
        $calLevel = $dealerHideLevel ? $dealerHideLevel : $dealerLevel;
        if (!$levels[$dealerLevel]['has_hide']) $calLevel = $dealerLevel; //主等级没使用子等级时，直接采用主等级
        // 单品自定义云仓价格
        if ($cloudStockRule > 0) {
            $ruleModel = ProductPriceRuleModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $cloudStockRule)
                ->first();
            $ruleInfo = json_decode($ruleModel->rule_info, true);
            if (
                $ruleModel
                && $ruleInfo
                && isset($ruleInfo['rule']['commission'][$calLevel])
            ) {
                // 固定云仓价格
                if ($ruleInfo['amountType'] == 1) {
                    $price = $ruleInfo['rule']['commission'][$calLevel];
                    if ($price) return $price;
                } else {
                    // 云仓折扣
                    $discount = $ruleInfo['rule']['commission'][$calLevel];
                    if ($discount) return intval($price * $discount / 100);
                }
            }
        }

        $discount = $levels[$calLevel]['discount'];
        if ($discount) return intval($price * $discount / 100);
        // 都不满足 则返回原价
        return $price;
    }

    /**
     * 设置云仓状态
     *
     * @param int $status
     * @return void
     */
    public function setStatus($status)
    {
        if ($this->_model && $this->_model->id) {
            $this->_model->status = $status;
            $this->_model->save();
        }
    }

    /**
     * 获取该会员进货单的总数量
     * param $filter (用于以后获取不同状态的进货单的总数量)
     * @return array
     */
    public function getPurchaseOrderCount($filter = [])
    {
        $purchaseOrder = [];
        if ($this->_model) {
            $query = CloudStockPurchaseOrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $this->_model->member_id);
            if ($filter['status']) {
                $query->whereIn('status', myToArray($filter['status']));
            }
            $query->addSelect(DB::raw('count(1) as count'));
            $query->addSelect(DB::raw('sum(total_money) as total_money'));
            $purchaseOrder = $query->first()->toArray();
        }
        return $purchaseOrder;
    }

    /**
     * 获取该会员出货单的总数量
     * param $filter (用于以后获取不同状态的进货单的总数量)
     * @return array
     */
    public function getTakeDeliveryOrderCount($filter = [])
    {
        $takeDeliveryOrder = [];
        if ($this->_model) {
            $query = CloudStockTakeDeliveryOrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $this->_model->member_id);
            if ($filter['status']) {
                $query->whereIn('status', myToArray($filter['status']));
            }
            $query->addSelect(DB::raw('count(1) as count'));
            $query->addSelect(DB::raw('sum(product_num) as product_num'));
            $takeDeliveryOrder = $query->first()->toArray();
        }
        return $takeDeliveryOrder;
    }

    /**
     * 获取该会员下级进货单的总数量
     * param $filter (用于以后获取不同状态的进货单的总数量)
     * @return array
     */
    public function getUnderPurchaseOrder($filter = [])
    {
        $underPurchaseOrder = [];
        if ($this->_model) {
            $query = CloudStockPurchaseOrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('cloudstock_id', $this->_model->id);
            if ($filter['status']) {
                $query->whereIn('status', myToArray($filter['status']));
            }
            $query->addSelect(DB::raw('count(1) as count, sum(total_money) as money'));
            $underPurchaseOrder = $query->first()->toArray();
        }
        return $underPurchaseOrder;
    }

    /**
     * 返回云仓的总库存
     *
     * @return int
     */

    public function getTotalInventory($status = -1)
    {
        $query = CloudStockSkuModel::where('member_id', $this->_model->member_id);
        if ($status > -1) $query->where('status', $status);
        $total = $query->sum('inventory');
        return $total;
    }

    /**
     * 获取云仓的收入统计情况
     * @param string $type 类型 默认获取全部
     * @return array|int
     */
    public function getMoneyCount($type = '')
    {
        if ($type) {
            switch ($type) {
                // 可提现余额
                case 'balance':
                    return FinanceHelper::getCloudStockBalance($this->getModel()->member_id);
                // 提现中
                case 'outcheck':
                    return FinanceHelper::getCloudStockCheck($this->getModel()->member_id);
                // 总收入 下级 c端收入
                case 'allStatus':
                    return self::getSettleCount($this->getModel()->member_id);
                default:
                    return 0;
            }
        } else {
            $balance = FinanceHelper::getCloudStockBalance($this->getModel()->member_id); //可提现余额
            $outcheck = FinanceHelper::getCloudStockCheck($this->getModel()->member_id); //提现中
            $settleCount = self::getSettleCount($this->getModel()->member_id);
            return [
                'balance' => moneyCent2Yuan($balance), //可提现余额
                'outcheck' => moneyCent2Yuan($outcheck), //提现中金额
                'allStatus1' => moneyCent2Yuan($settleCount['allStatus1']), //云仓总收入
                'retailStatus1' => moneyCent2Yuan($settleCount['retailStatus1']), //下级代理进货收入
                'purchaseStatus1' => moneyCent2Yuan($settleCount['purchaseStatus1']), //C端零售收入
            ];
        }
    }

    /**
     * 获取云仓列表(后台用)
     *
     * @param array $param
     * @return void
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $query = DB::table('tbl_cloudstock as cs')
            ->leftjoin('tbl_member as m', 'm.id', '=', 'cs.member_id')
            ->where('cs.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('cs.id', 'desc');
        $query->addSelect('cs.*', 'm.nickname', 'm.mobile', 'm.agent_level', 'm.headurl', 'm.id as member_id');
        $query->forPage($page, $pageSize);
        $list = $query->get();
        $memberIds = [];
        foreach ($list as $item) {
            $memberIds[] = $item->member_id;
        }

        // 统计相关会员的云仓收入
        $settles = [];
        if (count($memberIds)) {
            $listSettle = DB::table('tbl_cloudstock_sku_settle')
                ->whereIn('member_id', $memberIds)
                ->where('status', 1)
                ->selectRaw('member_id, sum(money) as money')->groupBy('member_id')->get();
            foreach ($listSettle as $d) {
                $settles[$d->member_id] = $d->money;
            }
        }

        // 合并信息
        foreach ($list as &$item) {
            $item->money = moneyCent2Yuan($settles[$item->member_id]);
            if ($item->headurl && !preg_match('@^(http:|https:)@i', $item->headurl)) $item->headurl = Site::getSiteComdataDir() . $item->headurl;
        }
        unset($item);

        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery($query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('cs.member_id', intval($param['member_id']));
        }
        // 状态
        if (is_numeric($param['status']) && intval($param['status']) != -1) {
            $query->where('cs.status', intval($param['status']));
        }
        // 等级
        if (is_numeric($param['level']) > 0) {
            $query->where('m.agent_level', intval($param['level']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('cs.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('cs.created_at', '<=', $param['created_at_max']);
        }
        // 指定ID(一般只用在导出时)
        if ($param['ids']) {
            if (!is_array($param['ids'])) $param['ids'] = explode(',', $param['ids']);
            $query->whereIn('cs.id', $param['ids']);
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
//            $keywordAscii = '%' . preg_replace("/[\x{4e00}-\x{9fa5}]+/u", '', $param['keyword']) . '%'; //有些字段编码为ascii，输入中文会导致SQL出错，这里将中文替换掉
            $originalKeyWord = $param['keyword'];
            $query->where(function ($query2) use ($keyword, $originalKeyWord) {
                $query2->orWhere('m.nickname', 'like', $keyword);
                if (preg_match('/^\w+$/i', $originalKeyWord)) $query2->orWhere('m.mobile', 'like', $keyword);
            });
        }
    }

    /**
     * 订单下单时，查找某商品的出库仓是哪个
     * 关于云仓出仓的逻辑
     * 前提说明：云仓有全仓和非全仓，全仓是每个sku代理都必须拿货，非全仓是代理可以只代理一部分的商品，
     * 为了适应这两个模式，系统的出仓规则如下：
     * 从购买者开始，从下往上根据sku查找上家是否有此sku的云仓，找到的话就从这里出，也就是说，一个订单里如果有多个sku，那出库仓不一定是同一个
     * 但我们前期只做全仓模式，所以根据以下流程处理出仓：
     * (1). 网站是否有云仓功能，没有就返回0
     * (2). 此代理的云仓是否为禁用（如果没有云仓记录，就自动生成一条，并且状态是启用，相当于没有云仓也认为是启用），如果禁用继续往上找
     * (3). 根据云仓的member_id云匹配sku子仓，看是否为禁用（如果没有子仓记录，就自动生成一条，库存为0，状态是启用，相当于没有子仓也认为是启用），如果禁用就继续找上一家的
     *      member_id进行匹配，重复第2、3步，直到没有上家为止
     * (4). 最后如果找到相应的子仓后，返回子仓的ID，否则返回0
     * @param int $memberId 会员ID
     * @param int $productId 商品ID
     * @param int $skuId 规格ID
     * @param int $includeSelf 当 $memberId 也是代理时，在返回的代理列表里，是否包含 $memberId 本身,一般只有在零售下单时会用到
     * @param int $findAll 是否查找整个代理链条的全部仓库情况
     * @return array of CloudStockSkuModel
     */
    public static function findSkuOutStock($memberId, $productId, $skuId, $includeSelf = 0, $findAll = 0)
    {
        $stocks = [];
        $site = Site::getCurrentSite();
        $sn = $site->getSn();
        //没有云仓功能，返回0
        if (!$sn->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)) return $stocks;
        $parents = DealerHelper::getParentDealers($memberId, $includeSelf)['normal'];
        foreach ($parents as $m) {
            //会员的总仓
            $cloudStock = new CloudStock($m['id']);
            $cloudStockModel = $cloudStock->getModel();
            if ($cloudStockModel && intval($cloudStockModel->status) !== 1) continue;
            //会员的SKU子仓
            $cloudStockSku = new CloudStockSku($m['id'], $productId, $skuId);
            $cloudStockSkuModel = $cloudStockSku->getModel();
            if (!$cloudStockSkuModel) continue;
            if (intval($cloudStockSkuModel->status) !== 1) continue;
            if ($cloudStockSkuModel) {
                $stocks[] = $cloudStockSkuModel;
                if (!$findAll) break;
            }
        }
        return $stocks;
    }

    /**
     * 获取云仓的上级id
     * @param $memberId
     * @return mixed
     */
    public static function getCloudStockParent($memberId)
    {
        $member = new Member($memberId);
        $memberModel = $member->getModel(false);
        $parentId = 0;
        if ($memberModel->dealer_parent_id) {
            $parentId = CloudStockModel::query()->where('site_id', getCurrentSiteId())
                ->where('member_id', $memberModel->dealer_parent_id)
                ->value('id');
        }
        return $parentId ?: 0;
    }

    /**
     * 根据进货单进行配仓
     * @param $orderIdOrModel 订单ID 或 订单模型
     * @param bool $throw 是否抛出错误
     * @param bool $isAuto 是否是自动配仓
     * @return array|bool
     * @throws \Exception
     */
    public static function stockDeliverByOrder($orderIdOrModel, $throw = false, $isAuto = false)
    {
        if ($orderIdOrModel instanceof CloudStockPurchaseOrderModel) {
            $order = $orderIdOrModel;
        } else {
            $order = CloudStockPurchaseOrderModel::query()->where('id', $orderIdOrModel)->first();
        }
        // 审核通过的才可以配仓
        if ($order->status != Constants::CloudStockPurchaseOrderStatus_Reviewed) {
            if ($throw) {
                throw new \Exception('该订单状态无法配仓');
            } else {
                return false;
            }
        }
        // 获取所有商品
        $items = CloudStockPurchaseOrderItemModel::query()
            ->where('order_id', $order->id)
            ->get();
        // 先检测库存
        $itemSkuInfo = [];
        foreach ($items as $item) {
            $itemSkuInfo[] = [
                'product_id' => $item->product_id,
                'sku_id' => $item->sku_id,
                'need' => $item->num
            ];
        }
        // 查找上级云仓信息
        $parentInfo = static::getParentCloudStockById($order->cloudstock_id);
        $parentId = $parentInfo ? $parentInfo['member_id'] : 0;
        $stockInventory = CloudStockSku::checkStockInventory($itemSkuInfo, $parentId);
        // 如果库存全部足够 则去自动配仓 否则不去自动配仓
        if ($stockInventory['all_enough'] == 1) {
            static::stockDeliverByOrderItems($items, $order, $parentId);
            // 配仓完成后 要改变订单和订单商品的状态
            $order->status = Constants::CloudStockPurchaseOrderStatus_Finished;
            $order->stock_status = 1;
            // 更新完成时间
            $order->finished_at = Carbon::now();
            // 更新log 自动配仓的审核时间和配仓时间是一样的
            $original = $order->order_log && !$isAuto ? $order->order_log : '';
            $order->order_log = Constants::getCloudStockOrderLogText(5, $original, '', $order->pay_type);
            CloudStockPurchaseOrderItemModel::query()
                ->where('site_id', $order->site_id)
                ->where('order_id', $order->id)
                ->update(['stock_status' => Constants::CloudStockPurchaseOrderItemStatus_Yes]);
            //消息通知
            app(Dispatcher::class)->dispatch(new MessageNotice(CoreConstants::MessageType_Order_Send, $order));
            return $order->save();
        } else {
            app(Dispatcher::class)->dispatch(new MessageNotice(CoreConstants::MessageType_CloudStock_Inventory_Not_Enough, $order));
            // 库存不满足 不可以配仓
            return makeServiceResult(400, '库存不足', ['checkInfo' => $stockInventory]);
        }
    }

    /**
     * 订单配仓逻辑
     * @param array $items 订单的商品列表
     * @param CloudStockPurchaseOrderModel $order
     * @param int $parentId 上级会员ID
     * @throws \Exception
     */
    private static function stockDeliverByOrderItems($items, $order, $parentId = 0)
    {
        try {
            DB::beginTransaction();
            if ($order->cloudstock_id > 0 && $parentId) {
                foreach ($items as $item) {
                    CloudStockSku::stockDeliver(
                        $item->product_id,
                        $item->sku_id,
                        $parentId,
                        $order->member_id,
                        Constants::CloudStockOrderType_Purchase,
                        $order->id,
                        $item->id,
                        $order->pay_type,
                        $item->num,
                        $item->money
                    );
                }
            } else {
                foreach ($items as $item) {
                    CloudStockSku::stockDeliverByManufactor(
                        $item->product_id,
                        $item->sku_id,
                        $order->member_id,
                        Constants::CloudStockInType_Purchase,
                        $order->id,
                        $item->id,
                        $item->num
                    );
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 根据id 查找云仓信息
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public static function getParentCloudStockById($id)
    {
        $parentInfo = [];
        if ($id) {
            $parentInfo = CloudStockModel::query()->where('id', $id)->first();
            if (!$parentInfo) {
                throw new \Exception('找不到该订单所属的云仓信息');
            }
        }
        return $parentInfo;
    }

    /* 获取某云仓(若无直属供货商，则为总部)
     * 获取某会员上级云仓(若无直属供货商，则为总部)
     * @param $memberId
     * @return mixed
     */
    public static function getDirectlyUnderSupplier($memberId)
    {
        // 云仓直属上级冗余在会员表了 直接查询会员表即可
        $member = (new Member($memberId))->getModel();
        $data = [];
        if ($member && $member->dealer_parent_id) {
            $parent = (new Member($member->dealer_parent_id))->getModel();
            if ($parent) {
                $data['headurl'] = $parent->headurl;
                $data['name'] = $parent->nickname;
                $data['mobile'] = $parent->mobile;
                $data['member_id'] = $parent->id;
            }
        }
//        $data = AgentParentsModel::query()
//            ->leftJoin('tbl_cloudstock', 'tbl_cloudstock.member_id', '=', 'tbl_agent_parents.parent_id')
//            ->leftJoin('tbl_member', 'tbl_member.id', '=', 'tbl_cloudstock.member_id')
//            ->where('tbl_agent_parents.site_id', Site::getCurrentSite()->getSiteId())
//            ->where('tbl_agent_parents.member_id', $memberId)
//            ->whereNotNull('tbl_cloudstock.id')
//            ->where('tbl_cloudstock.status',Constants::CommonStatus_Active)
//            ->orderBy('tbl_agent_parents.level', 'asc')
//            ->select('nickname as name', 'headurl', 'mobile', 'tbl_agent_parents.parent_id as member_id')
//            ->first();
        if (!$data) {
            $shopConfigData = (new ShopConfig())->getInfo()['info'];
            $storeConfigData = (new StoreConfig())->getInfo()['data'];
            $data['headurl'] = $shopConfigData->logo;
            $data['name'] = $shopConfigData->name;
            $data['mobile'] = $storeConfigData->custom_mobile;
        }
        return $data;

    }

    /**
     * 获取某会员的云仓团队
     * @param $memberId
     * @return mixed
     */
    public static function getCloudStockTeam($memberId)
    {
        $list = DealerParentsModel::query()
            ->from('tbl_dealer_parents as dp')
            ->leftJoin('tbl_cloudstock', 'tbl_cloudstock.member_id', '=', 'dp.member_id')
            ->leftJoin('tbl_member as member', 'member.id', 'dp.member_id')
            ->where('dp.site_id', Site::getCurrentSite()->getSiteId())
            ->where('dp.parent_id', $memberId)
            ->where('dp.level', '>', 0)
            ->where('member.dealer_level', '>', 0)
            ->where('tbl_cloudstock.status', Constants::CommonStatus_Active)
            ->whereNotNull('tbl_cloudstock.member_id')
            ->orderBy('dp.level', 'asc')
            ->select([
                'member.nickname as name',
                'member.headurl',
                'member.mobile',
                'dp.level as level',
                'member.dealer_level',
                'member.dealer_hide_level'
            ])
            ->get();
        return $list;
    }

    /**
     * 会员是否有需要审核的记录
     * @return bool
     */
    public function hasNeedVerify()
    {
        $has = VerifyLogModel::query()->where('site_id', getCurrentSiteId())
            ->where('member_id', $this->_model->member_id)
            ->where('status', 0)
            ->first();
        return !!$has;
    }

    /**
     * 查询当前云仓是否需要补货
     * @return bool
     */
    public function hasReplenish()
    {
        // 先查找待当前会员配仓的skuid和对应的数量
        $skus = CloudStockPurchaseOrderItemModel::query()->where('site_id', $this->_model->site_id)
            ->where('cloudstock_id', $this->_model->id)
            ->where('stock_status', Constants::CloudStockPurchaseOrderItemStatus_No)
            ->groupBy(['sku_id'])
            ->selectRaw('count(num) as num,sku_id')
            ->get();
        if ($skus->count() == 0) {
            return false;
        }
        $skuIds = $skus->pluck('sku_id')->toArray();
        // 查找对应的库存
        $inventory = CloudStockSkuModel::query()->where('site_id', $this->_model->site_id)
            ->where('member_id', $this->_model->member_id)
            ->whereIn('sku_id', $skuIds)
            ->pluck('inventory', 'sku_id')
            ->toArray();
        // 找不到对应的商品 也认为是需要补货
//        if (!$inventory) {
//            return true;
//        }
        $NoNumskuIds = [];
        // 循环比对有没有需要补货的商品
        foreach ($skus as $sku) {
            if (!isset($inventory[$sku['sku_id']]) || $sku['num'] > $inventory[$sku['sku_id']]) {
                $NoNumskuIds[] = $sku['sku_id'];
            }
        }
        if ($NoNumskuIds) {
            // 检测是否有有效的产品
            $checkProductStatus = ProductModel::query()->where('tbl_product.site_id', $this->_model->site_id)
                ->leftJoin('tbl_product_skus as sku', 'sku.product_id', 'tbl_product.id')
                ->where('tbl_product.status', \YZ\Core\Constants::Product_Status_Sell)
                ->whereIn('sku.id', $NoNumskuIds)
                ->select('sku.id')
                ->first();
            if ($checkProductStatus) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取云仓总收入、可提现余额 和 提现中金额 三个统计数据
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getMemberFinanceInfo($memberId)
    {
        $finance = FinanceModel::onWriteConnection()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $memberId)
            ->whereIn('type', [CoreConstants::FinanceType_CloudStock])
            ->whereIn('status', [CoreConstants::FinanceStatus_Active, CoreConstants::FinanceStatus_Freeze])
            ->selectRaw(
                'sum(if(status=? and money > 0, money, 0)) as total_income, sum(money) as balance, -sum(if(status=? and money < 0, money, 0)) as freeze',
                [CoreConstants::FinanceStatus_Active, CoreConstants::FinanceStatus_Freeze]
            )
            ->first();
        return $finance;
    }

    /**
     * 获取单个会员的云仓结算统计数据
     *
     * @param int $memberId
     * @return array
     */
    public static function getSettleCount($memberId)
    {
        $list = CloudStockSkuSettleModel::query()->where('member_id', $memberId)->where('site_id', Site::getCurrentSite()->getSiteId())->select(['order_type', 'status'])->selectRaw('sum(money) as money')->groupBy('order_type', 'status')->get();
        $retailStatus0 = 0;
        $retailStatus1 = 0;
        $purchaseStatus0 = 0;
        $purchaseStatus1 = 0;
        foreach ($list as $item) {
            if ($item->order_type == Constants::CloudStockOrderType_Retail && $item->status == 0) $retailStatus0 += $item->money;
            if ($item->order_type == Constants::CloudStockOrderType_Retail && $item->status == 1) $retailStatus1 += $item->money;
            if ($item->order_type == Constants::CloudStockOrderType_Purchase && $item->status == 0) $purchaseStatus0 += $item->money;
            if ($item->order_type == Constants::CloudStockOrderType_Purchase && $item->status == 1) $purchaseStatus1 += $item->money;
        }
        $allStatus0 = $retailStatus0 + $purchaseStatus0;
        $allStatus1 = $retailStatus1 + $purchaseStatus1;
        return [
            'retailStatus0' => $retailStatus0, //零售未结算
            'retailStatus1' => $retailStatus1, //零售已结算
            'purchaseStatus0' => $purchaseStatus0, //下级进货未结算
            'purchaseStatus1' => $purchaseStatus1, //下级进货已结算
            'allStatus0' => $allStatus0, //全部未结算
            'allStatus1' => $allStatus1 //全部已结算
        ];
    }

    /**
     * 获取云仓结算详情
     * @param $memberId
     * @return array
     * @throws \Exception
     */
    public static function getMemberSettleSummary($memberId)
    {
        $siteId = getCurrentSiteId();
        $member = MemberModel::query()->where('site_id', $siteId)
            ->where('id', $memberId)
            ->select(['id', 'nickname', 'headurl', 'mobile', 'dealer_level', 'dealer_hide_level'])
            ->first();
        if (!$member) {
            throw new \Exception('会员不存在');
        }
        $levelIds = [$member->dealer_level];
        if ($member->dealer_hide_level) {
            $levelIds[] = $member->dealer_hide_level;
        }
        $data = $member->toArray();
        // 查找经销商等级
        $data['level_names'] = DealerLevelModel::query()->where('site_id', $siteId)
            ->whereIn('id', $levelIds)
            ->pluck('name')->toArray();
        $money = self::getSettleCount($memberId);
        $data['settle'] = moneyCent2Yuan($money['allStatus1']);
        return $data;
    }

    /**
     * 经销商中心是否显示海报
     * @return bool
     */
    public static function isShowSharePaper()
    {
        $count = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_dealer_center', 1)
            ->count();
        return !!$count;
    }
}