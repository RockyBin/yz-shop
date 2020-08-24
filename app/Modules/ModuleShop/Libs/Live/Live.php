<?php

namespace App\Modules\ModuleShop\Libs\Live;

use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\LiveConstants;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\LiveCouponModel;
use App\Modules\ModuleShop\Libs\Model\LiveModel;
use App\Modules\ModuleShop\Libs\Model\LiveNavModel;
use App\Modules\ModuleShop\Libs\Model\LiveProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Common\ServerInfo;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Member\Auth;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

/**
 * 直播业务类
 * Class Viewer
 * @package App\Modules\ModuleShop\Libs\Live
 */
class Live
{
    private $_liveId = 0;
    private $_liveModel = null;

    public function __construct($liveId = 0)
    {
        $this->_liveId = $liveId;
    }

    private function loadModel()
    {
        if (!$this->_liveModel) {
            $this->_liveModel = LiveModel::query()->where(['site_id' => getCurrentSiteId(), 'id' => $this->_liveId])->first();
        }
    }

    /**
     * 返回数据对象模型
     * @return null
     */
    public function getModel()
    {
        $this->loadModel();
        return $this->_liveModel;
    }

    /**
     * 获取直播的基本信息，它将来与getModel()不同的地方是这里返回的信息可能是经过处理的
     * param $transform 是否需要转换数据
     * @return null
     */
    public function getInfo($transform = false)
    {
        $this->loadModel();
        if (!$this->_liveModel) throw new \Exception('直播不存在');
        $info = $this->_liveModel->toArray();
        if ($info['notice_link'] && $transform) {
            $link = json_decode($info['notice_link'], true);
            $info['notice_link'] = LinkHelper::getUrl($link['link_type'], $link['link_data']);
        }

        if ($info['status'] == LiveConstants::LiveStatus_End) {
            $info['time_length'] = static::calcTimeLength($info['real_live_start_time'], $info['live_end_time']);
            $info['viewer_count'] = $this->_liveModel->viewer()->count();
            $info['chat_count'] = $this->_liveModel->chat()->count();
        } else {
            $info['time_length'] = null;
        }

        if (!$info['live_helper_name']) $info['live_helper_name'] = "直播助手";
        return $info;
    }

