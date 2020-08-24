<?php
/**
 * 经销商奖金接口
 * User: liyaohui
 * Date: 2020/1/6
 * Time: 15:09
 */

namespace App\Modules\ModuleShop\Libs\Dealer;


interface IDealerReward
{
    /**
     * 申请兑换
     * @return mixed
     */
    public function exchange();

    /**
     * 审核通过
     * @return mixed
     */
    public function pass();

    /**
     * 拒绝申请
     * @param string $reason    拒绝原因
     * @return mixed
     */
    public function reject($reason = '');

    /**
     * 获取当前奖金模型
     * @return mixed
     */
    public function getModel();
}