<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\UI\Design;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Link\LinkConstants;
use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\UI\Module\Mobi\BaseMobiModule;
use App\Modules\ModuleShop\Libs\UI\Module\Mobi\ModuleFactory;
use App\Modules\ModuleShop\Libs\UI\PageMobi;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use YZ\Core\Site\Site;

class MobilePageController extends BaseAdminController
{
    public function __construct()
    {

    }

    public function save(Request $request){
        try {
            $all = $request->all();
            //获取页面对象
            if($request->get("page_id")) $page = new PageMobi($request->get("page_id"));
            else $page = PageMobi::getDefaultPage($all['pageInfo']['type'],$request->get("device_type"));
            //更新页面数据，在此方法内，只保存页面的背景，标题和描述，其它数据不应该保存
			$pageInfo = [
				'background' => $all['pageInfo']['background'],
				'title' => $all['pageInfo']['title'],
				'description' => $all['pageInfo']['description']
			];
            $page->update($pageInfo);
            //保存新增或更改的模块
            $newIds = [];
            foreach ($all['modules'] as $m) {
                $order = $all['sort'][$m['id']];
                $m['show_order'] = $order;
                $m['site_id'] = Site::getCurrentSite()->getSiteId();
                $id = $m['id'];
                $m['page_id'] = $request->get("page_id");
                if(!$m['page_id']){
                    $m['page_id'] = $page->getModel()->id;
                }
                if(!is_numeric($id)) $id = 0;
                if ($id) $instance = ModuleFactory::createInstance($id);
                else $instance = ModuleFactory::createInstanceByType($m['module_type']);
                if (!$instance) continue;
                $instance->update($m);
                $newId = $instance->getModuleId();
                if ($newId != $id) $newIds[$m['id']] = $newId;
            }
            //删除用户想删除的模块
            foreach ($all['deletedModules'] as $id){
                BaseMobiModule::deleteModule($id);
            }
            //更新页面
            if($page){
                $newPageId = $page->update(['saved_at' => date('Y-m-d H:i:s')]);
            }
            //发布
            if(intval($request->get("publish")) === 1) {
                $page->publish();
            }
            return makeApiResponse(200,'ok',['new_ids' => $newIds,'page_id' => $newPageId]);
        }catch(\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

    /**
     * 发布单个页面
     */
    public function publish(Request $request){
        try {
            //获取页面对象
            if($request->get("page_id")) $page = new PageMobi($request->get("page_id"));
            else $page = PageMobi::getDefaultPage(Constants::PageMobiType_Home,$request->get("device_type"));
            $page->publish();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取页面列表
     * @param Request $request
     * @return array
     */
    public function getPageList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $keyword = $request->input('keyword', null);
            $startDate = $request->input('start_date', null);
            $endDate = $request->input('end_date', null);
            $param = [
                'keyword' => $keyword,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
            $list = PageMobi::getPageList($param, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 设置主页
     * @param Request $request
     * @return array
     */
    public function setHomePage(Request $request)
    {
        try {
            $pageId = $request->input('page_id', '');
            if (!$pageId) {
                return makeApiResponse(400, '参数错误');
            }
            $set = PageMobi::setHomePage($pageId);
            if ($set) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '设置失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新建页面
     * @param Request $request
     * @return array
     */
    public function addPage(Request $request)
    {
        try {
            $info = PageMobi::addPage($request->all());
            return makeApiResponseSuccess('ok',$info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新建空白页面
     * @return array
     */
    public function addBlankPage(Request $request)
    {
        try {
            $info = PageMobi::addBlankPage($request->get('device_type', 1));
            return makeApiResponseSuccess('ok',$info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除页面
     * @param Request $request
     * @return array
     */
    public function deletePage(Request $request)
    {
        try {
            $pageIds = $request->input('page_ids', '');
            if (!$pageIds) {
                return makeApiResponse(400, '参数错误');
            }
            $delete = PageMobi::deletePage($pageIds);
            if ($delete) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '删除失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取首页详情
     * @return array
     */
    public function getHomePage(Request $request)
    {
        try {
            $pageInfo = PageMobi::getDefaultPage(Constants::PageMobiType_Home,$request->get('device_type'))->getModel();
            return makeApiResponseSuccess('ok', ['page' => $pageInfo->id ? $pageInfo : null]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取页面二维码和链接
     * @param Request $request
     * @return array
     */
    public function getHomePageQrCode(Request $request)
    {
        try {
            $size = $request->input('size', 200);
            $margin = $request->input('margin', 10);
            $pageId = $request->input('page_id', 0);
            if($pageId) $page = new PageMobi($pageId);

            // 获取页面链接
            $isHome = !$pageId || ($page && $page->getModel()->type == Constants::PageMobiType_Home);
            $pageUrl = !$isHome ? '/shop/front/'.LinkHelper::getUrl(LinkConstants::LinkType_Page, $pageId) : '/shop/front/';
            $pageUrl = url('/') . $pageUrl;
            // 生成二维码
            $qrcode = QrCode::format('png')
                ->size($size)
                ->encoding('UTF-8')
                ->errorCorrection('M')
                ->margin($margin)
                ->generate($pageUrl);
            // base64格式返回图片字符串
            return makeApiResponseSuccess('ok', ['image' => "data:image/png;base64," .base64_encode($qrcode), 'url' => $pageUrl]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getMemberCenterPageConfig()
    {
        try {
            $config = PageMobi::getMemberCenterPageConfig();
            return makeApiResponseSuccess('ok', $config);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function bigScreenSave(Request $request){
        try {
            return $this->save($request);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function bigScreenGetHomePage(Request $request){
        try {
            return $this->getHomePage($request);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function bigScreenAddBlankPage(Request $request){
        try {
            return $this->addBlankPage($request);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}