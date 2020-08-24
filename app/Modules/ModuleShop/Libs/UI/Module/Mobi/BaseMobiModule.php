<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;
use App\Modules\ModuleShop\Libs\Model\ModuleMobiModel;
use App\Modules\ModuleShop\Libs\UI\Module\BaseModule;

class BaseMobiModule extends BaseModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        if($idOrRow) {
            if (is_numeric($idOrRow)) {
                $this->load($idOrRow);
            } else if(is_array($idOrRow)){
                $this->init($idOrRow);
            }
        }
    }

    /**
     * 获取此模块的左右间距
     */
    public function getPaddingLeftRight()
    {
        return intval($this->_moduleInfo['padding_left_right']);
    }

    public function setPaddingLeftRight($value)
    {
        $this->_moduleInfo['padding_left_right'] = intval($value);
    }

    /**
     * 获取此模块的上下间距
     */
    public function getPaddingTopBottom()
    {
        return intval($this->_moduleInfo['padding_top_bottom']);
    }

    public function setPaddingTopBottom($value)
    {
        $this->_moduleInfo['padding_top_bottom'] = $value;
    }

    /**
     * 设置是否置顶显示
     * @param $seconds
     */
    public function setFixTop($isTop){
        $this->_moduleInfo['fix_top'] = $isTop;
    }

    /**
     * 获取是否置顶显示
     * @return mixed
     */
    public function getFixTop(){
        return $this->_moduleInfo['fix_top'];
    }

    /**
     * 设置背景颜色/图片等
     * @param string $background
     */
    public function setBackground($background){
        $this->_moduleInfo['background'] = $background;
    }

    /**
     * 获取背景颜色
     * @return string
     */
    public function getBackground(){
        $bg = $this->_moduleInfo['background'];
        if(!$bg) $bg = 'transparent';
        return $bg;
    }

    public function renderAct(array $context = [])
    {
        $common = self::getCommonContent();
        $common['padding_top_bottom'] = $this->getPaddingTopBottom();
        $common['padding_left_right'] = $this->getPaddingLeftRight();
        $common['background'] = $this->getBackground();
        $common['fix_top'] = $this->getFixTop();
        $context = array_merge($context,$common);
        return $context;
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        if(array_key_exists('background',$info)) $this->setBackground($info['background']);
        if(array_key_exists('padding_left_right',$info)) $this->setPaddingLeftRight($info['padding_left_right']);
        if(array_key_exists('padding_top_bottom',$info)) $this->setPaddingTopBottom($info['padding_top_bottom']);
        if(array_key_exists('fix_top',$info)) $this->setFixTop($info['fix_top']);
        if(array_key_exists('show_order',$info)) $this->setShowOrder($info['show_order']);
        if(array_key_exists('site_id',$info)) $this->setSiteId($info['site_id']);
        if(array_key_exists('publish',$info)) $this->setPublish($info['publish']);
        if(array_key_exists('layout',$info)) $this->setLayout($info['layout']);
        if(array_key_exists('page_id',$info)) $this->setPageId($info['page_id']);
        $this->save();
    }

    /**
     * 渲染模块
     */
    public function render(){

    }

    public static function deleteModule($id){
        $m = ModuleMobiModel::find($id);
        if($m) $m->delete();
    }
}
