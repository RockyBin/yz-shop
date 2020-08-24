<?php
/**
 * 供应商后台逻辑
 * User: liyaohui
 * Date: 2020/6/18
 * Time: 17:36
 */

namespace App\Modules\ModuleShop\Libs\Supplier;


use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierAdminModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierSettleModel;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Constants as LibsConstant;

class SupplierAdmin
{
    protected $siteId = 0;
    protected $memberId = 0;
    protected $memberModel = null;

    /**
     * SupplierAdmin constructor.
     * @param $memberId
     * @throws \Exception
     */
    public function __construct($memberId)
    {
        $this->siteId = getCurrentSiteId();
        if (is_numeric($memberId)) {
            $this->memberId = $memberId;
        } else {
            throw new \Exception('数据错误');
        }
    }

    /**
     * 获取会员模型
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getMemberModel()
    {
        if ($this->memberModel === null) {
            $this->memberModel = MemberModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $this->memberId)
                ->first();
            if (!$this->memberModel) {
                throw new \Exception('会员不存在');
            }
        }
        return $this->memberModel;
    }

    public static function getList($params = [])
    {
        $siteId = getCurrentSiteId();
        $query = SupplierModel::query()
            ->from('tbl_supplier as supplier')
            ->join('tbl_member as member', 'member.id', 'supplier.member_id')
            ->leftJoin('tbl_site_admin as admin', 'admin.id', 'member.admin_id')
            ->where('supplier.site_id', $siteId);
        // 搜索条件
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $keywordType = intval($params['keyword_type']);
            switch ($keywordType) {
                // 会员搜索
                case 1:
                    $query->where(function ($query) use ($keyword) {
                        $query->where('member.nickname', 'like', $keyword);
                        $query->orWhere('member.name', 'like', $keyword);
                        $query->orWhere('member.mobile', 'like', $keyword);
                    });
                    break;
                // 员工搜索
                case 2:
                    $query->where(function ($query) use ($keyword) {
                        $query->orWhere('admin.name', 'like', $keyword);
                        $query->orWhere('admin.mobile', 'like', $keyword);
                    });
                    break;
                // 供应平台搜索
                case 3:
                    $query->where('supplier.name', 'like', $keyword);
                    break;
            }
        }
        // 状态
        if (isset($params['status'])) {
            $query->where('supplier.status', intval($params['status']));
        }
        // 时间
        if (isset($params['created_at_start'])) {
            $query->where('supplier.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $query->where('supplier.created_at', '<=', $params['created_at_end']);
        }
        $page = $params['page'] ?: 1;
        $pageSize = $params['page_size'] ?: 20;
        $total = $query->count();
        $lastPage = ceil($total / $pageSize);
        $list = $query->orderByDesc('supplier.created_at')
            ->addSelect([
                'member.nickname',
                'member.mobile as member_mobile',
                'member.headurl',
                'member.name',
                'supplier.status',
                'supplier.member_id',
                'supplier.created_at',
                'admin.name as admin_name',
                'admin.mobile as admin_mobile',
                'supplier.name as supplier_name'
            ])
            ->forPage($page, $pageSize)
            ->get();
        if ($list->isNotEmpty()) {
            $memberIds = $list->pluck('member_id')->toArray();
            // 统计商品数量
            // 供应商产品需要知道供应商是否已取消资格
            $productCount = ProductModel::query()
                ->where('site_id', $siteId)
                ->whereIn('status', [Constants::Product_Status_Sell, Constants::Product_Status_Warehouse])
                ->whereIn('supplier_member_id', $memberIds)
                ->selectRaw('
                    supplier_member_id,
                    sum(if(verify_status in (?,?) and status in (?,?) , 1, 0)) as total,
                    sum(if(verify_status = ? and status = ?, 1, 0)) as sell_count,
                    sum(if(verify_status = ?, 1, 0)) as wait_review_count
                ', [
                    Constants::Product_VerifyStatus_Active,
                    Constants::Product_VerifyStatus_WaitReview,
                    Constants::Product_VerifyStatus_Active,
                    Constants::Product_Status_Warehouse,
                    Constants::Product_VerifyStatus_Active,
                    Constants::Product_Status_Sell,
                    Constants::Product_VerifyStatus_WaitReview
                ])
                ->groupBy('supplier_member_id')
                ->get()->keyBy('supplier_member_id')->toArray();
            // 财务统计
            $finance = FinanceModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('type', Constants::FinanceType_Supplier)
                ->whereIn('member_id', $memberIds)
                ->groupBy('member_id')
                ->selectRaw('
                    member_id,
                    sum(if(`status`=? and money>0,money,0) + if(`status`<>? and money<0,money,0)) as can_use',
                    [
                        Constants::FinanceStatus_Active,
                        Constants::FinanceStatus_Invalid
                    ]
                )
                ->get()->keyBy('member_id')->toArray();
            // 佣金结算情况
            $settle = SupplierSettleModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('supplier_member_id', $memberIds)
                ->groupBy('member_id')
                ->selectRaw('
                    supplier_member_id as member_id,
                    sum(if(`status`=?,(money + freight + after_sale_money + after_sale_freight),0)) as freeze,
                    sum(if(`status`=?,(money + freight+after_sale_money + after_sale_freight),0)) as total',
                    [
                        LibsConstant::SupplierSettleStatus_NoActive,
                        LibsConstant::SupplierSettleStatus_Active
                    ]
                )
                ->get()->keyBy('member_id')->toArray();
            // 匹配给列表
            foreach ($list as $value) {
                // 代理区域匹配
                if ($productCount[$value['member_id']]) {
                    // 如果此供应商是取消资格的，那么上架待审都为0，但也要显示总数
                    if ($value['status'] == SupplierConstants::SupplierStatus_Active) {
                        $value->product_count = $productCount[$value['member_id']];
                    } else {
                        $value->product_count = [
                            'total' => $productCount[$value['member_id']]['total'],
                            'sell_count' => 0,
                            'wait_review_count' => 0
                        ];
                    }
                } else {
                    $value->product_count = [
                        'total' => 0,
                        'sell_count' => 0,
                        'wait_review_count' => 0
                    ];
                }
                // 结算匹配
                $money = [
                    'total' => moneyCent2Yuan($settle[$value['member_id']]['total']),
                    'freeze' => moneyCent2Yuan($settle[$value['member_id']]['freeze']),
                    'can_use' => moneyCent2Yuan($finance[$value['member_id']]['can_use']),
                ];
                $value->money = $money;
                $value->member_mobile = Member::memberMobileReplace($value->member_mobile);
            }
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 新增供应商
     * @param array $params
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add($params = [])
    {
        try {
            $supplierName = trim($params['name']);
            if (!$supplierName) {
                throw new \Exception('请输入供应商平台名称');
            }
            DB::beginTransaction();
            $member = $this->getMemberModel();
            $coreMember = new \YZ\Core\Member\Member($member);
            // 会员是否存在
            if (!$coreMember->checkExist()) {
                throw new \Exception("会员不存在");
            }
            // 是否绑定了手机号
            $coreMember->checkBindMobile();
            if ($member->is_supplier == SupplierConstants::SupplierStatus_Active) {
                throw new \Exception('该会员已经是供应商', 412);
            } elseif ($member->is_supplier == SupplierConstants::SupplierStatus_Cancel) {
                throw new \Exception('该会员是已禁用的供应商', 410);
            }
            $supplier = new SupplierModel();
            $supplier->member_id = $this->memberId;
            $supplier->site_id = $this->siteId;
            $supplier->status = SupplierConstants::SupplierStatus_Active;
            $supplier->name = $supplierName;
            $save = $supplier->save();
            // 更新会员
            $member = new Member($member);
            $member->edit(['is_supplier' => SupplierConstants::SupplierStatus_Active]);
            $this->addAfter($this->memberId);
            DB::commit();
            return $save;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 取消资格
     * @return int
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancel()
    {
        try {
            DB::beginTransaction();
            $member = $this->getMemberModel();
            if ($member->is_supplier != SupplierConstants::SupplierStatus_Active) {
                throw new \Exception('供应商状态错误');
            }
            $save = SupplierModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->update(['status' => SupplierConstants::SupplierStatus_Cancel]);

            // 更新会员
            $member = new Member($member);
            $member->edit(['is_supplier' => SupplierConstants::SupplierStatus_Cancel]);
            $this->cancelAfter();
            DB::commit();
            return $save;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 取消资格后的操作 下架相关商品
     */
    private function cancelAfter()
    {
        // 下架相关商品
        ProductModel::query()
            ->where('site_id', $this->siteId)
            ->where('supplier_member_id', $this->memberId)
            ->where('status', Constants::Product_Status_Sell)
            ->update(['status' => Constants::Product_Status_Warehouse]);
    }

