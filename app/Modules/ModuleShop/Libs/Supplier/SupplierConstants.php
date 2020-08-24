<?php
/**
 * 供货商常量
 * User: liyaohui
 * Date: 2020/6/18
 * Time: 18:04
 */

namespace App\Modules\ModuleShop\Libs\Supplier;


class SupplierConstants
{
    const SupplierStatus_Active = 1; // 生效中
    const SupplierStatus_No = 0;     // 不是供货商
    const SupplierStatus_Cancel = -1;// 取消资格

    // 后台管理员状态
    const SupplierAdminStatus_Active = 1; // 生效
    const SupplierAdminStatus_UnActive = 0; // 禁用
    const SupplierAdminStatus_Delete = -1; // 删除
}