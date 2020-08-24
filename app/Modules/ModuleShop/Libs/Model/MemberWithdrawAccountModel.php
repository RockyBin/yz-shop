<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 会员等级模块
 * Class MemberLevelModel
 * @package App\Modules\Model
 */
class MemberWithdrawAccountModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_member_withdraw_account';
    protected $fillable = [
        'wx_qrcode',
        'alipay_qrcode',
        'alipay_account',
        'alipay_name',
        'bank_card_name',
        'bank',
		'bank_branch',
        'bank_account'
    ];

}