<?php
/**
 * 拼团商品
 * User: liyaohui
 * Date: 2020/4/8
 * Time: 17:17
 */

namespace App\Modules\ModuleShop\Libs\Shop;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use YZ\Core\Locker\Locker;
use YZ\Core\Member\Auth;
use YZ\Core\Model\BaseModel;

class GroupBuyingShopProduct extends BaseShopProduct
{
    protected $_groupProductSku = null;
    protected $_groupSettingId = 0;
    protected $_groupProductId = 0;
    protected $_groupSkuId = 0;
    protected $_openHeadPrice = false; // 是否开启了团长优惠
    protected $_openBuyLimit = false; // 是否开启
    protected $_isHead = 0; // 是否是团长
    protected $siteId = 0;

    /**
     * GroupBuyingShopProduct constructor.
     * @param int $num
     * @param $groupSkuId
     * @param $params
     * @throws \Exception
     */
    public function __construct($groupSkuId, $num = 1, $params = [])
    {
        $this->siteId = getCurrentSiteId();
        $this->initGroupProductSku($groupSkuId);
        $this->_groupSettingId = $this->_groupProductSku->group_buying_setting_id;
        $this->_groupProductId = $this->_groupProductSku->group_product_id;
        $this->_groupSkuId = $groupSkuId;
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        $this->_openHeadPrice = !!$setting->open_head_discount; // 是否开启了团长优惠
        $this->_openBuyLimit = !!$setting->open_buy_limit; // 是否开启了数量限制
        if ($params['is_head']) {
            $this->_isHead = 1;
        }
        parent::__construct($this->_groupProductSku->master_product_id, $this->_groupProductSku->sku_id, $num);
        $this->oriPrice = $this->_groupProductSku->group_price;
    }

    /**
     * @return int
     */
    public function getGroupProductId()
    {
        return $this->_groupProductId;
    }

    /**
     * @return int
     */
    public function getGroupSkuId()
    {
        return $this->_groupSkuId;
    }

