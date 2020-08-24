<?php
/**
 * 拼团产品逻辑
 * User: pangwenke
 * Date: 2020/4/3
 * Time: 10:17
 */

namespace App\Modules\ModuleShop\Libs\GroupBuying;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Shop\GroupBuyingShopOrder;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Member\Auth;
use YZ\Core\Model\FinanceModel;
use App\Modules\ModuleShop\Libs\Constants as LibConstants;
use YZ\Core\Model\MemberModel;

class GroupBuying
{
    private $_model = null;
    private $_siteId = 0;

    /**
     * GroupBuyingSetting constructor.
     * @param int $idOrModel
     * @throws \Exception
     */
    public function __construct($idOrModel = 0)
    {
        $this->_siteId = getCurrentSiteId();
        if (is_numeric($idOrModel) && $idOrModel > 0) {
            $model = GroupBuyingModel::query()->find($idOrModel);
            if (!$model) {
                throw new \Exception('找不到该团');
            }
        } elseif ($idOrModel instanceof GroupBuyingModel) {
            $model = $idOrModel;
        } else {
            $model = new GroupBuyingModel();
            $model->site_id = $this->_siteId;
        }
        $this->_model = $model;
    }

    public function getInfo($params = [])
    {
        $now = time();
        $setting = (new GroupBuyingSetting($this->_model->group_buying_setting_id))->getModel();
        $GroupProduct = (new GroupBuyingProducts($this->_model->group_product_id))->getModel();
        $product = (new Product($GroupProduct->master_product_id))->getModel();
        if (!GroupBuyingProducts::checkProduct($this->_model->group_product_id)) {
            throw new \Exception('产品出现修改');
        }
        $data = $this->_model->toArray();
        if ($params['group_buying_sku']) {
            $groupBuyingSku = GroupBuyingSkusModel::query()
                ->where('group_product_id', $this->_model->group_product_id)
                ->where('id', $params['group_buying_sku'])
                ->first();
            $data['sku_price'] = moneyCent2Yuan($groupBuyingSku->group_price);
        }

        if (strtotime($this->_model->end_time) < $now && $this->_model->status != GroupBuyingConstants::GroupBuyingTearmStatus_Yes) {
            // 如果过期状态-1
            $data['status'] = -1;
        }
        $data['remaining_time'] = self::getRemainingTime(strtotime($data['end_time']), $now);
        $data['rule_info'] = $setting->rule_info;
        $data['open_coupon'] = $setting->open_coupon;
        $data['open_inventory'] = $setting->open_inventory;
        $memberIds = json_decode($data['member_ids'], true);
        // 处理凑团插队头像的问题
        $memberIds = array_filter($memberIds, function ($value) {
            return $value != 0;
        });
        $memberIds = array_slice($memberIds, 0, 2); // 最多返回两个头像
        $headurl = MemberModel::query()
            ->where('site_id', getCurrentSiteId())
            ->whereIn('id', $memberIds)
            ->orderByRaw("find_in_set(id,'" . trim(implode(',', $memberIds)) . "')")
            ->pluck('headurl');
        $data['headurl'] = $headurl;
        $data['member_ids'] = json_decode($data['member_ids'], true);
        $data['productInfo']['name'] = $product->name;
        $data['productInfo']['small_images'] = explode(',', $product->small_images)[0];
        $data['productInfo']['groupbuying_price'] = moneyCent2Yuan($GroupProduct->min_price);
        $data['productInfo']['price'] = moneyCent2Yuan($product->price);
        $data['productInfo']['detail'] = $product->detail;
        $data['productInfo']['id'] = $product->id;
        return $data;
    }

    static function getVirtualGroupList($params)
    {
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;

        $query = GroupBuyingModel::query()
            ->from('tbl_group_buying as gb')
            ->where('gb.site_id', getCurrentSiteId())
            ->where('gb.group_product_id', $params['group_product_id']);
        if (isset($params['status'])) {
            $query->where('gb.status', $params['status']);
        }
        $query->leftJoin('tbl_member as head_member', 'head_member.id', 'gb.head_member_id');
        $query->leftJoin('tbl_group_buying_setting as gbs', 'gbs.id', 'gb.group_buying_setting_id');
        $query->select(['gb.*', 'head_member.headurl as head_member_headurl', 'head_member.nickname as head_member_nickname', 'gbs.start_time', 'gbs.type']);
        $total = $query->count();
        $query = $query->forPage($page, $page_size);
        //输出-最后页数
        $last_page = ceil($total / $page_size);
        $list = $query->get();

        if ($list) {
            $current_people_count_num = 0;
            foreach ($list as &$item) {
                $now = time();
                //计算剩余时间
                if ($now > strtotime($item->start_time)) {
                    $remainingTime = self::getRemainingTime(strtotime($item->end_time), $now);
                } else {
                    $remainingTime = self::getRemainingTime($now, strtotime($item->created_at));
                }
                $item->remaining_time = $remainingTime;
                $memberIds = json_decode($item->member_ids, true);
                // 处理凑团插队头像的问题
                $memberIds = array_filter($memberIds, function ($value) {
                    return $value != 0;
                });
                $memberIds = array_slice($memberIds, 0, 2); // 最多返回两个头像
                $headurl = MemberModel::query()
                    ->where('site_id', getCurrentSiteId())
                    ->whereIn('id', $memberIds)
                    ->orderByRaw("find_in_set(id,'" . trim(implode(',', $memberIds)) . "')")
                    ->pluck('headurl');
                $item->headurl = $headurl;
                $current_people_count_num += $item->current_people_num;
            }
        }
        $result = [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list,
            'current_people_count_num' => $current_people_count_num
        ];
        return $result;
    }

