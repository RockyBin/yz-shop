<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;

/**
 * 公告模块
 * Class ModuleNotice
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleNotice extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置图标
     * @param $text
     */
    public function setParam_Icon($icon){
	    $this->_moduleInfo['param_icon'] = $icon;
    }

    /**
     * 获取图标
     * @return mixed
     */
    public function getParam_Icon(){
	    return $this->_moduleInfo['param_icon'];
    }

    /**
     * 设置文字颜色
     * @param $color
     */
    public function setParam_color($color){
	    $this->_moduleInfo['param_color'] = $color;
    }

    /**
     * 获取文字颜色
     * @return mixed
     */
    public function getParam_color(){
	    return $this->_moduleInfo['param_color'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Icon($info['icon']);
        $this->setParam_Color($info['color']);
        $this->_moduleInfo['param_items'] = [];
        foreach ($info['items'] as $item){
            $this->_moduleInfo['param_items'][] = $this->addItem($item['content'],$info['color'],$item['link_desc'],$item['link_type'],$item['link_data'],$item['link_url']);
        }
        parent::update($info);
    }

    public function getItems(){
        return $this->_moduleInfo['param_items'];
    }

    private function addItem($content,$color,$linkDesc,$linkType,$linkData,$linkUrl){
        return [
            'content' => $content,
            'color' => $color,
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
        $context['icon'] = $this->getParam_Icon();
        $context['color'] = $this->getParam_Color();
        $context['items'] = $this->getItems();
        foreach ($context['items'] as $key => $item){
            //数据库记录以链接类型和链接data为做准，url在渲染时重新生成
            $context['items'][$key]['link_url'] = LinkHelper::getUrl($item['link_type'],$item['link_data']);
        }
		return $this->renderAct($context);
    }
}
