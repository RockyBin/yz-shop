<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Point;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Point\Point;

/**
 * 后台积分Controller
 * Class PointController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Point
 */
class PointController extends BaseAdminController
{
    private $siteId;
    private $point;

    /**
     * 初始化
     * PointController constructor.
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->point = new \App\Modules\ModuleShop\Libs\Point\Point($this->siteId);
    }

    /**
     * 添加积分记录
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $param = $request->toArray();
            if (!$this->dataCheck($request)) {
                return makeApiResponse(510, '数据异常');
            }
            $param['status'] = Constants::PointStatus_Active;
            $data = $this->point->add($param);
            if ($data) {
                return makeApiResponseSuccess('成功');
            } else {
                return makeApiResponse(500, '保存失败');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
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
            $param['outputText'] = true;
            // 后台只显示生效的
            if (!$param['status']) {
                $param['status'] = Constants::PointStatus_Active;
            }
            // 按生效时间排序
            if (!$param['order_by']) {
                $param['order_by'] = 'active_at';
            }
            $data = $this->point->getList($param);
            $list = $data['list']->toArray();

            if ($list) {
                foreach ($list as &$item) {
                    $item['in_out_type'] = Point::mergeInoutType($item)['type'];
                    $item['mobile'] = Member::memberMobileReplace($item['mobile']);
                }
            }

            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $list,
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取会员列表
     * @param Request $request
     * @return array
     */
    public function getMemberList(Request $request)
    {
        try {
            $param = $request->toArray();
            $member = new Member(0, $this->siteId);
            $data = $member->getList($param);
            if ($data && $data['list']) {
                $list = $data['list']->toArray();
                foreach ($list as &$item) {
                    $item = $this->convertMemberData($item);
                }
                unset($item);
                $data['list'] = $list;
            }

            // 会员等级列表
            if (intval($data['current']) <= 1) {
                $memberLevel = new MemberLevel();
                $levelData = $memberLevel->getList();
                $data['member_level_list'] = $levelData['list'];
            }

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 导出数据
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $param['outputText'] = true;
            $data = $this->point->getList($param);

            $exportHeadings = [
                '时间',
                '用户ID',
                '用户昵称',
                '用户手机',
                '终端来源',
                '来源/用途',
                '出/入账',
                '明细/备注',
                '积分变化',
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->created_at,
                        $item->member_id,
                        $item->nickname,
                        $item->mobile,
                        $item->terminal_type_text,
                        $item->inout_type_text,
                        $item->type_text,
                        $item->about==null?'--':$item->about,
                        $item->point
                    ];
                }
            }

            $exportObj = new Export(new Collection($exportData), 'JiFen-'. date("YmdHis").'.xlsx', $exportHeadings);

            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据检查
     * @param Request $request
     * @return bool 是否通过检查
     */
    private function dataCheck(Request $request)
    {
        $memberID = $request->member_id;
        if (intval($memberID) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertMemberData($item)
    {
        if ($item['buy_money']) {
            $item['buy_money'] = round(intval($item['buy_money']) / 100, 2);
        }

        if ($item['deal_money']) {
            $item['deal_money'] = round(intval($item['deal_money']) / 100, 2);
        }

        return $item;
    }
}