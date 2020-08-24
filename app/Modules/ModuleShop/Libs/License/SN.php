<?php
//phpcodelock
namespace App\Modules\ModuleShop\Libs\License;
use App\Modules\ModuleShop\Libs\Constants;

/**
 * 分销商城的序列号实例类
 * Class SN
 * @package App\Modules\ModuleShop\Libs\License
 */
class SN extends \YZ\Core\License\AbstractSN
{
    /**
     * 获取当前序列号的产品版本的文字表示形式
     * @return string
     */
    public function getCurLicenseText()
    {
        $ver = $this->getCurLicense();
        if ($ver == Constants::License_FREE) return "免费版";
        else if ($ver == Constants::License_STANDARD) return "微商城";
        else if ($ver == Constants::License_DISTRIBUTION) return "分销版";
        else if ($ver == Constants::License_AGENT_DISTRIBUTION) return "3+3渠道版";
        else if ($ver == Constants::License_GROUP) return "直播分销版";
        else if ($ver == Constants::License_MICRO_CLOUDSTOCK) return "微商版";
        else if ($ver == Constants::License_AGENT) return "3级代理版";
        else if ($ver == Constants::License_SUPPLIER) return "供应商版";
        else return "未知版本";
    }

    /**
     * 检测当前序列号是否有某个权限
     * @param $p 要检测的权限值
     * @return bool
     */
    public function hasPermission($p):bool
    {
        return $this->validate() && (in_array($p, $this->getPermission()));
    }

    /**
     * 获取当前序列号有哪些权限
     * @param $returnName int 是否返回权限的名称而不是权限的值，一般用于前端项目的权限判断或友好提示这种，用数字值不好理解
     * @return array
     */
    public function getPermission($returnName = 0):array
    {
        $lp = 0;
        if ($this->getCurLicense() == Constants::License_FREE) {
            $lp = array(
                Constants::FunctionPermission_BASE
            );
        } else if ($this->getCurLicense() == Constants::License_STANDARD) {
            $lp = array(
                Constants::FunctionPermission_BASE
            );
        } else if ($this->getCurLicense() == Constants::License_DISTRIBUTION) {
            $lp = array(
                Constants::FunctionPermission_BASE
                , Constants::FunctionPermission_ENABLE_DISTRIBUTION
                , Constants::FunctionPermission_ENABLE_WXAPP
                , Constants::FunctionPermission_ENABLE_WXWORK
                , Constants::FunctionPermission_ENABLE_GROUP_BUYING
                , Constants::FunctionPermission_ENABLE_RECHARGE_BONUS
            );
        } else if ($this->getCurLicense() == Constants::License_GROUP) {
            $lp = array(
                Constants::FunctionPermission_BASE
                , Constants::FunctionPermission_ENABLE_DISTRIBUTION
				, Constants::FunctionPermission_INFINITE_STORE
                , Constants::FunctionPermission_ENABLE_LIVE
                , Constants::FunctionPermission_ENABLE_GROUP_BUYING
                , Constants::FunctionPermission_ENABLE_WXAPP
                , Constants::FunctionPermission_ENABLE_WXWORK
                , Constants::FunctionPermission_ENABLE_RECHARGE_BONUS
            );
        } else if ($this->getCurLicense() == Constants::License_AGENT_DISTRIBUTION) {
            $lp = array(
                Constants::FunctionPermission_BASE
                , Constants::FunctionPermission_ENABLE_DISTRIBUTION
                , Constants::FunctionPermission_ENABLE_AGENT
                , Constants::FunctionPermission_ENABLE_LIVE
                , Constants::FunctionPermission_ENABLE_GROUP_BUYING
                , Constants::FunctionPermission_ENABLE_WXAPP
                , Constants::FunctionPermission_ENABLE_WXWORK
                , Constants::FunctionPermission_ENABLE_RECHARGE_BONUS
            );
        } else if ($this->getCurLicense() == Constants::License_AGENT) {
            $lp = array(
                Constants::FunctionPermission_BASE
            , Constants::FunctionPermission_ENABLE_AGENT
            );
        } else if ($this->getCurLicense() == Constants::License_MICRO_CLOUDSTOCK) {
            $lp = array(
                Constants::FunctionPermission_BASE
                , Constants::FunctionPermission_ENABLE_CLOUDSTOCK
                , Constants::FunctionPermission_ENABLE_DEALER_INVITE
                , Constants::FunctionPermission_ENABLE_AUTHCERT
                , Constants::FunctionPermission_ENABLE_LIVE
                , Constants::FunctionPermission_ENABLE_GROUP_BUYING
                , Constants::FunctionPermission_ENABLE_WXAPP
                , Constants::FunctionPermission_ENABLE_WXWORK
                , Constants::FunctionPermission_ENABLE_RECHARGE_BONUS
            );
        } else if ($this->getCurLicense() == Constants::License_SUPPLIER) {
            $lp = array(
                Constants::FunctionPermission_BASE
            , Constants::FunctionPermission_ENABLE_DISTRIBUTION
            , Constants::FunctionPermission_ENABLE_AGENT
            , Constants::FunctionPermission_ENABLE_LIVE
            , Constants::FunctionPermission_ENABLE_GROUP_BUYING
            , Constants::FunctionPermission_ENABLE_WXAPP
            , Constants::FunctionPermission_ENABLE_WXWORK
            , Constants::FunctionPermission_ENABLE_RECHARGE_BONUS
            , Constants::FunctionPermission_ENABLE_SUPPLIER
            );
        }

        $functions = Constants::getFunctionPermissions();
        if ($this->addFunctions) {
            $arr = explode(',', $this->addFunctions);
            for ($i = 0; $i < count($arr); $i++) {
                foreach ($functions as $varname => $val) {
                    if (preg_match("/(" . $arr[$i] . ")$/i", $varname)) {
                        $lp [] = $val;
                    }
                }
            }
        }
        if($returnName){
            $lpnames = [];
            $functions = Constants::getFunctionPermissions();
            foreach($functions as $varname => $val){
                if(in_array($val,$lp)) $lpnames[] = str_replace('FunctionPermission_','',$varname);
            }
            $lp = $lpnames;
        }
        return $lp;
    }
}