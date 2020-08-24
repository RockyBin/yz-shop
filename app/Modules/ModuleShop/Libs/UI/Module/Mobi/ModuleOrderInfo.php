<?php
/**
 * 订单信息模块
 * User: liyaohui
 * Date: 2019/11/16
 * Time: 10:54
 */

namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

class ModuleOrderInfo extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    /**
     * 渲染模块
     * @return array
     */
    public function render()
    {
        $context = [];
        return $this->renderAct($context);
    }
}
