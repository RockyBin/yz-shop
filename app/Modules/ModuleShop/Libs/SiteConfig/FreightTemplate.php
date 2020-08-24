<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use YZ\Core\Site\Site;
use  App\Modules\ModuleShop\Libs\Model\SiteConfig;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;

/**
 * 运费模板类
 * Class FreightTemplate
 * @package App\Modules\ModuleShop\Libs\FreightTemplate
 */
class FreightTemplate
{

    /**
     * 添加运费模板
     * @param array $info，运费模板信息，对应 FreightTemplateModel 的字段信息
     */
    public function add(array $info)
    {
        $model = new FreightTemplateModel();
        $model->fill($info);
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     * 编辑运费模板
     * @param array $info，运费模板信息，对应 FreightTemplateModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new FreightTemplateModel();
        $model->fill($info);
        $model->where(['id' => $info['id'], 'site_id' => Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 查找指定ID的运费模板
     * @param $templateId
     */
    public function getInfo($templateId)
    {
        return FreightTemplateModel::where(['id' => $templateId, 'site_id' => Site::getCurrentSite()->getSiteId()])->get();
    }

    /**
     * 搜索出所有运费模板的列表
     * @param
     */
    public function getList($param)
    {
        $page = $param['page'] ?? '1';
        $pagesize = $param['page_size'] ?? '20';
        $list = FreightTemplateModel::where('site_id', '=', Site::getCurrentSite()->getSiteId())->forPage($page, $pagesize)->orderBy('id', 'desc')->get();
        foreach ($list as $k => $v) {
            $area_name = [];
            foreach (json_decode($v['delivery_area'], 'true') as $k1 => $v1) {
                $res = \DB::table('tbl_district')->whereIn('id', explode(',', $v1['area']))->select((\DB::raw('GROUP_CONCAT(name) as name')))->get();
                $area_name[] = $res[0]->name;
            }
            $list[$k]['delivery_area_name'] = $area_name;
        }
        $total = FreightTemplateModel::where('site_id', '=', Site::getCurrentSite()->getSiteId())->count();
        $last_page = $total / $pagesize <= 1 ? 1 : $total / $pagesize;
        $data['list'] = $list;
        $data['total'] = $total;
        $data['page_size'] = count($list);
        $data['current'] = $page;
        $data['last_page'] = $last_page;
        return $data;
    }

    /**
     * 删除指定ID的运费模板
     * @param $templateId
     */
    public function delete($templateId)
    {
        return FreightTemplateModel::where(['id' => $templateId, 'site_id' => Site::getCurrentSite()->getSiteId()])->delete();
    }

    /**
     * 测试此ID是否在使用
     * @param $templateId
     */
    public function checkHaveUse($templateId)
    {
        $productFreight = \DB::table('tbl_product')->where(['freight_id' => $templateId, 'site_id' => Site::getCurrentSite()->getSiteId()])->count();
        $cloudeStockFreight = \DB::table('tbl_cloudstock_setting')->where(['freight_id' => $templateId, 'site_id' => Site::getCurrentSite()->getSiteId()])->count();
        return $productFreight > 0 || $cloudeStockFreight > 0;
    }

    /**
     * 检测名字是否有一样的
     * @param $templateId
     */
    public function checkTemplateName($TemplateName, $id = 0)
    {
        return FreightTemplateModel::where(['template_name' => $TemplateName, 'site_id' => Site::getCurrentSite()->getSiteId()])->whereNotIn('id', ['id' => $id])->count();
    }

    public function getDistrictJs()
    {
        $list = \DB::table('tbl_district')->get();
        $arr = [];
        foreach ($list as $k => $v) {
            if ($v->parent_id == 1) {
                $arr['provinceArr'][] = json_decode(json_encode($v));
                unset($list[$k]);
            }
        }

        foreach ($list as $k1 => $v1) {
            foreach ($arr['provinceArr'] as $k2 => $v2) {
                if ($v1->parent_id == $v2->id) {
                    $arr['provinceArr'][$k2]->cityArr[] = json_decode(json_encode($v1));
                }
            }
        }
        // file_put_contents("citedata.js",json_decode(json_encode($arr)));
        //return $arr;
        return json_encode($arr);

    }

    /**
     * 获取某个地区的运费
     * @param $templateId
     */
    public function getAreaFreight($templateId, $city)
    {
        $data = $this->getInfo($templateId)->toArray();
        $areaInfo = json_decode($data[0]['delivery_area'], true);

        // $city_code=\DB::table('tbl_district')->where('name','like','%'.$city.'%')->first()->id;

        $fee = [];
        $min_fee = 99999999;
        foreach ($areaInfo as $k => $v) {
            $min_fee = min($min_fee, $v['firstFee']);
//            $fee['renewFee'] = $v['renewFee'];
//            if ($v['area'] == '' || strpos($v['area'], (string)$city_code)) {
//                $fee['firstFee'] = $v['firstFee'];
//                $fee['renewFee'] = $v['renewFee'];
//            }
        }
        $fee['firstFee'] = $min_fee;
        return $fee;
    }


    /**
     * 获取所有运费模板
     * @return array
     */
    public static function getFreightTemplateList()
    {
        // 运费模板列表
        $freightTemplateList = FreightTemplateModel::query()
            ->where('status', 1)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->select(['template_name', 'id'])
            ->orderBy('id','desc')
            ->get()->toArray();
        return $freightTemplateList;
    }
}