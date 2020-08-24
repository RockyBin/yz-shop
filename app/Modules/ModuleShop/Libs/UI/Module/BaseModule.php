<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\UI\Module;
use YZ\Core\Site\Site;

abstract class BaseModule
{
	protected $_tableName = '';
    protected $_moduleInfo = array();

	/**
	* 将模块对象转为数组对象，这个主要是在 编辑模块时，用来加载模块数据时用的，json_encode() 不支持 encode 对象，只能转一下
	*/
	public function toArray(){
		$info = array();
		//取得所有属性的值
		$methods = get_class_methods($this);
		foreach($methods as $method){
			if(strtolower(substr($method,0,3)) == 'get'){
				$pname = substr($method,3);
				if($pname == 'ModuleInfo') continue;
				$pval = $this->$method();
				$info[$pname] = $pval;
			}
		}
		return $info;
	}

    // 模块ID，它是数据记录的主键
    public function getModuleId()
    {
        return intval($this->_moduleInfo['id']);
    }

    public function setModuleId($value)
    {
        $this->_moduleInfo['id'] = intval($value);
    }
    
    // 显示顺序
    public function getShowOrder()
    {
        return intval($this->_moduleInfo['show_order']);
    }

    public function setShowOrder($value)
    {
        $this->_moduleInfo['show_order'] = $value;
    }

    // 是否发布，不发布的不显示
    public function getPublish()
    {
        return intval($this->_moduleInfo['publish']);
    }

    public function setPublish($value)
    {
        $this->_moduleInfo['publish'] = $value;
    }    

    // 此模块的所有配置信息
    public function getModuleInfo()
    {
        return $this->_moduleInfo;
    }

    /**
     * 显示位置，有 Header、Footer、Main，目前只有 Main 区，Header、Footer 只是预留
     * @return mixed
     */
    public function getLocation()
    {
        return $this->_moduleInfo["location"];
    }

    public function setLocation($value)
    {
        $this->_moduleInfo["location"] = $value;
    }

    /**
     * 在哪个页面显示，填写此页面的路径，0 表示在所有页面显示，否则，应该记录页面的 id, tbl_page_XXX 的主键
     * @return mixed
     */
    public function getPageId()
    {
        return $this->_moduleInfo["page_id"];
    }

    public function setPageId($value)
    {
        $this->_moduleInfo["page_id"] = $value;
    }

    // 哪个模块类型
    public function getModuleType()
    {
        $type = get_class($this);
        if (strpos($type, "\\") !== false) $type = substr($type, strrpos($type, "\\") + 1);
        $this->_moduleInfo["type"] = $type;
        return $type;
    }

    // 此模块属于哪个网站
    public function getSiteId()
    {
        return intval($this->_moduleInfo["site_id"]);
    }

    public function setSiteId($value)
    {
        $this->_moduleInfo["site_id"] = $value;
    }
	
	// 当前模块是否容器类型的模块，它应该被容器类型的子类重载
	public function isContainerModule(){
		return false;
	}
	
	// 获取子模块（容器类型的模块应该重载此方法）
	public function getSubModules(){
		return null;
	}
	
	/**
    * 获取此模块的布局
    */
	public function getLayout()
    {
        return intval($this->_moduleInfo['layout']);
    }

    public function setLayout($value)
    {
        $this->_moduleInfo['layout'] = intval($value);
    }

	/**
    * 获取此模块的布局颜色
    */
	public function getLayoutColor()
    {
        return $this->_moduleInfo['layout_color'];
    }

    public function setLayoutColor($value)
    {
        $this->_moduleInfo['layout_color'] = $value;
    }

    /**
     * 初始始模块
     * @param array $row 数据库中的一行记录
     * @throws \Exception
     */
    protected function init(array $row = [])
    {
        if (is_array($row) && count($row) > 0) //数据行， 表 tbl_modules 中的一行数据
        {
            $this->_moduleInfo = $row;
        }

        $Params = $this->_moduleInfo["params"];
        if ($Params != "")
        {
            try
            {
                $xml = json_decode($Params,true);
                foreach ($xml as $key => $value)
                {
                    $this->_moduleInfo["param_" . $key] = $value;
                }
            }
            catch (\Exception $ex)
            {
                throw new \Exception("加载模块 " . $this->_moduleInfo["id"] . " 出错：" . $ex->getMessage());
            }
        }
    }

    /**
     * 通过模块ID初始化模块对象
     * @param $moduleId
     * @throws \Exception
     */
    protected function load($moduleId)
    {
        $data = $this->_tableName::find($moduleId);
        if ($data) $this->init($data->toArray());
        else  throw new \Exception("Can not found modle " . $moduleId);
    }

    /**
     * 保存模块时，获取模块的 params 字段所用的数据
     * @return string
     */
	public function getParamsJson()
    {
        $arr = array();
        foreach ($this->_moduleInfo as $key => $value)
        {
            if (strlen($key) > 6 && substr($key, 0, 6) == 'param_')
            {
                $pname = substr($key, 6);
                $arr[$pname] = $this->_moduleInfo[$key];
            }
        }
        $json = json_encode($arr,JSON_UNESCAPED_UNICODE);
        return $json;
    }

    /**
     * 保存模块到数据库
     */
    public function save()
    {
		$this->getModuleType(); //初始化一下 ModuleType 属性
        $hashTmp = array_slice($this->_moduleInfo, 0);
        unset($hashTmp["id"]); //id 是标识列，它不能被指定或更新的，所以要 Remove 掉
		$hashTmp["params"] = $this->getParamsJson();
        foreach ($this->_moduleInfo as $key => $value) //这里的foreach 不能用 hashTmp 进行枚举，否则会出现“集合已修改；可能无法执行枚举操作”
        {
            if (strlen($key) > 6 && substr($key, 0, 6) == 'param_')
            {
                unset($hashTmp[$key]);
            }
        }
        $hashTmp['updated_at'] = date("Y-m-d H:i:s");
        if ($this->getModuleId() == 0)
        {
			$hashTmp['created_at'] = date("Y-m-d H:i:s");
            $id = $this->_tableName::query()->insertGetId($hashTmp);
            $this->setModuleId($id);
        } else
        {
            $this->_tableName::where('id',$this->getModuleId())->update($hashTmp);
        }
    }

    /**
     * 获取用于渲染的公共数据
     * @return array
     */
    protected function getCommonContent()
    {
        $site = Site::getCurrentSite();
		
        $context = array();
        $context["module_type"] = $this->getModuleType();
        $context["layout"] = $this->getLayout();
        $context["layout_color"] = $this->getLayoutColor();
        //为了简化HTML源码，在非编辑模式下，不显示头部及尾部的代码
        $context["id"] = $this->getModuleId();
        $context["publish"] = $this->getPublish();
		
		//$candesign = (\YouZhan\Site\SiteSecurity::hasPerm(\YouZhan\Site\SiteSecurity::PermEnum_SysAdm) || \YouZhan\Site\SiteSecurity::hasPerm(\YouZhan\Site\SiteSecurity::PermEnum_TableAdm)) && !$_REQUEST["view"];
        //$context["CanDesign"] = $candesign;
				
        return $context;
    }

    //获取模块的数据
    public abstract function render();
}
