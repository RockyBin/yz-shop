<?php
/**
 * 产品评论模型
 */

namespace App\Modules\ModuleShop\Libs\Model;

use Illuminate\Database\Eloquent\Model;
use YZ\Core\Model\MemberModel;

class ProductCommentModel extends Model
{
    protected $table = 'tbl_product_comment';

    protected $fillable = [
        'site_id',
        'parent_id',
        'product_id',
        'order_id',
        'order_item_id',
        'member_id',
        'site_admin_id',
        'type',
        'status',
        'is_del',
        'star',
        'is_anonymous',
        'images',
        'nickname',
        'headurl',
        'content',
        'admin_reply',
        'created_at',
        'updated_at',
        'check_at',
    ];

    /**
     * 该评论属于哪个产品 关联tbl_product
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }

    /**
     * 该评论属于哪个会员 关联tbl_member
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function member()
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }
    // 还应该有订单 ...
}
