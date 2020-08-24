<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\UI\Design;

use App\Modules\ModuleShop\Libs\UI\Popup;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class PopupController extends BaseAdminController
{
    public function __construct()
    {

    }

    public function save(Request $request){
        try {
            $info = $request->all();
            //保存新增或更改的模块
            $id = $info['id'];
            if($id) $nav = new Popup($id);
            else $nav = Popup::getDefaultPopup($request->get('device_type'));
            $id = $nav->update($info);
            return makeApiResponse(200,'ok',['id' => $id]);
        }catch(\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

    public function getInfo(Request $request){
        if($request->get('id')){
            $nav = new Popup($request->get('id'));
        }else{
            $nav = Popup::getDefaultPopup($request->get('device_type'));
        }
        return makeApiResponse(200,'ok',$nav->render());
    }
}