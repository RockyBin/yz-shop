<?php
//phpcodelock
namespace YZ\Core\License;
/**
 * 未知序列号类，用于系统未定义的具体实例序列号类以外的
 * Class UnknowSN
 * @package YZ\Core\License
 */
class UnknowSN extends AbstractSN
{
    /**
     * 获取当前序列号的产品版本的文字表示形式
     * @return string
     */
    public function getCurLicenseText()
    {
        return "Unknow";
    }

    /**
     * 检测当前序列号是否有某个权限
     * @param $p 权限值
     * @return bool
     */
    public function hasPermission($p) : bool
    {
        return false;
    }

    /**
     * 获取当前序列号有哪些权限
     * @param $returnName int 是否返回权限的名称而不是权限的值，一般用于前端项目的权限判断或友好提示这种，用数字值不好理解
     * @return array
     */
    public function getPermission($returnName = 0) : array{
        return [];
    }
}