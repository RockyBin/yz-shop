<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Member;

use App\Modules\ModuleShop\Libs\Member\LevelUpgrade\UpgradeConditionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class MemberLevelController extends BaseAdminController
{
    private $memberLevel;

    public function __construct()
    {
        $this->memberLevel = new \App\Modules\ModuleShop\Libs\Member\MemberLevel();
    }

    /**
     * 获取列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['memberCount'] = true; // 需要获取会员数统计
            $data = $this->memberLevel->getList($param);
            $list = [];
            $total = intval($data['total']);
            if ($total > 0) {
                $list = $data['list']->toArray();
                foreach ($list as &$item) {
                    $item = $this->convertOutputData($item);
                }
                unset($item);
            }
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), [
                'list' => $list,
                'total' => $total
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo(Request $request)
    {
        try {
            $detail = $this->memberLevel->detail($request->id);
            if ($detail) {
                $detail = $detail->toArray();
                // 转换数据
                $detail = $this->convertOutputData($detail);
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $detail);
            } else {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 新建
     * @return Response
     */
    public function add(Request $request)
    {
        try {
            if (!$this->dataCheck($request)) {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
            // 修正数据
            $param = $this->convertInputData($request->toArray());
            $result = $this->memberLevel->add($param);
            if (intval($result['code']) == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $result['data']);
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            if (!$this->dataCheck($request)) {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
            // 修正数据
            $param = $this->convertInputData($request->toArray());
            $result = $this->memberLevel->edit($param);
            if (intval($result['code']) == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 状态启动或禁用
     * @param Request $request
     * @return array
     */
    public function status(Request $request)
    {
        try {
            $status = $request->status;
            $status = $status ? 1 : 0;
            $result = $this->memberLevel->status($request->id, $status);
            if (intval($result['code']) == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除会员等级
     * @param Request $request
     * @return array
     */
    public function delete(Request $request){
        try {

            $result = $this->memberLevel->delete($request->id);
            if (intval($result['code']) == 200) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
            } else {
                return makeApiResponse($result['code'], $result['msg']);
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 会员等级转移（把某一会员等级的会员批量修改成新的会员等级）
     * @param Request $request
     * @return array
     */
    public function transfer(Request $request)
    {
        try {
            $memberLevelSource = $request->source;
            $memberLevelTarget = $request->target;
            if (!$memberLevelSource || !$memberLevelTarget) {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
            if ($this->memberLevel->memberTransfer($memberLevelSource, $memberLevelTarget)) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
            } else {
                return makeApiResponseFail(trans("shop-admin.common.data_error"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 基础数据检查
     * @param Request $request
     * @return bool
     */
    private function dataCheck(Request $request)
    {
        $discount = floatval($request->get('discount'));
        // 检查数值
        if ($discount < 0 || $discount > 100) return false;
        // 检查字符窜
        if (empty(trim($request->get('name')))) return false;
        // 检查权重
        $weight = intval($request->get('weight'));
        if ($weight < 0 || $weight > 99) return false;

        return true;
    }

    /**
     * 数据输入转换
     * @param $param
     * @return mixed
     */
    private function convertInputData($param)
    {
        // 状态0或1
        if ($param['status'] != '') {
            $param['status'] = $param['status'] ? 1 : 0;
        }

        // 是否作用于新用户0或1
        if ($param['for_newmember'] != '') {
            $param['for_newmember'] = $param['for_newmember'] ? 1 : 0;
        }

        // 升级条件
        $param['condition'] = json_encode($param['condition']);

        return $param;
    }

    /**
     * 数据输出转换
     * @param $param
     * @return mixed
     */
    private function convertOutputData(array $info)
    {
        $info['condition'] = json_decode($info['condition'],true);
        if(!$info['condition']) $info['condition'] = [];
        $info['condition_text'] = '';
        foreach ($info['condition'] as &$item){
            $conIns = UpgradeConditionHelper::createInstance($item['type'], $item['value']);
            $item['name'] = $conIns->getNameText();
            $info['condition_text'] .= $item['name'];
            if(count($info['condition']) > 1) $info['condition_text'] .=($item['logistic'] == 'or' ? ' 或 ':' 且 ');
        }
        unset($item);
        return $info;
    }
}