    static function getList($params)
    {
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;

        $query = GroupBuyingModel::query()
            ->from('tbl_group_buying as gb')
            ->where('gb.site_id', getCurrentSiteId());
        if (isset($params['status'])) {
            $query->where('gb.status', $params['status']);
        }
        if (isset($params['keyword'])) {
            $query->where('gbs.title', 'like', '%' . $params['keyword'] . '%');
        }
        $query->leftJoin('tbl_group_buying_setting as gbs', 'gbs.id', 'gb.group_buying_setting_id');
        $query->select(['gb.*', 'gbs.title']);
        $query->orderByDesc('gb.id');
        $total = $query->count();
        $query = $query->forPage($page, $page_size);
        //输出-最后页数
        $last_page = ceil($total / $page_size);
        $list = $query->get();

        if ($list) {
            foreach ($list as &$item) {
                if ($item->status == GroupBuyingConstants::GroupBuyingTearmStatus_Yes && $item->success_at) {
                    $item->spend_time = (strtotime($item->success_at) - strtotime($item->created_at));
                } else {
                    $item->spend_time = (strtotime($item->end_time) - strtotime($item->created_at));
                }

                $memberIds = json_decode($item->member_ids, true);
                // 处理凑团插队头像的问题
                $memberIds = array_filter($memberIds, function ($value) {
                    return $value != 0;
                });
                $memberIds = array_slice($memberIds, 0, 3); // 最多返回两个头像
                $headurl = MemberModel::query()
                    ->where('site_id', getCurrentSiteId())
                    ->whereIn('id', $memberIds)
                    ->orderByRaw("find_in_set(id,'" . trim(implode(',', $memberIds)) . "')")
                    ->pluck('headurl');
                $item->headurl = $headurl;
                if (!$item->title) {
                    $snapshot = json_decode($item->snapshot, true);
                    $item->title = $snapshot['setting']['title'];
                }
            }
        }
        $result = [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }


    /** 计算剩余时间
     ** @param 时间戳 $endTime 结束时间
     ** @param 时间戳 $startTime 开始时间
     **/
    static function getRemainingTime($endTime, $startTime)
    {
        $remainingTime = abs($endTime - $startTime);
        $day = $remainingTime < 86400 ? 0 : intval($remainingTime / 86400);//天
        $hour = intval((($remainingTime / 86400) - $day) * 24);//小时
        $minute = intval((((($remainingTime / 86400) - $day) * 24) - $hour) * 60);//分钟
        $second = intval(((((((($remainingTime / 86400) - $day) * 24) - $hour) * 60) - $minute) * 60));//秒
        return [
            'day' => $day,
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second
        ];
    }

    public function getModel()
    {
        return $this->_model;
    }

    /** 检测资格
     ** @param 会员ID
     * @param 活动ID
     **/
    public function checkQualification($memberId = 0, $groupbuyingId)
    {
        $now = date('Y-m-d H:i:s', time());
        $setting = (new GroupBuyingSetting($this->_model->group_buying_setting_id))->getModel();
        if (!$memberId) makeApiResponse(403, '请先登录');
        // 老带新拼团的时候
        if ($setting->type == 1) {
            if (self::checkOldMember($memberId)) return makeApiResponse(501, '该活动只允许新会员参与拼单，您只能发起拼团活动哦~');
        }

        $activity = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->where('activity_id', $groupbuyingId)
            ->whereNotIn('status', [LibConstants::OrderStatus_Deleted, LibConstants::OrderStatus_Cancel])
            ->count();
        if ($activity > 0) return makeApiResponse(502, '您已经参与过这次拼团啦~');

        $groupbuying = (new GroupBuying($groupbuyingId))->getModel();
        if ($groupbuying->need_people_num == 0) return makeApiResponse(503, '拼团已经满人');
        if ($groupbuying->end_time < $now) return makeApiResponse(504, '拼团已经结束');
        $GroupProductCheck = GroupBuyingProducts::checkProduct($this->_model->group_product_id);
        if (!$GroupProductCheck) return makeApiResponse(505, '产品进行过:下架/删除/修改');
        return makeApiResponse(200, '通过检测');
    }

    /**
     * 拼团关闭
     * @param $id
     * @throws \Exception
     */
    public static function cancelGroupBuying($id)
    {
        $siteId = getCurrentSiteId();
        // 关闭相关订单
        $orderList = OrderModel::query()
            ->where('site_id', $siteId)
            ->where('type', LibConstants::OrderType_GroupBuying)
            ->where('activity_id', $id)
            ->pluck('id')->toArray();
        foreach ($orderList as $orderId) {
            // 关闭订单
            $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId, false);
            $shopOrder->cancel('自动关闭');
        }
        // 关闭团
        GroupBuyingModel::query()
            ->where('site_id', $siteId)
            ->where('id', $id)
            ->update(['status' => GroupBuyingConstants::GroupBuyingTearmStatus_Faile]);
    }

