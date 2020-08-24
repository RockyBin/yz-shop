<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

/**
 * 间隔线模块
 * Class ModuleLine
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleLine extends BaseMobiModule
{
    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置线条类型,0=实线,1=虚线,2=点线
     * @param int $lineType
     */
    public function setParam_Type($lineType){
	    $this->_moduleInfo['param_type'] = intval($lineType);
    }

    /**
     * 获取线条类型
     * @return int
     */
    public function getParam_Type(){
	    return intval($this->_moduleInfo['param_type']);
    }

    /**
     * 设置线粗
     * @param int $width
     */
    public function setParam_Width($width){
	    $this->_moduleInfo['param_width'] = intval($width);
    }

    /**
     * 获取线高度
     * @return int
     */
    public function getParam_Width(){
	    return intval($this->_moduleInfo['param_width']);
    }

    /**
     * 设置线条颜色
     * @param string $color
     */
    public function setParam_Color($color){
        $this->_moduleInfo['param_color'] = $color;
    }

    /**
     * 获取线条颜色
     * @return string
     */
    public function getParam_Color(){
        return $this->_moduleInfo['param_color'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Color($info['color']);
        $this->setParam_Width($info['width']);
        $this->setParam_Type($info['type']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['type'] = $this->getParam_Type();
        $context['width'] = $this->getParam_Width();
        $context['color'] = $this->getParam_Color();
		return $this->renderAct($context);
    }
}
