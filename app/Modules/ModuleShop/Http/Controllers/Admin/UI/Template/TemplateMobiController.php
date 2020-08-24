<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/11
 * Time: 9:53
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\UI\Template;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\TemplateMobi\TemplateMobi;
use Illuminate\Http\Request;

class TemplateMobiController extends BaseAdminController
{
    /**
     * 获取模板列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $industry_id = $request->input('industry_id', '');
            $status = $request->input('status', '');
            $site_id = $request->input('site_id', '');
            $page_id = $request->input('page_id', '');
            $keyword = $request->input('keyword', '');
            $page = $request->input('page', 1);
            $page_size = $request->input('page_size', 8);
            $device_type = $request->input('device_type', 1);
            $template = new TemplateMobi();
            $countParam = $param = compact(
                'industry_id',
                'status',
                'site_id',
                'page_id',
                'keyword',
                'page',
                'page_size',
                'device_type'
            );
            $countParam['return_total_record'] = 1;
            $count = $template->getList($countParam);
            $list = [];
            $lastPage = 0;
            $sort = ['is_blank' => 'desc','id' => 'desc'];
            if ($count) {
                $list = $template->getList($param,$sort);
                $lastPage = ceil($count / $page_size);
            }
            $data = [
                'list' => $list,
                'total' => $count,
                'current' => $page,
                'page_size' => $page_size,
                'last_page' => $lastPage
            ];
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}