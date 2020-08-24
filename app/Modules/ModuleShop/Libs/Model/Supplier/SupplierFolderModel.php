<?php
/**
 * 供应商平台文件夹
 * User: liyaohui
 * Date: 2020/7/21
 * Time: 18:36
 */

namespace App\Modules\ModuleShop\Libs\Model\Supplier;


use YZ\Core\Model\BaseModel;

class SupplierFolderModel extends BaseModel
{
    protected $table = 'tbl_supplier_folder';

    public function __construct($attributes = array())
    {
        parent::__construct($attributes);
        $this->created_at = date('Y-m-d H:i:s');
    }
}