<?php

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Support\Facades\DB;

/**
 * 云仓出入库记录
 * Class CloudStockSkuLog
 * @package App\Modules\ModuleShop\Libs\CloudStock
 */
class CloudStockSkuLog
{
    /**
     * 获取云仓出入库记录
     *
     * @param array $param
     * @return void
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $query = DB::table('tbl_cloudstock_sku_log as log')
            ->leftjoin('tbl_cloudstock_sku as cs', function ($join) {
                $join->on('cs.sku_id', 'log.sku_id')
                    ->whereRaw('cs.member_id = log.member_id');
            })
            ->leftjoin('tbl_product as p', function ($join) {
                $join->on('p.id', '=', 'log.product_id');
            })
            ->leftjoin('tbl_product_skus as sku', function ($join) {
                $join->on('sku.id', '=', 'log.sku_id')
                    ->whereRaw('sku.product_id = p.id');
            })
            ->where('log.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        $query->orderBy('log.id', 'desc');
        $query->addSelect('log.*', 'cs.product_name', 'cs.sku_name', 'cs.product_image as cs_product_image', 'p.name as proname', 'p.small_images as productimage', 'sku.sku_name as skuname', 'sku.sku_image as skuimage');
        $query->forPage($page, $pageSize);
        $list = $query->get();
        // 合并信息
        foreach ($list as &$item) {
            if ($item->productimage) {
                $item->product_image = ($item->skuimage ? $item->skuimage : explode(',', $item->productimage)[0]);
            } else {
                $item->product_image = $item->cs_product_image;
            }
            if ($item->proname) {
                $item->product_name = $item->proname;
            }
            $item->sku_name = $item->skuname
                ? json_decode($item->skuname, true)
                : $item->sku_name
                    ? json_decode($item->sku_name, true)
                    : [];
//            if ($item->skuname) {
//                $item->sku_name = implode(' ', json_decode($item->skuname, true));
//            }
            switch ($item->in_type) {
                case Constants::CloudStockInType_FirstGift:
                    $inTypeText = '首次开通云仓赠送';
                    break;
                case Constants::CloudStockInType_Manual:
                    $inTypeText = '手动增加库存';
                    $about = ($item->about ? $item->about : '');
                    break;
                case Constants::CloudStockInType_Purchase:
                    $inTypeText = '进货增加库存';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                case Constants::CloudStockInType_Return:
                    $inTypeText = '退货入仓';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                case Constants::CloudStockInType_TakeDelivery_Return:
                    $inTypeText = '取消提货单返回库存';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                default:
                    $inTypeText = '--';
                    break;
            }
            switch ($item->out_type) {
                case Constants::CloudStockOutType_Sale:
                    $outTypeText = '会员下单扣减库存';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                case Constants::CloudStockOutType_SubSale:
                    $outTypeText = '下级进货扣减库存';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                case Constants::CloudStockOutType_Manual:
                    $outTypeText = '手动减少库存';
                    $about = ($item->about ? $item->about : '');
                    break;
                case Constants::CloudStockOutType_Take:
                    $outTypeText = '提货扣减库存';
                    $about = ($item->order_id ? '订单号' . $item->order_id : '');
                    break;
                default:
                    $outTypeText = '--';
                    break;
            }
            $item->in_type_text = $inTypeText;
            $item->out_type_text = $outTypeText;
            $item->about = $about;
//            unset($item->skuname, $item->proname, $item->productimage);
        }
        unset($item);

        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize
        ];
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery($query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('log.member_id', intval($param['member_id']));
        }
        // 产品id
        if (is_numeric($param['product_id'])) {
            $query->where('log.product_id', intval($param['product_id']));
        }
        // sku id
        if (strlen($param['sku_id'])) {
            $query->where('log.sku_id', intval($param['sku_id']));
        }
        // 类型
        if (strlen($param['type'])) {
            $arr = explode(':', $param['type']);
            $type = $arr[0];
            $typeValue = $arr[1];
            if ($type == 'in') $query->where('log.in_type', $typeValue);
            elseif ($type == 'out') $query->where('log.out_type', $typeValue);
        }
        // 用于前台判断是出库还是入库 1 为入库， 2为出库
        if ($param['front_type'] == 1) {
            $query->where('log.num', '>=', 0);
        } elseif ($param['front_type'] == 2) {
            $query->where('log.num', '<', 0);
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('log.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('log.created_at', '<=', $param['created_at_max']);
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            $query->where(function ($query2) use ($keyword) {
                $query2->orWhere('p.name', 'like', $keyword);
                $query2->orWhere('cs.product_name', 'like', $keyword);
            });
        }
    }
}