    /**
     * 模拟成团
     * @param $groupBuyingId
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function mockGroupBuyingSuccess($groupBuyingId)
    {
        try {
            DB::beginTransaction();
            $groupBuying = GroupBuyingModel::query()->find($groupBuyingId);
            if ($groupBuying->status == GroupBuyingConstants::GroupBuyingTearmStatus_No) {
                // 更新团状态为成功
                $memberNum = $groupBuying->need_people_num - $groupBuying->current_people_num;
                $memberIds = json_decode($groupBuying->member_ids, true);
                // 人数不够 填充0
                if ($memberNum > 0) {
                    for ($i = 0; $i < $memberNum; $i++) {
                        array_push($memberIds, 0);
                    }
                    $groupBuying->member_ids = json_encode($memberIds);
                }
                $groupBuying->status = GroupBuyingConstants::GroupBuyingTearmStatus_Yes;
                $groupBuying->success_at = date('Y-m-d H:i:s');
                $groupBuying->save();
                self::groupBuyingSuccessAfter($groupBuying->id);
            } elseif ($groupBuying->status == GroupBuyingConstants::GroupBuyingTearmStatus_Yes) {
                // 对于插队的情况 拼团已经是成功了 也需要处理一下
                self::groupBuyingSuccessAfter($groupBuying->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 检测是否可以模拟成团
     * @param $groupBuyingId 活动ID
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function checkMockGroupBuying($groupBuyingId)
    {
        $now = time();
        $setting = static::getGroupBuyingSetting($groupBuyingId);
        if (!$setting) return false;
        // 是否开启凑团了
        if ($setting->open_mock_group == 0) return false;
        // 这个活动是否结束了
        if (strtotime($setting->end_time) < $now) return false;
        // 这个团是否已经成团了
        $groupBuying = GroupBuyingModel::find($groupBuyingId);
        if ($groupBuying->status !== GroupBuyingConstants::GroupBuyingTearmStatus_No) return false;
        // 还没到时间的不能自动成团
        if (strtotime($groupBuying->end_time) > $now) return false;
        return true;
    }

    /**
     * 根据活动获取设置
     * @param $groupBuyingId 活动ID
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function getGroupBuyingSetting($groupBuyingId)
    {
        $groupBuying = GroupBuyingModel::query()->where('id', $groupBuyingId)->first();
        if (!$groupBuying) return null;
        return GroupBuyingSetting::getSetting($groupBuying->group_buying_setting_id);
    }

    /**
     * 拼团成功后的处理
     * @param $id
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function groupBuyingSuccessAfter($id)
    {
        $query = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('activity_id', $id)
            ->where('type', LibConstants::OrderType_GroupBuying)
            ->where('status', LibConstants::OrderStatus_OrderPay)
            ->where('type_status', LibConstants::OrderType_GroupBuyingStatus_No);
        $list = $query->select(['id', 'member_id'])->get()->toArray();
        if ($list) {
            // 更新订单状态为拼团成功
            $update = $query->update(['type_status' => LibConstants::OrderType_GroupBuyingStatus_Yes]);
            // 处理拼团成功后 对订单的操作
            foreach ($list as $item) {
                $order = ShopOrderFactory::createOrderByOrderId($item['id']);
                $order->groupBuyingSuccessAfterUpdate(true);
            }
        }
    }

    /** 检测该会员是否是老会员
     ** 新老客户的定义：付过款的，不管有没有退单，都是老客户；否则就是新客户
     ** @param 会员ID
     ** @return boolean true 老会员 false 是新会员
     **/
    static public function checkOldMember($memberId)
    {
        $count = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->whereNotIn('status', [LibConstants::OrderStatus_Deleted, LibConstants::OrderStatus_NoPay, LibConstants::OrderStatus_Cancel])
            ->count();
        if ($count > 0) return true;
        return false;
    }
}