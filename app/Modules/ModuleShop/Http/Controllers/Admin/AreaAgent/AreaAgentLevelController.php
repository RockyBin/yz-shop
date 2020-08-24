<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentLevel;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentLevel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class AreaAgentLevelController extends BaseAdminController
{
    use ValidatesRequests;
    protected $areaAgentLevelModel;

    public function __construct(AreaAgentLevelModel $agentLevelModel)
    {
        $this->areaAgentLevelModel = $agentLevelModel;
    }

    /**
     * 查询
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                'status' => 'sometimes|required|integer',
                'page' => 'sometimes|required|integer'
            ]);
            $param = $request->toArray();
            $list = AreaAgentLevel::getList($param);
            return makeApiResponseSuccess('查询成功', ['list' => $list]);
        } catch (\Exception $exception) {
            return makeApiResponseError($exception);
        }
    }

    /**
     * 区域代理等级修改
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function AreaEdit(Request $request)
    {
        try {
            $params = $request->toArray();
            $model = (new AreaAgentLevel())->edit($params);
            $response = makeApiResponse(200, '保存成功', $model->toArray());
        } catch (\Exception $exception) {
            $response = makeApiResponseError($exception);
        }
        return $response;
    }

    public function getInfo(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('请输入等级ID');
            }
            $model = (new AreaAgentLevel($request->id))->getModel();
            return makeApiResponse(200, '保存成功', $model->toArray());
        } catch (\Exception $exception) {
            return makeApiResponseError($exception);
        }
    }
}
