<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\UI\Module\BaseModule;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Locker\Locker;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuLogModel;
use YZ\Core\Finance\FinanceHelper;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuSettleModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Constants as CoreConstants;
use Illuminate\Support\Facades\Redis;

/**
 * 会员的SKU子仓
 * Class CloudStockSku
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class CloudStockSku
{
    use DispatchesJobs;
    private $_model = null;
    private $_memberStock = null;

    public function __construct($memberIdOrStockId, $productId = 0, $skuId = 0)
    {
        if ($memberIdOrStockId && $productId && intval($skuId) > 0) { //当传三个参数时，认为是以非子仓ID进行初始化，否则认为是传子仓ID进行初始化
            $site = Site::getCurrentSite();
            $model = CloudStockSkuModel::where(['member_id' => $memberIdOrStockId, 'product_id' => $productId, 'sku_id' => $skuId, 'site_id' => Site::getCurrentSite()->getSiteId()])->first();
            if ($model) {
                $this->_model = $model;
            } else {
                $this->_model = new CloudStockSkuModel();
                try {
                    $model = static::createCloudStockSku($memberIdOrStockId, $productId, $skuId);
                    if ($model) {
                        $this->_model = $model;
                    }
                } catch (\Exception $ex) {
                }
            }
        } else {
            if (is_numeric($memberIdOrStockId)) $this->_model = CloudStockSkuModel::find($memberIdOrStockId);
            else $this->_model = $memberIdOrStockId;
        }
    }

    /**
     * 建立新的SKU子仓
     * @param int $memberId 会员ID
     * @param int $productId 商品ID
     * @param int $skuId 规格ID
     * @return mixed
     * @throws \Exception
     */
    public static function createCloudStockSku($memberId, $productId, $skuId = 0)
    {
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
                $model = new CloudStockSkuModel();
                $model->site_id = $site->getSiteId();
                $model->member_id = $memberId;
                $model->product_id = $productId;
                $model->sku_id = $skuId;
                $model->status = 1;
                $model->inventory = 0;
                $model->save();
                $instance = new static($model);
                $instance->updateRedundantInfo();
                return $instance->getModel();
            } else {
                throw new \Exception('会员未生效或不是经销商，不能增加云仓');
            }
        } else {
            throw new \Exception('当前版本没有云仓功能');
        }
    }

    /**
     * 更新冗余的商品信息
     *
     * @return void
     */
    public function updateRedundantInfo()
    {
        $productModel = ProductModel::find($this->_model->product_id);
        if ($productModel) {
            $productImage = explode(',', $productModel->small_images)[0];
            $skuNames = [];
            if ($this->_model->sku_id > 0) {
                $productSkuModel = ProductSkusModel::find($this->_model->sku_id);
                if ($productSkuModel) {
                    $skuNames = $productSkuModel->skuValueName()->toArray();
                }
                if ($productSkuModel->sku_image) {
                    $productImage = $productSkuModel->sku_image;
                }
                $this->_model->weight = $productSkuModel->weight;
            }
            $this->_model->product_name = $productModel->name;
            $this->_model->sku_name = $skuNames ? json_encode($skuNames, JSON_UNESCAPED_UNICODE) : '[]';
            $this->_model->product_image = $productImage;
            $this->_model->save();
        }
    }

    /**
     * 获取SKU子仓是否生效
     * @return boolean
     */
    public function enable()
    {
        $this->loadMemberStock();
        return intval($this->_model->status) === 1 && $this->_memberStock->enable();
    }

    /**
     * 加载会员的总仓对象
     *
     * @return void
     */
    private function loadMemberStock()
    {
        if ($this->_memberStock == null) {
            $this->_memberStock = new CloudStock($this->_model->member_id);
        }
    }

    /**
     * 获取SKU子仓的数据库记录模型
     *
     * @return CloudStockSkuModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 后台手动调整库存
     *
     * @param int $num 调整量(如为扣减，请用负数，只在 $isAdd = 1 时有效)
     * @param int $isAdd 是否为添加/扣减库存，1=添加 $num 库存,0=将库存设置为 $num
     * @param int $isFirstGift 是否为首次开仓赠送库存， 一般在新开代理时使用
     * @return void
     */
    public function adjustInventoryManual($num, $isAdd = 0, $isFirstGift = 0)
    {
        try {
            DB::beginTransaction();
            //生成出入库记录
            $log = new CloudStockSkuLogModel();
            $log->site_id = $this->getModel()->site_id;
            $log->member_id = $this->getModel()->member_id;
            $log->product_id = $this->getModel()->product_id;
            $log->sku_id = $this->getModel()->sku_id;
            $log->from_stock_id = 0;
            $log->num_before = $this->getModel()->inventory;
            if (!$isAdd) $num = $num - $this->getModel()->inventory;
            $log->num_after = $this->getModel()->inventory + $num;
            $log->num = $num;
            if ($num > 0) {
                if ($isFirstGift && $log->num_before == 0) $log->in_type = Constants::CloudStockInType_FirstGift;
                else $log->in_type = Constants::CloudStockInType_Manual;
            } else{
                $log->out_type = Constants::CloudStockOutType_Manual;
                $log->about =  '后台手动操作';
            }
            if (intval($num) !== 0) {
                $log->save();
                //修改库存
                $this->getModel()->increment('inventory', $num);
                $this->getModel()->save();
            }

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();
            throw $ex;
        }
    }

    /**
     * 获取云仓列表(后台用)
     *
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $query = DB::table('tbl_cloudstock_sku as cs')
            ->leftjoin('tbl_product as p', 'p.id', '=', 'cs.product_id')
            ->leftjoin('tbl_product_skus as sku', 'sku.id', '=', 'cs.sku_id')
            ->where('cs.site_id', Site::getCurrentSite()->getSiteId())
            ->where('cs.inventory', '>', 0);
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('cs.created_at', 'desc');
        $query->addSelect('cs.*', 'p.name as proname', 'p.small_images as productimage', 'sku.sku_name as skuname', 'sku.sku_image as skuimage');
        $query->forPage($page, $pageSize);
        $list = $query->get();

        // 合并信息
        foreach ($list as &$item) {
            if ($item->productimage) {
                $item->product_image = Site::getSiteComdataDir() . ($item->skuimage ? $item->skuimage : explode(',', $item->productimage)[0]);
            }
            if ($item->proname) {
                $item->product_name = $item->proname;
            }
            // 取最新的规格名称 取不到就拿云仓sku缓存的名称
            if ($item->skuname) {
                $item->sku_name = json_decode($item->skuname, true);
            } else if ($item->sku_name) {
                $item->sku_name = json_decode($item->sku_name, true);
            }
            unset($item->skuname, $item->proname, $item->productimage, $item->skuimage);
        }
        unset($item);

        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize
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
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            $query->where(function ($query2) use ($keyword) {
                $query2->orWhere('p.name', 'like', $keyword);
                $query2->orWhere('cs.product_name', 'like', $keyword);
            });
        }
    }

    /**
     * 代理进货时，如果是厂家发货，给相应的仓库配仓
     *
     * @param int $productId 产品ID
     * @param int $skuId sku ID
     * @param int $inMemberId 入库的会员
     * @param int $inType 入库类型，参考 Constants::CloudStockInType_XXX
     * @param string $orderId 进货单ID
     * @param int $orderItemId 订单的商品项ID
     * @param int $num 进货数量
     * @return void
     * @throws \Exception
     */
    public static function stockDeliverByManufactor($productId, $skuId, $inMemberId, $inType, $orderId, $orderItemId, $num)
    {
        try {
            $site = Site::getCurrentSite();
            $inStock = new CloudStockSku($inMemberId, $productId, $skuId);
            //生成入库记录
            $inLog = new CloudStockSkuLogModel();
            $inLog->site_id = $site->getSiteId();
            $inLog->member_id = $inMemberId;
            $inLog->product_id = $productId;
            $inLog->sku_id = $skuId;
            $inLog->from_stock_id = 0;
            $inLog->num_before = $inStock->getModel()->inventory;
            $inLog->num_after = $inStock->getModel()->inventory + $num;
            $num = abs($num);
            $inLog->num = $num;
            if ($orderId) {
                $inLog->order_type = Constants::CloudStockOrderType_Purchase; //后台可以调的，永远都是进货单类型
                $inLog->order_id = $orderId;
                $inLog->order_item_id = $orderItemId;
                $inLog->in_type = Constants::CloudStockInType_Purchase; //有指定订单时，强制为进货类型
            } else {
                $inLog->in_type = $inType;
                if ($inType == Constants::CloudStockInType_Manual) {
                    $inLog->about = '来自【公司】的转移';
                }
            }
            $inLog->out_type = 0;
            $inLog->save();

            //修改入库仓的库存
            $stockModelIn = $inStock->getModel();
            $stockModelIn->increment('inventory', $num);
            $stockModelIn->purchase_at = date('Y-m-d H:i:s');
            $stockModelIn->save();

            if ($inType != Constants::CloudStockInType_FirstGift) {
                // 修改平台商品的库存 并增加销量
                $productSku = ProductSkusModel::query()->where(['product_id' => $productId, 'id' => $skuId])->first();
                $productSku->decrement('inventory', $num);
                $productSku->increment('sku_sold_count', $num);
                ProductModel::query()->find($productId)->increment('sold_count', $num);
            }
            //消息通知，一个订单通知只发送一次
            $inLogRedisKey = $inLog->order_id . $inLog->in_type . $inLog->out_type . $inLog->member_id;
            if (!Redis::exists($inLogRedisKey)) {
                Redis::setex($inLogRedisKey, 60, '');
                MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Inventory_Change, $inLog);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * 子仓出仓，有两种情况：
     * 1. 代理进货时，给相应的仓库配仓，此方法适合出库仓是上家代理商的情况，如果出库仓是厂家，请调用 stockDeliverByManufactor
     * 2. 零售出仓，这时只会产生出仓记录，不会有下级代理的入仓记录
     * 3. 特别注意:目前认为orderId等于0的时候,是后台的人工操作,后期有需要改动的时候,可增加参数做标识
     * @param int $productId 产品ID
     * @param int $skuId sku ID
     * @param int $outMemberId 出库的会员
     * @param int $inMemberId 入库的会员 或 购买订单的会员ID，如果是数组，表示推荐关系或管理关系的链条
     * @param int $orderType 订单类型，具体看常量 CloudStockOrderType_XXX
     * @param string $orderId 相关订单ID，可以是零售订单ID 或 进货订单ID
     * @param int $orderItemId 订单的商品项ID
     * @param int $orderPayType 订单支付类型
     * @param int $num 数量
     * @param int $money 金额，代理之间调仓时需要或零售出仓
     * @return void
     * @throws \Exception
     */
    public static function stockDeliver($productId, $skuId, $outMemberId, $inMemberId, $orderType, $orderId, $orderItemId, $orderPayType, $num, $money = 0)
    {
        $site = Site::getCurrentSite();
        $lockId = "stockDeliverBySku_" . $outMemberId . "_" . $productId . "_" . $skuId;
        $locker = new Locker($lockId);
        if (is_array($inMemberId)) {
            $fromMemberIds = $inMemberId;
            $inMemberId = $inMemberId[0];
        } else {
            $fromMemberIds = $inMemberId;
        }
        if ($locker->lock()) {
            try {
                $outStock = new CloudStockSku($outMemberId, $productId, $skuId);
                // 产品说先暂时不去检测云仓状态 by hui
//                if (!$outStock->enable()) throw new \Exception('出库仓未生效');
                //只有订单类型是下级代理拿货的订单才需要检测子仓的库存和状态
                if ($orderType == Constants::CloudStockOrderType_Purchase || $orderType == Constants::CloudStockOrderType_Manual) {
                    if ($num > $outStock->getModel()->inventory) {
                        throw new \Exception('出库仓库存不足');
                    }
                    $inStock = new CloudStockSku($inMemberId, $productId, $skuId);
                    if (!$inStock->enable()) {
                        throw new \Exception('此经销商因无资格，所以入库仓未生效');
                    }
                }
//                DB::beginTransaction();
                //生成出库记录
                $outLog = new CloudStockSkuLogModel();
                $outLog->site_id = $site->getSiteId();
                $outLog->member_id = $outMemberId;
                $outLog->product_id = $productId;
                $outLog->sku_id = $skuId;
                $outLog->from_stock_id = 0;
                $outLog->num_before = $outStock->getModel()->inventory;
                $outLog->num_after = $outStock->getModel()->inventory - $num;
                $outLog->num = $num * -1;
                $outLog->order_type = $orderType;
                $outLog->order_id = $orderId;
                $outLog->order_item_id = $orderItemId;
                $outLog->in_type = 0;
                if ($orderType == Constants::CloudStockOrderType_Purchase) {
                    $outLog->out_type = Constants::CloudStockOutType_SubSale;
                } elseif ($orderType == Constants::CloudStockOrderType_Manual) {
                    $outLog->out_type = Constants::CloudStockOutType_Manual;
                    $inMember = (new Member($inMemberId))->getModel();
                    $outLog->about = '转移给【' . $inMember->nickname . '】';
                } else {
                    $outLog->out_type = Constants::CloudStockOutType_Sale;
                }
                $outLog->save();

                //修改出库仓的库存
                $stockModelOut = $outStock->getModel();
                $stockModelOut->decrement('inventory', $num);
                $stockModelOut->save();

                // 只有订单类型是下级代理拿货的订单才需要给下级代理配货 或者后台操作给下级增加库存的时候
                if ($orderType == Constants::CloudStockOrderType_Purchase || $orderType == Constants::CloudStockOrderType_Manual) {
                    //生成入库记录
                    $inLog = new CloudStockSkuLogModel();
                    $inLog->site_id = $site->getSiteId();
                    $inLog->member_id = $inMemberId;
                    $inLog->product_id = $productId;
                    $inLog->sku_id = $skuId;
                    $inLog->from_stock_id = $outStock->getModel()->id;
                    $inLog->num_before = $inStock->getModel()->inventory;
                    $inLog->num_after = $inStock->getModel()->inventory + $num;
                    $inLog->num = abs($num);
                    $inLog->order_type = $orderType;
                    $inLog->order_id = $orderId;
                    $inLog->order_item_id = $orderItemId;
                    $inLog->in_type = $orderType == Constants::CloudStockOrderType_Manual ? Constants::CloudStockInType_Manual : Constants::CloudStockInType_Purchase;
                    if ($inLog->in_type == Constants::CloudStockInType_Manual) {
                        $outMember = (new Member($outMemberId))->getModel();
                        $inLog->about = '来自【' . $outMember->nickname . '】的转移';
                    }
                    $inLog->out_type = 0;
                    $inLog->save();

                    //修改入库仓的库存
                    $stockModelIn = $inStock->getModel();
                    $stockModelIn->increment('inventory', $num);
                    $stockModelIn->purchase_at = date('Y-m-d H:i:s');
                    $stockModelIn->save();
                }

                //生成出库仓的结算记录
                //  产品需要:打给上级的款项不需要云仓结算,也不需要结算的财务记录
                $purchaseOrder = CloudStockPurchaseOrderModel::query()
                    ->where('site_id', $site->getSiteId())
                    ->where('id', $orderId)
                    ->first();
                if ($money > 0 && !($purchaseOrder->payee && in_array($purchaseOrder->pay_type, CoreConstants::getOfflinePayType()))) {
                    $settle = CloudStockSkuSettleModel::where(['member_id' => $outMemberId, 'product_id' => $productId, 'sku_id' => $skuId, 'order_type' => $orderType, 'order_id' => $orderId, 'order_item_id' => $orderItemId, 'num' => $num])->first();
                    if (!$settle) {
                        $settle = new CloudStockSkuSettleModel();
                        $settle->site_id = $site->getSiteId();
                        $settle->member_id = $outMemberId;
                        $settle->product_id = $productId;
                        $settle->sku_id = $skuId;
                        $settle->num = $num;
                        $settle->money = $money;
                        $settle->order_type = $orderType;
                        $settle->order_id = $orderId;
                        $settle->order_item_id = $orderItemId;
                    }
                    $financeId = FinanceHelper::addCloudStockGoodsMoney($site->getSiteId(), $outMemberId, $fromMemberIds, $orderId, $money, $orderPayType);
                    $settle->status = 1;
                    $settle->settled_at = date('Y-m-d H:i:s');
                    $settle->finance_id = $financeId;
                    $settle->save();
                }
//                DB::commit();
                $locker->unlock();
                //消息通知，一个订单通知只发送一次
                $inLogRedisKey = $inLog->order_id . $inLog->in_type . $inLog->out_type . $inLog->member_id;
                $outLogRedisKey = $outLog->order_id . $outLog->in_type . $outLog->out_type . $outLog->member_id;
                if (!Redis::exists($inLogRedisKey)) {
                    Redis::setex($inLogRedisKey, 60, '');
                    MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Inventory_Change, $inLog);
                }
                if (!Redis::exists($outLogRedisKey)) {
                    Redis::setex($outLogRedisKey, 60, '');
                    MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Inventory_Change, $outLog);
                }
            } catch (\Exception $ex) {
//                DB::rollback();
                $locker->unlock();
                throw $ex;
            }
        } else {
            throw new \Exception("锁失败，请稍候重试");
        }
    }

    /**
     * 检测指定的商品的库存量是否足够
     *
     * @param array $productAndSkuIds 产品ID、规格ID 和 需要的库存量，格式如 [['product_id' => 1,'sku_id' => 1,'need' => 1],['product_id' => 2,'sku_id' => 2,'need' => 1]]
     * @param int $outMemberId 出库仓的会员ID
     * @return array 格式如
     * [
     *  'all_enough' => 1, 是否所有商品的有足够的库存
     *  'part_enough' => 1, 是否至少有一种商品有足够的库存
     *  'list' => [
     *      $stockRecord1,$stockRecord2...
     *  ]
     * ]
     */
    public static function checkStockInventory($productAndSkuIds, $outMemberId = 0)
    {
        // 如果是总仓
        if (!$outMemberId) {
            return self::checkStockInventoryForManufactory($productAndSkuIds);
        }
        $query = CloudStockSkuModel::query();
        $query->where(['status' => 1, 'member_id' => $outMemberId, 'site_id' => Site::getCurrentSite()->getSiteId()]);
        $whereRaw = "(";
        foreach ($productAndSkuIds as $item) {
            $whereRaw .= "(product_id = " . $item['product_id'] . " and sku_id = " . $item['sku_id'] . ") OR ";
        }
        $whereRaw = substr($whereRaw, 0, -4) . ")";
        $query->whereRaw($whereRaw);
        $list = $query->get();
        $outList = [];
        $allEnough = true;
        $partEnough = false;
        $collection = collect($productAndSkuIds);
        //处理数据库里找到记录的
        foreach ($list as $item) {
            $find = $collection->where('product_id', $item->product_id)->where('sku_id', $item->sku_id)->first();
            $need = $find['need'];
            $key = 'item' . $item->product_id . '_' . $item->sku_id;
            $outList[$key] = ['product_id' => $item->product_id, 'sku_id' => $item->sku_id, 'member_id' => $item->member_id, 'inventory' => $item->inventory, 'need' => $need];
            $allEnough &= $item->inventory >= $need;
            $partEnough |= $item->inventory >= $need;
        }
        //处理数据库里找不到记录的
        foreach ($productAndSkuIds as $item) {
            $key = 'item' . $item['product_id'] . '_' . $item['sku_id'];
            if (!$outList[$key]) {
                $allEnough = false;
                $find = $collection->where('product_id', $item['product_id'])->where('sku_id', $item['sku_id'])->first();
                $need = $find['need'];
                $outList[$key] = ['product_id' => $item['product_id'], 'sku_id' => $item['sku_id'], 'member_id' => $outMemberId, 'inventory' => 0, 'need' => $need];
            }
        }
        return ['all_enough' => $allEnough, 'part_enough' => $partEnough, 'list' => $outList];
    }

    /**
     * 检测指定的商品的总仓库存量是否足够
     *
     * @param array $productAndSkuIds 产品ID、规格ID 和 需要的库存量，格式如 [['product_id' => 1,'sku_id' => 1,'need' => 1],['product_id' => 2,'sku_id' => 2,'need' => 1]]
     * @return array 格式如
     * [
     *  'all_enough' => 1, 是否所有商品的有足够的库存
     *  'part_enough' => 1, 是否至少有一种商品有足够的库存
     *  'list' => [
     *      $stockRecord1,$stockRecord2...
     *  ]
     * ]
     */
    public static function checkStockInventoryForManufactory($productAndSkuIds)
    {
        $collection = collect($productAndSkuIds);
        $skuIds = $collection->pluck('sku_id')->all();
        // 查找所有的sku数据
        $skus = ProductSkusModel::query()
            ->whereIn('id', $skuIds)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->select(['id', 'inventory', 'product_id'])
            ->get();
        $outList = [];
        $allEnough = true;
        $partEnough = false;
        //处理数据库里找到记录的
        foreach ($skus as $item) {
            $find = $collection->where('product_id', $item->product_id)->where('sku_id', $item->id)->first();
            $need = $find['need'];
            $key = 'item' . $item->product_id . '_' . $item->id;
            $outList[$key] = ['product_id' => $item->product_id, 'sku_id' => $item->id, 'member_id' => 0, 'inventory' => $item->inventory, 'need' => $need];
            $allEnough &= $item->inventory >= $need;
            $partEnough |= $item->inventory >= $need;
        }
        //处理数据库里找不到记录的
        foreach ($productAndSkuIds as $item) {
            $key = 'item' . $item['product_id'] . '_' . $item['sku_id'];
            if (!$outList[$key]) {
                $allEnough = false;
                $find = $collection->where('product_id', $item['product_id'])->where('sku_id', $item['sku_id'])->first();
                $need = $find['need'];
                $outList[$key] = ['product_id' => $item['product_id'], 'sku_id' => $item['sku_id'], 'member_id' => 0, 'inventory' => 0, 'need' => $need];
            }
        }
        return ['all_enough' => $allEnough, 'part_enough' => $partEnough, 'list' => $outList];
    }

    /**
     * 云仓提货/后台扣减库存
     * 特别注意:目前认为orderId等于0的时候,是后台的人工操作,后期有需要改动的时候,可增加参数做标识
     * @param int $productId 商品id
     * @param int $skuId 商品sku
     * @param int $outMemberId 会员id
     * @param string $orderId 订单id
     * @param int $orderItemId 订单的item id
     * @param int $num 商品数量
     * @throws \Exception
     */
    public static function cloudStockCreateTakeDelivery($productId, $skuId, $outMemberId, $orderId, $orderItemId, $num)
    {
        $site = Site::getCurrentSite();
        $lockId = "cloudStockTakeDeliveryBySku_" . $outMemberId . "_" . $productId . "_" . $skuId;
        $locker = new Locker($lockId);
        if ($locker->lock()) {
            try {
                $outStock = new CloudStockSku($outMemberId, $productId, $skuId);
                $stockModelOut = $outStock->getModel();
                if ($num > $stockModelOut->inventory) {
                    throw new \Exception('出库仓库存不足');
                }

                //生成出库记录
                $outLog = new CloudStockSkuLogModel();
                $outLog->site_id = $site->getSiteId();
                $outLog->member_id = $outMemberId;
                $outLog->product_id = $productId;
                $outLog->sku_id = $skuId;
                $outLog->from_stock_id = 0;
                $outLog->num_before = $stockModelOut->inventory;
                $outLog->num_after = $stockModelOut->inventory - $num;
                $outLog->num = $num * -1;
                $outLog->order_type = Constants::CloudStockOrderType_TakeDelivery;
                $outLog->order_id = $orderId;
                $outLog->order_item_id = $orderItemId;
                $outLog->in_type = 0;
                $outLog->out_type = Constants::CloudStockOutType_Take;
                $outLog->save();

                //修改出库仓的库存
                $stockModelOut->decrement('inventory', $num);
                $stockModelOut->save();
                $locker->unlock();
            } catch (\Exception $ex) {
                $locker->unlock();
                throw $ex;
            }
        } else {
            throw new \Exception("锁失败，请稍候重试");
        }
    }

    /**
     * 云仓提货(用于用户提货时，取消订单，把库存给用户加回去)
     * @param int $productId 商品id
     * @param int $skuId 商品sku
     * @param int $inMemberId 会员id
     * @param string $orderId 订单id
     * @param int $orderItemId 订单的item id
     * @param int $num 商品数量
     * @throws \Exception
     */
    public static function cloudStockRefundTakeDelivery($productId, $skuId, $inMemberId, $orderId, $orderItemId, $num)
    {
        $site = Site::getCurrentSite();
        $lockId = "cloudStockTakeDeliveryBySku_" . $inMemberId . "_" . $productId . "_" . $skuId;
        $locker = new Locker($lockId);
        if ($locker->lock()) {
            try {
                $inStock = new CloudStockSku($inMemberId, $productId, $skuId);
                $stockModelIn = $inStock->getModel();

                //生成出库记录
                $inLog = new CloudStockSkuLogModel();
                $inLog->site_id = $site->getSiteId();
                $inLog->member_id = $inMemberId;
                $inLog->product_id = $productId;
                $inLog->sku_id = $skuId;
                $inLog->from_stock_id = 0;
                $inLog->num_before = $stockModelIn->inventory;
                $inLog->num_after = $stockModelIn->inventory + $num;
                $inLog->num = $num;
                $inLog->order_type = Constants::CloudStockOrderType_RefundTakeDelivery;
                $inLog->order_id = $orderId;
                $inLog->order_item_id = $orderItemId;
                $inLog->in_type = Constants::CloudStockInType_TakeDelivery_Return;
                $inLog->out_type = 0;
                $inLog->save();

                //修改出库仓的库存
                $stockModelIn->increment('inventory', $num);
                $stockModelIn->save();
                $locker->unlock();

                $inLogRedisKey = $inLog->order_id . $inLog->in_type . $inLog->out_type . $inLog->member_id;
                if (!Redis::exists($inLogRedisKey)) {
                    Redis::setex($inLogRedisKey, 60, '');
                    MessageNotice::dispatch(CoreConstants::MessageType_CloudStock_Inventory_Change, $inLog);
                }
            } catch (\Exception $ex) {
                $locker->unlock();
                throw $ex;
            }
        } else {
            throw new \Exception("锁失败，请稍候重试");
        }
    }

    /**
     * 获取某会员的补货列表
     * @param $memberId
     * @return mixed
     */
    public static function getReplenishProduct($memberId, $page = 1, $page_size = 20)
    {
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;
        $cloudstock = (new CloudStock($memberId))->getModel();
        //先查出来需要补货的产品ID，待配舱，库存，待补货,购物车数量数据等等
        //搜出未配舱的订单，同SKU_ID的商品相加减去对应的库存
        $sql = 'SELECT cpoi.product_id,
			              p.name as product_name,
			              if(ps.sku_image,ps.sku_image,p.small_images) as product_image,
			              ps.sku_name as sku_name,
			              cpoi.sku_id,
			              sum(cpoi.num) as stockdeliver_num,
			              if(cs.inventory,cs.inventory,0) as inventory ,
			             (CAST(if(cs.inventory,cs.inventory,0) AS signed) - sum(CAST(cpoi.num AS signed))) as group_replenish,
			              if(csc.product_quantity,csc.product_quantity,0) as cart_num
                     from 
                     tbl_cloudstock_purchase_order_item AS cpoi 
                    LEFT JOIN tbl_cloudstock_sku as cs on cpoi.sku_id = cs.sku_id and cs.member_id =' . $memberId . '
                    LEFT JOIN tbl_cloudstock_shop_cart as csc on csc.product_skus_id=cpoi.sku_id and csc.member_id=' . $cloudstock->member_id . '
                    LEFT JOIN tbl_product_skus as ps on cpoi.sku_id=ps.id
                    LEFT JOIN tbl_product as p on ps.product_id=p.id
                    where 
                    cpoi.order_id in (SELECT id from tbl_cloudstock_purchase_order where status = ' . Constants::CloudStockPurchaseOrderStatus_Reviewed . ' and cloudstock_id = ' . $cloudstock->id . ')
                    and cpoi.site_id = ' . Site::getCurrentSite()->getSiteId() . ' 
                    and ps.id is not null
                    and p.status = 1
                    and cpoi.cloudstock_id = ' . $cloudstock->id . ' GROUP BY sku_id HAVING group_replenish<0 
                    ';
        $list = DB::select($sql);
        $new_list = [];
        $replenish_product_total = 0;
        //整合数据
        foreach ($list as &$item) {
            $item->product_image = explode(',', $item->product_image)[0];
            $item->group_replenish = abs($item->group_replenish);
            $replenish_product_total += $item->group_replenish;
            $new_list[$item->product_id]['product_name'] = $item->product_name;
            $new_list[$item->product_id]['product_image'] = $item->product_image;

            $item->sku_name = $item->sku_name ? json_decode($item->sku_name, true) : [];
            if (count($item->sku_name)) {
                $new_list[$item->product_id]['total_replenish'] += $item->group_replenish;
                $new_list[$item->product_id]['item'][] = $item;
            } else {
                $item->total_replenish = $item->group_replenish;
                $new_list[$item->product_id] = $item;
            }
        }
        // 拿取所有产品ID
        $product_id = array_keys($new_list);
        //根据所有产品ID来进行分页
        $product_expression = ProductModel::query()
            ->whereIn('id', $product_id)
            ->where('status', CoreConstants::Product_Status_Sell)
            ->select('id');
        $count = $product_expression->count();
        $product_expression = $product_expression->forPage($page, $page_size);
        $product_list = $product_expression->get();
        $new_product_list = [];
        //分页后的数据 再进行数据整合
        foreach ($product_list as $item) {
            $new_product_list[] = $new_list[$item->id];
        }
        $last_page = ceil($count / $page_size);
        $shopCart = (new ShopCart())->getShoppingCartNum(0, 1);
        $data = [
            'list' => $new_product_list,
            'replenish_product' => $count,
            'replenish_product_total' => abs($replenish_product_total),
            'total' => $count,
            'last_page' => $last_page,
            'total_cart_num' => $shopCart['product_num']
        ];
        return $data;
    }


    /**
     * 新增某会员云仓库存商品
     * @param $memberId 会员ID
     * @param $decreaseType 扣减的类型 0:公司 1:上级
     * @param $product [['product_id' => 1,'sku_id' => 1,'num' => 1],['product_id' => 2,'sku_id' => 2,'num => 1]]
     * @param $parentId 指定上级，上级会员ID
     * @return mixed
     */
    public static function addCloudstockSkuProduct($member_id, $decreaseType, $productList, $parentId = 0)
    {
        try {
            // 寻找上级 如果有指定上级或指定人，首选指定人为出仓人，否则去寻找父级
            if ($decreaseType == 1) $parentDealerMemberId = $parentId ? $parentId : ((new Member($member_id))->getModel())->dealer_parent_id;
            // 商品检测,检测商品是否足够添加
            $check = self::checkProductInventory($parentDealerMemberId, $decreaseType, $productList);
            if ($check) {
                return makeApiResponseFail('', $check);
            }
            DB::beginTransaction();
            // 添加库存 ,需要判断这个人的云仓里面是否已有这个产品如果有只是更改数量,没有就新加
            foreach ($productList as $item) {
                if ($decreaseType == 1 && $parentDealerMemberId) {
                    static::stockDeliver(
                        $item['product_id'],
                        $item['sku_id'],
                        $parentDealerMemberId, // out
                        $member_id, // in
                        Constants::CloudStockOrderType_Manual,
                        0,
                        $item['id'],
                        0,
                        $item['num'],
                        0
                    );
                } else {
                    static::stockDeliverByManufactor(
                        $item['product_id'],
                        $item['sku_id'],
                        $member_id,
                        Constants::CloudStockInType_Manual,
                        0,
                        0,
                        $item['num']
                    );
                }
            }
            DB::commit();
            return makeServiceResultSuccess('ok');
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    /**
     * 减少某会员云仓库存商品
     * @param $memberId 会员ID
     * @param $product [['product_id' => 1,'sku_id' => 1,'num' => 1],['product_id' => 2,'sku_id' => 2,'num => 1]]
     * @return mixed
     */
    public static function decreaseCloudstockSkuProduct($member_id, $productList)
    {
        try {
            DB::beginTransaction();
            // 添加库存 ,需要判断这个人的云仓里面是否已有这个产品如果有只是更改数量,没有就新加
            foreach ($productList as $item) {
                $self = new self($member_id, $item['product_id'], $item['sku_id']);
                $self->adjustInventoryManual(-$item['num'], 1, 0);
            }
            DB::commit();
            return makeServiceResultSuccess('ok');
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    /**
     * 检测库存
     * @param $memberId 会员ID
     * @param $decreaseType 扣减的类型 0:公司 1:上级
     * @param $product [['product_id' => 1,'sku_id' => 1,'add' => 1],['product_id' => 2,'sku_id' => 2,'add => 1]]
     * @return mixed
     */
    public static function checkProductInventory($member_id, $decreaseType, $productList)
    {
        $collection = collect($productList);
        $skuIds = $collection->pluck('sku_id')->all();

        // 查找所有的sku数据
        if ($decreaseType == 0 || intval($member_id) == 0) {
            $skus = ProductSkusModel::query()
                ->leftJoin('tbl_product', 'tbl_product.id', '=', 'tbl_product_skus.product_id')
                ->whereIn('tbl_product_skus.id', $skuIds)
                ->where('tbl_product_skus.site_id', Site::getCurrentSite()->getSiteId())
                ->selectRaw('tbl_product_skus.id as sku_id,inventory,product_id,sku_name,sku_image as product_image,status')
                ->get();
        } else if ($decreaseType == 1 && $member_id > 0) {
            $skus = CloudStockSkuModel::query()
                ->whereIn('sku_id', $skuIds)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $member_id)
                ->select(['id', 'inventory', 'product_id', 'product_image', 'sku_name', 'sku_id', 'status'])
                ->get();
        }
        foreach ($productList as $item) {
            $skusItem = $skus->where('product_id', $item['product_id'])->where('sku_id', $item['sku_id'])->first();
            $notEnough = $item['num'] - intval($skusItem->inventory);
            if ($notEnough > 0 || $skusItem->status != 1) {
                $outList[] = [
                    'product_id' => $skusItem->product_id ? $skusItem->product_id : $item['product_id'],
                    'sku_id' => $skusItem->sku_id ? $skusItem->sku_id : $item['sku_id'],
                    'inventory' => $skusItem->inventory ? $skusItem->inventory : 0,
                    'not_enough' => $notEnough,
                    'sku_name' => $skusItem['sku_name'] ? ($skusItem['sku_name'] ? json_decode($skusItem['sku_name'], true) : '') : $item['sku_name'],
                    'product_image' => $skusItem['product_image'] ? $skusItem['product_image'] : $item['small_image'],
                    // 新增商品选择为上级的时候，如果找不到这个商品， 显示为缺库存
                    'status' => $skusItem->status ? $skusItem->status : ($decreaseType == 1 && $member_id > 0) ? 1 : -9
                ];
            }
        }
        return $outList;
    }


    /**
     * 检测库存
     * @param $memberId 会员ID
     * @param $decreaseType 扣减的类型 0:公司 1:上级
     * @param $product [['product_id' => 1,'sku_id' => 1,'add' => 1],['product_id' => 2,'sku_id' => 2,'add => 1]]
     * @return mixed
     */
    public static function ProductInventoryList($member_id, $decreaseType, $changeType, $productList)
    {
        $collection = collect($productList);
        $skuIds = $collection->pluck('sku_id')->all();
        $dealerParentId = ((new Member($member_id))->getModel())->dealer_parent_id;
        // 查找所有的sku数据
        if (($decreaseType == 0 && $changeType == 1) || ($dealerParentId == 0 && $changeType == 1)) {
            $skus = ProductSkusModel::query()
                ->leftJoin('tbl_product', 'tbl_product.id', '=', 'tbl_product_skus.product_id')
                ->whereIn('tbl_product_skus.id', $skuIds)
                ->where('tbl_product_skus.site_id', Site::getCurrentSite()->getSiteId())
                ->selectRaw('tbl_product_skus.id as sku_id,inventory,product_id,sku_name,sku_image as product_image,status')
                ->get();
        } else if (($decreaseType == 1 || $changeType = 2) && $member_id > 0) {
            // 减少的时候，查看自己的库存
            if ($changeType == 2) {
                $memberId = $member_id;
            } else {
                $memberId = $dealerParentId;
            }
            $skus = CloudStockSkuModel::query()
                ->whereIn('sku_id', $skuIds)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $memberId)
                ->select(['id', 'inventory', 'product_id', 'product_image', 'sku_name', 'sku_id', 'status'])
                ->get();
        }
        foreach ($productList as $item) {
            $skusItem = $skus->where('product_id', $item['product_id'])->where('sku_id', $item['sku_id'])->first();
            $list[] = [
                'product_id' => $skusItem->product_id ? $skusItem->product_id : $item['product_id'],
                'sku_id' => $skusItem->sku_id ? $skusItem->sku_id : $item['sku_id'],
                'inventory' => $skusItem->inventory ? $skusItem->inventory : 0,
                'sku_name' => $skusItem['sku_name'] ? ($skusItem['sku_name'] ? json_decode($skusItem['sku_name'], true) : '') : $item['sku_name'],
                'product_image' => $skusItem['product_image'] ? $skusItem['product_image'] : $item['small_image'],
                'status' => $skusItem->product_id ? $skusItem->status : 1
            ];
        }
        return $list;
    }
}