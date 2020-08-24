<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\MemberModel;

/**
 * 产品收藏
 * Class ProductCollectionModel
 * @package App\Modules\ModuleShop\Libs\Model
 */
class ProductCollectionModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_product_collection';

    protected $fillable = [
        'site_id',
        'member_id',
        'product_id',
    ];

    /**
     * 初始化
     * ProductCollectionModel constructor.
     */
    public function __construct()
    {
        $this->created_at = date('Y-m-d H:i:s');
        parent::__construct();
    }

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
}
