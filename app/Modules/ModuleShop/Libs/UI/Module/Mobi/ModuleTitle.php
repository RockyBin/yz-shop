<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;
use App\Modules\ModuleShop\Libs\Link\LinkHelper;

/**
 * 标题模块
 * Class ModuleTitle
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleTitle extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

	/**
	 * 获取标题内容
	 * @return mixed
	 */
	public function getParam_Title(){
	    return $this->_moduleInfo['param_title'];
	}
	
	/**
	 * 设置链接内容
	 * @param $value
	 */
	public function setParam_Title($value){
	    $this->_moduleInfo['param_title'] = $value;
	}
	
	/**
	 * 获取标题类型
	 * @return mixed
	 */
	public function getParam_Type(){
	    return $this->_moduleInfo['param_type'];
	}
	
	/**
	 * 设置标题类型
	 * @param $type
	 */
	public function setParam_Type($type){
	    $this->_moduleInfo['param_type'] = $type;
	}	

	/**
	 * 获取文字颜色
	 * @return mixed
	 */
	public function getParam_Color(){
	    return $this->_moduleInfo['param_color'];
	}
	
	/**
	 * 设置文字颜色
	 * @param $value
	 */
	public function setParam_Color($value){
	    $this->_moduleInfo['param_color'] = $value;
	}

	/**
	 * 获取链接说明
	 * @return mixed
	 */
	public function getParam_LinkDesc(){
	    return $this->_moduleInfo['param_link_desc'];
	}
	
	/**
	 * 设置链接说明
	 * @param $value
	 */
	public function setParam_LinkDesc($value){
	    $this->_moduleInfo['param_link_desc'] = $value;
	}

    /**
	 * 获取链接类型
	 * @return mixed
	 */
	public function getParam_LinkType(){
	    return $this->_moduleInfo['param_link_type'];
	}
	
	/**
	 * 设置链接类型
	 * @param $value
	 */
	public function setParam_LinkType($value){
	    $this->_moduleInfo['param_link_type'] = $value;
	}
	
	/**
	 * 获取链接数据
	 * @return mixed
	 */
	public function getParam_LinkData(){
	    return $this->_moduleInfo['param_link_data'];
	}
	
	/**
	 * 设置链接数据
	 * @param $value
	 */
	public function setParam_LinkData($value){
	    $this->_moduleInfo['param_link_data'] = $value;
	}
	
	/**
	 * 获取链接地址
	 * @return mixed
	 */
	public function getParam_LinkUrl(){
	    return $this->_moduleInfo['param_link_url'];
	}

	/**
	 * 设置链接地址
	 * @param $value
	 */
	public function setParam_LinkUrl($value){
	    $this->_moduleInfo['param_link_url'] = $value;
	}

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Title($info['title']);
		$this->setLayout($info['layout']);
		$this->setParam_Type($info['type']);
		$this->setParam_Color($info['color']);
		$this->setBackground($info['background']);
		$this->setParam_LinkDesc($info['link_desc']);
		$this->setParam_LinkType($info['link_type']);
		$this->setParam_LinkData($info['link_data']);
		$this->setParam_LinkUrl($info['link_url']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['title'] = $this->getParam_Title();
		$context['layout'] = $this->getLayout();
		$context['type'] = $this->getParam_Type();
		$context['color'] = $this->getParam_Color();
		$context['background'] = $this->getBackground();
		$context['link_desc'] = $this->getParam_LinkDesc();
		$context['link_type'] = $this->getParam_LinkType();
		$context['link_data'] = $this->getParam_LinkData();
		$context['link_url'] = LinkHelper::getUrl($this->getParam_LinkType(),$this->getParam_LinkData());
		return $this->renderAct($context);
    }
}
