<?php

namespace App\Modules\ModuleShop\Libs\Coupon;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\CouponItemModel;
use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Constants as CodeConstants;

/**
 * 优惠券类
 * Class Coupon
 * @package App\Modules\ModuleShop\Libs\Coupon
 */
class CouponItem
{
    private $siteID = 0; // 站点ID
    private $couponItemEntity = null;

    /**
     * 初始化
     * Point constructor.
     */
    public function __construct()
    {
        $this->siteID = Site::getCurrentSite()->getSiteId();
        $this->couponItemEntity = new couponItemModel();
    }


    /**
     * 编辑优惠卷记录状态
     * @param array $info，设置信息，对应 couponItemModel 的字段信息
     */
    public function confirm(array $info)
    {
        $model = $this->couponItemEntity::find($info['id']);
        $model->status = $info['status'];
        $model->remark = $info['remark'];
        $model->use_time = date("Y-m-d H:i:s");
        $model->use_terminal_type=$info['use_terminal_type'];
        //$model->site_id = $this->siteID;
        $model->save();
    }

    /**
     * 批量添加优惠券
     * @param array $param，添加的信息
     */
    public function batchAdd($param)
    {
        $this->couponItemEntity->insert($param);
    }


    /**
     * 批量添加优惠券
     * @param array $where 需要修改的记录
     * @param array $param，修改的信息
     */
    public function batchEdit(array $where, array $param)
    {
        $expression = $this->getExpression($where);
        $expression->update($param);
    }

    /**
     * 查询某张优惠券，被领取的张数以及已经领取的用户数
     * @param array 需传状态值以及优惠券ID，也可以传输其他参数
     */
    public function getAffectCounponItem(array $params = [])
    {
        $expression = $this->getExpression($params);
        $receiverCount=$expression->count();
        $expression->leftJoin('tbl_member as member', 'member.id', '=', 'item.member_id');
        $expression->where('member.status','=',Constants::CommonStatus_Active);
        $expression->select(\DB::raw('distinct member_id'));
        $all_member_id=$expression->get();
        return  [
            'received_count' => $receiverCount,
            'all_member_id'=>$all_member_id
        ];
    }

    /**
     * 查询某张优惠券，某用户领取了某张优惠券的总数量（可查询任何状态下）
     * @param array 需传状态值以及优惠券ID，也可以传输其他参数
     */
    public function getMemberCouponItem(array $params = []){
        $expression = $this->getExpression($params);
        return $expression->count();
    }

    /**
     * 列表查询
     * @param array $params 参数
     * @return array
     */
    public function getList(array $params = [])
    {
        // 分页参数
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        $isShowAll = $params['show_all'] ? true : false; // 是否显示全部数据（不分页）
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;

        // 查询表达式
        $expression = $this->getExpression($params);
        $expression = $expression->leftjoin('tbl_member as member', 'member.id', '=', 'item.member_id');
        $expression = $expression->leftjoin('tbl_coupon as coupon', 'coupon.id', '=', 'item.coupon_id');

        if ($params['ids']) {
            $expression = $expression->whereIn('item.id', $params['ids']);
            $isShowAll=true;
        }
        //输出总数
        $total = $expression->count();

        if ($isShowAll) {
            $page_size = $total > 0 ? $total : 1;
            $page = 1;
        }
            $expression = $expression->forPage($page, $page_size);

        //输出-最后页数
        $last_page = ceil($total / $page_size);
        //前端按照领取时间排序
        if($params['front']){
            $expression->orderby('item.receive_time', 'desc');
        }else{
            $expression->orderby('item.id', 'desc');
        }
        //输出-列表数据
        $list = $expression->select('*', 'item.status as item_status', 'item.id as item_id')->get();
        return [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];

    }

