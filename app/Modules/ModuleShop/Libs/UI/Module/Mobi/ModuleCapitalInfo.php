<?php
/**
 * 会员资金信息模块
 * User: liyaohui
 * Date: 2019/11/16
 * Time: 10:54
 */

namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;


use YZ\Core\Model\ConfigModel;

class ModuleCapitalInfo extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    /**
     * 获取我的余额是否显示
     * @return mixed
     */
    public function getParam_MyBalanceIsShow()
    {
        return $this->_moduleInfo['param_my_balance_is_show'];
    }

    /**
     * 设置我的余额是否显示
     * @param $show
     */
    public function setParam_MyBalanceIsShow($show)
    {
        $this->_moduleInfo['param_my_balance_is_show'] = $show;
    }

    /**
     * 获取我的积分是否显示
     * @return mixed
     */
    public function getParam_MyPointIsShow()
    {
        return $this->_moduleInfo['param_my_point_is_show'];
    }

    /**
     * 设置我的积分是否显示
     * @param $show
     */
    public function setParam_MyPointIsShow($show)
    {
        $this->_moduleInfo['param_my_point_is_show'] = $show;
    }

    /**
     * 获取我的优惠券是否显示
     * @return mixed
     */
    public function getParam_MyCouponIsShow()
    {
        return $this->_moduleInfo['param_my_coupon_is_show'];
    }

    /**
     * 设置我的优惠券是否显示
     * @param $show
     */
    public function setParam_MyCouponIsShow($show)
    {
        $this->_moduleInfo['param_my_coupon_is_show'] = $show;
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_MyBalanceIsShow($info['my_balance_is_show']);
        $this->setParam_MyPointIsShow($info['my_point_is_show']);
        $this->setParam_MyCouponIsShow($info['my_coupon_is_show']);
        parent::update($info);
    }

    /**
     * 渲染模块
     * @return array
     */
    public function render()
    {
        $context = [];
        $context['my_balance_is_show'] = $this->getParam_MyBalanceIsShow();
        $context['my_point_is_show'] = $this->getParam_MyPointIsShow();
        $context['my_coupon_is_show'] = $this->getParam_MyCouponIsShow();
        return $this->renderAct($context);
    }
}
