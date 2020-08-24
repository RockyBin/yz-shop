<?php
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;

/**
 * 视频模块
 * Class ModuleVideo
 * @package App\Modules\ModuleShop\Libs\UI\Module\Mobi
 */
class ModuleVideo extends BaseMobiModule
{
    protected $_tableName = '\App\Modules\ModuleShop\Libs\Model\ModuleMobiModel';

    public function __construct($idOrRow = 0)
    {
        parent::__construct($idOrRow);
    }

    /**
     * 设置视频地址
     * @param $text
     */
    public function setParam_Url($url){
	    $this->_moduleInfo['param_url'] = $url;
    }

    /**
     * 获取视频地址
     * @return mixed
     */
    public function getParam_Url(){
	    return $this->_moduleInfo['param_url'];
    }
	
	/**
	 * 设置视频比例
	 * @param $text
	 */
	public function setParam_Scale($scale){
	    $this->_moduleInfo['param_scale'] = $scale;
	}
	
	/**
	 * 获取视频比例
	 * @return mixed
	 */
	public function getParam_Scale(){
	    return intval($this->_moduleInfo['param_scale']);
	}
	
	/**
	 * 设置视频封面
	 * @param $text
	 */
	public function setParam_Cover($cover){
	    $this->_moduleInfo['param_cover'] = $cover;
	}
	
	/**
	 * 获取视频封面
	 * @return mixed
	 */
	public function getParam_Cover(){
	    return $this->_moduleInfo['param_cover'];
	}

    /**
     * 设置是否自动播放
     * @param $text
     */
    public function setParam_Autoplay($autoplay){
        $this->_moduleInfo['param_autoplay'] = $autoplay;
    }

    /**
     * 获取是否自动播放
     * @return mixed
     */
    public function getParam_Autoplay(){
        return $this->_moduleInfo['param_autoplay'];
    }

    /**
     * 设置是否循环播放
     * @param $text
     */
    public function setParam_Loop($loop){
        $this->_moduleInfo['param_loop'] = $loop;
    }

    /**
     * 获取是否循环播放
     * @return mixed
     */
    public function getParam_Loop(){
        return $this->_moduleInfo['param_loop'];
    }

    /**
     * 更新模块数据，此方法只用在页面设计时，接收用户的模块设置数据并更新到数据库
     * @param array $info
     */
    public function update(array $info){
        $this->setParam_Url($info['url']);
		$this->setParam_Scale($info['scale']);
		$this->setParam_Cover($info['cover']);
        $this->setParam_Autoplay($info['autoplay']);
        $this->setParam_Loop($info['loop']);
        parent::update($info);
    }

	/**
     * 渲染模块
     */
    public function render(){
        $context = [];
        $context['url'] = $this->getParam_Url();
		$context['scale'] = $this->getParam_Scale();
		$context['cover'] = $this->getParam_Cover();
        $context['autoplay'] = $this->getParam_Autoplay();
        $context['loop'] = $this->getParam_Loop();
		return $this->renderAct($context);
    }
}
