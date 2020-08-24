<?php
/**
 * 前台申请
 * User: liyaohui
 * Date: 2020/5/23
 * Time: 16:24
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent\AreaAgentLevelController;
use App\Modules\ModuleShop\Libs\AreaAgent\Condition\BecomeConditionHelper;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyFormDataModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentLevelModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Model\DistrictModel;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Site\Site;

class AreaAgentApplyFront
{
    private $siteId = 0;

    public function __construct()
    {
        $this->siteId = getCurrentSiteId();
    }

    public static function getAreaAgentApplyByMemberId($memberId, $status)
    {
        $status = myToArray($status);
        $areaAgentApply = AreaAgentApplyModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->whereIn('status', $status)
            ->first();
        if ($areaAgentApply) return $areaAgentApply;
        else return false;
    }

    public static function getApplyInfo($memberId)
    {
        return AreaAgentApplyModel::query()
            ->leftJoin('tbl_district as tbl_district_prov', 'tbl_area_agent_apply.apply_prov', '=', 'tbl_district_prov.id')
            ->leftJoin('tbl_district as tbl_district_city', 'tbl_area_agent_apply.apply_city', '=', 'tbl_district_city.id')
            ->leftJoin('tbl_district as tbl_district_area', 'tbl_area_agent_apply.apply_district', '=', 'tbl_district_area.id')
            ->where('member_id', $memberId)
            ->where('site_id', getCurrentSiteId())
            ->addSelect([
                'tbl_area_agent_apply.*',
                'tbl_district_prov.name as prov_text',
                'tbl_district_city.name as city_text',
                'tbl_district_area.name as area_text'
            ])
            ->first();
    }

    /**
     * 获取已使用或已申请的省市区
     */
    public static function getUsedDistrict()
    {
        $prov = [];
        $city = [];
        $district = [];
        $areaAgentApplyDistrict = AreaAgentApplyModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('status', 0)
            ->select(['apply_prov as prov', 'apply_city as city', 'apply_district as district', 'apply_area_type as type'])
            ->get()
            ->toArray();
        $areaAgentDistrict = AreaAgentModel::query()
            ->where('site_id', getCurrentSiteId())
            ->select(['prov', 'city', 'district', 'area_type as type'])
            ->get()
            ->toArray();
        $usedDistrict = array_merge($areaAgentApplyDistrict, $areaAgentDistrict);
        foreach ($usedDistrict as $item) {
            if ($item['type'] == AreaAgentConstants::AreaAgentLevel_Province) array_push($prov, $item['prov']);
            if ($item['type'] == AreaAgentConstants::AreaAgentLevel_City) array_push($city, $item['city']);
            if ($item['type'] == AreaAgentConstants::AreaAgentLevel_District) array_push($district, $item['district']);
        }
        return ['prov' => $prov, 'city' => $city, 'district' => $district];
    }

    /**
     * @param UploadedFile $file 上传的文件
     * @param int $memberId 会员id
     * @param string $type 文件是身份证还是营业执照
     * @return string           返回保存后的文件路径
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, $memberId, $type = '')
    {
        $subPath = '/areaAgent/';
        $upload_filename = "{$type}_" . $memberId . '_' . genUuid(8);
        $upload_filepath = Site::getSiteComdataDir('', true) . $subPath;
        $upload_handle = new FileUpload($file, $upload_filepath, $upload_filename);
        $upload_handle->reduceImageSize(1500);
        $file = $subPath . $upload_handle->getFullFileName();
        return $file;
    }


    /**
     * 保存申请表单字段
     * @param $info
     * @throws \Exception
     */
    public function saveFrom($info)
    {
        // 因为有重新申请，所以重新申请，把原来的记录删除重新生成一条即刻
        AreaAgentApplyModel::where('site_id', getCurrentSiteId())
            ->where('id', $info['apply_id'])
            ->where('member_id', $info['member_id'])
            ->delete();
        $applyCount = AreaAgentApplyModel::query()->where('site_id', getCurrentSiteId())->where('member_id', $info['member_id'])->count();
        if ($applyCount <= 1) {
            AreaAgentApplyFormDataModel::where('site_id', getCurrentSiteId())->where('member_id', $info['member_id'])->delete();
        }
        // 获取保存表单的字段
        $areaAgentFormDataColumb = Schema::getColumnListing('tbl_area_agent_apply_form_data');
        $formModel = new AreaAgentApplyFormDataModel();
        $formModel->site_id = getCurrentSiteId();
        $formModel->member_id = $info['member_id'];
        $applySetting = (new AreaAgentApplySetting())->getInfo();
        $apply_form_data = [];
        foreach ($info as $key => $value) {
            if (in_array($key, $areaAgentFormDataColumb) && $value) {
                if ($key == 'extend_fields') $value = json_encode($value);
                $formModel->{$key} = $value;
                $apply_form_data[$key] = $value;
            }
        }

        $formModel->save();

        // 获取审核表的字段
        $apply = [];
        $applyModel = new AreaAgentApplyModel();
        $areaAgentApplyColumb = Schema::getColumnListing('tbl_area_agent_apply');
        foreach ($info as $key => $value) {
            if (in_array($key, $areaAgentApplyColumb)) {
                $applyModel->{$key} = $value;
                $apply[$key] = $value;
            }
        }
        $applyModel->site_id = getCurrentSiteId();
        $applyModel->member_id = $info['member_id'];
        $applyModel->created_at = date('Y-m-d H:i:s');
        $self_level = [];
        if ($applySetting['self_level']) {
            $desc = ['and' => [], 'or' => []];
            $self_level = json_decode($applySetting['self_level'], true);
            foreach ($self_level as $araeAgentLevel => $conditionItem) {
                if ($araeAgentLevel == $apply['apply_area_type']) {
                    //这里排序暂时用 ASCII来排序，后期有需要再进行修改，暂不做复杂的操作
                    ksort($conditionItem);
                    foreach ($conditionItem as $key => $val) {
                        if ($val['value']) {
                            $conI = BecomeConditionHelper::createInstance($key, $val['value']);
                            array_push($desc[$val['logistic']], $conI->getTitle());
                        }
                    }
                }
            }
        }
        $applyCondition = ['apply_setting' => ['apply_level' => $applySetting['apply_level'], 'self_level' => $self_level, 'status' => $applySetting['status'], 'become_desc' => $desc], 'apply_form_data' => $apply_form_data, 'apply' => $apply];
        $applyModel->apply_condition = json_encode($applyCondition);
        // 目前只有默认等级，所以申请只能申请默认等级
        $level = (new AreaAgentLevel())->getDefaultLevel();
        $applyModel->apply_area_agent_level = $level['id'];

        $applyModel->save();
    }
}