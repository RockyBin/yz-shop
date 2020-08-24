<?php

namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;

use App\Modules\ModuleShop\Libs\Model\WithdrawConfigModel;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;

/**
 * 余额提现
 * Class BalanceWithdraw
 */
class BalanceWithdraw extends AbstractWithdraw
{
    private $_model = null;
    private $_siteId = 0;

    /**
     * 初始化代理设置对象
     */
    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_model = WithdrawConfigModel::where(['site_id' => $this->_siteId])->first();
        if (!$this->_model) {
            $this->_model = new WithdrawConfigModel();
            $this->_model->site_id = $this->_siteId;
            $this->_model->balance_type = '{"wxpay":0,"alipay":0,"wx_qrcode":0,"alipay_qrcode":0,"alipay_account":0,"bank_account":0}';
            $this->_model->withdraw_date = '{"date":0}'; // 提现时间 默认任意时间
            $this->_model->save();
            $this->_model = WithdrawConfigModel::where(['site_id' => $this->_siteId])->first();
        }
    }

    /**
     * 获取可提现的余额
     * @param $financeType 财务类型
     * @param $memberId 会员ID
     * @return mixed
     */
    public function getAvailableBalance($financeType, $memberId){
        return FinanceHelper::getMemberBalance($memberId,$financeType);
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 WithdrawConfigModel 的字段信息
     */
    public function editConfig(array $info)
    {
        $this->_model->fill($info);
        $this->_model->save();
    }

    /**
     * 获取指定网站的提现设置
     * @return mixed
     */
    public function getConfig()
    {
        $data = $this->_model->toArray();
        $data = $this->getParsedConfig($data);
        return $data;
    }

    /**
     * 获取提现方式的配置，直接读取相应字段，不进行检测
     * @return array
     */
    public function getWithdrawWayConfig()
    {
        $info = json_decode($this->_model->balance_type, true);
        return $info;
    }
}