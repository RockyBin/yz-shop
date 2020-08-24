<?php
/**
 * 会员地址业务类
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Libs\Model\MemberWithdrawAccountModel;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\FileUpload\FileUpload as FileUpload;

class MemberWithdrawAccount
{
    protected $_memberId = 0;

    public function __construct($memberId)
    {
        $this->_memberId = $memberId;
    }

    /**
     * 获取某个会员的提现账户
     * @return array
     */
    public function getInfo()
    {
        $info = MemberWithdrawAccountModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->first();

        return $info;
    }

    /**
     * 新增修改某个会员的提现账户
     * @param $param
     * @return $this|\LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     * @throws \Exception
     */
    public function edit($param)
    {
        if ($param['wx_qrcode']) {
            $wx_qrcode_filename = 'wx_qrcode' . time();
            $wx_qrcode_filepath = Site::getSiteComdataDir('', true) . '/withdrawAccount/wx_qrcode/';
            $wx_qrcode_upload = new FileUpload($param['wx_qrcode'], $wx_qrcode_filepath, $wx_qrcode_filename);
            $wx_qrcode_upload->reduceImageSize(1500);
            $param['wx_qrcode'] = '/withdrawAccount/wx_qrcode/' . $wx_qrcode_upload->getFullFileName();
        }
        if ($param['alipay_qrcode']) {
            $alipay_qrcode_filename = 'alipay_qrcode' . time();
            $alipay_qrcode_filepath = Site::getSiteComdataDir('', true) . '/withdrawAccount/alipay_qrcode/';
            $alipay_qrcode_upload = new FileUpload($param['alipay_qrcode'], $alipay_qrcode_filepath, $alipay_qrcode_filename);
            $alipay_qrcode_upload->reduceImageSize(1500);
            $param['alipay_qrcode'] = '/withdrawAccount/alipay_qrcode/' . $alipay_qrcode_upload->getFullFileName();
        }
        if ($param['code']) unset($param['code']);
        if ($param['is_delete']) unset($param['is_delete']);
        if ($param['id']) {
            $model = MemberWithdrawAccountModel::query()->where(['id' => $param['id'],'member_id' => $this->_memberId])->first();
            $model = $model->fill($param);
        } else {
            $model = (new MemberWithdrawAccountModel())->fill($param);
            $model->site_id = Site::getCurrentSite()->getSiteId();
            $model->member_id = $this->_memberId;
        }
        $model->save();
        return $model;
    }

    /**
     * 根据提现方式获取用户相关提现账户信息
     * @param $param $type
     * @return $this|\LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     * @throws \Exception
     */
    public function getMemberWithdrawAccount($type)
    {
        $memberWithdrawAllInfo = $this->getInfo();
        $info = [];
        /**
         * @param $account 收款帐号 如果是支付宝、微信等二维码收款，请传入收款二维码的图片地址，如果是银行收款，传入银行帐号；如果是支付宝转帐，传入支付宝帐号
         * @param $bank 如果是银行收款，传入银行名称，其它先置空
         * @param $accountName 如果是银行收款，传入银行户名，如果是支付宝账户，就传入支付宝账户姓名其它先置空
         * */
        switch (true) {
            case $type == Constants::PayType_WeixinQrcode:
                $info['wx_qrcode'] = $memberWithdrawAllInfo->wx_qrcode;
                break;
            case $type == Constants::PayType_AlipayQrcode:
                $info['alipay_qrcode'] = $memberWithdrawAllInfo->alipay_qrcode;
                break;
            case $type == Constants::PayType_AlipayAccount:
                $info['alipay_account'] = $memberWithdrawAllInfo->alipay_account;
                $info['alipay_name'] = $memberWithdrawAllInfo->alipay_name;
                break;
            case $type == Constants::PayType_Bank:
                $info['bank_account'] = $memberWithdrawAllInfo->bank_account;
                $info['bank'] = $memberWithdrawAllInfo->bank;
				$info['bank_branch'] = $memberWithdrawAllInfo->bank_branch;
                $info['bank_card_name'] = $memberWithdrawAllInfo->bank_card_name;
                break;
        }
        return $info;
    }


}