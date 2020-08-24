<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Product;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController as BaseController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Product\ProductComment;
use Illuminate\Http\Request;

/**
 * 商品评论（无需登录）
 * Class ProductCommentController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Product
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
            $productId = $param['product_id'];
            if (!is_numeric($productId)) {
                return makeApiResponseFail(trans('shop-front.common.action_fail'));
            }
            $param['type'] = Constants::ProductCommentType_Comment;
            $param['is_del'] = Constants::ProductCommentIsDel_No;
            $param['status'] = Constants::ProductCommentStatus_Active;
            $data = ProductComment::getList($param);
            if ($data) {
                $list = [];
                foreach ($data['list'] as $item) {
                    $memberData = ProductComment::getMemberInfo($item, [
                        'nickname' => $item->member_nickname,
                        'headurl' => $item->member_headurl,
                    ]);
                    $comment = [
                        'member_nickname' => $memberData['nickname'],
                        'member_headurl' => $memberData['headurl'],
                        'product_name' => ProductComment::getProductName($item),
                        'product_image' => ProductComment::getProductImage($item),
                        'images' => $item->images ? explode(',', $item->images) : [],
                        'content' => $item->content,
                        'admin_reply' => $item->admin_reply,
                        'star' => $item->star,
                        'created_at' => $item->created_at->toDateString(),
                        'is_anonymous' => $item->is_anonymous,
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
}