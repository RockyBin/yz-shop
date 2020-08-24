<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Product\ProductComment;
use Illuminate\Http\Request;
use Ipower\Common\Util;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Constants as CoreConstants;

/**
 * 商品评论
 * Class ProductCommentController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Product
 */
class ProductCommentController extends BaseSiteAdminController
{
    /**
     * 列表数据
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['type'] = Constants::ProductCommentType_Comment;
            $param['is_del'] = Constants::ProductCommentIsDel_No;

            // 处理状态
            if (array_key_exists('status', $param)) {
                if (intval($param['status']) == 10) {
                    $param['status'] = Constants::ProductCommentStatus_Active;
                    $param['has_admin_reply'] = -1;
                } else if (intval($param['status']) == 11) {
                    $param['status'] = Constants::ProductCommentStatus_Active;
                    $param['has_admin_reply'] = 1;
                }
            }
            $data = ProductComment::getList($param);
            if ($data) {
                foreach ($data['list'] as $item) {
                    $item->is_real = $item->member_id ? 1 : -1;
                    $item->has_admin_reply = $item->admin_reply ? 1 : -1;
                    $item->images = $item->images ? explode(',', $item->images) : [];
                    $item->product_small_images = ProductComment::getProductImage($item);
                    $item->product_image = ProductComment::getProductImage($item);
                    $item->product_name = ProductComment::getProductName($item);
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 添加评论
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $param = $request->toArray();
            $productId = intval($param['product_id']);
            if (!$productId) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $param['type'] = Constants::ProductCommentType_Comment;
            $param['parent_id'] = 0;
            $param['site_admin_id'] = SiteAdmin::getLoginedAdminId();
            $param['status'] = Constants::ProductCommentStatus_Active;
            // 验证数据合法性
            if (!$param['nickname'] || !$param['content'] || intval($param['star']) < 1 || intval($param['star']) > 5) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 头像
            if ($request->hasFile('headurl_data')) {
                $upload_file_name = time() . randString(4);
                $upload_file_path = Site::getSiteComdataDir('', true) . '/member/';
                $upload_handle = new FileUpload($request->file('headurl_data'), $upload_file_path, $upload_file_name);
                $upload_handle->reduceImageSize(200);
                $param['headurl'] = '/member/' . $upload_handle->getFullFileName();
            }
            // 评论图片
            $images = [];
            for ($i = 1; $i <= 4; $i++) {
                $imageKey = 'image_data_' . $i;
                if ($request->hasFile($imageKey)) {
                    $upload_file_name = time() . randString(4);
                    $upload_file_path = Site::getSiteComdataDir('', true) . '/product/comment/';
                    Util::mkdirex($upload_file_path);
                    $upload_handle = new FileUpload($request->file($imageKey), $upload_file_path, $upload_file_name);
                    $upload_handle->reduceImageSize(800);
                    $images[] = '/product/comment/' . $upload_handle->getFullFileName();
                }
            }
            $param['images'] = count($images) > 0 ? implode(',', $images) : null;
            $productComment = new ProductComment();
            $id = $productComment->add($param);
            if ($id) {
                return makeApiResponseSuccess(trans('shop-admin.common.save_ok'), [
                    'id' => $id
                ]);
            } else {
                return makeApiResponseFail(trans('shop-admin.common.save_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 商家回复评论
     * @param Request $request
     * @return array
     */
    public function adminReply(Request $request)
    {
        try {
            $id = $request->get('id');
            $adminReply = $request->get('admin_reply');
            $productComment = new ProductComment($id);
            if (!$productComment->isActive()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $result = $productComment->edit([
                'admin_reply' => $adminReply ? $adminReply : null,
            ]);
            if ($result) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            ProductComment::delete(myToArray($request->get('id')));
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 审核
     * @param Request $request
     * @return array
     */
    public function status(Request $request)
    {
        try {
            ProductComment::updateStatus(myToArray($request->get('id')), intval($request->get('status')));
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 产品列表
     * @param Request $request
     * @return array
     */
    public function getProductList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('pageSize', 20);
            $filter = [
                'status' => CoreConstants::Product_Status_Sell,
                'class' => $request->input('class', []),
                'keyword' => $request->input('keyword', ''),
                'order_by' => [
                    'column' => 'created_at',
                    'order' => 'asc'
                ],
            ];
            $data = Product::getList($filter, $page, $pageSize);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}