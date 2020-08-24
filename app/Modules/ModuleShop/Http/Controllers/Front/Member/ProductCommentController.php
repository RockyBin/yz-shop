<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Product\ProductComment;
use Illuminate\Http\Request;
use Ipower\Common\Util;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

/**
 * 评论中心
 * Class ProductCommentController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\Product
 */
class ProductCommentController extends BaseController
{
    /**
     * 评论列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $param['member_id'] = $this->memberId;
            $param['type'] = Constants::ProductCommentType_Comment;
            $param['is_del'] = Constants::ProductCommentIsDel_No;
            $data = ProductComment::getList($param);
            if ($data) {
                $list = [];
                foreach ($data['list'] as $item) {
                    $comment = [
                        'order_id' => $item->order_id,
                        'order_item_id' => $item->order_item_id,
                        'product_id' => $item->product_id,
                        'product_name' => ProductComment::getProductName($item),
                        'product_image' => ProductComment::getProductImage($item),
                        'images' => $item->images ? explode(',', $item->images) : [],
                        'content' => $item->content,
                        'admin_reply' => $item->admin_reply,
                        'star' => $item->star,
                        'created_at' => $item->created_at->toDateString(),
                    ];
                    $list[] = $comment;
                }
                $data['list'] = $list;
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 发表评论
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $content = trim($request->get('content', ''));
            $star = intval($request->get('star'));
            $orderItemId = intval($request->get('order_item_id'));
            $isAnonymous = $request->get('is_anonymous') ? Constants::ProductCommentIsAnonymous_Yes : Constants::ProductCommentIsAnonymous_No;
            if (intval($star) < 1 || intval($star) > 5 || !$orderItemId || !$content) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            // 检查数据合法性
            $orderItemModel = OrderItemModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $orderItemId)
                ->where('comment_status', Constants::OrderItemCommentStatus_NoComment)
                ->first();
            if (!$orderItemModel) {
                return makeApiResponseFail(trans('shop-front.comment.action_fail'));
            }
            $orderModel = OrderModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->where('id', $orderItemModel->order_id)
                ->where('comment_status', Constants::OrderCommentStatus_CanComment)
                ->first();
            if (!$orderModel) {
                return makeApiResponseFail(trans('shop-front.comment.action_fail'));
            }
            // 检查是否可以评论
            $hasComment = ProductComment::count([
                'site_id' => $this->siteId,
                'member_id' => $this->memberId,
                'order_item_id' => $orderItemId
            ]);
            if ($hasComment) {
                return makeApiResponseFail(trans('shop-front.comment.action_fail'));
            }
            // 是否开启评论
            $commentConfig = Site::getCurrentSite()->getConfig()->getProductCommentConfig();
            if (!$commentConfig['product_comment_status']) {
                return makeApiResponseFail(trans('shop-front.comment.comment_close'));
            }
            // 评论图片
            $imageList = [];
            for ($i = 1; $i <= 4; $i++) {
                $imageKey = 'image_data_' . $i;
                if ($request->hasFile($imageKey)) {
                    $upload_file_name = time() . randString(4);
                    $upload_file_path = Site::getSiteComdataDir('', true) . '/product/comment/';
                    Util::mkdirex($upload_file_path);
                    $upload_handle = new FileUpload($request->file($imageKey), $upload_file_path, $upload_file_name);
                    $upload_handle->reduceImageSize(800);
                    $imageList[] = '/product/comment/' . $upload_handle->getFullFileName();
                }
            }
            // 构造数据
            $param = [
                'site_id' => $this->siteId,
                'member_id' => $this->memberId,
                'order_item_id' => $orderItemId,
                'order_id' => $orderModel->id,
                'product_id' => $orderItemModel->product_id,
                'star' => $star,
                'content' => trim($content),
                'is_anonymous' => $isAnonymous,
                'images' => count($imageList) > 0 ? implode(',', $imageList) : null,
            ];
            // 是否自动审核
            $param['status'] = $commentConfig['product_comment_check_way'] ? Constants::ProductCommentStatus_Active : Constants::ProductCommentStatus_WaitCheck;
            $productComment = new ProductComment();
            $id = $productComment->add($param);
            if ($id) {
                return makeApiResponseSuccess(trans('shop-front.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-front.comment.action_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}