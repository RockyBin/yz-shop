<?php


namespace App\Modules\ModuleShop\Libs\AreaAgent;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use Illuminate\Support\Facades\Schema;

class AreaAgentLevel
{
    private $_model = null;


    public function __construct($idOrModel = null)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->_model = $this->find($idOrModel);
                if (!$this->_model) {
                    throw new \Exception("区域代理不存在");
                }
            } else {
                $this->_model = $idOrModel;
            }
        }
    }

    /**
     * 获取当前网站的分销等级列表
     * @param array $param 查询参数
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getList(array $param = [])
    {
        $query = AreaAgentLevelModel::query();
        $query->where('site_id', Site::getCurrentSite()->getSiteId());

        if (isset($param['status'])) {
            $query->where('status', $param['status']);
        }
        // 如果传入weight 则去查找比当前weight大的等级
        if (isset($param['weight']) && $param['weight'] > 0) {
            $query->where('weight', '>', $param['weight']);
        }
        // 等级使用中的人
        $passCountSql = "(SELECT count(distinct(member_id)) FROM tbl_member left join  `tbl_area_agent` on tbl_member.id = tbl_area_agent.member_id WHERE `tbl_area_agent_level`.`id` = `tbl_area_agent`.`area_agent_level` and tbl_member.site_id=" . getCurrentSiteId() . " and tbl_member.is_area_agent in (1)) AS `pass_count`";
        // 申请中的人
        $applyCountSql = "(SELECT count(*) FROM `tbl_area_agent_apply` WHERE `tbl_area_agent_level`.`id` = `tbl_area_agent_apply`.`apply_area_agent_level` AND `tbl_area_agent_apply`.`status` = 0) AS `applying_count`";
        $query->selectRaw('*,' . $passCountSql . ',' . $applyCountSql);
        $list = $query->get();

        // 如果列表没数据，则自动创建一条默认数据
        if (count($list) == 0) {
            (new static())->createDefaultLevel();
            $list = $query->useWritePdo()->get();
        }

        return $list;
    }

    /**
     * 获取默认等级
     * @param bool $write 是否读写库
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getDefaultLevel($write = false)
    {
        $level = AreaAgentLevelModel::query();
        if ($write) {
            $level->useWritePdo();
        }
        $level = $level->where('site_id', getCurrentSiteId())
            ->where('status', 1)
            ->where('weight', 0)
            ->first();
        if (!$level) {
            $this->createDefaultLevel();
            $level = $this->getDefaultLevel(true);
        }
        return $level;
    }

    /**
     * 生成默认等级
     */
    private function createDefaultLevel()
    {
        $model = new AreaAgentLevelModel();
        $model->site_id = getCurrentSiteId();
        $model->name = '默认等级';
        $model->status = 1;
        $model->weight = 0;
        $model->commission = ["province" => 0, "city" => 0, "district" => 0];
        $model->save();
    }


    public function edit(array $param)
    {
        if (isset($param['id'])) {
            $model = AreaAgentLevelModel::find($param['id']);
            if (!$model) throw  new \Exception('无此区域代理等级');
        } else {
            $model = new AreaAgentLevelModel();
            $model->site_id = getCurrentSiteId();
        }
        // 获取保存表单的字段
        $areaAgentLevelColumb = Schema::getColumnListing('tbl_area_agent_level');

        foreach ($param as $key => $value) {
            if (in_array($key, $areaAgentLevelColumb) && $value) {
                $model->{$key} = $value;
            }
        }
        $model->save();
        return $model;
    }

    private function find($id)
    {
        return AreaAgentLevelModel::query()
            ->where('id', $id)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->first();
    }

    public function getModel()
    {
        return $this->_model;
    }
}