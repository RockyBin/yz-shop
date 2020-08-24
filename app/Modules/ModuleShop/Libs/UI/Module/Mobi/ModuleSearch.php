<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

/**
 * 搜索模块
 * Class ModuleSearch
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleSearch extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置样式
     * @param $style
     */
    public function setParam_Style($style){
        $this->_moduleInfo['param_style'] = $style;
    }

    /**
     * 获取样式
     * @return mixed
     */
    public function getParam_Style(){
        return $this->_moduleInfo['param_style'];
    }

    /**
     * 设置预设关键词
     * @param $keyword
     */
    public function setParam_Keyword($keyword){
        $this->_moduleInfo['param_keyword'] = $keyword;
    }

    /**
     * 获取预设关键词
     * @return mixed
     */
    public function getParam_Keyword(){
        return $this->_moduleInfo['param_keyword'];
    }

    /**
     * 设置预设关键词位置
     * @param $align
     */
    public function setParam_KeywordAlign($align){
        $this->_moduleInfo['param_keyword_align'] = $align;
    }

    /**
     * 获取预设关键词位置
     * @return mixed
     */
    public function getParam_KeywordAlign(){
        return $this->_moduleInfo['param_keyword_align'];
    }

    /**
     * 设置文字颜色
     * @param $color
     */
    public function setParam_FontColor($color){
        $this->_moduleInfo['param_font_color'] = $color;
    }

    /**
     * 获取文字颜色
     * @return mixed
     */
    public function getParam_FontColor(){
        return $this->_moduleInfo['param_font_color'];
    }

    /**
     * 设置图标颜色
     * @param $color
     */
    public function setParam_IconColor($color){
        $this->_moduleInfo['param_icon_color'] = $color;
    }

    /**
     * 获取图标颜色
     * @return mixed
     */
    public function getParam_IconColor(){
        return $this->_moduleInfo['param_icon_color'];
    }

    /**
     * 设置框体背景
     * @param $color
     */
    public function setParam_InputBackground($color){
        $this->_moduleInfo['param_input_background'] = $color;
    }

    /**
     * 获取框体背景
     * @return mixed
     */
    public function getParam_InputBackground(){
        return $this->_moduleInfo['param_input_background'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Style($info['style']);
        $this->setParam_Keyword($info['keyword']);
        $this->setParam_KeywordAlign($info['keyword_align']);
        $this->setParam_FontColor($info['font_color']);
        $this->setParam_IconColor($info['icon_color']);
        $this->setParam_InputBackground($info['input_background']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['style'] = $this->getParam_Style();
        $context['keyword'] = $this->getParam_Keyword();
        $context['keyword_align'] = $this->getParam_KeywordAlign();
        $context['font_color'] = $this->getParam_FontColor();
        $context['icon_color'] = $this->getParam_IconColor();
        $context['input_background'] = $this->getParam_InputBackground();
		return $this->renderAct($context);
    }
}
