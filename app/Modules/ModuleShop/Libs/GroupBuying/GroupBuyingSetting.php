<?php
/**
 * 拼团设置业务逻辑
 * User: liyaohui
 * Date: 2020/4/2
 * Time: 16:38
 */

namespace App\Modules\ModuleShop\Libs\GroupBuying;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSettingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkuNameModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkuValueModel;
use Illuminate\Support\Facades\DB;

class GroupBuyingSetting
{
    private $_model = null;
    private $_siteId = 0;
    private $_minPeopleNum = 2; // 最大人数
    private $_maxPeopleNum = 100; // 最小人数

    public static $setting; // 设置的数据 主要用来前端频繁使用设置项时使用

    /**
     * GroupBuyingSetting constructor.
     * @param int $idOrModel
     * @throws \Exception
     */
    public function __construct($idOrModel = 0)
    {
        $this->_siteId = getCurrentSiteId();
        if (is_numeric($idOrModel) && $idOrModel > 0) {
            $model = GroupBuyingSettingModel::query()->where('is_delete', 0)->where('id', $idOrModel)->first();
            if (!$model) {
                throw new \Exception('找不到该活动');
            }
        } elseif ($idOrModel instanceof GroupBuyingSettingModel) {
            $model = $idOrModel;
        } else {
            $model = new GroupBuyingSettingModel();
            $model->site_id = $this->_siteId;
        }
        $this->_model = $model;
    }

    /**
     * 获取当前模型
     * @return GroupBuyingSettingModel|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|int|null|string|static|static[]
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取设置
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getSetting($id)
    {
        if (!self::$setting) {
            self::$setting = GroupBuyingSettingModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $id)
                ->first();
        }
        return self::$setting;
    }

    /**
     * 获取活动详情
     * @return array
     */
    public function getInfo()
    {
        // 基础信息
        $baseInfo = $this->getModel()->toArray();
        $baseInfo['status'] = self::getStatus($baseInfo['start_time'], $baseInfo['end_time']);
        return [
            'base_info' => $baseInfo,
            'product_list' => $this->getProductList() // 获取对应的商品信息
        ];
    }

