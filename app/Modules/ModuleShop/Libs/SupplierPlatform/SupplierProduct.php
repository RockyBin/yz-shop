<?php
/**
 * 供应商平台商品逻辑
 * User: liyaohui
 * Date: 2020/6/27
 * Time: 15:51
 */

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierBaseSettingModel;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use App\Modules\ModuleShop\Libs\Product\ProductParamTemplate;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\Supplier\SupplierBaseSetting;
use App\Modules\ModuleShop\Libs\Supplier\SupplierShopProduct;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use YZ\Core\Common\Export;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use App\Modules\ModuleShop\Libs\SiteConfig\FreightTemplate as Freight;
use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use YZ\Core\Common\DataCache;
use YZ\Core\Member\Member;

class SupplierProduct

{
    private $_site = null;
    private $_product = null;

    public function __construct($product = null, $site = null)
    {
        $this->_site = $site;
        if (!$this->_site) {
            $this->_site = Site::getCurrentSite();
        }
        if ($product) {
            if ($product instanceof ProductModel) {
                $this->_product = $product;
            } else {
                // 将产品对象放到全局变量中，避免当一个请求中需要多次使用此类时要多读数据库
                $this->_product = DataCache::getData(static::class . '_product_' . $product);
                if (!$this->_product) {
                    $this->_product = ProductModel::query()
                        ->where('id', $product)
                        ->where('status', '!=', Constants::Product_Status_Delete)
                        ->first();
                    DataCache::setData(static::class . '_product_' . $product, $this->_product);
                }
                if (!$this->_product) {
                    throw new \Exception('数据不存在');
                }
            }
        } else {
            $this->_product = new ProductModel();
        }
    }

    public static function buildQueryWhere(&$query, $filter)
    {
        // 搜索时间字段
        $timeFiled = 'created_at';
        // 审核状态
        $verifyStatus = Constants::Product_VerifyStatus_Active;
        if (isset($filter['verify_status'])) {
            $verifyStatus = intval($filter['verify_status']);
            // 时间筛选
            switch ($verifyStatus) {
                case Constants::Product_VerifyStatus_WaitReview:
                    $timeFiled = 'submit_verify_at';
                    break;
                case Constants::Product_VerifyStatus_Refuse:
                    $timeFiled = 'verify_at';
                    break;
            }
        }
        $query->where('tbl_product.verify_status', $verifyStatus);
        if (isset($filter['created_at_start'])) {
            $query->where('tbl_product.' . $timeFiled, '>=', $filter['created_at_start']);
        }
        if (isset($filter['created_at_end'])) {
            $query->where('tbl_product.' . $timeFiled, '<=', $filter['created_at_end']);
        }
        // 产品状态
        if (isset($filter['status'])) {
            if (is_array($filter['status'])) {
                $query->whereIn('tbl_product.status', $filter['status']);
            } else {
                if ($filter['status'] == Constants::Product_Status_Sold_Out) {
                    $query->where('is_sold_out', Constants::Product_Sold_Out);
                    // 已售罄的 只查询上架的商品
                    $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
                } else {
                    $query->where('tbl_product.status', $filter['status']);
                }
            }
        } else {
            $query->where('tbl_product.status', '!=', Constants::Product_Status_Delete);
        }

        // 查询分类下的产品
        if ($filter['class']) {
            if (!is_array($filter['class'])) {
                $filter['class'] = [$filter['class']];
            }
            // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
            $allClassIds = $filter['class'];
            ProductClass::getChildClassIds($filter['class'], $allClassIds);
            $allClassIds = array_unique($allClassIds);
            $query->whereHas('productClass', function ($q) use ($allClassIds) {
                return $q->whereIn('tbl_product_class.id', $allClassIds);
            });

        }

        // 查询关键字匹配到的产品
        if ($filter['keyword'] && $keyword = trim($filter['keyword'])) {
            $query->where(function ($q) use ($keyword) {
                return $q->orWhere('tbl_product_skus.serial_number', 'like', '%' . $keyword . '%')
                    ->orWhere('tbl_product.name', 'like', '%' . $keyword . '%');
            });
        }
        // 查找预警产品
        if ($filter['is_inventory'] == 1) {
            $query->selectRaw('Min(tbl_product_skus.inventory) as skus_inventory');
            $query->havingRaw('tbl_product.warning_inventory >= skus_inventory');
        }
        // 商品类型
        if (isset($filter['type'])) {
            $query->where('tbl_product.type', intval($filter['type']));
        }
    }

