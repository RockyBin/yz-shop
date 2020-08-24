<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use YZ\Core\Member\Auth;

/**
 * 优惠券模块
 * Class ModuleCoupon
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleCoupon extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置优惠券ID
     * @param array $ids
     */
    public function setParam_CouponIds($ids){
        $this->_moduleInfo['param_coupon_ids'] = $ids;
    }

    /**
     * 获取分类ID
     * @return mixed
     */
    public function getParam_CouponIds(){
        return $this->_moduleInfo['param_coupon_ids'];
    }

    /**
     * 设置颜色
     * @param $color
     */
    public function setParam_Color($color){
        $this->_moduleInfo['param_color'] = $color;
    }

    /**
     * 获取颜色
     * @return mixed
     */
    public function getParam_Color(){
        return $this->_moduleInfo['param_color'];
    }

    /**
     * 设置一屏显示个数
     * @param $color
     */
    public function setParam_Cols($num){
        $this->_moduleInfo['param_cols'] = $num;
    }

    /**
     * 获取一屏显示个数
     * @return mixed
     */
    public function getParam_Cols(){
        return $this->_moduleInfo['param_cols'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Color($info['color']);
        $this->setParam_Cols($info['cols']);
        $this->setParam_CouponIds($info['coupon_ids']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['layout'] = $this->getLayout();
        $context['color'] = $this->getParam_Color();
        $context['cols'] = $this->getParam_Cols();
        $context['coupon_ids'] = $this->getParam_CouponIds();
        $context['coupon_list'] = [];
        if($context['coupon_ids']){
            $couponIds = is_array($context['coupon_ids']) ? $context['coupon_ids'] : implode(",",$context['coupon_ids']);
            $params = ['ids' => $couponIds,'order_by_raw' => "FIELD(id,".implode(",",$context['coupon_ids']).")",'valid' => 1];
            $memberId = Auth::hasLogin();
            if($memberId) $params['member_id'] = $memberId;
            $list = (new Coupon())->getList($params);
            $context['coupon_list'] = $list['list'];
        }

		return $this->renderAct($context);
    }
}