    /**
     * 结束直播
     */
    public function close()
    {
        $this->loadModel();
        //将上屏的优惠券和商品下掉
        LiveProductModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId])->update(['is_onscreen' => 0]);
        LiveCouponModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId])->update(['is_onscreen' => 0]);
        $this->livingEdit(['status' => LiveConstants::LiveStatus_End, 'live_end_time' => date('Y-m-d H:i:s')]);
        return $this->getModel();
    }

    /**
     * 开始直播
     * @param $livePlatform 直播平台类型
     * @param $liveSrc 直播流地址
     * @return null
     */
    public function open($livePlatform, $liveSrc)
    {
        $this->loadModel();
        //将上屏的优惠券和商品下掉
        $this->livingEdit(['live_src' => $liveSrc, 'live_platform' => $livePlatform, 'status' => 1, 'real_live_start_time' => date('Y-m-d H:i:s')]);
        return $this->getModel();
    }

    /**
     * 添加数据
     * @param array $info
     * @throws \Exception
     */
    public function add($info = [])
    {
        try {
            DB::beginTransaction();
            $this->_liveModel = new LiveModel();
            $this->_liveModel->site_id = getCurrentSiteId();

            foreach ($info['base_info'] as $k => $v) {
                $this->_liveModel->{$k} = $v;
            }
            $this->_liveModel->save();
            // 保存优惠券
            $this->setCoupons($info['coupon_list']);
            // 保存产品
            $this->setProducts($info['product_list']);
            // 保存导航
            $this->setNavs($info['nav_list']);
            DB::commit();
            return $this->_liveModel->id;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 更新数据
     * @param array $info
     * @throws \Exception
     */
    public function edit($info = [])
    {
        try {
            DB::beginTransaction();
            $this->loadModel();
            // 如果是开播状态 不能修改以下的字段
            if ($this->_liveModel->status == LiveConstants::LiveStatus_Living) {
                unset($info['base_info']['live_src']);
            }
            foreach ($info['base_info'] as $k => $v) {
                $this->_liveModel->{$k} = $v;
            }
            // 保存导航 开播后禁止修改
            $this->setNavs($info['nav_list']);
            $this->_liveModel->save();
            // 保存优惠券
            $this->setCoupons($info['coupon_list']);
            // 保存产品
            $this->setProducts($info['product_list']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 主要用于直播工作台的修改
     * @param array $info
     */
    public function livingEdit($info = [])
    {
        if ($info && is_array($info)) {
            $this->loadModel();
            foreach ($info as $key => $value) {
                $this->_liveModel->{$key} = $value;
            }
            $this->_liveModel->save();
        }
    }

    /**
     * 相关图片的修改
     * @param array $images
     * @return array
     */
    public function uploadLiveImage($info)
    {
        if ($info['id'] != 0) $this->loadModel();
        if ($info['live_headurl']) {
            if (is_file($info['live_headurl'])) {
                //删除旧的图片
                if ($this->_liveModel) {
                    $rootPath = Site::getSiteComdataDir(getCurrentSiteId(), true);
                    if (is_file($rootPath . $this->_liveModel->live_headurl)) {
                        unlink($rootPath . $this->_liveModel->live_headurl);
                    }
                }
                // 上传直播头像
                $liveHeadurlSaveDir = Site::getSiteComdataDir('', true) . '/live/liveHeadurl/';
                $liveHeadurlImageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $liveHeadurlupload = new FileUpload($info['live_headurl'], $liveHeadurlSaveDir, $liveHeadurlImageName);
                $liveHeadurlupload->reduceImageSize(1000);
                $info['live_headurl'] = '/live/liveHeadurl/' . $liveHeadurlupload->getFullFileName();
            }
        }

        if ($info['live_poster']) {
            if (is_file($info['live_poster'])) {
                //删除旧的图片
                if ($this->_liveModel) {
                    $rootPath = Site::getSiteComdataDir(getCurrentSiteId(), true);
                    if (is_file($rootPath . $this->_liveModel->live_poster)) {
                        unlink($rootPath . $this->_liveModel->live_poster);
                    }
                }
                // 上传直播间封面图
                $livePosterSaveDir = Site::getSiteComdataDir('', true) . '/live/livePoster/';
                $livePosterImageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $livePosterupload = new FileUpload($info['live_poster'], $livePosterSaveDir, $livePosterImageName);
                $livePosterupload->reduceImageSize(1000);
                $info['live_poster'] = '/live/livePoster/' . $livePosterupload->getFullFileName();
            }
        }

        if ($info['list_poster']) {
            if (is_file($info['list_poster'])) {
                //删除旧的图片
                if ($this->_liveModel) {
                    $rootPath = Site::getSiteComdataDir(getCurrentSiteId(), true);
                    if (is_file($rootPath . $this->_liveModel->list_poster)) {
                        unlink($rootPath . $this->_liveModel->list_poster);
                    }
                }
                // 上传直播间封面图
                $listPosterSaveDir = Site::getSiteComdataDir('', true) . '/live/listPoster/';
                $listPosterImageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $listPosterupload = new FileUpload($info['list_poster'], $listPosterSaveDir, $listPosterImageName);
                $listPosterupload->reduceImageSize(1000);
                $info['list_poster'] = '/live/listPoster/' . $listPosterupload->getFullFileName();
            }
        }

        if ($info['nav_follow_image']) {
            if (is_file($info['nav_follow_image'])) {
                //删除旧的图片
                if ($this->_liveModel) {
                    $oldImgat = $this->_liveModel->navs()
                        ->where('nav_type', LiveConstants::LiveNavType_Button)
                        ->where('link_type', LiveConstants::LiveNavLinkType_Follow)
                        ->first();
                    $rootPath = Site::getSiteComdataDir(getCurrentSiteId(), true);
                    if ($oldImgat && is_file($rootPath . $oldImgat)) {
                        unlink($rootPath . $oldImgat);
                    }
                }
                // 上传直播间封面图
                $navFollowImageSaveDir = Site::getSiteComdataDir('', true) . '/live/nav/';
                $navFollowImageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $navFollowImageupload = new FileUpload($info['nav_follow_image'], $navFollowImageSaveDir, $navFollowImageName);
                $navFollowImageupload->reduceImageSize(1000);
                $info['nav_follow_image'] = '/live/nav/' . $navFollowImageupload->getFullFileName();
            }
        }


        return $info;
    }

    public static function uploadNavCustomImage($item)
    {
        if ($item['id']) {
            $navModel = LiveNavModel::find($item['id']);
            $rootPath = Site::getSiteComdataDir(getCurrentSiteId(), true);
            $image = json_decode($navModel->extra_params, true);
            if ($image['type'] == 1 && is_file($rootPath . $image['content'])) {
                unlink($rootPath . $image['content']);
            }
        }
        if (is_file($item['image'])) {
            $navCustomImageSaveDir = Site::getSiteComdataDir('', true) . '/live/nav/';
            $navCustomImageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
            $navCustomImageupload = new FileUpload($item['image'], $navCustomImageSaveDir, $navCustomImageName);
            $navCustomImageupload->reduceImageSize(1000);
            $data = '/live/nav/' . $navCustomImageupload->getFullFileName();
        }
        return $data;
    }


    /**
     * 获取直播列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getList($params = [], $page = 1, $pageSize = 20)
    {
        $query = LiveModel::query()
            ->where('site_id', getCurrentSiteId());
        // 状态
        if (isset($params['status'])) {
            $query->where('status', intval($params['status']));
        }
        // 状态
        if (isset($params['show_live_list'])) {
            $query->where('show_live_list', intval($params['show_live_list']));
        }
        // 关键字
        if ($params['keyword'] && $keyword = trim($params['keyword'])) {
            $query->where('title', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('sort')->orderByDesc('created_at')->forPage($page, $pageSize)->get();
        foreach ($list as $item) {
            $item['chat_count'] = $item->chat()->count();
            $item['viewer_count'] = $item->viewer()->count();
            // 如果已结束的 计算一下时长
            if ($item['status'] == LiveConstants::LiveStatus_End) {
                $item['time_length'] = static::calcTimeLength($item['real_live_start_time'], $item['live_end_time']);
            } else {
                $item['time_length'] = null;
            }
            $item['populariz_url'] = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . "/shop/front/#/live/live-detail?id=" . $item->id;
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    public static function calcTimeLength($liveStartTime, $liveEndTime)
    {
        $timeLength = strtotime($liveEndTime) - strtotime($liveStartTime);
        $hour = intval($timeLength / 3600);
        $minute = intval(($timeLength % 3600) / 60);
        $second = $timeLength % 60;
        return [
            'hour' => $hour < 10 ? '0' . $hour : $hour,
            'minute' => $minute < 10 ? '0' . $minute : $minute,
            'second' => $second < 10 ? '0' . $second : $second
        ];
    }

    /**
     * 获取直播广场列表
     * @param int $page
     * @param int $pageSize
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getLiveList($page = 1, $pageSize = 20)
    {
        $list = LiveModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('show_live_list', 1)
            ->orderByDesc('sort')
            ->forPage($page, $pageSize)
            ->get();
        return $list;
    }

    /**
     * 增加/减少点击数
     * @param $num
     */
    public function changeHits($num)
    {
        $this->loadModel();
        if ($num < 0 && $this->_liveModel->hits < 1) return;
        $this->_liveModel->increment('hits', $num);
    }

    /**
     * 增加/减少在线人数
     * @param $num
     */
    public function changeOnlineNum($num)
    {
        $this->loadModel();
        if ($num < 0 && $this->_liveModel->online_num < 1) return;
        $this->_liveModel->increment('online_num', $num);
    }

    /**
     * 增加/减少点赞数
     * @param $num
     */
    public function changeLike($num)
    {
        $this->loadModel();
        if ($num < 0 && $this->_liveModel->like_num < 1) return;
        $this->_liveModel->increment('like_num', $num);
    }

    /**
     * 获取直播的上屏商品信息
     * @return array|bool|int
     */
    public function getOnScreenProduct()
    {
        $liveProduct = LiveProductModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('is_onscreen', 1)
            ->where('live_id', $this->_liveModel->id)
            ->first();
        // 直播产品表的product_id存的是活动产品的ID，所以拿产品的时候，先要去拿到真实的产品ID
        if ($liveProduct->type == 1) {
            $GroupBuyingProducts = (new GroupBuyingProducts($liveProduct->product_id))->getModel();
            $productId = $GroupBuyingProducts->master_product_id;
        } else {
            $productId = $liveProduct->product_id;
        }

        $product = (new  Product($productId))->getModel();
        if ($product) {
            // 前端统一变量跳转
            if ($liveProduct->type == 1 && $GroupBuyingProducts) {
                $product->price = $GroupBuyingProducts->min_price;
                $product->product_id = $GroupBuyingProducts->id;
            } else {
                $product->product_id = $product->id;
            }
            // 暂时这样处理，来区分是否是活动产品
            $product->type = $liveProduct->type;
            return $product;
        } else
            return null;
    }

    /**
     * 设置商品是否上屏
     * @param $productId
     * @param $onScreen
     */
    public function setOnScreenProduct($productId, $onScreen)
    {
        if ($onScreen) {
            //当将某优惠券开启上屏时，先取消原来的上屏
            LiveProductModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId])->update(['is_onscreen' => 0]);
        }
        LiveProductModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId, 'product_id' => $productId])->update(['is_onscreen' => $onScreen]);
    }

    /**
     * 获取直播的上屏优惠券信息
     * @return array|bool|int
     */
    public function getOnScreenCoupon()
    {
        $item = LiveCouponModel::where('live_id', $this->_liveId)->where('is_onscreen', 1)->first();
        if ($item) {
            $coupon = new Coupon();
            $data = $coupon->getList([
                'page_size' => 1,
                'id' => $item->coupon_id,
                'member_id' => Auth::hasLogin()
            ]);
            $list = $data['list'];
            if (count($list)) return $list[0];
            else return null;
        } else {
            return null;
        }
    }

    /**
     * 设置优惠券是否上屏
     * @param $couponId
     * @param $onScreen
     */
    public function setOnScreenCoupon($couponId, $onScreen)
    {
        if ($onScreen) {
            //当将某优惠券开启上屏时，先取消原来的上屏
            LiveCouponModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId])->update(['is_onscreen' => 0]);
        }
        LiveCouponModel::where(['site_id' => getCurrentSiteId(), 'live_id' => $this->_liveId, 'coupon_id' => $couponId])->update(['is_onscreen' => $onScreen]);
    }

    /**
     * 设置商品
     * @param array $productList
     * @throws \Exception
     */
    public function setProducts($productList = [])
    {
        if ($productList) {
            $live = $this->getModel();
            $updateData = [];
            $newData = [];
            $existIds = []; // 编辑的数据 不在该数组里的 需要删除
            foreach ($productList as $item) {
                $data = [
                    'show_order' => $item['show_order'] ?: 0,
                    'type' => $item['type']
                ];
                // 已有的数据
                if ($item['id']) {
                    $existIds[] = $item['id'];
                    $data['id'] = $item['id'];
                    $updateData[] = $data;
                } else {
                    $data['site_id'] = $live->site_id;
                    $data['live_id'] = $live->id;
                    $data['product_id'] = $item['product_id'];
                    $newData[] = $data;
                }
            }
            // 先删除旧数据
            $existQuery = LiveProductModel::query()->where('site_id', getCurrentSiteId())
                ->where('live_id', $live->id);
            if ($existIds) {
                $existQuery->whereNotIn('id', $existIds);
            }
            $existQuery->delete();
            // 更新数据
            if ($updateData) {
                (new LiveProductModel())->updateBatch($updateData);
            }
            // 新增数据
            if ($newData) {
                LiveProductModel::query()->insert($newData);
            }
        } else {
            $this->loadModel();
            if ($this->_liveModel) {
                $this->_liveModel->product()->delete();
            }
        }
    }

    /**
     * 获取直播的商品列表
     * @return mixed
     */
    public function getProductList()
    {
        //产品通用字段
        $product_filed = ['p.name', 'p.big_images', 'p.small_images', 'p.status'];
        // 特别注意：每个产品列表的主键必须命名为foreign_product_id，若有更好的办法可做修改
        $product = LiveProductModel::where('live_id', $this->_liveId)
            ->where('tbl_live_product.type', 0)
            ->leftJoin('tbl_product as p', 'p.id', 'tbl_live_product.product_id')
            ->orderBy('id', 'desc')
            ->select(array_merge(['tbl_live_product.*', 'p.id as foreign_product_id', 'p.price'], $product_filed))
            ->get();
        // 团购商品
        $groupProduct = LiveProductModel::where('live_id', $this->_liveId)
            ->leftJoin('tbl_group_buying_products as gbp', 'gbp.id', 'tbl_live_product.product_id')
            ->leftJoin('tbl_product as p', 'p.id', 'gbp.master_product_id')
            ->where('tbl_live_product.type', 1)
            ->orderBy('id', 'desc')
            ->select(array_merge(['tbl_live_product.*', 'p.price as product_price', 'gbp.min_price as price', 'gbp.id as foreign_product_id', 'gbp.master_product_id'], $product_filed))
            ->get();
        // 合并不同的产品
        $ProductCollection = $product->merge($groupProduct);
        // 格式化数据
        foreach ($ProductCollection as &$item) {
            $item->price = moneyCent2Yuan($item->price);
        }
        // 排序
        $ProductCollection->sortByDesc('id');
        // 直播信息
        $liveProduct = LiveProductModel::where('live_id', $this->_liveId)->orderBy('id', 'desc')->get();
        // 合并产品信息
        foreach ($liveProduct as &$item) {
            foreach ($ProductCollection as &$pitem) {
                if ($item->product_id == $pitem->foreign_product_id) {
                    $item->product = new Collection($pitem);
                }
            }
        }
        return $liveProduct;
    }

    /**
     * 设置优惠券
     * @param array $couponList
     * @throws \Exception
     */
    public function setCoupons($couponList = [])
    {
        if ($couponList) {
            $live = $this->getModel();
            $updateData = [];
            $newData = [];
            $existIds = []; // 编辑的数据 不在该数组里的 需要删除
            foreach ($couponList as $item) {
                $data = [
                    'show_order' => $item['show_order'] ?: 0
                ];
                // 已有的数据
                if ($item['id']) {
                    $existIds[] = $item['id'];
                    $data['id'] = $item['id'];
                    $updateData[] = $data;
                } else {
                    $data['site_id'] = $live->site_id;
                    $data['live_id'] = $live->id;
                    $data['coupon_id'] = $item['coupon_id'];
                    $newData[] = $data;
                }
            }
            // 先删除旧数据
            $existQuery = LiveCouponModel::query()->where('site_id', getCurrentSiteId())
                ->where('live_id', $live->id);
            if ($existIds) {
                $existQuery->whereNotIn('id', $existIds);
            }
            $existQuery->delete();
            // 更新数据
            if ($updateData) {
                (new LiveCouponModel())->updateBatch($updateData);
            }
            // 新增数据
            if ($newData) {
                LiveCouponModel::query()->insert($newData);
            }
        } else {
            $this->loadModel();
            if ($this->_liveModel) {
                $this->_liveModel->coupon()->delete();
            }
        }
    }

    /**
     * 获取直播的优惠券列表
     * @return mixed
     */
    public function getCouponList($filter = [])
    {
        $items = LiveCouponModel::where('live_id', $this->_liveId)->orderByDesc('id')->get();
        $ids = $items->pluck('coupon_id')->values()->toArray();
        $coupon = new Coupon();
        $params = [
            'page_size' => 100,
            'ids' => $ids,
            'member_id' => Auth::hasLogin(),
            'order_by' => "find_in_set(id,'" . trim(implode(',', $ids)) . "')"
        ];
        if (isset($filter['status'])) {
            $params['status'] = $filter['status'];
        }
        if (isset($filter['count_member_canuse'])) {
            $params['count_member_canuse'] = $filter['count_member_canuse'];
        }
        $data = $coupon->getList($params);
        $list = $data['list'];

        $itemsArray = [];
        foreach ($items as $key => &$item) {
            $couponItem = $list->where('id', '=', $item->coupon_id)->first();
            if ($couponItem) {
                $item->coupon = $couponItem;
                array_push($itemsArray, $item);
            }
        }

        unset($item);
        return $itemsArray;
    }

    /**
     * 保存导航 没有修改操作
     * @param array $navList
     */
    public function setNavs($navList = [])
    {
        if ($navList) {
            $liveId = $this->getModel()->id;
            $siteId = $this->getModel()->site_id;
            $updateData = [];
            $newData = [];
            $navIds = [];
            foreach ($navList as $nav) {
                $nav = [
                    "id" => $nav['id'],
                    "name" => $nav['name'],
                    "nav_type" => $nav['nav_type'],
                    "link_type" => $nav['link_type'],
                    "image" => $nav['image'],
                    "status" => $nav['status'],
                    "extra_params" => $nav['extra_params']
                ];
                $nav['site_id'] = $siteId;
                $nav['live_id'] = $liveId;
                if (!isset($nav['image'])) $nav['image'] = null;
                if (!isset($nav['extra_params'])) $nav['extra_params'] = null;
                if ($nav['link_type'] == LiveConstants::LiveNavLinkType_Customize) {
                    $nav['extra_params'] = $nav['extra_params'] ? json_encode($nav['extra_params']) : null;
                }
                if ($nav['id']) {
                    $updateData[] = $nav;
                    $navIds[] = $nav['id'];
                } else {
                    unset($nav['id']);
                    $newData[] = $nav;
                }
            }
            // 删除旧数据
            if ($navIds) {
                LiveNavModel::query()->where('site_id', $siteId)->where('live_id', $liveId)->whereNotIn('id', $navIds)->delete();
            }
            // 更新数据
            if ($updateData) {
                (new LiveNavModel())->updateBatch($updateData);
            }
            // 新增数据
            if ($newData) {
                LiveNavModel::query()->insert($newData);
            }
        } else {
            $this->loadModel();
            if ($this->_liveModel) {
                $this->_liveModel->navs()->delete();
            }
        }
    }

    /**
     * 获取直播的菜单信息
     * @param  $transform 是否转换数据
     * @param  $params
     * @return array
     */
    public function getMenuList($transform = false, $params = [])
    {
        $expression = LiveNavModel::query()->where('live_id', $this->getModel()->id);
        if (isset($params['status'])) {
            $expression->where('status', $params['status']);
        }
        $liveNav = $expression->get();
        if ($liveNav) {
            foreach ($liveNav as &$item) {
                if ($item->extra_params) {
                    $extraParamsArr = json_decode($item->extra_params, true);
                    //当按钮是菜单按钮，且是自定义的时候并且是链接选择器的时候，需要转换一下连接
                    if ($item->link_type == LiveConstants::LiveNavLinkType_Customize && $extraParamsArr['type'] == 3 && $item->nav_type == LiveConstants::LiveNavType_Menu && $transform) {
                        $content = json_decode($extraParamsArr['content'], true);
                        $extraParamsArr['content'] = LinkHelper::getUrl($content['link_type'], $content['link_data']);
                    }
                    $item->extra_params = new Collection($extraParamsArr);
                }
            }
        }
        return $liveNav;
    }

    public function delete()
    {
        try {
            $this->loadModel();
            if ($this->_liveModel) {
                if ($this->_liveModel->status != 1) {
                    $this->_liveModel->coupon()->delete();
                    $this->_liveModel->product()->delete();
                    $this->_liveModel->navs()->delete();
                    $this->_liveModel->viewer()->delete();
                    $this->_liveModel->chat()->delete();
                    $this->_liveModel->delete();
                } else {
                    throw new \Exception('正在直播中不能删除');
                }

            } else {
                throw new \Exception('没有此记录');
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getOnlineNum()
    {
        if ($this->_liveModel) {
            return $this->_liveModel->viewer()->where('status', 1)->count();
        }
        return 0;
    }
}