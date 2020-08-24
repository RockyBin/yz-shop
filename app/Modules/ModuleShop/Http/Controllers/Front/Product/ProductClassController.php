<?php
/**
 * 产品分类
 * User: 李耀辉
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Product;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Site\Site;

class ProductClassController extends BaseFrontController
{
    public function getClassList(Request $request)
    {
        try{
            $param=$request->toArray();
            $data = ProductClass::getClassList($param);
            //拿取分类的模板ID
            $this->StoreConfigObj = new StoreConfig();
            $config=$this->StoreConfigObj->getInfo();
            $class_tempid=1;
            $search=0;
            $allProduct=0;
            if($config['data']->product_class_setting){
                $class_tempid=$config['data']->product_class_setting->tempID;
                $search=$config['data']->product_class_setting->relativeFunction->search;
                $allProduct=$config['data']->product_class_setting->relativeFunction->allproduct;
            }
            $classInfo=['class_tempid'=>$class_tempid,'search'=>$search,'all_product'=>$allProduct];

            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $data['list'],
                'class_info'=>$classInfo
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }

    }


}