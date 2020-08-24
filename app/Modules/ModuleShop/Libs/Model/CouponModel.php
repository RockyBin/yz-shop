<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 优惠券模块
 * Class CouponModel
 * @package App\Modules\Model
 */
class CouponModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_coupon';
    protected $fillable = [
        'title',
        'terminal_type',
        'doorsill_type',
        'doorsill_full_money',
        'coupon_type',
        'coupon_money',
        'effective_type',
        'effective_starttime',
        'effective_endtime',
        'member_type',
        'product_type',
        'product_info',
        'amount_type',
        'amount',
        'receive_limit_type',
        'receive_limit_num',
        'status',
        'receivie_status'
    ];

    /**
     * 获取领取了该优惠券的会员
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function couponItem()
    {
        return $this->hasMany(CouponItemModel::class, 'coupon_id');
    }

    /**
     * 直播关联的优惠卷
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function couponLive()
    {
        return $this->hasMany(LiveCouponModel::class, 'coupon_id');
    }

    public function product(string $keyword='', array $orderBy = [],int $page = 1, int $pageSize =10,$paginate=true)
    {
        if ($this->product_type == 2)
        {
            $productInfo = explode(',',trim($this->product_info));
            $res = ProductModel::query()->whereIn('id', $productInfo)->when($keyword, function ($query, $keyword){
                $query->where('name','like',"%{$keyword}%");
            })->when($orderBy, function($query,$orderBy){
                $query->orderBy($orderBy['column'],$orderBy['order']);
            });
            if (!$paginate)
            {
                return $res->get();
            }
            $total = $res->count();
            $currentPage = $page;
            $totalPage = $total > $pageSize ? intval(ceil($total / $pageSize)) : 1;
            $nextPage = $totalPage > $page ? $page + 1 : $page;
            $data = $res->get();
            $this->products = [
                'total'=>$total,
                'page_size' => $pageSize,
                'current' => $currentPage,
                'last_page' => $totalPage,
                'list' => $data,
                'next_page' => $nextPage
            ];
        }
    }
}