<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Product;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\ProductCommentModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Site\Site;

/**
 * 商品评论
 * Class ProductComment
 * @package App\Modules\ModuleShop\Libs\Product
 */
class ProductComment
{
    private $_model = null;

    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            $this->findById($idOrModel);
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 列表数据
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $showAll = $param['show_all'] ? true : false;

        $query = ProductCommentModel::query()->from('tbl_product_comment as comment');
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        // 获取数据
        $last_page = ceil($total / $pageSize);
        if ($showAll) {
            $last_page = 1;
            $page = 1;
        } else {
            $query->forPage($page, $pageSize);
        }
        $query->orderByDesc('comment.id');
        $query->addSelect('comment.*');
        $query->addSelect(['member.nickname as member_nickname', 'member.headurl as member_headurl', 'member.mobile as member_mobile']);
        $query->addSelect(['product.name as product_name', 'product.small_images as product_small_images']);
        $query->addSelect(['order_item.name as order_item_name', 'order_item.image as order_item_image']);
        $list = $query->get();
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
     * 统计数量
     * @param array $param
     * @return int
     */
    public static function count(array $param)
    {
        $query = ProductCommentModel::query()->from('tbl_product_comment as comment');
        self::setQuery($query, $param);
        return $query->count();
    }

    /**
     * 查询条件
     * @param Builder $query
     * @param array $param
     */
    public static function setQuery(Builder $query, array $param)
    {
        $query->leftJoin('tbl_member as member', 'comment.member_id', '=', 'member.id');
        $query->leftJoin('tbl_product as product', 'comment.product_id', '=', 'product.id');
        $query->leftJoin('tbl_order_item as order_item', 'comment.order_item_id', '=', 'order_item.id');
        $query->where('comment.site_id', Site::getCurrentSite()->getSiteId());
        // 关键词查询
        if (trim($param['keyword'])) {
            $keyword = trim($param['keyword']);
            $query->where(function (Builder $subQuery) use ($keyword) {
                $subQuery->where('product.name', 'like', '%' . $keyword . '%');
            });
        }
        // 父级id
        if (is_numeric($param['parent_id'])) {
            $query->where('comment.parent_id', intval($param['parent_id']));
        }
        // 商品id
        if (is_numeric($param['product_id'])) {
            $query->where('comment.product_id', intval($param['product_id']));
        }
        // 订单id
        if ($param['order_id']) {
            if ($param['order_id'] == 'is_null') {
                $query->where(function (Builder $subQuery) {
                    $subQuery->whereNull('comment.order_id')->orWhere('comment.order_id', '');
                });
            } else {
                $query->where('comment.order_id', $param['order_id']);
            }
        }
        // 订单明细id
        if (is_numeric($param['order_item_id'])) {
            $query->where('comment.order_item_id', intval($param['order_item_id']));
        }
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('comment.member_id', intval($param['member_id']));
        }
        // 员工id
        if (is_numeric($param['site_admin_id'])) {
            $query->where('comment.site_admin_id', intval($param['site_admin_id']));
        }
        // 类型：0=评论，1=评论回复
        if (is_numeric($param['type'])) {
            $query->where('comment.type', intval($param['type']));
        }
        // 状态
        $status = myToArray($param['status'], ',', '-9');
        if (count($status) > 0) {
            if (count($status) == 1) {
                $query->where('comment.status', $status[0]);
            } else {
                $query->whereIn('comment.status', $status);
            }
        }
        // 是否已删除
        if (is_numeric($param['is_del'])) {
            $query->where('comment.is_del', intval($param['is_del']));
        }
        // 是否匿名
        if (is_numeric($param['is_anonymous'])) {
            $query->where('comment.is_anonymous', intval($param['is_anonymous']));
        }
        // 星级
        if (is_numeric($param['star']) && intval($param['star']) > 0) {
            $query->where('comment.star', intval($param['star']));
        }
        // 创建时间
        if ($param['created_at_min']) {
            $query->where('comment.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('comment.created_at', '<=', $param['created_at_max']);
        }
        // 是否商家已回复
        if (is_numeric($param['has_admin_reply'])) {
            $hasAdminReply = intval($param['has_admin_reply']);
            if ($hasAdminReply == 1) {
                // 已回复
                $query->where(function (Builder $subQuery) {
                    $subQuery->whereNotNull('comment.admin_reply')->where('comment.admin_reply', '!=', '');
                });
            } else if ($hasAdminReply == -1) {
                // 未回复
                $query->where(function (Builder $subQuery) {
                    $subQuery->whereNull('comment.admin_reply')->orWhere('comment.admin_reply', '');
                });
            }
        }
        // 是否真实
        if (is_numeric($param['is_real'])) {
            $isReal = intval($param['is_real']);
            if ($isReal == 1) {
                // 真实评论
                $query->where('comment.member_id', '>', 0);
            } else if ($isReal == -1) {
                // 后台评论
                $query->where('comment.member_id', 0);
            }
        }
    }

