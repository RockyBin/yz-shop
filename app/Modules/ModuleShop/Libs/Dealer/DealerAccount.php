<?php

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerAccountModel;
use YZ\Core\Constants;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\FileUpload\FileUpload as FileUpload;

class DealerAccount
{
    protected $_memberId = 0;

    public function __construct($memberId)
    {
        $this->_memberId = $memberId;
    }

    /**
     * 获取某个会员的提现账户
     */
    public function getList()
    {
        $list = DealerAccountModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->get();

        return $list;
    }

    /**
     * 新增修改某个会员的提现账户
     * @param $param
     * @return $this|\LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     * @throws \Exception
     */
    public function edit($param)
    {
        if (intval($param['type']) === Constants::PayType_WeixinQrcode) {
            $wx_qrcode_filename = 'member_' . $this->_memberId . '_wx_qrcode' . time();
            $wx_qrcode_filepath = Site::getSiteComdataDir('', true) . '/dealerAccount/wx_qrcode/';
            $wx_qrcode_upload = new FileUpload($param['wx_qrcode'], $wx_qrcode_filepath, $wx_qrcode_filename);
            $wx_qrcode_upload->reduceImageSize(800);
            $param['account'] = '/dealerAccount/wx_qrcode/' . $wx_qrcode_upload->getFullFileName();
            $param['account_name'] = '微信收款码';
            $param['bank'] = '线下结算-微信';
        }
        if (intval($param['type']) === Constants::PayType_AlipayQrcode) {
            $alipay_qrcode_filename = 'member_' . $this->_memberId . '_alipay_qrcode' . time();
            $alipay_qrcode_filepath = Site::getSiteComdataDir('', true) . '/dealerAccount/alipay_qrcode/';
            $alipay_qrcode_upload = new FileUpload($param['alipay_qrcode'], $alipay_qrcode_filepath, $alipay_qrcode_filename);
            $alipay_qrcode_upload->reduceImageSize(800);
            $param['account'] = '/dealerAccount/alipay_qrcode/' . $alipay_qrcode_upload->getFullFileName();
            $param['account_name'] = '支付宝收款码';
            $param['bank'] = '线下结算-支付宝';
        }
        if ($param['id']) {
            $model = DealerAccountModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $this->_memberId)
                ->where('id', $param['id'])
                ->first();
            $model = $model->fill($param);
        } else {
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['member_id'] = $this->_memberId;
            $model = (new DealerAccountModel())->fill($param);
        }
        $model->save();
        return $model;
    }

    /**
     * 删除某个收款信息
     * @param $id
     * @throws \Exception
     */
    public function delete($id)
    {
        $model = DealerAccountModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->where('id', $id)
            ->first();
        $model->delete();
    }

    /**
     * 根据支付方式获取经销商相关账户信息
     * @param $param $type
     */
    public function getAccount($type)
    {
        $info = DealerAccountModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->where('type', $type)
            ->first();
        return $info;
    }

    /**
     * 获取指定经销商的收款方式配置
     * @param $memberId
     * @param  是否用于经销商充值
     * @return array
     */
    public static function getDealerPayConfig($memberId,$recharge = false )
    {
        $account = new DealerAccount($memberId);
        $list = $account->getList();
        $config = [
            'types' => [],
            //以下几个参数是为了与平台的收款方式的输出格式保持一致
            'wx_qrcode' => '',
            'alipay_qrcode' => '',
            'alipay_account' => '',
            'alipay_name' => '',
            'bank_card_name' => '',
            'bank_account' => '',
            'bank' => '',
            'member_id' => $memberId
        ];
        $dealerBaseSetting = DealerBaseSetting::getCurrentSiteSetting();
        if ($dealerBaseSetting->pay_parent_type == 0 && $dealerBaseSetting->purchases_money_target == 1 && !$recharge) {
            $config['types'][] = [
                'type' => \YZ\Core\Constants::PayType_Balance,
                'text' => "余额",
                'group' => "online",
                'account' => 'balance'
            ];
        } else {
            foreach ($list as $item) {
                if ($item->type == Constants::PayType_WeixinQrcode) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_WeixinQrcode,
                        'text' => "线下结算-微信",
                        'group' => "offline",
                        'account' => $item->account
                    ];
                    $config['wx_qrcode'] = $item->account;
                }
                if ($item->type == Constants::PayType_AlipayQrcode) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayQrcode,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $item->account
                    ];
                    $config['alipay_qrcode'] = $item->account;
                }
                if ($item->type == Constants::PayType_AlipayAccount) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_AlipayAccount,
                        'text' => "线下结算-支付宝",
                        'group' => "offline",
                        'account' => $item->account,
                        'account_name' => $item->account_name
                    ];
                    $config['alipay_account'] = $item->account;
                    $config['alipay_name'] = $item->account_name;
                }
                if ($item->type == Constants::PayType_Bank) {
                    $config['types'][] = [
                        'type' => \YZ\Core\Constants::PayType_Bank,
                        'text' => "线下结算-银行账户",
                        'group' => "offline",
                        'account' => $item->account,
                        'account_name' => $item->account_name,
                        'bank' => $item->bank
                    ];
                    $config['bank_account'] = $item->account;
                    $config['bank_card_name'] = $item->account_name;
                    $config['bank'] = $item->bank;
                }
            }
        }

        return $config;
    }

    /**
     * 申请经销商时，获取某会员的上级管理经销商的支付方式
     * @param $memberId 申请的经销商的会员ID
     * @param $level 申请的经销商等级
     */
    public static function getParentPayConfigForApply($memberId, $level)
    {
        $parents = DealerHelper::findDealerParent($memberId, $level);
        $parentMemberId = $parents['manage_parent'] ? $parents['manage_parent']->id : 0;
        return static::getDealerPayConfig($parentMemberId);
    }

    /**
     * 获取当前会员的上家经销商的收款方式配置
     * @param $memberId 会员ID
     * @return array
     */
    public static function getParentPayConfig($memberId)
    {
        $parents = DealerHelper::getParentDealers($memberId, false);
        $parentMemberId = is_array($parents['normal']) && count($parents['normal']) ? $parents['normal'][0]['id'] : 0;
        return static::getDealerPayConfig($parentMemberId);
    }
}