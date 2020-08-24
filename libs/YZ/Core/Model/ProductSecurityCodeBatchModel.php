<?php
/**
 * 防伪码批次模型
 * User: liyaohui
 * Date: 2019/10/30
 * Time: 13:54
 */

namespace YZ\Core\Model;


class ProductSecurityCodeBatchModel extends BaseModel
{
    protected $table = "tbl_product_security_code_batch";

    public function securityCode()
    {
        return $this->hasMany(ProductSecurityCodeModel::class, 'batch_id');
    }
}