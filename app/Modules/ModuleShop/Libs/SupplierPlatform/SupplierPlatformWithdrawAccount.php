<?php
/**
 * 供应商提现账号
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawConditionHelper;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;
use YZ\Core\Constants;


class SupplierPlatformWithdrawAccount
{
    protected $_memberId = 0;
    protected $_memberWithdrawAccount = null;

    public function __construct($memberId)
    {
        $this->_memberId = $memberId;
        $this->_memberWithdrawAccount = new MemberWithdrawAccount($memberId);
    }

    /**
     * 获取某个会员的提现账户
     * @return array
     */
    public function getInfo()
    {
        $supplierWithdraw = WithdrawConditionHelper::createInstance(Constants::FinanceType_Supplier);
        $data['supplier_withdraw_way_config'] = $supplierWithdraw->getWithdrawWayConfig();
        $data['member_withdraw_accout'] = $this->_memberWithdrawAccount->getInfo();
        return $data;
    }

    /**
     * 新增修改某个会员的提现账户
     * @param $param
     * @return $this|\LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     * @throws \Exception
     */
    public function edit($param)
    {
        foreach ($param as &$value) {
            if ($value == 'null') $value = Null;
        }
        return $this->_memberWithdrawAccount->edit($param);
    }


}