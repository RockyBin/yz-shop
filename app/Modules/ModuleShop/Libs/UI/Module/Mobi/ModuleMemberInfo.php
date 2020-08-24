<?php
/**
 * 会员信息模块
 * User: liyaohui
 * Date: 2019/11/16
 * Time: 10:54
 */

namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;


class ModuleMemberInfo extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    /**
     * 获取邀请码是否显示在会员中心
     * @return mixed
     */
    public function getParam_InvitationCodeIsShow()
    {
        return $this->_moduleInfo['param_invitation_code_is_show'];
    }

    /**
     * 设置邀请码是否显示在会员中心
     * @param $show
     */
    public function setParam_InvitationCodeIsShow($show)
    {
        $this->_moduleInfo['param_invitation_code_is_show'] = $show;
    }

    /**
     * 获取会员等级是否显示
     * @return mixed
     */
    public function getParam_MemberLevelIsShow()
    {
        return $this->_moduleInfo['param_member_level_is_show'];
    }

    /**
     * 设置会员等级是否显示
     * @param $show
     */
    public function setParam_MemberLevelIsShow($show)
    {
        $this->_moduleInfo['param_member_level_is_show'] = $show;
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_InvitationCodeIsShow($info['invitation_code_is_show']);
        $this->setParam_MemberLevelIsShow($info['member_level_is_show']);
        parent::update($info);
    }

    /**
     * 渲染模块
     * @return array
     */
    public function render()
    {
        $context = [];
        $context['invitation_code_is_show'] = $this->getParam_InvitationCodeIsShow();
        $context['member_level_is_show'] = $this->getParam_MemberLevelIsShow();
        return $this->renderAct($context);
    }
}