    /**
     * 新增供应商后，添加对应的供应商平台账号
     */
    private function addAfter($memberId)
    {
        $supplierAdminModel = new SupplierAdminModel();
        $member = (new Member($memberId))->getModel();
        $checkMobile = SupplierPlatformAdmin::checkMobile($member->mobile);
        if($checkMobile){
            throw new \Exception('该会员已经为供应商的员工，不能新增为供应商');
        }
        $supplierAdminModel->site_id = $this->siteId;
        $supplierAdminModel->member_id = $memberId;
        $supplierAdminModel->role_id = 0;
        $supplierAdminModel->name = $member->nickname;
        $supplierAdminModel->mobile = $member->mobile;
        $supplierAdminModel->password = $member->password;
        $supplierAdminModel->status = 1;
        $supplierAdminModel->save();
    }

    /**
     * 恢复供应商资格
     * @return int
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function recover()
    {
        try {
            DB::beginTransaction();
            $member = $this->getMemberModel();
            if ($member->is_supplier != SupplierConstants::SupplierStatus_Cancel) {
                throw new \Exception('供应商状态错误');
            }
            $save = SupplierModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->update(['status' => SupplierConstants::SupplierStatus_Active]);

            // 更新会员
            $member = new Member($member);
            $member->edit(['is_supplier' => SupplierConstants::SupplierStatus_Active]);
            $this->cancelAfter();
            DB::commit();
            return $save;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 获取供应商基础信息
     * @return mixed
     * @throws \Exception
     */
    public function getCountInfo()
    {
        $model = $this->getMemberModel();
        // 判断供应商是否存在，禁用也需要查看数据
        $supplier = SupplierModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $model->id)
            ->first();
        if (!$supplier) {
            throw new \Exception('供应商不存在');
        }
        // 基础信息
        $data = [
            'id' => $model->id,
            'nickname' => $model->nickname,
            'name' => $model->name,
            'mobile' => $model->mobile,
            'level' => $model->level,
            'headurl' => $model->headurl,
            'agent_level' => $model->agent_level,
            'is_distributor' => $model->is_distributor,
            'dealer_level' => $model->dealer_level,
            'is_area_agent' => $model->is_area_agent,
            'is_supplier' => $model->is_supplier,
            'supplier_name' => $supplier->name
        ];