    /**
     * 初始化拼团商品数据
     * @param $groupSkuId
     * @throws \Exception
     */
    public function initGroupProductSku($groupSkuId)
    {
        $this->_groupProductSku = GroupBuyingSkusModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $groupSkuId)
            ->first();
        if (!$this->_groupProductSku) {
            throw new \Exception('活动商品不存在');
        }
    }

    /**
     * 检测是否满足限购条件
     * @return bool|array
     */
    public function checkBuyLimit()
    {

        if ($this->_openBuyLimit) {
            $groProduct = GroupBuyingProductsModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $this->getGroupProductId())
                ->first();
            // 无填写数量的时候，认为限购
            if(!$groProduct->buy_limit_num) return true;
            $have = $this->getBuyNumWithLimit();
            if ($have + $this->num > $groProduct->buy_limit_num) {
                $max = $groProduct->buy_limit_num;
                if ($max < 0) $max = 0;
                return ['code' => 414, 'max' => $max];
            }
        }
        return true;
    }

    /**
     * 结合当前登录会员获取商品的限购量
     * @return int -1表示不限制
     */
    public function getBuyLimit()
    {
        if ($this->_openBuyLimit) {
            $groProduct = GroupBuyingProductsModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $this->getGroupProductId())
                ->first();
            // 无填写数量的时候，认为限购
            if(!$groProduct->buy_limit_num) return -1;
            $have = $this->getBuyNumWithLimit();
            $max = $groProduct->buy_limit_num - $have;
            if ($this->_groupProductSku->group_inventory == null) {
                $productSkus = ProductSkusModel::query()
                    ->where('site_id', $this->siteId)
                    ->where('id', $this->_groupProductSku->sku_id)
                    ->select('inventory')
                    ->first();
                $group_inventory = $productSkus->inventory;
            } else {
                $group_inventory = $this->_groupProductSku->group_inventory;
            }
            if ($max > $group_inventory) $max = $group_inventory;
            return $max;
        }
        return -1;
    }

    /**
     * 检测是否满足最低起购量
     * @return bool|array
     */
    public function checkMinBuyNum()
    {
        return true;
    }

    /**
     * 结合当前登录会员获取商品的起购量
     * @return int
     */
    public function getMinBuyNum()
    {
        return 1;
    }
    /**
     * 获取拼团商品已购买的数量
     * @return int
     */
    private function getBuyNumWithLimit()
    {
        $orderStatus = [
            Constants::OrderStatus_NoPay,
            Constants::OrderStatus_OrderPay,
            Constants::OrderStatus_OrderSend,
            Constants::OrderStatus_OrderSuccess,
            Constants::OrderStatus_OrderReceive,
            Constants::OrderStatus_OrderFinished
        ];
        $sql = "select sum(item.num) as total from tbl_order_item as item left join tbl_order as o on o.id = item.order_id ";
        $sql .= "where o.status in (" . implode(',',$orderStatus) . ") ";
        $sql .= "and item.activity_sku_id = '" . $this->_groupSkuId . "' ";
        $sql .= "and o.member_id = " . Auth::hasLogin() . " ";
        $res = BaseModel::runSql($sql);
        $have = intval($res[0]->total);
        return $have;
    }

    /**
     * 检测库存
     * @param $quantity
     * @return array
     * @throws \Exception
     */
    public function canBuyCheckInventory($quantity)
    {
        // 先判断主商品的库存
        $parantCheck = parent::canBuyCheckInventory($quantity);
        // 如果主库存不满足 直接返回
        if (!$parantCheck['check']) {
            return $parantCheck;
        }
        // 是否受活动库存限制
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        if ($setting->open_inventory == 1) {
            $locker = new Locker($this->getGroupProductLock(), 10);
            if ($locker->lock(5)) {
                try {
                    // 获取最新的数据
                    $this->initGroupProductSku($this->_groupSkuId);
                    $locker->unlock();
                    return [
                        'check' => $quantity <= $this->_groupProductSku->group_inventory, // 库存是否满足
                        'inventory' => $this->_groupProductSku->group_inventory // 现有库存
                    ];
                } catch (\Exception $e) {
                    $locker->unlock();
                    throw $e;
                }

            } else {
                throw new \Exception('can not init group buying sku locker');
            }
        } else {
            return $parantCheck;
        }
    }

    /**
     * 获取拼团商品sku锁
     * @return string
     */
    public function getGroupProductLock()
    {
        return "Locker_" . $this->_groupSettingId . "_" . $this->_groupProductSku->id;
    }

    /**
     * 获取拼团单价
     * @param bool $headPrice 是否获取团长价
     * @return mixed
     */
    public function getGroupBuyingPrice($headPrice = true)
    {
        if ($headPrice && $this->_isHead && $this->_openHeadPrice) {
            $money = $this->_groupProductSku->head_price;
        } else {
            $money = $this->_groupProductSku->group_price;
        }
        return $money;
    }

    /**
     * 计算产品价格 算团长价
     * @return mixed
     */
    public function calGroupBuyingPrice()
    {
        $money = $this->getGroupBuyingPrice();
        return $money * $this->num;
    }

    /**
     * 计算产品价格
     * @param $memberId
     * @return mixed
     */
    public function calPrice(int $memberId = 0)
    {
        // 不含团长优惠
        $this->money = $this->getGroupBuyingPrice(false);
        return $this->money * $this->num;
    }

    /**
     * 获取当前商品的拼团团长优惠
     * @return mixed
     */
    public function calHeadDiscount()
    {
        return $this->calPrice() - $this->calGroupBuyingPrice();
    }

    /**
     * 计算此商品的分销金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额，格式如
     * ['memberId1' => money1,'memberId2' => money2]
     */
    public function calDistribution(int $memberId): array
    {
        // 是否开启分佣
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        if ($setting->open_distribution == 1) {
            return parent::calDistribution($memberId);
        } else {
            return [];
        }
    }

    /**
     * 计算此商品的正常团队代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentOrderCommision(int $memberId): array
    {
        // 是否开启分佣
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        if ($setting->open_agent == 1) {
            return parent::calAgentOrderCommision($memberId);
        } else {
            return [];
        }
    }

    /**
     * 计算此商品的平级/越级代理分佣金额
     * @param int $memberId 购买者的会员ID
     * @return array 返回会员所得的金额
     */
    public function calAgentSaleRewardCommision(int $memberId): array
    {
        // 是否开启分佣
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        if ($setting->open_agent == 1) {
            return parent::calAgentSaleRewardCommision($memberId);
        } else {
            return [];
        }
    }

    /**
     * 计算此商品的区域代理佣金
     * @param int $memberId 购买者的会员ID
     * @param int $areaId 收货人的县/区ID
     * @return array
     */
    public function calAreaAgentCommission(int $memberId, int $areaId): array
    {
        // 是否开启分佣
        $setting = GroupBuyingSetting::getSetting($this->_groupSettingId);
        if ($setting->open_area_agent == 1) {
            return parent::calAreaAgentCommission($memberId, $areaId);
        } else {
            return [];
        }
    }

    /**
     * 获取此商品的会员单价 拼团是没有会员价的
     * @param int $userLevel
     * @param null $rule
     * @return int|mixed
     */
    public function getMemberPrice($userLevel, $rule = null)
    {
        return $this->getGroupBuyingPrice(false);
    }

    /**
     * 获取商品价格 减去了团长优惠
     * @return mixed
     */
    public function getProductMoney()
    {
        return $this->calGroupBuyingPrice();
    }

    /**
     * 获取此产品在订购时需要记录的快照信息
     * @return mixed
     */
    public function getSnapShotInfo()
    {
        $parent = parent::getSnapShotInfo();
        // 把当前的活动skus信息也保存起来
        $parent['group_buying_sku'] = $this->_groupProductSku->toArray();
        return $parent;
    }
}