    /**
     * 输出表达式供getList
     * @param array $params 参数
     * @return $expression
     */
    public function getExpression(array $params)
    {
        $expression = couponItemModel::query()
            ->from('tbl_coupon_item as item')
            ->where([
                ['item.site_id', $this->siteID]
            ]);

        if ($params['id']) {
            $expression->where('id', intval($params['id']));
        }
        if ($params['member_id']) {
            $expression = $expression->where('member_id', intval($params['member_id']));
        }
        if ($params['coupon_id']) {
            $expression->where('coupon_id', intval($params['coupon_id']));
        }
        // 终端显示
        if (($params['receive_terminal_type'] || $params['receive_terminal_type'] === '0') && $params['receive_terminal_type'] != -1) {
            $expression->where('item.receive_terminal_type', $params['receive_terminal_type']);
        }
        // 优惠券类型
        if (($params['coupon_type'] || $params['coupon_type'] === '0') && $params['coupon_type'] != -1) {
            $expression->where('coupon.coupon_type', $params['coupon_type']);
        }
        // 状态
        if (($params['status'] || $params['status'] === '0') && $params['status'] != -1) {
            $expression->whereIn('item.status', explode(',', $params['status']));
        }
        // 手机号码
        if ($params['mobile']) {
            $expression->where('member.mobile', 'like', '%' . $params['mobile'] . '%');
        }
        // 会员昵称
        if ($params['nickname']) {
            $expression->where('member.nickname', 'like', '%' . $params['nickname'] . '%');
        }
        // 优惠券标题

        if ($params['keyword']) {
            $keyword = $params['keyword'];
            $expression->where(function ($query) use ($keyword) {
                $query->where('member.nickname', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('coupon.title', 'like', '%' . trim($keyword) . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query->orWhere('member.mobile', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
        // 会员id
        if ($params['member_id']) {
            $expression->where('item.member_id', intval($params['member_id']));
        }
        // 过期时间
        if ($params['expiry_time_start']) {
            $expression->where('item.expiry_time', '>=', $params['expiry_time_start']);
        }
        if ($params['expiry_time_end']) {
            $expression->where('item.expiry_time', '<=', $params['expiry_time_end']);
        }
        // 开始时间
        if ($params['start_time_start']) {
            $expression->where('item.start_time', '>=', $params['start_time_start']);
        }
        if ($params['start_time_end']) {
            $expression->where('item.start_time', '<=', $params['start_time_end']);
        }

        return $expression;
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertOutputData($item)
    {
//        if ($item['coupon_type'] !== null) {
//            switch ($item['coupon_type']) {
//                case 0:
//                    $item['coupon_type'] = '现金卷';
//                    break;
//                case 1:
//                    $item['coupon_type'] = '折扣卷';
//                    break;
//            }
//        }
        if ($item['coupon_type'] == 1) {
            $item['coupon_money'] = $item['coupon_money'];
        } else {
            $item['coupon_money'] = intval($item['coupon_money']) / 100;
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
                case CodeConstants::TerminalType_WxWork:
                    $item['receive_terminal_type'] = '企业微信';
                    break;
                case CodeConstants::TerminalType_WxApp:
                    $item['receive_terminal_type'] = '小程序';
                    break;
            }
        }

//        if ($item['status']) {
//            switch ($item['status']) {
//                case 0:
//                    $item['status'] = '失效';
//                    break;
//                case 1:
//                    $item['status'] = '已使用';
//                    break;
//                case 2:
//                    $item['status'] = '未使用';
//                    break;
//                case 3:
//                    $item['status'] = '已过期';
//                    break;
//                case 4:
//                    $item['status'] = '已锁定';
//                    break;
//            }
//        }


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
        }elseif($item['product_type'] == 2){
            $item['product_info'] = '指定商品';
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
                    case CodeConstants::TerminalType_WxWork:
                        $terminal_string .= '企业微信、';
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
        if ($item['expiry_time']) {
            $item['expiry_time'] =date('Y.m.d',strtotime(explode(' ', $item['expiry_time'])[0]));
        }
        if ($item['receive_time']) {
            $item['receive_time'] =date('Y.m.d',strtotime(explode(' ', $item['receive_time'])[0])) ;
        }
        if ($item['effective_starttime']) {
            $item['effective_starttime'] =date('Y.m.d',strtotime($item['effective_starttime']));
        }
        return $item;
    }

}