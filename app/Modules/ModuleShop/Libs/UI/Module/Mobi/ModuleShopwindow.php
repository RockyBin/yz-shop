<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;

/**
 * 橱窗模块
 * Class ModuleShopwindow
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleShopwindow extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 获取图片间距
     * @return mixed
     */
    public function getParam_ItemMargin(){
        return intval($this->_moduleInfo['param_item_margin']);
    }

    /**
     * 设置图片间距
     * @param $color
     */
    public function setParam_ItemMargin($margin){
        $this->_moduleInfo['param_item_margin'] = $margin;
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setLayout($info['layout']);
        $this->setParam_ItemMargin($info['item_margin']);
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
        $context['layout'] = $this->getLayout();
        $context['items'] = $this->getItems();
        $context['item_margin'] = $this->getParam_ItemMargin();
        foreach ($context['items'] as $key => $item){
            //数据库记录以链接类型和链接data为做准，url在渲染时重新生成
            $context['items'][$key]['link_url'] = LinkHelper::getUrl($item['link_type'],$item['link_data']);
        }
		return $this->renderAct($context);
    }
}
