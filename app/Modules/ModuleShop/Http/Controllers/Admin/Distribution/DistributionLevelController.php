<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Distribution;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;

class DistributionLevelController extends BaseAdminController
{

    public function __construct()
    {
    }

    /**
     * 获取等级列表
     * @return json
     */
    public function getList()
    {
        try {
            $list = DistributionLevel::getList(true);
            if (count($list) == 0) {
                // 自动生成默认等级
                $d = new DistributionLevel();
                $d->addDefaultLevel();
                $list = DistributionLevel::getList(true);
            }
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 添加等级
     * @param Request $request
     * @return array
     * @throws \App\Modules\ModuleShop\Libs\Distribution\Exception
     */
    public function add(Request $request)
    {
        try {
            $level = new DistributionLevel();
            $name = $request->get('name');
            $weight = $request->get('weight');
            $levelCommission = $request->get('levelCommission');
            $upgradeCondition = $request->get('upgradeCondition');
            $autoUpgrade = $request->input('auto_upgrade', 0);
            $level->add($name, $weight, $levelCommission, $upgradeCondition, $autoUpgrade);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 返回单个等级的信息
     * @param Request $request
     * @return type
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            $level = new DistributionLevel($id);
            return makeApiResponseSuccess('ok', $level->getInfo());
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改等级
     * @param Request $request
     * @return array
     * @throws \App\Modules\ModuleShop\Libs\Distribution\Exception
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->get('id');
            $level = new DistributionLevel($id);
            $name = $request->get('name');
            $weight = $request->get('weight');
            $new_open = $request->get('new_open');
            $levelCommission = $request->get('levelCommission');
            $upgradeCondition = $request->get('upgradeCondition');
            $autoUpgrade = $request->input('auto_upgrade', 0);
            $level->edit($name, $weight, $new_open, $levelCommission, $upgradeCondition, $autoUpgrade);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 禁用等级
     * @param Request $request
     * @return type
     */
    public function disable(Request $request)
    {
        try {
            $id = $request->get('id');
            $level = new DistributionLevel($id);
            $level->disable();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 禁用等级
     * @param Request $request
     * @return type
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->get('id');
            $level = new DistributionLevel($id);
            $level->delete();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
    /**
     * 启用等级
     * @param Request $request
     * @return type
     */
    public function enable(Request $request)
    {
        try {
            $id = $request->get('id');
            $level = new DistributionLevel($id);
            $level->enable();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 将某等级下的分销商转移到其它等级
     * @param Request $request
     * @return type
     */
    public function trans(Request $request)
    {
        try {
            $id = $request->get('id');
            $new_level_id = $request->get('new_level_id');
            $level = new DistributionLevel($id);
            $level->transToLevel($new_level_id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