    /**
     * 批量更新数据
     * @param $ids
     * @param $status
     * @return int
     */
    public static function updateStatus($ids, $status)
    {
        if (!$ids) return 0;
        $idList = myToArray($ids);
        $updateRowNum = ProductCommentModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $idList)
            ->update([
                'status' => intval($status),
                'check_at' => date('Y-m-d H:i:s'),
            ]);
        return $updateRowNum;
    }

    /**
     * 批量删除（软删除）
     * @param $ids
     * @return int
     */
    public static function delete($ids)
    {
        if (!$ids) return 0;
        $idList = myToArray($ids);
        $updateRowNum = ProductCommentModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $idList)
            ->update([
                'is_del' => Constants::ProductCommentIsDel_Yes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return $updateRowNum;
    }

    /**
     * 获取用户信息
     * @param $item
     * @param $memberData
     * @return array
     */
    public static function getMemberInfo($item, $memberData)
    {
        $anonymous = $item->is_anonymous ? true : false;
        $nickName = $item->member_id ? $memberData['nickname'] : $item->nickname;
        $headUrl = $item->member_id ? $memberData['headurl'] : $item->headurl;
        if (!$nickName) $nickName = '**';
        if ($anonymous) {
            $strLen = mb_strlen($nickName);
            if ($nickName && $strLen > 1) {
                $firstChar = mb_substr($nickName, 0, 1);
                $lastChar = $strLen > 2 ? mb_substr($nickName, mb_strlen($nickName) - 1, 1) : '';
                $nickName = $firstChar . '**' . $lastChar;
            } else {
                $nickName = "**";
            }
        }
        return [
            'nickname' => $nickName,
            'headurl' => $headUrl
        ];
    }

    /**
     * 获取产品图片
     * @param $item
     * @return null
     */
    public static function getProductImage($item)
    {
        if ($item->order_item_image) return $item->order_item_image;
        else if ($item->product_small_images) return explode(',', $item->product_small_images)[0];
        else return null;
    }

    /**
     * 获取产品名称
     * @param $item
     * @return null
     */
    public static function getProductName($item)
    {
        if ($item->order_item_name) return $item->order_item_name;
        else if ($item->product_name) return $item->product_name;
        else return null;
    }

    /**
     * 添加数据
     * @param array $param
     * @param bool $reload
     * @return bool|mixed
     */
    public function add(array $param, $reload = false)
    {
        if ($param) {
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['created_at'] = date('Y-m-d H:i:s');
            $param['updated_at'] = date('Y-m-d H:i:s');
            // 审核时间
            if (array_key_exists('status', $param) && intval($param['status']) != Constants::ProductCommentStatus_WaitCheck) {
                $param['check_at'] = date('Y-m-d H:i:s');
            }
            $model = new ProductCommentModel();
            $model->fill($param);
            $model->save($param);
            if ($param['order_id'] && $param['order_item_id']) {
                // 更新订单明细评论状态
                OrderItemModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('id', $param['order_item_id'])
                    ->where('comment_status', Constants::OrderItemCommentStatus_NoComment)
                    ->update([
                        'comment_status' => Constants::OrderItemCommentStatus_HasComment
                    ]);
                // 更新订单评论状态
                if (!$param['not_update_order_comment_status']) {
                    $orderHelper = new OrderHelper();
                    $orderHelper->updateCommentStatus($param['order_id']);
                }
            }
            if ($reload) {
                $this->findById($model->id);
            }
            return $model->id;
        } else {
            return false;
        }
    }

    /**
     * 修改数据
     * @param array $param
     * @param bool $reload
     * @return bool
     */
    public function edit(array $param, $reload = false)
    {
        if ($this->checkExist()) {
            unset($param['site_id']);
            // 审核时间
            if (array_key_exists('status', $param) && intval($param['status']) != Constants::ProductCommentStatus_WaitCheck) {
                $param['check_at'] = date('Y-m-d H:i:s');
            }
            $param['updated_at'] = date('Y-m-d H:i:s');
            $this->_model->fill($param);
            $this->_model->save();
            if ($reload) {
                $this->findById($this->_model->id);
            }
            return true;
        }
        return false;
    }

    /**
     * 是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && intval($this->_model->status) == Constants::ProductCommentStatus_Active && intval($this->_model->is_del) == Constants::ProductCommentIsDel_No) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model && $this->_model->id ? true : false;
    }

    /**
     * 返回模型数据
     * @return bool|null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        }
    }

    /**
     * 根据id查找
     * @param $id
     */
    private function findById($id)
    {
        if ($id) {
            $model = ProductCommentModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $id)
                ->first();
            $this->init($model);
        }
    }
}