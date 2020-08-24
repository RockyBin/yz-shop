<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\UI\Module\Mobi;
use App\Modules\ModuleShop\Libs\Model\ModuleMobiModel;

class ModuleFactory
{
    /**
     * 实例化具体的模块类,传入参数可能是模块ID或模块表某一行的数据
     * @param $argument
     * @return null|object
     */
    public static function createInstance($argument): BaseMobiModule
    {
        if (is_array($argument)) return self::createInstanceByRow($argument);
		else return self::createInstanceByID($argument);
    }

    public static function createInstanceByType($type) : BaseMobiModule
    {
        $class = new \ReflectionClass("\\App\\Modules\\ModuleShop\\Libs\\UI\\Module\\Mobi\\$type");
        $instance = $class->newInstance();
        return $instance;
    }

    private static function createInstanceByID($moduleId)
    {
        $row = ModuleMobiModel::find($moduleId);
        if ($row) return self::createInstanceByRow($row->toArray());
        else  return null;
    }

    private static function createInstanceByRow(array $row): BaseMobiModule
    {
        $moduleType = $row['type'];
        try {
            $class = new \ReflectionClass("\\App\\Modules\\ModuleShop\\Libs\\UI\\Module\\Mobi\\$moduleType");
            $instance = $class->newInstance($row);
            return $instance;
        }
        catch (\Exception $ex)
        {
            \YZ\Core\Logger\Log::writeLog("mobi_module", "can not found moduleType " . $moduleType . ' ' . $ex->getMessage());
        }
        return null;
    }
}
