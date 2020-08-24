<?php

namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSettingModel;
use YZ\Core\Member\Auth;

/**
 * 团购模块
 * Class ModuleGroupBuying
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleGroupBuying extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置产品ID
     * @param array $ids
     */
    public function setParam_ProductIds($ids)
    {
        $this->_moduleInfo['param_product_ids'] = $ids;
    }

    /**
     * 获取产品ID
     * @return mixed
     */
    public function getParam_ProductIds()
    {
        return $this->_moduleInfo['param_product_ids'];
    }

    /**
     * 设置活动ID
     * @param array $ids
     */
    public function setParam_GroupBuyingSettingId($ids)
    {
        $this->_moduleInfo['param_groupbuying_setting_id'] = $ids;
    }

    /**
     * 获取活动ID
     * @return mixed
     */
    public function getParam_GroupBuyingSettingId()
    {
        return $this->_moduleInfo['param_groupbuying_setting_id'];
    }


    /**
     * 设置商品间距
     * @param $margin
     */
    public function setParam_ProductMargin($margin)
    {
        $this->_moduleInfo['param_product_margin'] = $margin;
    }

    /**
     * 获取商品间距
     * @return mixed
     */
    public function getParam_ProductMargin()
    {
        return $this->_moduleInfo['param_product_margin'];
    }

    /**
     * 设置商品边框样式
     * @param $style ,0 = 直角，1 = 圆角
     */
    public function setParam_BorderStyle($style)
    {
        $this->_moduleInfo['param_border_style'] = $style;
    }

    /**
     * 获取商品边框样式
     * @return mixed
     */
    public function getParam_BorderStyle()
    {
        return $this->_moduleInfo['param_border_style'];
    }

    /**
     * 设置商品样式
     * @param $style 0 = 无边白底 1 = 卡片投影 2 = 描边白底
     */
    public function setParam_ProductStyle($style)
    {
        $this->_moduleInfo['param_product_style'] = $style;
    }

    /**
     * 获取商品样式
     * @return mixed
     */
    public function getParam_ProductStyle()
    {
        return $this->_moduleInfo['param_product_style'];
    }


    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info)
    {
        $this->setParam_ProductStyle($info['product_style']);
        $this->setParam_ProductIds($info['product_ids']);
        $this->setParam_GroupBuyingSettingId($info['groupbuying_setting_id']);
        $this->setParam_BorderStyle($info['border_style']);
        $this->setParam_ProductMargin($info['product_margin']);
        parent::update($info);
    }

    /**
     * 渲染模块
     */
    public function render()
    {
        $context = [];
        $context['layout'] = $this->getLayout();
        $context['padding_left_right'] = $this->getPaddingLeftRight();
        $context['product_ids'] = $this->getParam_ProductIds();
        $context['groupbuying_setting_id'] = $this->getParam_GroupBuyingSettingId();
        $context['product_style'] = $this->getParam_ProductStyle();
        $context['border_style'] = $this->getParam_BorderStyle();
        $context['product_margin'] = $this->getParam_ProductMargin();
        if ($context['groupbuying_setting_id']) {
            $groupbuyingSetting = GroupBuyingSettingModel::query()
                ->where('is_delete', 0)
                ->where('id', $context['groupbuying_setting_id'])
                ->first();
        }
        $context['groupbuying_name'] = $groupbuyingSetting->title ? $groupbuyingSetting->title : '';
        $context['product_list'] = [];
        if ($context['product_ids']) {
            $params = ['groupbuying_setting_id' => $context['groupbuying_setting_id'], 'id' => $context['product_ids']];
            $memberId = Auth::hasLogin();
            if ($memberId) $params['member_id'] = $memberId;
            $params['page'] = 1;
            $params['page_size'] = 1000;
            $params['order_by'] = true;
            $params['is_delete'] = 0;
            $list = GroupBuyingProducts::getFrontList($params);
            $context['product_list'] = $list['list'];
        }

        return $this->renderAct($context);
    }
}