    /**
     * 获取商品列表
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getProductList()
    {
        // 先取出主记录
        $proList = GroupBuyingProductsModel::query()
            ->from('tbl_group_buying_products as gpro')
            ->join('tbl_product as pro', 'pro.id', 'gpro.master_product_id')
                 //  判断产品是否规格进行单变双，双变单的操作
            ->leftJoin('tbl_group_buying_skus as gbs', 'gbs.group_product_id', 'gpro.id')
            ->leftJoin('tbl_product_skus as ps', 'gbs.sku_id', 'ps.id')
            ->whereNotNull('ps.id')
            ->where('gpro.site_id', $this->_siteId)
            ->where('gpro.group_buying_setting_id', $this->getModel()->id)
            ->select([
                'gpro.id',
                'gpro.master_product_id',
                'pro.name',
                'pro.small_images',
                'pro.status as product_status',
                'pro.price as product_price',
                'gpro.order_base_num',
                'gpro.buy_limit_num'
            ])
            ->orderByDesc('gpro.id')
            ->groupBy('gpro.master_product_id')
            ->get();

        if ($proList->count()) {
            // 取出对应的sku 因为要统计库存 所以只能取出来一条
            $productIds = $proList->pluck('master_product_id')->toArray();
            $skuList = ProductSkusModel::query()
                ->leftJoin('tbl_group_buying_skus as gsku', 'gsku.sku_id', 'tbl_product_skus.id')
                ->where('tbl_product_skus.site_id', $this->_siteId)
                ->whereIn('tbl_product_skus.product_id', $productIds)
                ->where('gsku.group_buying_setting_id', $this->getModel()->id)
                ->groupBy('tbl_product_skus.product_id')
                ->selectRaw('
                    sum(tbl_product_skus.inventory) as inventory, 
                    tbl_product_skus.sku_code, 
                    gsku.group_price, 
                    gsku.head_price, 
                    tbl_product_skus.product_id,
                    gsku.group_inventory
                ')
                ->get()
                ->keyBy('product_id');
            // 获取拼团sku数据
            $groupSkuList = GroupBuyingSkusModel::query()
                ->from('tbl_group_buying_skus as gsku')
                ->leftJoin('tbl_product_skus as sku', 'sku.id', 'gsku.sku_id')
                ->where('gsku.site_id', $this->_siteId)
                ->where('gsku.group_buying_setting_id', $this->getModel()->id)
                ->select([
                    'gsku.id',
                    'gsku.group_price',
                    'gsku.head_price',
                    'gsku.group_product_id',
                    'gsku.group_inventory',
                    'sku.sku_name'
                ])
                ->get();
            foreach ($groupSkuList as $sku) {
                $sku['group_price'] = moneyCent2Yuan($sku['group_price']);
                $sku['head_price'] = moneyCent2Yuan($sku['head_price']);
            }

            foreach ($proList as $pro) {
                $pro['inventory'] = $skuList[$pro['master_product_id']]['inventory'];
                $pro['sku_code'] = $skuList[$pro['master_product_id']]['sku_code'];
                $pro['group_price'] = moneyCent2Yuan($skuList[$pro['master_product_id']]['group_price']);
                $pro['head_price'] = moneyCent2Yuan($skuList[$pro['master_product_id']]['head_price']);
                $pro['group_inventory'] = $skuList[$pro['master_product_id']]['group_inventory'];
                $pro['product_price'] = moneyCent2Yuan($pro['product_price']);
                $pro['sku_list'] = $groupSkuList->where('group_product_id', $pro['id'])->values()->all();
            }
        }
        return $proList;
    }

    /**
     * 获取单个商品的sku信息
     * @param int $productId    商品id
     * @param int $groupId      活动id 新加的为0
     * @return array
     */
    public static function getProductSkus($productId, $groupId = 0)
    {
        $siteId = getCurrentSiteId();
        // 先获取sku列表
        $skuList = ProductSkusModel::query()
            ->leftJoin('tbl_group_buying_skus as gsku', function ($join) use ($groupId) {
                $join->on('gsku.sku_id', 'tbl_product_skus.id')
                    ->where('gsku.group_buying_setting_id', $groupId);
            })
            ->where('tbl_product_skus.site_id', $siteId)
            ->where('tbl_product_skus.product_id', $productId)
            ->select([
                'tbl_product_skus.price',
                'gsku.group_price',
                'gsku.head_price',
                'tbl_product_skus.inventory',
                'gsku.group_inventory',
                'tbl_product_skus.sku_code',
                'tbl_product_skus.sku_name',
                'tbl_product_skus.id as sku_id',
                'gsku.id'
            ])
            ->get();
        foreach ($skuList as $item) {
            $item['price'] = moneyCent2Yuan($item['price']);
            $item['group_price'] = $item['group_price'] ? moneyCent2Yuan($item['group_price']) : $item['group_price'];
            $item['head_price'] = $item['head_price'] ? moneyCent2Yuan($item['head_price']) : $item['head_price'];
        }
        // 获取sku的名称
        $skuNames = ProductSkuNameModel::query()
            ->where('site_id', $siteId)
            ->where('product_id', $productId)
            ->select(['id', 'name'])
            ->get();
        // 获取sku的value
        $skuValues = ProductSkuValueModel::query()
            ->where('site_id', $siteId)
            ->where('product_id', $productId)
            ->select(['id', 'value', 'sku_name_id'])
            ->get();
        return [
            'sku_list' => $skuList,
            'sku_names' => $skuNames,
            'sku_values' => $skuValues
        ];
    }

