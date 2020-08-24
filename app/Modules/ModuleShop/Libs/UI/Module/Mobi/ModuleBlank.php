<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

/**
 * 辅助空白模块
 * Class ModuleBlank
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleBlank extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置文本内容
     * @param $text
     */
    public function setParam_Height($text){
	    $this->_moduleInfo['param_height'] = $text;
    }

    /**
     * 获取文本内容
     * @return mixed
     */
    public function getParam_Height(){
	    return $this->_moduleInfo['param_height'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Height($info['height']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['height'] = $this->getParam_Height();
		return $this->renderAct($context);
    }
}
