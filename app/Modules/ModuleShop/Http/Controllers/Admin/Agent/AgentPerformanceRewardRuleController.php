<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardRule;
use Illuminate\Http\Request;

class AgentPerformanceRewardRuleController extends BaseSiteAdminController
{
    /**
     * 列表数据
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $data = AgentPerformanceRewardRule::getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $this->convertData($item);
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            $agentPerformanceRewardRule = new AgentPerformanceRewardRule($id);
            if (!$agentPerformanceRewardRule->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $agentPerformanceRewardRule->getModel();
            $this->convertData($model);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存数据
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $id = intval($request->get('id', 0));
            // 数据完整性检查
            if (!$request->get('target') || !$request->get('reward')) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $param = $request->toArray();
            $target = moneyYuan2Cent($param['target']);
            $agentLevel = intval($param['agent_level']);
            if ($agentLevel <= 0) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $agentPerformanceRewardRule = new AgentPerformanceRewardRule($id);
            if ($id) {
                if (!$agentPerformanceRewardRule->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            }
            // 如果是 新增数据 或者 目标与代理等级变化，就检查是否存在相同条件
            if (!$id || intval($agentPerformanceRewardRule->getModel()->target) != $target || intval($agentPerformanceRewardRule->getModel()->agent_level) != $agentLevel) {
                // 验证是否有相同的条件
                $existCount = AgentPerformanceRewardRule::count([
                    'agent_level' => $agentLevel,
                    'target' => $target,
                ]);
                if ($existCount > 0) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            }
            // 数据转换
            $param['target'] = moneyYuan2Cent($param['target']);
            $param['reward'] = moneyYuan2Cent($param['reward']);
            // 保存数据
            if ($id) {
                $agentPerformanceRewardRule->edit($param);
            } else {
                $id = $agentPerformanceRewardRule->add($param);
            }
            // 返回数据
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'), [
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除数据
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->get('id');
            $agentPerformanceRewardRule = new AgentPerformanceRewardRule($id);
            if (!$agentPerformanceRewardRule->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
            $agentPerformanceRewardRule->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 输出数据转换
     * @param $item
     */
    private function convertData($item)
    {
        if ($item) {
            $item['target'] = moneyCent2Yuan($item['target']);
            $item['reward'] = moneyCent2Yuan($item['reward']);
        }
    }
}