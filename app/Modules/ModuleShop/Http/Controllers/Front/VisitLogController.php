<?php
namespace App\Modules\ModuleShop\Http\Controllers\Front;

use Illuminate\Http\Request;
use YZ\Core\VisitCount\VisitLog;

class VisitLogController extends BaseFrontController
{
	public function index(Request $request)
    {
        try {
            VisitLog::log($request->all());
        } catch (\Exception $ex) {
            return "var error = \"".str_replace(["\n","\""],"",$ex->getMessage())."\"";
        }
    }
}