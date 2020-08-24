<?
namespace YZ\Core\Plugin;

/**
 * 插件接口
 */
interface IPlugin {
    /**
     * 初始化此插件，如加载参数，设置内部变量等
     * @param array $params 插件的初始化参数(通常是设置类的参数)
     * @return mixed
     */
    public function init(array $params);

    /**
     * 执行插件
     * @param null $runTimeParams 运行时参数，不同的插件根据自己的情况来定
     * @return mixed
     */
    public function execute($runTimeParams = null);
}
?>