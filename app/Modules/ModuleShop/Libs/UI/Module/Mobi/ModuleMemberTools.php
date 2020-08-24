<?php
/**
 * 会员中心必备工具模块
 * User: liyaohui
 * Date: 2019/11/18
 * Time: 15:04
 */

namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use YZ\Core\Site\Site;

class ModuleMemberTools extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    /**
     * 获取我的粉丝是否显示
     * @return mixed
     */
    public function getParam_MyFansIsShow()
    {
        if (array_key_exists('param_my_fans_is_show', $this->_moduleInfo)) return $this->_moduleInfo['param_my_fans_is_show'];
        return 1;
    }

    /**
     * 设置我的粉丝是否显示
     * @param $show
     */
    public function setParam_MyFansIsShow($show)
    {
        $this->_moduleInfo['param_my_fans_is_show'] = $show;
    }


    /**
     * 获取领券中心是否显示
     * @return mixed
     */
    public function getParam_CouponCenterIsShow()
    {
        if (array_key_exists('param_coupon_center_is_show', $this->_moduleInfo)) return $this->_moduleInfo['param_coupon_center_is_show'];
        return 1;
    }

    /**
     * 设置领券中心是否显示
     * @param $show
     */
    public function setParam_CouponCenterIsShow($show)
    {
        $this->_moduleInfo['param_coupon_center_is_show'] = $show;
    }


    /**
     * 获取收藏夹是否显示
     * @return mixed
     */
    public function getParam_MemberCollectionIsShow()
    {
        return $this->_moduleInfo['param_member_collection_is_show'];
    }

    /**
     * 设置收藏夹是否显示
     * @param $show
     */
    public function setParam_MemberCollectionIsShow($show)
    {
        $this->_moduleInfo['param_member_collection_is_show'] = $show;
    }

    /**
     * 获取我的足迹是否显示
     * @return mixed
     */
    public function getParam_MyBrowseRecordIsShow()
    {
        return $this->_moduleInfo['param_my_browse_record_is_show'];
    }

    /**
     * 设置我的足迹是否显示
     * @param $show
     */
    public function setParam_MyBrowseRecordIsShow($show)
    {
        $this->_moduleInfo['param_my_browse_record_is_show'] = $show;
    }

    /**
     * 获取地址管理是否显示
     * @return mixed
     */
    public function getParam_MyAddressIsShow()
    {
        return $this->_moduleInfo['param_my_address_is_show'];
    }

    /**
     * 设置地址管理是否显示
     * @param $show
     */
    public function setParam_MyAddressIsShow($show)
    {
        $this->_moduleInfo['param_my_address_is_show'] = $show;
    }

    /**
     * 获取评价中心是否显示
     * @return mixed
     */
    public function getParam_CommentCenterIsShow()
    {
        return $this->_moduleInfo['param_comment_center_is_show'];
    }

    /**
     * 设置评价中心是否显示
     * @param $show
     */
    public function setParam_CommentCenterIsShow($show)
    {
        $this->_moduleInfo['param_comment_center_is_show'] = $show;
    }

    /**
     * 获取分销中心是否显示
     * @return mixed
     */
    public function getParam_DistributionIsShow()
    {
        return $this->_moduleInfo['param_distribution_is_show'];
    }

    /**
     * 设置分销中心是否显示
     * @param $show
     */
    public function setParam_DistributionIsShow($show)
    {
        $this->_moduleInfo['param_distribution_is_show'] = $show;
    }


    /**
     * 获取区域中心是否显示
     * @return mixed
     */
    public function getParam_AreaAgentIsShow()
    {
        if (array_key_exists('param_area_agent_is_show', $this->_moduleInfo)) {
            return $this->_moduleInfo['param_area_agent_is_show'];
        } elseif (Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT)) {
            return 1;
        }
    }

    /**
     * 设置分销中心是否显示
     * @param $show
     */
    public function setParam_AreaAgentIsShow($show)
    {
        $this->_moduleInfo['param_area_agent_is_show'] = $show;
    }


    /**
     * 获取代理分红是否显示
     * @return mixed
     */
    public function getParam_AgentIsShow()
    {
        return $this->_moduleInfo['param_agent_is_show'];
    }

    /**
     * 设置代理分红是否显示
     * @param $show
     */
    public function setParam_AgentIsShow($show)
    {
        $this->_moduleInfo['param_agent_is_show'] = $show;
    }

    /**
     * 获取经销商中心是否显示
     * @return mixed
     */
    public function getParam_DealerIsShow()
    {
        if (array_key_exists('param_dealer_is_show', $this->_moduleInfo)) {
            return $this->_moduleInfo['param_dealer_is_show'];
        } elseif (Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)) {
            return true;
        }
        return false;
    }

    /**
     * 设置经销商中心是否显示
     * @param $show
     */
    public function setParam_DealerIsShow($show)
    {
        $this->_moduleInfo['param_dealer_is_show'] = $show;
    }

    /**
     * 获取防伪查询是否显示
     * @return mixed
     */
    public function getParam_SecurityCodeIsShow()
    {
        return $this->_moduleInfo['param_security_code_is_show'];
    }

    /**
     * 设置防伪查询是否显示
     * @param $show
     */
    public function setParam_SecurityCodeIsShow($show)
    {
        $this->_moduleInfo['param_security_code_is_show'] = $show;
    }

    /**
     * 获取购物车是否显示
     * @return mixed
     */
    public function getParam_ShopCartIsShow()
    {
        return $this->_moduleInfo['param_shop_cart_is_show'];
    }

    /**
     * 设置购物车是否显示
     * @param $show
     */
    public function setParam_ShopCartIsShow($show)
    {
        $this->_moduleInfo['param_shop_cart_is_show'] = $show;
    }

    /**
     * 获取个人设置是否显示
     * @return mixed
     */
    public function getParam_MemberSettingIsShow()
    {
        return $this->_moduleInfo['param_member_setting_is_show'];
    }

    /**
     * 设置个人设置是否显示
     * @param $show
     */
    public function setParam_MemberSettingShow($show)
    {
        $this->_moduleInfo['param_member_setting_is_show'] = $show;
    }

    /**
     * 获取修改信息是否显示
     * @return mixed
     */
    public function getParam_MemberModifyIsShow()
    {
        return $this->_moduleInfo['param_member_modify_is_show'];
    }

    /**
     * 设置修改信息是否显示
     * @param $show
     */
    public function setParam_MemberModifyShow($show)
    {
        $this->_moduleInfo['param_member_modify_is_show'] = $show;
    }

    /**
     * 获取客服电话和二维码是否显示
     * @return mixed
     */
    public function getParam_ServicePhoneIsShow()
    {
        return $this->_moduleInfo['param_service_phone_is_show'];
    }

    /**
     * 设置客服电话和二维码是否显示
     * @param $show
     */
    public function setParam_ServicePhoneShow($show)
    {
        $this->_moduleInfo['param_service_phone_is_show'] = $show;
    }

    public function getParam_ShowSortList()
    {
        $hasCloudStock = Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK);
        $hasAreaAgent = Site::getCurrentSite()->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT);
        $list = $this->_moduleInfo['param_sort_list'];
        if (!array_search('dealer_is_show', $list) && $hasCloudStock) {
            $list[] = 'dealer_is_show';
        }
        if (!array_search('area_agent_is_show', $list) && $hasAreaAgent) {
            $list[] = 'area_agent_is_show';
        }
        //领券中心口是新加的,如果原来没有设置过,就默认显示
        if (!array_search('coupon_center_is_show', $list)) {
            array_unshift($list, 'coupon_center_is_show');
        }
        //我的粉丝入口是新加的,如果原来没有设置过,就默认显示
        if (!array_search('my_fans_is_show', $list)) {
            array_unshift($list, 'my_fans_is_show');
        }
        return $list;
    }

    public function setParam_ShowSortList($list)
    {
        $this->_moduleInfo['param_sort_list'] = $list;
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info)
    {
        $this->setParam_MyFansIsShow($info['my_fans_is_show']);
        $this->setParam_CouponCenterIsShow($info['coupon_center_is_show']);
        $this->setParam_MemberCollectionIsShow($info['member_collection_is_show']);
        $this->setParam_MyBrowseRecordIsShow($info['my_browse_record_is_show']);
        $this->setParam_MyAddressIsShow($info['my_address_is_show']);
        $this->setParam_CommentCenterIsShow($info['comment_center_is_show']);
        $this->setParam_DistributionIsShow($info['distribution_is_show']);
        $this->setParam_AgentIsShow($info['agent_is_show']);
        $this->setParam_AreaAgentIsShow($info['area_agent_is_show']);
        $this->setParam_DealerIsShow($info['dealer_is_show']);
        $this->setParam_SecurityCodeIsShow($info['security_code_is_show']);
        $this->setParam_ShopCartIsShow($info['shop_cart_is_show']);
        $this->setParam_MemberSettingShow($info['member_setting_is_show']);
        $this->setParam_MemberModifyShow($info['member_modify_is_show']);
        $this->setParam_ServicePhoneShow($info['service_phone_is_show']);
        $this->setParam_ShowSortList($info['sort_list']);
        parent::update($info);
    }

    /**
     * 渲染模块
     * @return array
     */
    public function render()
    {
        $context = [];
        $context['my_fans_is_show'] = $this->getParam_MyFansIsShow();
        $context['coupon_center_is_show'] = $this->getParam_CouponCenterIsShow();
        $context['member_collection_is_show'] = $this->getParam_MemberCollectionIsShow();
        $context['my_browse_record_is_show'] = $this->getParam_MyBrowseRecordIsShow();
        $context['my_address_is_show'] = $this->getParam_MyAddressIsShow();
        $context['comment_center_is_show'] = $this->getParam_CommentCenterIsShow();
        $context['distribution_is_show'] = $this->getParam_DistributionIsShow();
        $context['agent_is_show'] = $this->getParam_AgentIsShow();
        $context['area_agent_is_show'] = $this->getParam_AreaAgentIsShow();
        $context['dealer_is_show'] = $this->getParam_DealerIsShow();
        $context['security_code_is_show'] = $this->getParam_SecurityCodeIsShow();
        $context['shop_cart_is_show'] = $this->getParam_ShopCartIsShow();
        $context['member_setting_is_show'] = $this->getParam_MemberSettingIsShow();
        $context['member_modify_is_show'] = $this->getParam_MemberModifyIsShow();
        $context['service_phone_is_show'] = $this->getParam_ServicePhoneIsShow();
        if ($context['service_phone_is_show']) {
            $storeConfig = new StoreConfig();
            $storeConfigData = $storeConfig->getInfo();
            $context['service_phone'] = $storeConfigData['data']['custom_mobile'];
            $context['qrcode'] = $storeConfigData['data']['qrcode'];
        }
        $context['sort_list'] = $this->getParam_ShowSortList();
        return $this->renderAct($context);
    }
}