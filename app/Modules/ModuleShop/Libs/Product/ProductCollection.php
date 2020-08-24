<?php
/**
 * Created by Aison
 */

namespace App\Modules\ModuleShop\Libs\Product;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\ProductCollectionModel;

class ProductCollection
{
    private $siteId = 0;
    private $model = null;

    /**
     * 初始化
     * ProductCollection constructor.
     * @param null $modelOrId
     * @param int $siteId
     */
    public function __construct($modelOrId = null, $siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
        if ($modelOrId) {
            if (is_numeric($modelOrId)) {
                $this->findById($modelOrId);
            } else {
                $this->init($modelOrId);
            }
        }
    }

    /**
     * 获取列表
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;

        // 过滤掉产品已经删除的
        $query = ProductCollectionModel::query()
            ->from('tbl_product_collection as collection')
            ->join('tbl_product as product', 'collection.product_id', '=', 'product.id')
            ->where('product.status','>=', 0)
            ->where('collection.site_id', $this->siteId);

        if (intval($param['member_id']) > 0) {
            $query->where('collection.member_id', intval($param['member_id']));
        }

        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        $query->forPage($page, $pageSize);
        // 获取数据
        $list = $query->select('collection.*')
            ->orderBy('collection.id', 'desc')
            ->get();

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 添加一个收藏
     * @param $param
     * @return bool
     */
    public function add($param)
    {
        $this->findByMemberProduct($param['member_id'], $param['product_id']);
        // 不存在，才添加
        if (!$this->checkExist()) {

            // 检查产品是否存在
            $product = new Product(intval($param['product_id']), $this->siteId);
            if (!$product->checkExist()) {
                return false;
            }

            $modelNew = new ProductCollectionModel();
            $modelNew->fill($param);
            $modelNew->site_id = $this->siteId;
            $modelNew->save();
            $this->init($modelNew);

            return true;
        }

        return true;
    }

    /**
     * 统计单个会员的收藏数量
     * @param $memberId
     * @return int
     */
    public function count($memberId)
    {
        if (empty($memberId)) return 0;
        return ProductCollectionModel::query()
            ->from('tbl_product_collection as collection')
            ->join('tbl_product as product', 'collection.product_id', '=', 'product.id')
            ->where('product.status','>=', 0)
            ->where('collection.site_id', $this->siteId)
            ->where('member_id', $memberId)
            ->count();
    }

    /**
     * 删除当前收藏
     * params $productId
     * @return bool
     */
    public static function delete($memberId,$productId)
    {
        ProductCollectionModel::query()
            ->where('site_id',  Site::getCurrentSite()->getSiteId())
            ->where('member_id', intval($memberId))
            ->whereIn('product_id', myToArray($productId))
            ->delete();
    }

    /**
     * 返回当前数据模型
     * @return bool|null
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->model;
        } else {
            return false;
        }
    }

    /**
     * 通过会员id和产品id获取实例
     * @param $memberId
     * @param $productId
     */
    public function findByMemberProduct($memberId, $productId)
    {
        $model = ProductCollectionModel::query()
            ->where('site_id', intval($this->siteId))
            ->where('member_id', intval($memberId))
            ->where('product_id', intval($productId))
            ->first();
        $this->init($model);
    }

    /**
     * 检查是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->model && $this->model->id) return true;
        else return false;
    }

    /**
     * 通过id查找
     * @param $id
     */
    private function findById($id)
    {
        if (empty($id)) return;
        $query = ProductCollectionModel::query()
            ->where('id', $id);
        if ($this->siteId > 0) {
            $query->where('site_id', $this->siteId);
        }
        $this->init($query->first());
    }

    /**
     * 实例化
     * @param $model
     */
    private function init($model)
    {
        if ($model && $model->id) {
            $this->model = $model;
            $this->siteId = $model->site_id;
        } else {
            $this->model = null;
        }
    }
}