        $orderStatus = [
            LibsConstant::OrderStatus_OrderPay,
            LibsConstant::OrderStatus_OrderSend,
            LibsConstant::OrderStatus_OrderReceive,
            LibsConstant::OrderStatus_OrderSuccess,
            LibsConstant::OrderStatus_OrderFinished,
            LibsConstant::OrderStatus_OrderRefund
        ];
        // 订单统计
        $orderCount = OrderModel::query()->where('site_id', $model->site_id)
            ->whereIn('status', $orderStatus)
            ->where('supplier_member_id', $model->id)
            ->selectRaw('sum(money + after_sale_money) as total_money, count(id) as order_num')
            ->first();

        // 已结算金额
        $settled = FinanceModel::query()->where('site_id', $model->site_id)->where('member_id', $model->id)->where('type', Constants::FinanceType_Supplier)
            ->where('money', '>', 0)->sum('money');

        // 未结算金额
        $unsettled = SupplierSettleModel::query()->where('site_id', $model->site_id)->where('supplier_member_id', $model->id)
            ->where('status', 0)->sum(DB::raw('(money + freight + after_sale_money + after_sale_freight)'));

        // 提现中
        $withdrawing = FinanceModel::query()->where('site_id', $model->site_id)->where('member_id', $model->id)->where('type', Constants::FinanceType_Supplier)
            ->whereIn('out_type', [Constants::FinanceOutType_SupplierToBalance, Constants::FinanceOutType_Withdraw])->where('money', '<', 0)
            ->where('status', Constants::FinanceStatus_Freeze)->sum('money');

        // 已提现
        $withdrawn = FinanceModel::query()->where('site_id', $model->site_id)->where('member_id', $model->id)->where('type', Constants::FinanceType_Supplier)
            ->whereIn('out_type', [Constants::FinanceOutType_SupplierToBalance, Constants::FinanceOutType_Withdraw])->where('money', '<', 0)
            ->where('status', Constants::FinanceStatus_Active)->sum('money');

        // 可提现余额
        $balance = FinanceHelper::getSupplierBalance($model->id);

        $data['order_count'] = intval($orderCount->order_num);
        $data['order_money'] = moneyCent2Yuan($orderCount->total_money);
        $data['settled'] = moneyCent2Yuan($settled);
        $data['unsettled'] = moneyCent2Yuan($unsettled);
        $data['withdrawing'] = moneyCent2Yuan(abs($withdrawing));
        $data['withdrawn'] = moneyCent2Yuan(abs($withdrawn));
        $data['balance'] = moneyCent2Yuan($balance);
        return $data;
    }

    /**
     * 获取供应商信息
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public static function getSupplierInfo($memberId)
    {
        $supplier = SupplierModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->first();
        if (!$supplier) {
            throw new \Exception('供应商不存在');
        }
        return $supplier;
    }


    /**
     * 保存供应商信息
     * @param array $info 设置信息
     */
    public function saveBaseSetting(array $info)
    {
        $model = self::getSupplierInfo($this->memberId);
        $model->name = $info['name'];
        $model->refunds_contacts = $info['refunds_contacts'];
        $model->refunds_mobile = $info['refunds_mobile'];
        $model->refunds_description = $info['refunds_description'];
        $model->refunds_address = $info['refunds_address'];
        $model->save();
    }

}