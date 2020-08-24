<?php

namespace App\Modules\ModuleShop\Libs\Browse;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\BrowseModel;

/**
 * 浏览记录
 * Class Browse
 * @package App\Modules\ModuleShop\Libs\Browse
 */
class Browse
{
    private $browseEntity = null;
    private $siteID = 0;

    /**
     * 初始化
     * Point constructor.
     */
    public function __construct()
    {
        $this->siteID = Site::getCurrentSite()->getSiteId();
        $this->browseEntity = new BrowseModel();
    }

    /**
     * 保存浏览记录
     * @param $params
     */
    public function save($params)
    {
        $memberId = intval($params['member_id']);
        $productId = intval($params['product_id']);
        if (!$memberId || !$productId) return;

        $count = $this->browseEntity->query()
            ->where('site_id', $this->siteID)
            ->where('member_id', $memberId)
            ->where('product_id', $productId)
            ->count();
        // 防止数据多于一条
        if ($count > 1) {
            $this->browseEntity->query()
                ->where('site_id', $this->siteID)
                ->where('member_id', $memberId)
                ->where('product_id', $productId)
                ->delete();
            $count = 0;
        }
        if ($count > 0) {
            // 更新
            $this->browseEntity->query()
                ->where('site_id', $this->siteID)
                ->where('member_id', $memberId)
                ->where('product_id', $productId)
                ->update([
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // 插入数据
            $params['created_at'] = date('Y-m-d H:i:s');
            $params['updated_at'] = date('Y-m-d H:i:s');
            $this->browseEntity->fill($params);
            $this->browseEntity->site_id = $this->siteID;
            $this->browseEntity->save();
        }
    }

    /**
     * 列表查询
     * @param array $params 参数
     * @return array
     */
    public function getList($params)
    {
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;
        $expression = $this->browseEntity::query()
            ->from('tbl_browse as browse');
        $expression->join('tbl_product as product', 'product.id', '=', 'browse.product_id');
        $expression->where('browse.site_id', $this->siteID);
        $expression->where('member_id', $params['member_id']);
        $expression->where('product.status', '>=', 0);
        $total = $expression->count();
        if ($params['only_return_count']) return $total;
        // 输出-最后页数
        $last_page = ceil($total / $page_size);
        $expression = $expression->forPage($page, $page_size);
        $list = $expression
            ->select('browse.*')
            ->orderby('browse.updated_at', 'desc')
            ->get();

        return [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 删除X天的以外的浏览数据，默认30天
     * @param $member_id
     * @param int $day
     */
    public function deleteByMemberId($member_id, $day = 30)
    {
        if ($member_id) {
            $expire_time = date('Y-m-d 00:00:00', strtotime("-" . intval($day) . " day", time()));
            $where[] = ['updated_at', '<', $expire_time];
            $where[] = ['site_id', $this->siteID];
            $where[] = ['member_id', $member_id];
            $this->browseEntity::query()->where($where)->delete();
        }
    }

    /**
     * 删除指定的浏览数据
     * @param $ids 浏览记录的ID号，可以是单个ID或ID数组
     */
    public function delete($ids,$memberId = 0)
    {
        if(!is_array($ids)) $ids = [$ids];
        if (count($ids)) {
            $where[] = ['site_id', $this->siteID];
            if($memberId) $where[] = ['member_id', $memberId];
            $this->browseEntity::query()->where($where)->whereIn('id',$ids)->delete();
        }
    }
}