<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Wx\WxSubscribeSetting;
use YZ\Core\Member\Auth;
use YZ\Core\Model\ConfigModel;

/**
 * 会员中心首页引导关注模块
 * Class ModuleWxSubscribe
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleWxSubscribe extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        parent::update($info);
    }

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
