<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/11
 * Time: 15:06
 */

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

class StyleColorModel extends BaseModel
{
    protected $table = 'tbl_style_color';

    /**
     * 获取配色的信息
     * @return mixed
     */
    public function getColorInfo()
    {
        return json_decode($this->color_info, true);
    }
}
