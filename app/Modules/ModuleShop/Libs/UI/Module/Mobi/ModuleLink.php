<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;

/**
 * 链接导航模块
 * Class ModuleLink
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleLink extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置字体颜色
     * @param $color
     */
    public function setParam_FontColor($color){
        $this->_moduleInfo['param_font_color'] = $color;
    }

    /**
     * 获取字体颜色
     * @return mixed
     */
    public function getParam_FontColor(){
        $color = $this->_moduleInfo['param_font_color'];
        if(!$color) $color = "#333";
        return $color;
    }

    /**
     * 设置是否划动
     * @param $slide
     */
    public function setParam_Slide($slide){
        $this->_moduleInfo['param_slide'] = $slide;
    }

    /**
     * 获取是否划动
     * @return mixed
     */
    public function getParam_Slide(){
        return $this->_moduleInfo['param_slide'];
    }

    /**
     * 设置一行的数目
     * @param $num
     */
    public function setParam_RowNum($num){
        $this->_moduleInfo['param_row_num'] = $num;
    }

    /**
     * 获取一行的数目
     * @return mixed
     */
    public function getParam_RowNum(){
        return $this->_moduleInfo['param_row_num'];
    }

    /**
     * 设置滑动时的行数
     * @param $num
     */
    public function setParam_RowCount($num){
        $this->_moduleInfo['param_row_count'] = $num;
    }

    /**
     * 获取滑动时的行数
     * @return mixed
     */
    public function getParam_RowCount(){
        return $this->_moduleInfo['param_row_count'];
    }

    /**
     * 设置滑动时一屏显示多少个
     * @param $num
     */
    public function setParam_ShowNum($num){
        $this->_moduleInfo['param_show_num'] = $num;
    }

    /**
     * 获取滑动时的行数
     * @return mixed
     */
    public function getParam_ShowNum(){
        return $this->_moduleInfo['param_show_num'];
    }

    /**
     * 设置图片圆角
     * @param $num
     */
    public function setParam_BorderRadius($num){
        $this->_moduleInfo['param_border_radius'] = $num;
    }

    /**
     * 获取滑动时的行数
     * @return mixed
     */
    public function getParam_BorderRadius(){
        return $this->_moduleInfo['param_border_radius'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setLayout($info['layout']);
        $this->setParam_RowNum($info['row_num']);
        $this->setParam_RowCount($info['row_count']);
        $this->setParam_ShowNum($info['show_num']);
        $this->setParam_Slide($info['slide']);
        $this->setParam_FontColor($info['font_color']);
        $this->setParam_BorderRadius($info['border_radius']);
        $this->_moduleInfo['param_items'] = [];
        foreach ($info['items'] as $item){
            $this->_moduleInfo['param_items'][] = $this->addItem($item['image'],$item['link_text'],$item['link_desc'],$item['link_type'],$item['link_data'],$item['link_url']);
        }
        parent::update($info);
    }

    public function getItems(){
        return $this->_moduleInfo['param_items'];
    }

    private function addItem($image,$linkText,$linkDesc,$linkType,$linkData,$linkUrl){
        return [
            'image' => $image,
            'link_text' => $linkText,
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
        $context['row_num'] = $this->getParam_RowNum();
        $context['row_count'] = $this->getParam_RowCount();
        $context['show_num'] = $this->getParam_ShowNum();
        $context['background'] = $this->getBackground();
        $context['font_color'] = $this->getParam_FontColor();
        $context['border_radius'] = $this->getParam_BorderRadius();
        $context['slide'] = $this->getParam_Slide();
        $context['items'] = $this->getItems();
        foreach ($context['items'] as $key => $item){
            //数据库记录以链接类型和链接data为做准，url在渲染时重新生成
            $context['items'][$key]['link_url'] = LinkHelper::getUrl($item['link_type'],$item['link_data']);
        }
		return $this->renderAct($context);
    }
}
