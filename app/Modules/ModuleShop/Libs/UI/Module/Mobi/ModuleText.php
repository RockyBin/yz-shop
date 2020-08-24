<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

/**
 * 文本模块
 * Class ModuleText
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleText extends BaseMobiModule
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
    public function setParam_Text($text){
	    $this->_moduleInfo['param_text'] = $text;
    }

    /**
     * 获取文本内容
     * @return mixed
     */
    public function getParam_Text(){
	    return $this->_moduleInfo['param_text'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Text($info['text']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['text'] = $this->getParam_Text();
		return $this->renderAct($context);
    }
}