    /**
     * @param array $filter 要筛选的条件 status,type,class 数组, keyword
     * @param int $page
     * @param int $pageSize
     * @param string $selectRaw 要查找的字段
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     * @throws \Exception
     */
    public static function getList($filter = [], $page = 1, $pageSize = 20, $selectRaw = null)
    {
        // 数据过滤
        $page = intval($page);
        $pageSize = intval($pageSize);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;
        $supplierId = $filter['supplier_id'] ?: SupplierPlatformAdmin::getLoginedSupplierPlatformAdmin()['member_id'];
        $query = ProductModel::query()
            ->where('supplier_member_id', $supplierId);
        if ($filter) {
            // 如果传入了product_ids 说明是要查找特定的产品 其他搜索条件就不应该成立了
            if (empty($filter['product_ids'])) {
                self::buildQueryWhere($query, $filter);
            } else {
                $query->whereIn('tbl_product.id', $filter['product_ids']);
                if (isset($filter['status'])) {
                    $status = myToArray($filter['status']);
                    $query->whereIn('tbl_product.status', $status);
                }
                $page = 0;
            }
        }
        // skus查询要使用leftJoin 不然排序会有问题
        $query->leftJoin('tbl_product_skus', 'tbl_product_skus.product_id', '=', 'tbl_product.id');
        if ($selectRaw === null) {
            $selectRaw = 'sum(tbl_product_skus.inventory) as inventory,tbl_product_skus.sku_code,tbl_product_skus.id as skus_id, tbl_product.*';
        }
        $query->selectRaw($selectRaw);
        $query->groupBy('tbl_product.id');

        // 因为查询中有 group by 和 having 所以count会有问题
        // 使用原生的sql去查询总记录数
        // sql 语句
        $sql = $query->toSql();
        $sql = "select count(*) as product_count from ({$sql}) as temp_count";
        $bindings = $query->getBindings();
        $total = ProductModel::runSql($sql, $bindings);
        $total = $total[0]->product_count;
        // 排序
        $query = self::buildProductListOrder($query, $filter['order_by']);
        // 加载分类
        if ($filter['show_class'] !== 0 && $filter['show_class'] !== false) {
            $query->with([
                'productClass' => function ($q) {
                    $q->where('tbl_product_class.status', 1);
                    $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
                }
            ]);
        }

        $last_page = ceil($total / $pageSize);
        // 没有传page 代表获取所有
        if ($page > 0) {
            $query->forPage($page, $pageSize);
        }

        $list = $query->get()->toArray();
        foreach ($list as &$pro) {
            $pro = self::productPriceCent2Yuan($pro);
            // 售后量
            $pro['after_sale_count'] = $pro['after_sale_count'] ?: '0';
            $pro['sold_count'] = $pro['sold_count'] ?: '0';
        }

        $result = [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }

    /**
     * 导出商品列表
     * @param $params
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     * @throws \Exception
     */
    public static function getExportList($params)
    {
        $supplierId = $params['supplier_id'] ?: SupplierPlatformAdmin::getLoginedSupplierPlatformAdmin()['member_id'];
        $query = ProductModel::query()
            ->from('tbl_product')
            ->where('supplier_member_id', $supplierId)
            ->rightJoin('tbl_product_skus as ps', 'tbl_product.id', 'ps.product_id');
        if ($params['product_ids']) {
            $query->whereIn('tbl_product.id', $params['product_ids']);
        } else {
            // 产品状态
            if (isset($params['status'])) {
                if (is_array($params['status'])) {
                    $query->whereIn('tbl_product.status', $params['status']);
                } else {
                    if ($params['status'] == Constants::Product_Status_Sold_Out) {
                        $query->where('is_sold_out', Constants::Product_Sold_Out);
                        // 已售罄的 只查询上架的商品
                        $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
                    } else {
                        $query->where('tbl_product.status', $params['status']);
                    }
                }
            } else {
                // 默认去查询出售中的
                $query->where('tbl_product.status', '=', Constants::Product_Status_Sell);
            }
            // 审核状态
            $verifyStatus = Constants::Product_VerifyStatus_Active;
            if (isset($filter['verify_status'])) {
                $verifyStatus = intval($params['verify_status']);
            }
            $query->where('tbl_product.verify_status', $verifyStatus);

            // 查询关键字匹配到的产品
            if ($params['keyword']) {
                $query->where(function ($q) use ($params) {
                    return $q->where('ps.serial_number', 'like', '%' . $params['keyword'] . '%')
                        ->orWhere('name', 'like', '%' . $params['keyword'] . '%');
                });
            }
            // 查询分类下的产品
            if ($params['class']) {
                if (!is_array($params['class'])) {
                    $params['class'] = [$params['class']];
                }
                // 如果查询的是父级分类 也需要查询该分类的所有下级分类的产品
                $allClassIds = $params['class'];
                ProductClass::getChildClassIds($params['class'], $allClassIds);
                $allClassIds = array_unique($allClassIds);
                $query->whereHas('productClass', function ($q) use ($allClassIds) {
                    return $q->whereIn('tbl_product_class.id', $allClassIds);
                });
            }
            // 查找预警产品
            if ($params['is_inventory'] == 1) {
                $query->whereRaw('tbl_product.warning_inventory >= ps.inventory');
            }
        }
        $query->addSelect(['tbl_product.id', 'ps.serial_number', 'tbl_product.name', 'ps.supplier_price', 'ps.inventory', 'ps.weight', 'tbl_product.status', 'ps.sku_name', 'tbl_product.verify_status', 'ps.sku_sold_count']);

        $query->with([
            'productClass' => function ($q) {
                $q->where('tbl_product_class.status', 1);
                $q->select(['class_name', 'tbl_product_class.id', 'parent_id']);
            }
        ]);
        if ($params['product_ids']) {
            $query->orderByRaw("find_in_set(tbl_product.id,'" . implode(',', $params['product_ids']) . "')");
        } else {
            // 排序
            $query = self::buildProductListOrder($query, $params['order_by']);
            $query->orderBy('tbl_product.id');
        }

        $list = $query->get();
        foreach ($list as &$item) {
            $item->supplier_price = moneyCent2Yuan($item->supplier_price);
            $classId = $item->productClass->where('parent_id', 0)->pluck('id')->toArray();
            $classArray = [];
            foreach ($item->productClass as $class) {
                if (!in_array($class->parent_id, $classId)) {
                    $classArray[] = $class;
                }
            }
            $item->newProductClass = $classArray;
        }
        return $list;
    }

    /**
     * @param array|int $productId 要修改的产品id 数组或数字
     * @param int $status 要修改为的状态
     * @return int
     * @throws \Exception
     */
    public static function editProductStatus($productId, $status)
    {
        if (empty($productId)) {
            throw new \Exception('缺少产品id');
        }
        // 检测一下供应商状态
        SupplierPlatformAdmin::checkCurrentSupplierStatus();
        $memberId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        // 允许修改的状态
        $statusList = [
            Constants::Product_Status_Sell,
            Constants::Product_Status_Warehouse,
            Constants::Product_Status_Delete
        ];
        $status = intval($status);
        if (in_array($status, $statusList, true)) {
            $update = ProductModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('supplier_member_id', $memberId)
                ->where('status', '!=', Constants::Product_Status_Delete);
            if (!is_array($productId)) {
                $productId = [$productId];
            }
            $update->whereIn('id', $productId);
            $data = ['status' => $status];
            // 如果是上架 同时更新上架时间
            if ($status === Constants::Product_Status_Sell) {
                $data['sell_at'] = Carbon::now();
            } else {
                if ($status === Constants::Product_Status_Delete) {
                    // 删掉中间表的数据
                    DB::table('tbl_product_relation_class')
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->whereIn('product_id', $productId)
                        ->delete();
                }
            }
            return $update->update($data);
        } else {
            throw new \Exception('不能修改为该状态');
        }
    }

    /**
     * 修改商品审核状态
     * @param $params
     * @param $status
     * @return int
     * @throws \Exception
     */
    public static function editProductVerifyStatus($params, $status)
    {
        // 检测一下供应商状态
        SupplierPlatformAdmin::checkCurrentSupplierStatus();
        $memberId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        $update = ProductModel::query()
            ->where('tbl_product.site_id', getCurrentSiteId())
            ->where('supplier_member_id', $memberId)
            ->where('tbl_product.status', '!=', Constants::Product_Status_Delete);
        if (!empty($params['product_id'])) {
            $productId = $params['product_id'];
            if (!is_array($productId)) {
                $productId = [$productId];
            }
            $update->whereIn('id', $productId);
        } else {
            self::buildQueryWhere($update, $params);
        }

        // 允许修改的状态
        $statusList = [
            Constants::Product_VerifyStatus_WaitReview,
            Constants::Product_VerifyStatus_Draft
        ];
        $status = intval($status);
        if (in_array($status, $statusList, true)) {
            $data = ['verify_status' => $status];
            // 如果是提审 更新提审时间
            if ($status === Constants::Product_VerifyStatus_WaitReview) {
                $data['submit_verify_at'] = Carbon::now();
            }
            return $update->update($data);
        } else {
            throw new \Exception('不能修改为该状态');
        }
    }

    /**
     * 构建产品列表的排序
     * @param $query
     * @param $orderBy
     * @return mixed
     * @throws \Exception
     */
    public static function buildProductListOrder($query, $orderBy)
    {
        //使用原生SQL排序的情况
        if ($orderBy['raworder']) {
            return $query->orderByRaw($orderBy['raworder']);
        }
        switch ($orderBy['column']) {
            // 按价格或会员价排序
            case 'price':
                $orderColumn = 'supplier_price';
                break;
            // 库存排序
            case 'inventory':
                $orderColumn = 'inventory';
                break;
            // 销量排序
            case 'sold_count':
                $orderColumn = 'sold_count';
                break;
            // 售罄时间排序
            case 'sold_out_at':
                $orderColumn = 'sold_out_at';
                break;
            // 上架时间排序
            case 'sell_at':
                $orderColumn = 'sell_at';
                break;
            // 创建时间排序
            case 'created_at':
                $orderColumn = 'tbl_product.created_at';
                break;
            // 更新时间排序
            case 'updated_at':
                $orderColumn = 'tbl_product.updated_at';
                break;
            // 按售后数排序
            case 'after_sale_count':
                $orderColumn = 'after_sale_count';
                break;
            // 按sku 预警库存排序
            case 'skus_inventory':
                $orderColumn = 'skus_inventory';
                break;
            // 审核时间排序
            case 'verify_at':
                $orderColumn = 'verify_at';
                break;
            // 提交审核时间排序
            case 'submit_verify_at':
                $orderColumn = 'submit_verify_at';
                break;
            default:
                $orderColumn = 'sell_at';
                $orderBy['order'] = 'desc';
        }
        return $query->orderBy($orderColumn, $orderBy['order']);
    }

    /**
     * 根据时间 导出产品列表
     * @param array $list 要导出的数据
     * @return \Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public static function exportProductList($list)
    {
        $exportList = [];
        $exportFiled = self::formatExportFiled();
        $head = self::exportProductHead();
        foreach ($list as $pro) {
            // 分类名称
            if (empty($pro['newProductClass'])) {
                $pro['class_name'] = '';
            } else {
                $pro['class_name'] = collect($pro['newProductClass'])->implode('class_name', ',');
            }
            switch ($pro['status']) {
                case Constants::Product_Status_Sell:
                    $pro['status'] = '上架';
                    break;
                case Constants::Product_Status_Warehouse:
                    $pro['status'] = '下架';
                    break;
                case Constants::Product_Status_Sold_Out:
                    $pro['status'] = '售罄';
                    break;
                default:
                    $pro['status'] = '未知状态';
            }
            switch ($pro['verify_status']) {
                case Constants::Product_VerifyStatus_Active:
                    $pro['verify_status'] = '已审核';
                    break;
                case Constants::Product_VerifyStatus_WaitReview:
                    $pro['verify_status'] = '待审核';
                    break;
                case Constants::Product_VerifyStatus_Refuse:
                    $pro['verify_status'] = '已拒绝';
                    break;
                case Constants::Product_VerifyStatus_Draft:
                    $pro['verify_status'] = '未提审';
                    break;
                default:
                    $pro['verify_status'] = '未知审核状态';
            }
            $pro['sku'] = json_decode($pro['sku_name'], true);
            $pro['sku1'] = $pro['sku'][0];
            $pro['sku2'] = $pro['sku'][1];
            $pro['sku3'] = $pro['sku'][2];
            $pro['weight'] = $pro['weight'] ? $pro['weight'] : "\t0\t";
            $pro['inventory'] = $pro['inventory'] ? $pro['inventory'] : "\t0\t";
            $pro['sku_sold_count'] = $pro['sku_sold_count'] ? $pro['sku_sold_count'] : "\t0\t";
            $exportList[] = self::formatExportProductData($pro->toArray(), $exportFiled);
        }
        // dd($exportList);
        $export = new Export(collect($exportList), 'Shangpin-' . date("YmdHis") . '.xlsx', $head);
        return $export->export();
    }

    /**
     * 根据要查询的时间 获取要导出的字段
     * @return array
     */
    public static function formatExportFiled()
    {
        $exportFiled = [
            'serial_number' => '',
            'class_name' => '',
            'name' => '',
            'sku1' => '',
            'sku2' => '',
            'sku3' => '',
            'supplier_price' => '0',
            'sku_sold_count' => '0',
            'inventory' => '0',
            'weight' => '0',
            'status' => '',
            'verify_status' => ''
        ];
        return $exportFiled;
    }

    /**
     * 把数据整理成要导出的顺序
     * @param $product
     * @param $format
     * @return array
     */
    public static function formatExportProductData($product, $format)
    {
        $product = array_replace($format, $product);
        $product = array_slice($product, 0, count($format));
        return $product;
    }

    /**
     * 根据时间类型去设置导出的文件头
     * @param string $timeType 数据库里面的时间字段名称
     * @return array
     */
    public static function exportProductHead()
    {
        $head = ['商品编号', '商品分类', '商品名称', '规格1', '规格2', '规格3', '供货价', '销量', '库存', '重量', '状态', '审核状态'];
        return $head;
    }

    /**
     * 获取产品数据
     * @return array
     */
    public function getProductData()
    {
        $product = $this->_product;
        $productData = $product->toArray();
        $class = $product->productClass()->pluck('tbl_product_class.id');
        $skus = $product->productSkus()->select(['id', 'product_id', 'price', 'market_price', 'supply_price', 'member_rule', 'fenxiao_rule', 'agent_order_commission_rule', 'agent_sale_reward_rule', 'dealer_sale_reward_rule', 'cloud_stock_rule', 'area_agent_rule', 'serial_number', 'inventory', 'sku_sold_count', 'weight', 'sku_code', 'supplier_price'])->get();
        $skus = $skus->toArray();
        $skuValue = $product->productSkuValue()->select(['id', 'product_id', 'sku_name_id', 'value', 'small_image', 'big_image'])->get()->toArray();
        $skuName = $product->productSkuName()->select(['id', 'product_id', 'has_image', 'name'])->get()->toArray();
        $viewLevels = $product->viewLevels()->select(['tbl_member_level.id', 'tbl_member_level.name'])->get()->toArray();
        $buyLevels = $product->buyLevels()->select(['tbl_member_level.id', 'tbl_member_level.name'])->get()->toArray();

        // 分转元
        $productData = self::productPriceCent2Yuan($productData);
        foreach ($skus as &$sku) {
            $sku = self::productPriceCent2Yuan($sku);
        }
        unset($sku);
        return [
            'productData' => $productData,
            'class' => $class,
            'skus' => $skus,
            'skuValue' => $skuValue,
            'skuName' => $skuName,
            'viewLevels' => $viewLevels,
            'buyLevels' => $buyLevels,
            'memberLevelList' => (new MemberLevel())->getList()["list"],
            'classList' => self::getClassList(), // 分类列表
            'freightTemplateList' => self::getFreightTemplateList(), // 运费模板列表
            'imagePath' => Site::getSiteComdataDir(),
            'product_sku_num' => ShopConfig::getProductSkuNum(),
            'paramTemplateList' => self::getProductParamTemplateList()
        ];
    }

    /**
     * 检测商品编码是否重复
     * @param string $serialNumber
     * @param int $productId
     * @return bool|int
     */
    public static function checkSerialNumber($serialNumber, $productId = 0)
    {
        if (!$serialNumber) {
            return true;
        } else {
            if (mb_strlen($serialNumber) > 20) {
                return 0;
            } else {
                // 查找是否重复
                $has = ProductSkusModel::query()->where('serial_number', $serialNumber);
                if ($productId) {
                    $has->where('product_id', '<>', $productId);
                }
                $has = $has->count();
                if ($has > 0) {
                    return -1;
                } else {
                    return true;
                }
            }
        }
    }

    /**
     * 获取产品的url
     * @param $id
     * @return string
     */
    public static function getProductUrl($id)
    {
        return url('/') . '/shop/front/#/product/product-detail?id=' . $id;
    }

    /**
     * 获取所有分类列表
     * @return array
     */
    public static function getClassList()
    {
        // 分类列表
        $classList = ProductClassModel::query()
            ->where('status', 1)
            ->select(['id', 'class_name', 'parent_id'])
            ->orderBy('order')
            ->get()->toArray();
        return $classList;
    }

    /**
     * 获取所有运费模板
     * @return array
     */
    public static function getFreightTemplateList()
    {
        // 运费模板列表
        $freightTemplateList = FreightTemplateModel::query()
            ->where('status', 1)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->select(['template_name', 'id'])
            ->orderBy('id', 'desc')
            ->get()->toArray();
        return $freightTemplateList;
    }

    /**
     * 保存供应商商品前的处理
     * @param $productData
     * @param $skus
     * @throws \Exception
     */
    public function beforeSaveSupplierProduct(&$productData, &$skus)
    {
        // 检测一下登录状态
        SupplierPlatformAdmin::checkCurrentSupplierStatus();
        $supplierId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        // 如果为新建的 则要增加供应商id字段
        if (!$this->_product['id']) {
            $productData['supplier_member_id'] = $supplierId;
            $productData['market_price'] = null;
        } elseif ($this->_product['supplier_member_id'] != $supplierId) {
            throw new \Exception('找不到数据');
        }
        $productData['verify_status'] = isset($productData['verify_status']) ? $productData['verify_status'] : Constants::Product_VerifyStatus_Draft;
        // 如果是提交审核 需要更新时间
        if ($productData['verify_status'] === Constants::Product_VerifyStatus_WaitReview) {
            $productData['submit_verify_at'] = Carbon::now();
        }

        // 没有审核通过的 状态都为下架
        if ($this->_product['verify_status'] !== Constants::Product_VerifyStatus_Active) {
            $productData['status'] = Constants::Product_Status_Warehouse;
            // 供应商平台不能修改审核状态为通过
            if ($productData['verify_status'] == Constants::Product_VerifyStatus_Active) {
                throw new \Exception('数据错误：审核状态错误');
            }
        }

        // 获取设置 一些设置要特殊处理
        $setting = SupplierBaseSetting::getCurrentSiteSetting();
        // 不参加云仓
        $productData['cloud_stock_status'] = 0;
        // 价格比例
        $costPricePercent = bcdiv($setting['cost_price_percent'], 100, 5);
        $salePricePercent = bcdiv($setting['sale_price_percent'], 100, 5);
        // 根据设置生成对应的价格规则
        $memberRule = $setting['open_member_price'] ? 0 : -1;
        $pointStatus = $setting['open_point'] ? 0 : -1;
        $fenxiaoRule = $setting['open_distribution'] ? 0 : -1;
        $agentSaleRewardRule = $agentOrderCommissionRule = $setting['open_agent'] ? 0 : -1;
        $areaAgentRule = $setting['open_area_agent'] ? 0 : -1;

        $priceChange = false;
        // 多规格的处理
        if (!empty($skus)) {
            $needRefresh = false; // 是否需要刷新价格
            if ($this->_product['id']) {
                // 获取旧的sku价格数据
                $originalSku = ProductSkusModel::query()
                    ->where('site_id', $this->_site->getSiteId())
                    ->where('product_id', $this->_product['id'])
                    ->select(['id', 'supplier_price'])
                    ->get();
                // 数量不一直需要新增
                if (count($skus) != $originalSku->count()) {
                    $needRefresh = true;
                }
                if (!$needRefresh) {
                    $originalSku = $originalSku->keyBy('id')->toArray();
                    foreach ($skus as $sku) {
                        // 没有id 说明为新加的sku 或者供应商价格有修改 需要去刷新价格
                        if (
                            !$sku['id']
                            || moneyCent2Yuan($originalSku[$sku['id']]['supplier_price']) != $sku['supplier_price']
                        ) {
                            $needRefresh = true;
                            break;
                        }
                    }
                }
            } else {
                $needRefresh = true;
                // 相关的价格规则根据设置生成
                foreach ($skus as &$sku) {
                    $sku['member_rule'] = $memberRule;
                    $sku['fenxiao_rule'] = $fenxiaoRule;
                    $sku['agent_order_commission_rule'] = $agentOrderCommissionRule;
                    $sku['agent_sale_reward_rule'] = $agentSaleRewardRule;
                    $sku['area_agent_rule'] = $areaAgentRule;
                    $sku['market_price'] = null;
                }
                unset($sku);
            }
            // 需要更新sku价格
            if ($needRefresh) {
                foreach ($skus as &$sku) {
                    $sku['price'] = bcmul($sku['supplier_price'], $salePricePercent, 7);
                    $sku['supply_price'] = bcmul($sku['supplier_price'], $costPricePercent, 7);
                }
                unset($sku);
            }
            $priceChange = $needRefresh;
        } else {
            // 单规格 新建的或者价格有修改的需要重新计算价格
            if (
                !$this->_product['id']
                || moneyCent2Yuan($this->_product['supplier_price']) != $productData['supplier_price']
            ) {
                $productData['price'] = bcmul($productData['supplier_price'], $salePricePercent, 7);
                $productData['supply_price'] = bcmul($productData['supplier_price'], $costPricePercent, 7);
                $priceChange = true;
            }
            // 新建的单规格 根据设置生成价格规则
            if (!$this->_product['id']) {
                $productData['member_rule'] = $memberRule;
                $productData['point_status'] = $pointStatus;
                $productData['fenxiao_rule'] = $fenxiaoRule;
                $productData['agent_order_commission_rule'] = $agentOrderCommissionRule;
                $productData['agent_sale_reward_rule'] = $agentSaleRewardRule;
                $productData['area_agent_rule'] = $areaAgentRule;
            } else {
                // 编辑的时候 不去编辑这几个 获取旧的数据
                $originalSku = ProductSkusModel::query()
                    ->where('site_id', $this->_site->getSiteId())
                    ->where('product_id', $this->_product['id'])
                    ->select([
                        'member_rule',
                        'fenxiao_rule',
                        'agent_order_commission_rule',
                        'agent_sale_reward_rule',
                        'area_agent_rule'
                    ])
                    ->first();
                $productData['member_rule'] = $originalSku['member_rule'];
                $productData['point_status'] = $this->_product['point_status'];
                $productData['fenxiao_rule'] = $originalSku['fenxiao_rule'];
                $productData['agent_order_commission_rule'] = $originalSku['agent_order_commission_rule'];
                $productData['agent_sale_reward_rule'] = $originalSku['agent_sale_reward_rule'];
                $productData['area_agent_rule'] = $originalSku['area_agent_rule'];
            }
        }
        // 商品价格是否发生了改变
        if ($priceChange && $this->_product['id']) {
            // 是否需要重新审核
            if ($setting['open_verify_again']) {
                $productData['status'] = Constants::Product_Status_Warehouse;
                $productData['verify_status'] = Constants::Product_VerifyStatus_WaitReview;
                $productData['submit_verify_at'] = Carbon::now();
            }
            $productData['need_send_msg'] = true;
        } elseif (!$priceChange) {
            // 没改变的价格 要使用旧的
            $productData['price'] = moneyCent2Yuan($this->_product['price']);
            $productData['supply_price'] = moneyCent2Yuan($this->_product['supply_price']);
        }
    }

    /**
     * 保存产品相关数据
     * @param array $productData 产品主要数据
     * @param array $skus 产品skus数据
     * @param array $skuData 产品sku的name value关系数组
     * @return ProductModel|null
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function store($productData, $skus = [], $skuData = [])
    {
        if (empty($productData)) {
            throw new \Exception('数据为空');
        }
        DB::beginTransaction();
        try {
            $product = $this->_product;
            $this->beforeSaveSupplierProduct($productData, $skus);

            // 没有sku记录时  直接保存一条新的记录
            if (empty($skus)) {
                $skus = [
                    [
                        'fenxiao_rule' => $productData['fenxiao_rule'],
                        'member_rule' => $productData['member_rule'],
                        'price' => $productData['price'],
                        'supplier_price' => $productData['supplier_price'],
                        'supply_price' => $productData['supply_price'],
                        'market_price' => $productData['market_price'],
                        'inventory' => $productData['inventory'],
                        'weight' => $productData['weight'],
                        'sku_code' => '0',
                        'serial_number' => $productData['serial_number'],
                        'site_id' => $this->_site->getSiteId(),
                        'agent_order_commission_rule' => $productData['agent_order_commission_rule'],
                        'agent_sale_reward_rule' => $productData['agent_sale_reward_rule'],
                        'area_agent_rule' => $productData['area_agent_rule'],
                    ]
                ];
            } else {
                // 获取skus的最低售价和市场价、成本价 保存到产品表
                $productData['price'] = SupplierProductSku::getSkusMinPrice($skus, 'price');
                $productData['supply_price'] = SupplierProductSku::getSkusMinPrice($skus, 'supply_price');
                $productData['supplier_price'] = SupplierProductSku::getSkusMinPrice($skus, 'supplier_price');
            }

            $isNew = $productData['id'] ? false : true;
            $collectSkus = collect($skus);
            $this->beforeSaveProduct($productData, $collectSkus, $isNew);
            // 转换元为分
            $productData = self::productPriceYuan2Cent($productData);
            foreach ($skus as &$sku) {
                $sku = self::productPriceYuan2Cent($sku);
            }
            unset($sku);

            // 如果是已售罄的商品 检查一下 如果增加了库存 则把售罄状态取消
            $inventory = $collectSkus->sum('inventory');
            if ($inventory > 0 && $product->is_sold_out == Constants::Product_Sold_Out) {
                $product->is_sold_out = Constants::Product_No_Sold_Out;
            } elseif ($inventory <= 0) {
                // 如果修改了库存为0 更新售罄时间
                if ($product->is_sold_out != Constants::Product_Sold_Out) {
                    $product->sold_out_at = Carbon::now();
                    $product->is_sold_out = Constants::Product_Sold_Out;
                }
            }
            $newBigImages = $productData['big_images'];
            $originalBigImages = $product->big_images;
            $productData['big_images'] = implode(',', $productData['big_images']);
            $productData['small_images'] = implode(',', $productData['small_images']);

            // 产品参数
            if (is_array($productData['params']) && count($productData['params']) > 0) {
                $productData['params'] = json_encode($productData['params']);
            } else {
                $productData['params'] = null;
            }
            $productData['site_id'] = $this->_site->getSiteId();
            // 如果是直接上架  需要更新一下最新上架时间
            if (intval($productData['status']) === Constants::Product_Status_Sell) {
                $productData['sell_at'] = Carbon::now();
            }
            unset($productData['serial_number']);
            $needSendMsg = $productData['need_send_msg'];
            unset($productData['need_send_msg']);
            $product->fill($productData)->save();
            $this->saveProductClass($productData['class_ids']); // 保存分类
            $productSku = new SupplierProductSku($product, $this->_site);
            $productSku->editProductSkuInfo($skus, $skuData, $isNew); // 保存sku
            // 改变了价格 需要发送消息
            if ($needSendMsg) {
                MessageNoticeHelper::sendMessageSupplierPriceChange($this->_product);
            }
            DB::commit();
            if (!$isNew) {
                // 产品图片 做下处理 如果有不需要的图片要删掉
                beforeSaveImage(explode(',', $originalBigImages), $newBigImages);
                // 小图暂时不删除了
//                beforeSaveImage(explode(',', $product->small_images), $productData['small_images']);
            }
            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 保存商品数据前的操作
     * @param $productData
     * @param $collectSkus
     * @param $isNew
     * @throws \Exception
     */
    public function beforeSaveProduct($productData, $collectSkus, $isNew)
    {
        // 供应商价格
        if ($productData['supplier_price'] < 0) {
            throw new \Exception('供货价不能小于0');
        }
        // 价格是否合法
        if (isset($productData['price']) && $productData['price'] < 0) {
            throw new \Exception('商品价格必须大于0');
        }
        if (isset($productData['supply_price']) && $productData['supply_price'] < 0) {
            throw new \Exception('商品成本价不能小于0');
        }
        if ($productData['inventory'] < 0) {
            throw new \Exception('商品库存不能小于0');
        }
        // 检测商品编码是否重复
        $serialNumber = $collectSkus
            ->pluck('serial_number')
            ->filter(function ($value) {
                return mb_strlen($value) > 0;
            });
        // 判断是否有重复的
        if ($serialNumber->count() != $serialNumber->unique()->count()) {
            throw new \Exception('商品编码重复');
        }
        $serialNumber = $serialNumber->all();
        if ($serialNumber) {
            $isExist = ProductSkusModel::query()->whereIn('serial_number', $serialNumber);
            if (!$isNew) {
                $isExist->where('product_id', '<>', $productData['id']);
            }
            $isExist = $isExist->select(['id'])->first();
            if ($isExist) {
                throw new \Exception('商品编码已存在');
            }
        }
    }

    /**
     * 把相关价格转换为分
     * @param $priceData
     * @return mixed
     */
    public static function productPriceYuan2Cent($priceData)
    {
        $filed = ['price', 'market_price', 'supply_price', 'supplier_price'];
        foreach ($filed as $item) {
            if (isset($priceData[$item])) {
                $priceData[$item] = moneyYuan2Cent($priceData[$item]);
            }
        }
        return $priceData;
    }

    /**
     * 把相关价格转换为元
     * @param $priceData
     * @return mixed
     */
    public static function productPriceCent2Yuan($priceData)
    {
        $filed = ['price', 'ori_price', 'market_price', 'supply_price', 'member_price', 'supplier_price'];
        foreach ($filed as $item) {
            if (isset($priceData[$item])) {
                $priceData[$item] = moneyCent2Yuan($priceData[$item]);
            }
        }
        return $priceData;
    }

    /**
     * 上传产品图片
     * @param UploadedFile $image
     * @return array                大图和小图的路径
     * @throws \Exception
     */
    public static function uploadProductImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/image/';
        // 保存名称
        $saveName = 'product' . time() . str_random(5);
        $bigImage = $saveName . '_b';
        $smallImage = $saveName . '_s';
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(1500, $bigImage);
        $img->reduceImageSize(400, $smallImage);
        return [
            'bigImage' => $savePath . $bigImage . '.' . $extension,
            'smallImage' => $savePath . $smallImage . '.' . $extension
        ];
    }

