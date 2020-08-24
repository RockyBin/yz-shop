<?php
namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;

/**
 * 供应商提现管理类
 * Class SupplierSettleAdmin
 * @package App\Modules\ModuleShop\Libs\Supplier
 */
class SupplierPlatformWithdraw
{
    private $siteId = 0; // 站点ID
    private $supplierId = 0; // 供应商ID

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId 站点ID
     * @param int $supplierId 供应商会员ID
     */
    public function __construct($siteId,$supplierId)
    {
        $this->siteId = $siteId;
        $this->supplierId = $supplierId;
        if($this->siteId < 1 || $this->supplierId < 1){
            throw new \Exception("数据错误，站点ID或供应商ID不对");
        }
    }

    /**
     * 获取供应商结算列表
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = $param['page'] ? intval($param['page']) : 1; // 查询页码
        $pageSize = $param['page_size'] ? intval($param['page_size']) : 20; // 每页数据量
        $isShowAll = $param['show_all'] || ($param['ids'] && strlen($param['ids'] > 0)) ? true : false; // 是否显示全部数据（不分页），主要用于导出

        $query = FinanceModel::query();
        $this->setQuery($query, $param);

        // 总数据量
        $total = $query->count();
        // 如果拿出全部，就看作拿第一页且数量为总数量
        if ($total > 0 && $isShowAll) {
            $pageSize = $total;
            $page = 1;
        }
        $last_page = ceil($total / $pageSize); // 总页数

        // 分页
        $query->forPage($page, $pageSize);

        $query->addSelect('id','status','tradeno','pay_type','out_type','money','money_fee','money_real','created_at','reason','snapshot');

        $list = $query->orderBy('created_at', 'desc')->get();
        if ($total > 0) {
            foreach ($list as &$item) {
                $item->money = moneyCent2Yuan(abs($item->money));
                $item->money_real = moneyCent2Yuan(abs($item->money_real));
                $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                $item->snapshot = json_decode($item->snapshot,true);
                $this->convertOutputData($item);
            }
        }
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 处理数据
     * @param $item
     */
    private function convertOutputData(&$item)
    {
        // 收款方账户烈性
        $item['withdraw_from'] = '';
        if (in_array($item['out_type'], [\YZ\Core\Constants::FinanceInType_SupplierToBalance])) {
            $item['withdraw_from'] = '余额';
        } else if (in_array($item['pay_type'], [\YZ\Core\Constants::PayType_Weixin, \YZ\Core\Constants::PayType_WeixinQrcode])) {
            $item['withdraw_from'] = '微信钱包';
        } else if (in_array($item['pay_type'], [\YZ\Core\Constants::PayType_Alipay, \YZ\Core\Constants::PayType_AlipayQrcode, \YZ\Core\Constants::PayType_AlipayAccount])) {
            $item['withdraw_from'] = '支付宝';
        } else if (in_array($item['pay_type'], [\YZ\Core\Constants::PayType_Bank])) {
            $item['withdraw_from'] = '银行账户';
        }
        // 线上或线下结算
        $item['withdraw_from_extend'] = '';
        if (in_array(intval($item['pay_type']), \YZ\Core\Constants::getOnlinePayType())) {
            $item['withdraw_from_extend'] = "线上结算";
        } else if (in_array(intval($item['pay_type']), \YZ\Core\Constants::getOfflinePayType())) {
            $item['withdraw_from_extend'] = "线下结算";
        }
        // 状态说明
        $item['status_text'] = '提现中';
        if($item['status'] == 1) $item['status_text'] = '提现成功';
        if($item['status'] == 2) $item['status_text'] = '提现失败';
    }

    /**
     * 构造查询语句
     * @param $param
     * @param Builder $query
     * @return Builder
     */
    private function setQuery(Builder $query, $param)
    {
        $query->from('tbl_finance as finance')
            ->where('finance.site_id', $this->siteId)->where('finance.member_id',$this->supplierId)
            ->where('type',\YZ\Core\Constants::FinanceType_Supplier)
            ->whereIn('out_type',[\YZ\Core\Constants::FinanceOutType_Withdraw,\YZ\Core\Constants::FinanceOutType_SupplierToBalance])
            ->where('money','<',0);
        // 提现状态
        if ($param['status'] > -1) {
            $query->where('finance.status', $param['status']);
        }
        // 指定列表（通常用于导出）
        if ($param['ids']) {
            $financeIds = [];
            if (is_array($param['ids'])) {
                $financeIds = $param['ids'];
            } else if (trim($param['ids'])) {
                $financeIds = explode(',', trim($param['ids']));
            }
            if (count($financeIds) > 0) {
                $query->whereIn('finance.id', $financeIds);
            }
        }
        // 搜索
        if ($param['keyword']) {
            $keyword = $param['keyword'];
            $query->where('finance.tradeno', 'like', '%' . trim($keyword) . '%');
        }
        // 时间开始
        if (trim($param['created_start'])) {
            $query->where('finance.created_at', '>=', trim($param['created_start']));
        }
        // 时间结束
        if (trim($param['created_end'])) {
            $query->where('finance.created_at', '<=', trim($param['created_end']));
        }
        return $query;
    }
}