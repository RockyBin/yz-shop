<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;

/**
 * 广告图片模块
 * Class ModuleSlider
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleSlider extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置图片样式 1=常规，2=卡片投影
     * @param $style
     */
    public function setParam_ImageStyle($style){
        $this->_moduleInfo['param_image_style'] = $style;
    }

    /**
     * 获取图片样式
     * @return mixed
     */
    public function getParam_ImageStyle(){
        return $this->_moduleInfo['param_image_style'];
    }

    /**
     * 设置边框样式 1=直角，2=圆角
     * @param $style
     */
    public function setParam_BorderStyle($style){
        $this->_moduleInfo['param_border_style'] = $style;
    }

    /**
     * 获取边框样式
     * @return mixed
     */
    public function getParam_BorderStyle(){
        return $this->_moduleInfo['param_border_style'];
    }

    /**
     * 设置自动轮播时间
     * @param $seconds
     */
    public function setParam_AutoPlay($seconds){
        $this->_moduleInfo['param_auto_play'] = $seconds;
    }

    /**
     * 获取自动轮播时间
     * @return mixed
     */
    public function getParam_AutoPlay(){
        return $this->_moduleInfo['param_auto_play'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
	    $this->setParam_ImageStyle($info['image_style']);
	    $this->setParam_BorderStyle($info['border_style']);
	    $this->setParam_AutoPlay($info['auto_play']);
        $this->_moduleInfo['param_items'] = [];
        foreach ($info['items'] as $item){
            $this->_moduleInfo['param_items'][] = $this->addItem($item['image'],$item['link_desc'],$item['link_type'],$item['link_data'],$item['link_url']);
        }
        parent::update($info);
    }

    public function getItems(){
        return $this->_moduleInfo['param_items'];
    }

    private function addItem($image,$linkDesc,$linkType,$linkData,$linkUrl){
        return [
            'image' => $image,
            'link_desc' => $linkDesc,
            'link_type' => $linkType,
            'link_data' => $linkData,
            'link_url' => $linkUrl
        ];
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['image_style'] = $this->getParam_ImageStyle();
        $context['border_style'] = $this->getParam_BorderStyle();
        $context['auto_play'] = $this->getParam_AutoPlay();
        $context['items'] = $this->getItems();
        foreach ($context['items'] as $key => $item){
            //数据库记录以链接类型和链接data为做准，url在渲染时重新生成
            $context['items'][$key]['link_url'] = LinkHelper::getUrl($item['link_type'],$item['link_data']);
        }
		return $this->renderAct($context);
    }
}
