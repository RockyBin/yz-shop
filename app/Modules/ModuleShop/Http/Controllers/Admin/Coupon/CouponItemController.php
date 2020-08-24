<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Coupon;

use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Common\Export;
use YZ\Core\Constants as CodeConstants;

/**
 * 后台积分Controller
 * Class PointController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Point
 */
class CouponItemController extends BaseAdminController
{
    private $coupon_item;

    /**
     * 初始化
     * CouponController constructor.
     */
    public function __construct()
    {
        $this->coupon_item = new \App\Modules\ModuleShop\Libs\Coupon\CouponItem();
    }

    /**
     * 添加优惠券
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        try {
            $param = $request->toArray();
            if (!$this->coupon_item->dataCheck($param)) {
                return makeApiResponse(510, '已存在与之设置信息完全一致的优惠券');
            }
            $this->coupon_item->add($param);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑优惠券
     * @param Request $request
     * @return array
     */
    public function confirm(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $param = $request->toArray();
            $param['use_terminal_type']=CodeConstants::TerminalType_Manual;
            $this->coupon_item->confirm($param);
            return makeApiResponseSuccess('成功');
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
            $data = $this->coupon_item->getList($param);
            foreach ($data['list'] as $k => $v ){
                if($v->coupon_money && $v->coupon_type==0){
                    $v->coupon_money= moneyCent2Yuan(intval($v->coupon_money));
                }
                $v->coupon_money = preg_replace('/(\.00?0?)/','',$v->coupon_money);
                $v->doorsill_full_money = moneyCent2Yuan(intval($v->doorsill_full_money));
                $v->doorsill_full_money = preg_replace('/(\.00?0?)/','',$v->doorsill_full_money);
                if(!$v->name) $v->name = '--';
                if(!$v->nickname) $v->nickname = '--';
                $v->mobile = Member::memberMobileReplace($v->mobile);
            }
            $list = new Collection($data['list']);
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
     * 优惠券记录列表导出
     * $param 搜索条件
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $data = $this->coupon_item->getList($param);
            //var_dump($data['list']->toArray());die;
            $exportHeadings = [
                '优惠券标题',
                '券码',
                '类型',
                '优惠',
                '领取终端',
                '用户ID',
                '用户昵称',
                '用户手机号',
                '领取时间',
                '过期时间',
                '使用状态',
                '使用终端',
                '使用时间',
                '备注'
            ];
            $exportData = [];

            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item = $this->exportConvertOutputData($item);
                    $exportData[] = [
                        $item->title,
                        $item->code,
                        $item->coupon_type,
                        $item->coupon_money,
                        $item->receive_terminal_type,
                        $item->member_id,
                        $item->nickname,
                        $item->mobile,
                        $item->receive_time,
                        $item->expiry_time,
                        $item->status,
                        $item->use_terminal_type==null?'--':$item->use_terminal_type,
                        $item->use_time==null?'--':$item->use_time,
                        $item->remark==null?'--':$item->remark
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'YongHuiQuanJiLu-'. date("YmdHis").'.xlsx', $exportHeadings);

            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function exportConvertOutputData($item)
    {
        if ($item['coupon_type'] !== null) {
            switch ($item['coupon_type']) {
                case 0:
                    $item['coupon_type'] = '现金卷';
                    break;
                case 1:
                    $item['coupon_type'] = '折扣卷';
                    break;
            }
        }
        if ($item['coupon_type'] == 1) {
            $item['coupon_money'] = $item['coupon_money'].'折';
        } else {
            $item['coupon_money'] = (intval($item['coupon_money']) / 100) .'元';
        }

        $item['doorsill_full_money'] = intval($item['doorsill_full_money']) / 100;

        if ($item['receive_terminal_type']) {
            switch ($item['receive_terminal_type']) {
                case CodeConstants::TerminalType_PC:
                    $item['receive_terminal_type'] = 'PC';
                    break;
                case CodeConstants::TerminalType_Mobile:
                    $item['receive_terminal_type'] = 'H5';
                    break;
                case CodeConstants::TerminalType_WxOfficialAccount:
                    $item['receive_terminal_type'] = '公众号';
                    break;
                case CodeConstants::TerminalType_WxApp:
                    $item['receive_terminal_type'] = '小程序';
                    break;
            }
        }

        if ($item['status']) {
            switch ($item['status']) {
                case 0:
                    $item['status'] = '失效';
                    break;
                case 1:
                    $item['status'] = '已使用';
                    break;
                case 2:
                    $item['status'] = '未使用';
                    break;
                case 3:
                    $item['status'] = '已过期';
                    break;
                case 4:
                    $item['status'] = '已锁定';
                    break;
            }
        }


        if ($item['product_type'] == 1) {
            $id = explode(',', $item['product_info']);
            $data = \DB::table('tbl_product_class')->where(['site_id' => $this->siteID])->whereIn('id', $id)->select(['id', 'class_name as title', 'parent_id'])->get();
            $parent_arr = [];
            $product_info = '';
            //把父类先拿出来，减少二次循环的次数
            foreach ($data as $k => $v) {
                if ($v->parent_id == 0) {
                    $parent_arr[] = $v;
                }
            }
            //两重循环删除子类
            foreach ($data as $k1 => &$v1) {
                if ($v1->parent_id == 0) {
                    continue;
                }
                foreach ($parent_arr as $k2 => $v2) {
                    if ($v1->parent_id == $v2->id) {
                        unset($data[$k1]);
                    }
                }
            }
            //拼接字符串
            $i = 0;
            $product_info_length = count($data);
            foreach ($data as $k => $v) {
                $i++;
                $product_info .= $v->title;
                if ($i != $product_info_length) {
                    $product_info .= '、';
                }
            }
            //因为存在中文顿号，不能使用rtrim
            $item['product_info'] = $product_info;
        } else {
            $item['product_info'] = '全场通用';
        }

//        if($item['doorsill_type']==1){
//            $item['doorsill_full_info']='满'.$item['doorsill_full_money'].'可用';
//        }else{
//            $item['doorsill_full_info']='无门槛';
//        }

        if ($item['terminal_type'] != '') {
            $item['terminal_type'] = ltrim(rtrim($item['terminal_type'], ','), ',');
            $terminal_type = explode(',', $item['terminal_type']);
            $terminal_string = '';
            foreach ($terminal_type as $k => $v) {
                switch ($v) {
                    case CodeConstants::TerminalType_PC:
                        $terminal_string .= 'PC、';
                        continue;
                    case CodeConstants::TerminalType_Mobile:
                        $terminal_string .= 'H5、';
                        continue;
                    case CodeConstants::TerminalType_WxOfficialAccount:
                        $terminal_string .= '公众号、';
                        continue;
                    case CodeConstants::TerminalType_WxApp:
                        $terminal_string .= '小程序、';
                        continue;
                }
            }
            if ($terminal_string) {
                $item['terminal_type'] = '仅限' . rtrim($terminal_string, '、') . '使用';
            }
        }

        if ($item['effective_starttime']) {
            $item['effective_starttime'] =date('Y.m.d H:i:s',strtotime($item['effective_starttime']));
        }
        return $item;
    }
}