    /**
     * 上传产品sku图片
     * @param UploadedFile $image
     * @return array                大图和小图的路径
     * @throws \Exception
     */
    public static function uploadProductSkuImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/sku/';
        // 保存名称
        $saveName = 'product-sku' . time() . str_random(5);
        $bigImage = $saveName . '_b';
        $smallImage = $saveName . '_s';
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(1500, $bigImage);
        $img->reduceImageSize(400, $smallImage);
        return [
            'bigImage' => $savePath . $bigImage . '.' . $extension,
            'smallImage' => $savePath . $smallImage . '.' . $extension
        ];
    }

    /**
     * 上传产品视频封面
     * @param UploadedFile $image
     * @return 封面图的路径
     * @throws \Exception
     */
    public static function uploadProductVideoPoster(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/product/image/';
        // 保存名称
        $saveName = 'video_poster' . time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 限制图片的宽度
        $img->reduceImageSize(1500, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 保存产品分类关系
     * @param array $classIds
     */
    public function saveProductClass($classIds = [])
    {
        if (!empty($classIds)) {
            $product = $this->_product;
            $saveClass = [];
            foreach ($classIds as $id) {
                $saveClass[$id] = ['site_id' => $this->_site->getSiteId()];
            }
            $product->productClass()->sync($saveClass);
        }
    }

    /**
     * 获取产品详情数据
     * $front_params 前端传输数据
     * @return array
     */
    public function getProducDetail($params, $front_show = false)
    {
        $supplierBaseSetting = SupplierBaseSettingModel::find($this->_product->site_id);
        $product = $this->_product;
        $productData = $product->toArray();
        $skus = $product->productSkus()->get()->toArray();
        // 分转元
        $productData = self::productPriceCent2Yuan($productData);
        //输出SKU最大值以及SKU最小值
        $productData['max_price'] = 0;
        $productData['min_price'] = 999999999; // 设置一个足够的值
        $discount = 100;
        if ($params['member_id']) {
            $memberLevel = new MemberLevel();
            // 输出会员折扣
            $discount = $memberLevel->getMemberDiscount($params['member_id']);
            if (!$discount) $discount = 100;
            if ($this->_product->supplier_member_id && !$supplierBaseSetting->open_member_price) {
                $discount = 100; //供应商关闭了会员价
            }
        }
        foreach ($skus as &$sku) {
            $sku = self::productPriceCent2Yuan($sku);
            $productData['max_price'] = max($productData['max_price'], $sku['price']) * ($discount / 100);
            $productData['min_price'] = min($productData['min_price'], $sku['price']) * ($discount / 100);
        };
        unset($sku);
        //如果有
        if ($front_show) {
            //输出可领取的优惠券，有效的，在有效期内的，产品ID，该网站ID，数量大于0的
            $coupon = new Coupon();
            $param = [];
            $param['product_id'] = $productData['id'];
            $param['status'] = 1;
            if ($this->_product->supplier_member_id && !$supplierBaseSetting->open_coupon) {
                $couponData = [];
            } else {
                $couponData = $coupon->couponProduct($param);
            }
            $productData['couponData'] = $couponData;
            //输出运费模板的运费
            if ($product['freight_id']) {
                $freight = new Freight();
                $freightData = $freight->getAreaFreight($product['freight_id'], $params['city']);
                $productData['freight_fee'] = $freightData;
            } else {
                $productData['freight_fee'] = 0;
            }
            //输出这个产品是否被收藏了
            if ($params['member_id']) {
                $collection = new ProductCollection();
                $collection->findByMemberProduct($params['member_id'], $param['product_id']);
                $productData['have_collection'] = $collection->getModel();
            }
        }
        $productData['detail'] = LinkHelper::replaceHtmlLink($productData['detail']);
        return [
            'productData' => $productData,
            'imagePath' => Site::getSiteComdataDir()
        ];
    }

    public function getSku($memberId = 0)
    {
        if ($memberId) {
            $member = new Member($memberId);
        }
        $product = $this->_product;
        $skus = $product->productSkus()->get();
        $skuValue = $product->productSkuValue()->get()->toArray();
        $skuName = $product->productSkuName()->get()->toArray();
        $baseShopProduct = null;
        $expression = ProductPriceRuleModel::query()->from('tbl_product_price_rule');
        //寻找自定义规则
        $expression->whereIn('id', $skus->pluck('member_rule')->toArray());
        $rules = $expression->get()->keyBy('member_rule');
        foreach ($skus as &$sku) {
            if ($member) {
                if (!$baseShopProduct) {
                    if ($product->supplier_member_id) $baseShopProduct = new SupplierShopProduct($product, $sku);
                    else $baseShopProduct = new NormalShopProduct($product, $sku);
                } else $baseShopProduct->setSku($sku);
                $rule = $rules[$sku->member_rule];
                $sku->price = $baseShopProduct->getMemberPrice($member->getModel()->level, $rule);
            }
            $sku = self::productPriceCent2Yuan($sku);
        };
        foreach ($skuName as $k => &$v) {
            foreach ($skuValue as $k1 => $v1) {
                if ($v['id'] == $v1['sku_name_id']) {
                    $v['children'][] = $v1;
                }
            }
        }

        return ['skuName' => $skuName, 'skus' => $skus];
    }

    /**
     * 返回当前数据模型
     * @return mixed
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->_product;
        } else {
            return false;
        }
    }

    /**
     * 当前数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_product && $this->_product->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取产品数量统计
     * @return array
     */
    public static function getProductCount($supplierId)
    {
        $productQuery = ProductModel::query()
            ->where('type', '<', Constants::Product_Type_Fenxiao_Physical)
            ->where('status', '!=', Constants::Product_Status_Delete)
            ->where('supplier_member_id', $supplierId);
        $productWarningCountQuery = clone $productQuery;
        // 统计出出售中 仓库中 和 已售罄 未提审 待审核 已拒绝的产品数量
        $productCount = $productQuery->selectRaw(
            "SUM(CASE WHEN status=? and verify_status=? THEN 1 ELSE 0 END) AS sell,
                SUM(CASE WHEN status=? and verify_status=? THEN 1 ELSE 0 END) AS warehouse,
                SUM(CASE WHEN is_sold_out=? and status=? and verify_status=? THEN 1 ELSE 0 END) AS sold_out,
                SUM(CASE verify_status WHEN ? THEN 1 ELSE 0 END) AS wait_review,
                SUM(CASE verify_status WHEN ? THEN 1 ELSE 0 END) AS draft,
                SUM(CASE verify_status WHEN ? THEN 1 ELSE 0 END) AS refuse",
            [
                Constants::Product_Status_Sell,
                Constants::Product_VerifyStatus_Active,
                Constants::Product_Status_Warehouse,
                Constants::Product_VerifyStatus_Active,
                Constants::Product_Sold_Out,
                Constants::Product_Status_Sell,
                Constants::Product_VerifyStatus_Active,
                Constants::Product_VerifyStatus_WaitReview,
                Constants::Product_VerifyStatus_Draft,
                Constants::Product_VerifyStatus_Refuse
            ]
        )
            ->first();

        $productWarningCount = $productWarningCountQuery
            ->where('status', Constants::Product_Status_Sell)
            ->where('type', '<', Constants::Product_Type_Fenxiao_Physical)
            ->leftJoin('tbl_product_skus', 'tbl_product_skus.product_id', '=', 'tbl_product.id')
            ->selectRaw('sum(tbl_product_skus.inventory) as inventory, tbl_product.*')
            ->selectRaw('Min(tbl_product_skus.inventory) as skus_inventory')
            ->groupBy('tbl_product.id')
//            ->havingRaw('tbl_product.warning_inventory >= inventory');
            ->havingRaw('tbl_product.warning_inventory >= skus_inventory');
        $sql = $productWarningCount->toSql();
        $sql = "select count(*) as product_count from ({$sql}) as temp_count";
        $bindings = $productWarningCount->getBindings();
        $total = ProductModel::runSql($sql, $bindings);
        $total = $total[0]->product_count;
        $count = $productCount->toArray();
        $count['warning'] = $total;

        // 总的商品数
        $productTotalQuery = clone $productQuery;
        $count['total'] = $productTotalQuery->count();

        return $count;
    }

    /**
     * 检测产品是否删除或下架,或者此产品SKU被删除了
     * @param $productId 产品ID
     * @param $skuId 产品SkuId
     * @return Boolean
     */
    public static function checkProductOffShelvesOrDelete(int $productId, int $skuId)
    {
        $count = ProductModel::query()
            ->where('id', $productId)
            ->where('status', 1)
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()])
            ->count();
        $skuCount = ProductSkusModel::query()
            ->where('id', $skuId)
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()])
            ->count();
        return ($count > 0 && $skuCount > 0) ? true : false;
    }

    /**
     * 是否有生效的商品
     * @param $productIds
     * @return bool
     */
    public static function hasActiveProduct($productIds)
    {
        if (!$productIds || !is_array($productIds)) {
            return false;
        }
        $count = ProductModel::query()->where('site_id', getCurrentSiteId())
            ->where('status', Constants::Product_Status_Sell)
            ->whereIn('id', $productIds)
            ->select('id')
            ->first();
        return !!$count;
    }

    public function getSkuInfo()
    {
        $product = $this->_product;
        $productData = $product->toArray();
        $skus = $product->productSkus()->select(['id', 'product_id', 'price', 'market_price', 'supply_price', 'member_rule', 'fenxiao_rule', 'agent_order_commission_rule', 'agent_sale_reward_rule', 'dealer_sale_reward_rule', 'cloud_stock_rule', 'area_agent_rule', 'serial_number', 'inventory', 'sku_sold_count', 'weight', 'sku_code'])->get();
        $skus = $skus->toArray();
        $skuValue = $product->productSkuValue()->select(['id', 'product_id', 'sku_name_id', 'value', 'small_image', 'big_image'])->get()->toArray();
        $skuName = $product->productSkuName()->select(['id', 'product_id', 'has_image', 'name'])->get()->toArray();
        return [
            'productData' => $productData,
            'skus' => $skus,
            'skuValue' => $skuValue,
            'skuName' => $skuName,
        ];
    }

    public function saveWarningInventory($warningInventory)
    {
        $this->_product->warning_inventory = intval($warningInventory);
        $this->_product->save();
    }

    public static function import($importFile)
    {
        // 错误数据
        $errorData = [];
        // 正确可更新的数据
        $rightData = [];
        $skus = ProductSkusModel::query()->where('site_id', getCurrentSiteId())
            ->whereNotNull('serial_number')
            ->select(['serial_number', 'id'])
            ->get();
        $newSkusArr = [];
        foreach ($skus as $value) {
            $newSkusArr[$value->serial_number] = $value->id;
        }
        foreach ($importFile as &$item) {
            if (!$item['serial_number']) {
                $item['error_data'] = "商品编码为空";
                $errorData[] = $item;
            } elseif (!$newSkusArr[$item['serial_number']]) {
                $item['error_data'] = "商品编码不正确";
                $errorData[] = $item;
            } elseif (!$item['inventory']) {
                $item['error_data'] = "库存为空";
                $errorData[] = $item;
            } else {
                $updateData['id'] = $newSkusArr[$item['serial_number']];
                $updateData['inventory'] = $item['inventory'];
                $rightData[] = $updateData;
            }
        }
        if ($rightData) {
            (new ProductSkusModel())->updateBatch($rightData);
        };
        return ['error_data' => $errorData, 'error_data_count' => count($errorData), 'right_data_count' => count($rightData)];
    }

    /**
     * 获取商品参数列表
     * @return mixed
     */
    public static function getProductParamTemplateList()
    {
        $data = ProductParamTemplate::getList([
            'order_by' => 'updated_at',
            'show_all' => true
        ]);
        if ($data['list']) {
            foreach ($data['list'] as &$item) {
                $item['params'] = json_decode($item['params'], true);
            }
        }
        return $data['list'];
    }
}