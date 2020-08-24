<?
namespace YZ\Core\Plugin;
use YZ\Core\Common\DataCache;
use YZ\Core\Site\Site;

/**
 * 插件工具类
 */
class PluginHelper  {

    /**
     * 运行单个插件
     * @param IPlugin $plugin 插件实例
     * @param $runTimeParams 插件的运行时参数
     */
    public static function runPlugin(IPlugin $plugin,$runTimeParams){
        $plugin->execute($runTimeParams);
    }

    /**
     * 加载当前站点指定类型的插件并执行
     * @param string $pluginType
     * @param $runTimeParams 插件的运行时参数
     */
    public static function runPlugins(string $pluginType,$runTimeParams){
        $plugins = static::loadPlugins($pluginType);
        foreach ($plugins as $plugin){
            static::runPlugin($plugin,$runTimeParams);
        }
    }

    /**
     * 加载当前站点指定通用类型的插件
     * plugins.json 的格式如，
     * {
     *  'plugtypeA': [
     *      {
     *          'class': '\App\PluginA',
     *          'params': {}
	 *			 'desc': '插件说明',
     *      },
     *      {
     *          'class': '\App\PluginB',
     *          'params': {},
	 *			 'logistic': 'and|or',  //升级条件类插件需要
	 *			 'desc': '插件说明',
     *      }
     *  ]
     * }
     * @param string $pluginType
     */
    public static function loadPlugins(string $pluginType){
        $arr = [];
        $config = static::loadPluginsConfig();
        foreach ($config[$pluginType] as $item){
            $class = $item['class'];
            $params = $item['params'];
            $instance = new $class();
            $instance->init($params);
            $arr[] = $instance;
        }
        return $arr;
    }

    /**
     * 加载当前站点升级条件类型的插件
     * @param string $pluginType 插件类型，以下值之一
        分销升级条件：DistributionUpgradeCondition
        团队代理升级条件：AgentUpgradeCondition
        区域代理升级条件：AreaAgentUpgradeCondition
        经理商升级条件：DealerUpgradeCondition
     * @param $level 相应等的ID
     * @param $logic 条件逻辑，$logic = 'or' 或 'and'
     *
     * 格式如
     * {
        "AgentUpgradeCondition": {
            "等级ID": [
                {
                "class": "App\\Modules\\ModuleShop\\Libs\\Custom\\Site1696\\AgentUpgradeConditionAllSubOrderMoney",
                "params": {"value": 10000},
                "desc": "所有推荐下级订单金额满 100 元",
                "logistic": "or"
                },
                {
                "class": "App\\Modules\\ModuleShop\\Libs\\Custom\\Site1696\\AgentUpgradeConditionAllSubAgentNum",
                "params": {},
                "desc": "所有推荐下级1，2，3代理等级满 10 人，2条不同线",
                "logistic": "or"
                }
            ],
        }
        }
     */
    public static function loadUpgradeConditionPlugins(string $pluginType,$level,$logic){
        $arr = [];
        $config = static::loadPluginsConfig();
        $condition = $config[$pluginType]['level_' . $level];
        if ($condition) {
            foreach ($condition as $item){
                $class = $item['class'];
                $params = $item['params'];
                if ($item['logistic'] == $logic) {
                    $instance = new $class();
                    $instance->init($params);
                    $arr[] = $instance;
                }
            }
        }
        return $arr;
    }

    /**
     * 获取升级条件的配置 不实例化
     * @param string $pluginType
     * @param $level
     * @param string $logic
     * @return array
     */
    public static function loadUpgradeConditionPluginsConfig(string $pluginType,$level = 0,$logic = ''){
        $arr = [];
        $config = static::loadPluginsConfig();

        if ($level == 0) {
            return $config[$pluginType];
        }
        $condition = $config[$pluginType]['level_' . $level];
        if ($condition) {
            if ($logic) {
                foreach ($condition as $item){
                    if ($item['logistic'] == $logic) {
                        $arr[] = $item;
                    }
                }
            } else {
                return $condition;
            }
        }
        return $arr;
    }

    /**
     * 加载当前站点的插件配置JSON到数组
     * @return array|mixed
     */
    private static function loadPluginsConfig(){
        $configFile = Site::getSiteComdataDir(0,true).'/plugins.json';
        $cache = DataCache::getData($configFile);
        if($cache) return $cache;
        if(!file_exists($configFile)){
            DataCache::setData($configFile,[]);
            return [];
        }
        $config = json_decode(file_get_contents($configFile),true);
        if(!is_array($config)){
            DataCache::setData($configFile,[]);
            return [];
        }
        DataCache::setData($configFile,$config);
        return $config;
    }
}
?>