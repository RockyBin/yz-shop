<?php

namespace App\Modules\ModuleShop\Libs;

/**
 * 工具类
 * Class Utils
 * @package App\Modules\ModuleShop\Libs
 */
class Utils
{
    /**
     * 检查密码强度
     * @param $password
     * @param int $wordLength 密码长度
     * @return bool
     */
    public static function checkPasswordStrength($password, $wordLength = 8)
    {
        $password = trim($password);
        // 检查密码长度
        if (empty($password) || strlen($password) < $wordLength) return false;
        // 必须包含字母和数字
        if (!preg_match('/^.*[a-zA-Z]+.*/i', $password) || !preg_match('/^.*[0-9]+.*/i', $password)) return false;

        return true;
    }

    /**
     * 检查支付密码强度
     * @param $password
     * @return bool
     */
    public static function checkPayPasswordStrength($password)
    {
        $password = trim($password);
        // 长度 6 位
        if (empty($password) || strlen($password) != 6) return false;
        // 6位数字
        if (!preg_match('/^[0-9]{6}/i', $password)) return false;

        return true;
    }
}