    /**
     * 获取活动列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getList($params = [], $page = 1, $pageSize = 20)
    {
        $query = GroupBuyingSettingModel::query()
            ->from('tbl_group_buying_setting as gbs')
            ->where('gbs.site_id', getCurrentSiteId())
            ->where('gbs.is_delete', 0);
        // 状态 根据时间
        if (isset($params['status'])) {
            $now = date('Y-m-d H:i:s');
            if ($params['status'] == GroupBuyingConstants::GroupBuyingStatus_Ready) {
                $query->where('gbs.start_time', '>', $now);
            } elseif ($params['status'] == GroupBuyingConstants::GroupBuyingStatus_Processing) {
                $query->where('gbs.start_time', '<=', $now)
                    ->where('gbs.end_time', '>=', $now);
            } elseif ($params['status'] == GroupBuyingConstants::GroupBuyingStatus_End) {
                $query->where('gbs.end_time', '<', $now);
            } elseif ($params['status'] == GroupBuyingConstants::GroupBuyingStatus_NoEnd) {
                $query->where('gbs.end_time', '>', $now);
            }
        }
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $query->where('gbs.title', 'like', "%{$keyword}%");
        }
        // 获取总数量
        $total = $query->count();
        $query->leftJoin('tbl_group_buying as gb', function ($join) {
                $join->on('gb.group_buying_setting_id', 'gbs.id')
                    ->where('gb.status', GroupBuyingConstants::GroupBuyingTearmStatus_Yes);
            })
            ->leftJoin('tbl_order as order', function($join) {
                $join->on('order.activity_id', 'gb.id')
                    ->whereIn('order.status', Constants::getPaymentOrderStatus());
            })
            ->groupBy('gbs.id');
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('gbs.id')
            ->forPage($page, $pageSize)
            ->selectRaw('
                gbs.title,
                gbs.id,
                sum(order.money) as order_money_count,
                count(order.id) as order_count,
                gbs.start_time,
                gbs.end_time
            ')
            ->get();
        if ($list->count()) {
            $settingIds = $list->pluck('id')->toArray();
            $productCount = GroupBuyingProductsModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('group_buying_setting_id', $settingIds)
                ->selectRaw('count(id) as product_count, group_buying_setting_id')
                ->groupBy('group_buying_setting_id')
                ->get()->keyBy('group_buying_setting_id');
            foreach ($list as $item) {
                $item['order_money_count'] = moneyCent2Yuan($item['order_money_count']);
                $item['status'] = self::getStatus($item['start_time'], $item['end_time']);
                $item['product_count'] = $productCount[$item['id']]['product_count'];
            }
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 结束活动
     * @return bool
     */
    public function end()
    {
        // 只有进行中的才可以结束
        $model = $this->getModel();
        if (self::getStatus($model->start_time, $model->end_time) == GroupBuyingConstants::GroupBuyingStatus_Processing) {
            try {
                DB::beginTransaction();
                // 把结束时间设为当前时间即可
                $now = date('Y-m-d H:i:s');
                $model = $this->getModel();
                $model->end_time = $now;
                $save = $model->save();
                // 修改所有当前活动未成团的结束时间
                GroupBuyingModel::query()
                    ->where('site_id', $this->_siteId)
                    ->where('group_buying_setting_id', $model->id)
                    ->where('status', GroupBuyingConstants::GroupBuyingTearmStatus_No)
                    ->update(['end_time' => $now]);
                DB::commit();
                return $save;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            return false;
        }

    }

    /**
     * 删除活动
     * @return bool|null
     * @throws \Exception
     */
    public function delete()
    {
        try {
            DB::beginTransaction();
            $model = $this->getModel();
            $status = self::getStatus($model->start_time, $model->end_time);
            // 未开始的直接删除
            if ($status == GroupBuyingConstants::GroupBuyingStatus_Ready) {
                $this->deleteGroupBuying();
            } elseif ($status == GroupBuyingConstants::GroupBuyingStatus_End) {
                // 结束的 查看是否有成团的 有成团的无法删除
                $count = GroupBuyingModel::query()
                    ->where('site_id', $this->_siteId)
                    ->where('group_buying_setting_id', $model->id)
                    ->where('status', GroupBuyingConstants::GroupBuyingTearmStatus_Yes)
                    ->first();
                if ($count) {
                    throw new \Exception('有数据的活动无法删除');
                } else {
                    $this->deleteGroupBuying();
                }
            } else {
                throw new \Exception('进行中的活动无法删除');
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除活动所有数据
     * @return mixed
     * @throws \Exception
     */
    private function deleteGroupBuying()
    {
        $model = $this->getModel();
        // 删除关联的商品
//        GroupBuyingProductsModel::query()
//            ->where('site_id', $this->_siteId)
//            ->where('group_buying_setting_id', $model->id)
//            ->delete();
//        GroupBuyingSkusModel::query()
//            ->where('site_id', $this->_siteId)
//            ->where('group_buying_setting_id', $model->id)
//            ->delete();
        // 修改一些 未成团的数据状态
        $activityIds = GroupBuyingModel::query()
            ->where('site_id', $this->_siteId)
            ->where('group_buying_setting_id', $model->id)
            ->where('status', GroupBuyingConstants::GroupBuyingTearmStatus_No)
            ->pluck('id')->toArray();
        if ($activityIds) {
            // 把相关的拼团关闭
            foreach ($activityIds as $id) {
                GroupBuying::cancelGroupBuying($id);
            }
//            GroupBuyingModel::query()
//                ->where('site_id', $this->_siteId)
//                ->where('group_buying_setting_id', $model->id)
//                ->update(['status' => GroupBuyingConstants::GroupBuyingTearmStatus_Faile]);
//
//            // 所有订单也要修改
//            OrderModel::query()
//                ->where('site_id', $this->_siteId)
//                ->where('type', Constants::OrderType_GroupBuying)
//                ->where('type_status', Constants::OrdetType_GroupBuyingStatus_No)
//                ->whereIn('activity_id', $activityIds)
//                ->update(['type_status' => Constants::OrdetType_GroupBuyingStatus_Faile]);
        }
        $model->is_delete = 1;
        return $model->save();
    }

    /**
     * 保存设置
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function save($data)
    {
        if ($data && $data['base_info'] && $data['product_info']) {
            try {
                DB::beginTransaction();
                $baseInfo = $this->beforeSave($data['base_info']);
                $model = $this->getModel();
                // 进行中的只允许修改这些字段
                if ($model->id && self::getStatus($model->start_time, $model->end_time) == 1) {
                    $baseInfo = [
                        'title' => $baseInfo['title'],
                        'open_order_base_num' => $baseInfo['open_order_base_num'],
                        'rule_info' => $baseInfo['rule_info'],
                        'open_inventory' => $baseInfo['open_inventory']
                    ];
                }
                foreach ($baseInfo as $key => $item) {
                    $model->{$key} = $item;
                }
                $save = $model->save();
                $this->_model = $model;
                $this->saveProduct($data['product_info']);
                DB::commit();
                return $save;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            throw new \Exception('数据不完整');
        }
    }

    /**
     * 返回活动的状态
     * @return int
     */
    public static function getStatus($startTime, $endTime)
    {
        $now = time();
        $startTime = strtotime($startTime);
        $endTime = strtotime($endTime);
        if ($now < $startTime) {
            return GroupBuyingConstants::GroupBuyingStatus_Ready; // 未开始
        } elseif ($now > $startTime && $now < $endTime) {
            return GroupBuyingConstants::GroupBuyingStatus_Processing; // 进行中
        } elseif ($now >= $endTime) {
            return GroupBuyingConstants::GroupBuyingStatus_End; // 已结束
        }
    }

    /**
     * 保存活动商品
     * @param $productInfo
     * @throws \Exception
     */
    private function saveProduct($productInfo)
    {
        $existSkuIds = [];
//        $productData = [
//            [
//                'id' => 111,
//                'master_product_id' => 12,
//                'min_price' => 45666,
//                'skus' => [
//                    'id' => 1,
//                    'group_price' => 33333,
//                    'head_price' => 4444,
//                    'group_inventory' => 5555,
//                    'group_product_id' => 111,
//                    'sku_name' => '["abc", "ddd"]'
//                ]
//            ]
//        ];
        $updateProductData = []; // 更新的商品主表数据
        $newProductData = []; // 新增的商品主表数据
        $newSkuData = []; // 新增的商品sku数据 没有主表id
        $updateSkuData = []; // 更新的sku数据
        $insterSkuData = []; // 可以直接插入的新的sku数据 已有主表id
        $newProductIds = []; // 新增的商品id
        $groupId = $this->getModel()->id;
        foreach ($productInfo as $pro) {
            $minPrice = collect($pro['skus'])->min('group_price');
            $pro['min_price'] = moneyYuan2Cent($minPrice);
            // 已经存在商品主记录
            if ($pro['id']) {
                $updateProductData[] = [
                    'id' => $pro['id'],
                    'min_price' => $pro['min_price'],
                    'order_base_num' => $pro['order_base_num'],
                    'buy_limit_num'=>$pro['buy_limit_num']
                ];
                // 处理sku
                foreach ($pro['skus'] as $sku) {
                    $sku['group_price'] = moneyYuan2Cent($sku['group_price']);
                    $sku['head_price'] = moneyYuan2Cent($sku['head_price']);
                    // 有id的 去更新就可以了
                    if ($sku['id']) {
                        $existSkuIds[] = $sku['id'];
                        $updateSkuData[] = [
                            'id' => $sku['id'],
                            'group_price' => $sku['group_price'],
                            'head_price' => $sku['head_price'],
                            'group_inventory' => $sku['group_inventory'],
                            'sku_name' => $sku['sku_name']
                        ];
                    } else {
                        // 因为有主记录了 可以直接去插入
                        $insterSkuData[] = [
                            'group_price' => $sku['group_price'],
                            'head_price' => $sku['head_price'],
                            'group_inventory' => $sku['group_inventory'],
                            'group_buying_setting_id' => $groupId,
                            'sku_id' => $sku['sku_id'],
                            'master_product_id' => $pro['master_product_id'],
                            'site_id' => $this->_siteId,
                            'sku_name' => $sku['sku_name'],
                            'group_product_id' => $pro['id']
                        ];
                    }
                }
            } else {
                // 全新的数据
                $newProductData[] = [
                    'min_price' => $pro['min_price'],
                    'site_id' => $this->_siteId,
                    'master_product_id' => $pro['master_product_id'],
                    'group_buying_setting_id' => $groupId,
                    'order_base_num' => $pro['order_base_num'],
                    'buy_limit_num'=>$pro['buy_limit_num']
                ];
                $newProductIds[] = $pro['master_product_id'];
                foreach ($pro['skus'] as $sku) {
                    $sku['group_price'] = moneyYuan2Cent($sku['group_price']);
                    $sku['head_price'] = moneyYuan2Cent($sku['head_price']);
                    $newSkuData[] = [
                        'group_price' => $sku['group_price'],
                        'head_price' => $sku['head_price'],
                        'group_inventory' => $sku['group_inventory'],
                        'group_buying_setting_id' => $groupId,
                        'sku_id' => $sku['sku_id'],
                        'master_product_id' => $pro['master_product_id'],
                        'site_id' => $this->_siteId,
                        'sku_name' => $sku['sku_name'],
                    ];
                }
            }
        }

        // 删除旧的
        if ($existSkuIds) {
            // 删除不需要的sku
            GroupBuyingSkusModel::query()
                ->where('site_id', $this->_siteId)
                ->whereNotIn('id', $existSkuIds)
                ->where('group_buying_setting_id', $groupId)
                ->delete();
            // 删除拼团商品 sku为空的主表记录
            GroupBuyingProductsModel::query()
                ->where('site_id', $this->_siteId)
                ->doesntHave('productSkus')
                ->where('group_buying_setting_id', $groupId)
                ->delete();
        } else {
            // 删除所有的sku 商品
            GroupBuyingSkusModel::query()
                ->where('site_id', $this->_siteId)
                ->where('group_buying_setting_id', $groupId)
                ->delete();
            GroupBuyingProductsModel::query()
                ->where('site_id', $this->_siteId)
                ->where('group_buying_setting_id', $groupId)
                ->delete();
        }
        // 更新数据
        if ($updateProductData) {
            (new GroupBuyingProductsModel())->updateBatch($updateProductData);
        }
        if ($updateSkuData) {
            (new GroupBuyingSkusModel())->updateBatch($updateSkuData);
        }
        // 直接插入的sku数据
        if ($insterSkuData) {
            GroupBuyingSkusModel::query()->insert($insterSkuData);
        }
        // 新的商品
        if ($newProductData) {
            GroupBuyingProductsModel::query()->insert($newProductData);
            // 获取id
            $productIdsObj = GroupBuyingProductsModel::query()
                ->where('site_id', $this->_siteId)
                ->where('group_buying_setting_id', $groupId)
                ->whereIn('master_product_id', $newProductIds)
                ->pluck('id', 'master_product_id');
            foreach ($newSkuData as &$sku) {
                $sku['group_product_id'] = $productIdsObj[$sku['master_product_id']];
            }
            GroupBuyingSkusModel::query()->insert($newSkuData);
        }
    }

    /**
     * 保存前的数据检测和处理
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    private function beforeSave($data)
    {
        // 检测开始结束时间
        if ($data['start_time'] && $data['end_time']) {
            if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
                throw new \Exception('结束时间必须大于开始时间');
            }
        }
        // 参团人数
        if ($data['people_num'] && ($data['people_num'] < $this->_minPeopleNum || $data['people_num'] > $this->_maxPeopleNum)) {
            throw new \Exception('参团人数最少为' . $this->_minPeopleNum . '人，最多' . $this->_maxPeopleNum . '人');
        }
        // 去除文本的空格
        $data['title'] = trim($data['title']);
        if (!$data['title']) {
            throw new \Exception('请输入活动名称');
        }
        $data['rule_info'] = trim($data['rule_info']);
        return $data;
    }
}