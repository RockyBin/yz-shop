<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\UI\Design;

use App\Modules\ModuleShop\Libs\UI\Module\Mobi\BaseMobiModule;
use App\Modules\ModuleShop\Libs\UI\Module\Mobi\ModuleFactory;
use App\Modules\ModuleShop\Libs\UI\NavMobi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Site\Site;

class NavMobileController extends BaseAdminController
{
    public function __construct()
    {

    }

    public function save(Request $request){
        try {
            $info = $request->all();
            //保存新增或更改的模块
            $id = $info['id'];
            if($id) $nav = new NavMobi($id);
            else $nav = NavMobi::getDefaultNav($request->get('device_type'));
            $id = $nav->update($info);
            return makeApiResponse(200,'ok',['id' => $id]);
        }catch(\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

    public function getInfo(Request $request){
        if($request->get('id')){
            $nav = new NavMobi($request->get('id'));
        }else{
            $nav = NavMobi::getDefaultNav($request->get('device_type'));
        }
        return makeApiResponse(200,'ok',$nav->render());
    }

    public function bigScreenSave(Request $request)
    {
        try {
            return $this->save($request);
        } catch (\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

    public function bigScreenGetInfo(Request $request)
    {
        try {
            return $this->getInfo($request);
        } catch (\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }
}