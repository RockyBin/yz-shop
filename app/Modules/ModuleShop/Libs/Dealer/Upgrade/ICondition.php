<?php
/**
 * 升级条件接口
 * User: liyaohui
 * Date: 2019/11/30
 * Time: 11:34
 */

namespace App\Modules\ModuleShop\Libs\Dealer\Upgrade;


interface ICondition
{
    /**
     * 获取升级条件的文案
     * @return mixed
     */
    public function getNameText();

    /**
     * 判断某经销商是否满足此条件
     * @param int $memberId 经销商会员id
     * @param array $params 额外的参数
     */
    public function canUpgrade($memberId, $params = []);
}