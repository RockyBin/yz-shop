<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Test;

use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MyToolController extends Controller
{
    public function updateProductParam()
    {
        try {
            $list = DB::select("select * from tbl_product where left(params, 1) = '{'");
            if ($list) {
                echo 'total:' . count($list) . '<br/>';
                foreach ($list as $item) {
                    if ($item->params) {
                        $paramArray = json_decode($item->params, true);
                        $inputArray = [];
                        if ($paramArray && is_array($paramArray)) {
                            foreach ($paramArray as $key => $value) {
                                $inputArray[] = [
                                    "paramsName" => $key,
                                    "paramsDesc" => $value,
                                ];
                            }
                            $paramStr = json_encode($inputArray);
                            DB::table('tbl_product')->where('id', $item->id)->update([
                                'params' => $paramStr
                            ]);
                            echo $item->id . '<br/>';
                        }
                    }
                }
            }
            die();
        } catch (\Exception $e) {
            dd($list, $e);
//            return makeApiResponseError($e);
        }
    }

    /**
     * 执行 resetAgentParentRelationTree
     */
    public function resetAgentParentRelationTree(Request $request)
    {
        try {
            $memberId = $request->get('member_id');
            if ($memberId) {
                AgentHelper::resetAgentParentRelationTree($memberId);
            }
            return 'member_id:' . $memberId;
        } catch (\Exception $e) {
            return '出错：' . $e->getMessage();
        }
    }
}