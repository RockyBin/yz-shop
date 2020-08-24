<?php

namespace App\Modules\ModuleShop\Libs\Coupon;

use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\CouponModel;
use \App\Modules\ModuleShop\Libs\Member\Member;
use \App\Modules\ModuleShop\Libs\Member\MemberLevel;
use \App\Modules\ModuleShop\Libs\Coupon\CouponItem;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants as CodeConstants;

/**
 * 优惠券类
 * Class Coupon
 * @package App\Modules\ModuleShop\Libs\Coupon
 */
class Coupon
{
    private $siteID = 0; // 站点ID
    private $couponEntity = null;
    private $couponItemEntity = null;
    private $proClass = null;

    /**
     * 初始化
     * Point constructor.
     */
    public function __construct()
    {
        $this->siteID = Site::getCurrentSite()->getSiteId();
        $this->couponEntity = new CouponModel();
        $this->couponItemEntity = new CouponItem();
    }

    /**
     * 添加优惠卷
     * @param array $info，设置信息，对应 CouponModel 的字段信息
     */
    public function add(array $info)
    {
        $this->couponEntity->fill($info);
        $this->couponEntity->site_id = Site::getCurrentSite()->getSiteId();
        if ($info['terminal_type']) {
            $this->couponEntity->terminal_type = ',' . $info['terminal_type'] . ',';
        }
        if ($info['product_type'] == 1) {
            $this->couponEntity->product_info = ',' . $info['product_info'] . ',';
        }
        if ($info['effective_type'] == 0 && $info['effective_endtime']) {
            $this->couponEntity->effective_endtime = strtotime($info['effective_endtime']);
        }
        if ($info['coupon_type'] == 0) {
            $this->couponEntity->coupon_money = moneyYuan2Cent(intval($info['coupon_money']));
        }
        $this->couponEntity->doorsill_full_money = moneyYuan2Cent(intval($info['doorsill_full_money']));

        $this->couponEntity->save();
    }


    /**
     * 编辑优惠卷
     * @param array $info，设置信息，对应 CouponModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = $this->couponEntity::where(['site_id' => $this->siteID, 'id' => $info['id']])->first();
        foreach ($info as $key => $val) {
            $model->$key = $val;
        }
        if ($info['amount_type'] == 1) {
            $model->amount = $info['amount'];
        }
        if ($info['receive_limit_type'] == 1) {
            $model->receive_limit_num = $info['receive_limit_num'];
        }
        if ($info['effective_type'] == 0 && $info['effective_endtime']) {
            $model->effective_endtime = strtotime($info['effective_endtime']);
        }
        if ($info['coupon_type'] == 0) {
            $model->coupon_money = moneyYuan2Cent(intval($info['coupon_money']));
        }
        if ($info['terminal_type']) {
            $model->terminal_type = ',' . $info['terminal_type'] . ',';
        }
        $model->doorsill_full_money = moneyYuan2Cent(intval($info['doorsill_full_money']));

        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }


    /**
     * 更改优惠券状态
     * @param array $info，设置信息，对应 CouponModel 的字段信息
     */
    public function editStatus(array $info)
    {
        $model = $this->couponEntity::where(['id' => $info['id'], 'site_id' => $this->siteID])->first();
        $model->status = $info['status'];
        DB::beginTransaction();
        try {
            $model->save();
            if ($model->status == 0) {
                $this->couponItemEntity->batchEdit(['coupon_id' => $info['id'], 'status' => Constants::CouponStatus_NoUse], ['status' => 0]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 删除指定ID的优惠券
     * @param $coupon_id
     */
    public function delete($coupon_id)
    {
        $coupon = $this->couponEntity::where(['id' => $coupon_id, 'site_id' => $this->siteID])->first();
        $coupon->is_delete = 1;
        // 同步删除直播的优惠卷
        $coupon->couponLive()->delete();
        return $coupon->save();
    }

    /**
     * 测试此ID是否在使用
     * @param $coupon_id
     */
    public function checkHaveUse($coupon_id)
    {
        return $this->couponEntity::where(['id' => $coupon_id, 'site_id' => $this->siteID, 'status' => 1])->count();
    }

    /**
     * 检测数据的方法，目前主要检测数据是否存在一样的数据
     * @param array $info，设置信息，对应 CouponModel 的字段信息
     */
    public function dataCheck(array $info)
    {
        //不需要检测时间
        unset($info['effective_type']);
        unset($info['effective_starttime']);
        unset($info['effective_endtime']);
        if ($info['terminal_type']) {
            $arr = explode(',', $info['terminal_type']);
            sort($arr, 1);
            $info['terminal_type'] = implode(',', $arr);
        }
        if ($info['coupon_type'] == 0) {
            $info['coupon_money'] = moneyYuan2Cent($info['coupon_money']);
        }
        if ($info['doorsill_type'] == 1) {
            $info['doorsill_full_money'] = moneyYuan2Cent($info['doorsill_full_money']);
        }
        $expression = $this->getExpression($info);
        $count = $expression->count();
        //若存在一样的话，则返回false
        if ($count != 0) {
            return false;
        }
        return true;
    }

    /**
     * 获取某个优惠券的信息
     * @param int $id，优惠券ID
     */
    public function getInfo(int $id)
    {
        $data['info'] = $this->couponEntity::find($id);
        $data['info']->terminal_type = ltrim(rtrim($data['info']->terminal_type, ','), ',');
        if ($data['info']->effective_type == 0) {
            $data['info']->effective_endtime = date('Y-m-d', $data['info']->effective_endtime);
        }
        //输出符合前端的数据结构
        if ($data['info']->product_type == 1) {
            $procuct_info = ltrim(rtrim($data['info']->product_info, ','), ',');
            $where[] = ['site_id', '=', $this->siteID];
            $where[] = ['status', '=', '1'];
            $expand_expression = 'IF((select id from tbl_product_class as p2 where id in (' . $procuct_info . ') and site_id = ' . $this->siteID . ' and p1.id=p2.id),true,false) as checked ';
            $product_data = \DB::table('tbl_product_class as p1')->where($where)->orderBy('order')->select(\DB::raw('p1.id,p1.class_name as title,p1.parent_id,' . $expand_expression))->get();
            $data['info']->product_info = $this->getProductClassChild($product_data);
        }

        if ($data['info']->product_type == 2)
        {
            $procuct_info = ltrim(rtrim($data['info']->product_info, ','), ',');

            $productM = \App\Modules\ModuleShop\Libs\Model\ProductModel::query()->with(['productClass' => function($query){
                $query->select(['tbl_product_class.id','tbl_product_class.class_name']);
            }]);

            $res = $productM->whereIn('id', explode(',', $procuct_info))->get(['id','name','price','small_images','status']);

            if ($res->isNotEmpty())
            {
                $res = $res->map(function($model, $key){

                    $model->price = number_format2($model->price / 100,2);

                    $class_name = $model->productClass->pluck('class_name')->toArray();

                    $arr = $model->toArray();

                    $arr['product_class'] = implode(',',$class_name);

                    return $arr;
                });
            }

            $data['info']->product_info = $res;
        }

        if ($data['info']['coupon_type'] == 0) {
            $data['info']['coupon_money'] = intval(moneyCent2Yuan($data['info']['coupon_money']));
        } else {
            $data['info']['coupon_money'] = $data['info']['coupon_money'];
        }
        $data['info']['doorsill_full_money'] = intval(moneyCent2Yuan($data['info']['doorsill_full_money']));
        //输出已领取的优惠券
        $data['info']['have_received'] = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'coupon_id' => $id])->count();
        return $data;
    }

    /**
     * 获取有效的产品分类
     */
    public function getProductClass()
    {
        $where[] = ['site_id', '=', $this->siteID];
        $where[] = ['status', '=', '1'];
        $data = \DB::table('tbl_product_class')->where($where)->orderBy('order')->select(['id', 'class_name as title', 'parent_id'])->get();
        $productClass = $this->getProductClassChild($data);
        return $productClass;
    }

    public function getProductClassChild($data)
    {
        $parent_arr = [];
        $child_arr = [];

        foreach ($data as $k => $v) {
            if ($v->parent_id == 0) {
                $parent_arr[] = $v;
            } else {
                $child_arr[] = $v;
            }
        }
        foreach ($child_arr as $k1 => $v1) {
            foreach ($parent_arr as $k2 => $v2) {
                if ($v1->parent_id == $v2->id) {
                    $parent_arr[$k2]->children[] = $v1;
                }
            }
        }
        return $parent_arr;
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
        //显示未删除的
        $params['is_delete'] = 0;
        // 查询表达式
        $expression = $this->getExpression($params);
        //输出-已领取
        $have_received_field = '(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id) AS have_received';
        //输出-某会员领取数 没会员的时候就应该是0
        $member_received_field = "(0) as member_received";
        $member_canuse_field = "(0) as member_canuse"; //会员可使用的数量（已领取，未使用并且未过期）
        if ($params['member_id']) {
            $member_received_field = '(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id and tbl_coupon_item.member_id = ' . $params['member_id'] . ') AS member_received';
            if ($params['count_member_canuse']) {
                $member_canuse_field = '(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id and tbl_coupon_item.member_id = ' . $params['member_id'] . ' and tbl_coupon_item.status = 2 and tbl_coupon_item.expiry_time > NOW() ) AS member_canuse';
            }
        }
        //输出-已使用
        $already_used_field = "(0) as already_used";
        if ($params['count_used']) {
            $already_used_field = '(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id and tbl_coupon_item.status=1) AS already_used';
        }
        //输出-被领取但未使用
        $received_nouse_field = "(0) as received_nouse";
        if ($params['count_nouse']) {
            $received_nouse_field = '(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id and tbl_coupon_item.status=2) AS received_nouse';
        }

        $expression = $expression->select(\DB::raw('*,' . $have_received_field . ',' . $already_used_field . ',' . $received_nouse_field . ',' . $member_received_field . ',' . $member_canuse_field));
        if ($params['ids']) {
            $expression = $expression->whereIn('id', $params['ids']);
            //$isShowAll = true;
        }

        //having过滤，只支持raw查询字符串
        if ($params['having']) {
            $expression = $expression->havingRaw($params['having']);
        }

        //输出总数
        if ($params['having']) {
            $bindings = $expression->getBindings();
            $sql = str_replace('?', '%s', $expression->toSql());
            $sql = sprintf($sql, ...$bindings);
            $res = DB::select('select count(1) as rows from (' . $sql . ') as tbl');
            $total = $res[0]->rows;
        } else $total = $expression->count();

        //if ($isShowAll) {
        if ($params['show_all']) {
            $page_size = $total > 0 ? $total : 1;
            $page = 1;
        }

        $expression = $expression->forPage($page, $page_size);


        //输出-最后页数
        $last_page = ceil($total / $page_size);
        //输出-列表数据
        if (isset($params['order_by'])) {
            //特殊要求
            $expression->orderByRaw($params['order_by']);
        } else {
            $expression->orderby('coupon.id', 'desc');
        }
        $list = $expression->get();

        foreach ($list as $k => &$v) {
            //输出-可领取=总数量-已领取
            if ($v['amount_type'] == 0) {
                $v['can_received'] = -1;
            } else {
                $v['can_received'] = (intval($v['amount']) - intval($v['have_received'])) >= 0 ? (intval($v['amount']) - intval($v['have_received'])) : 0;
            }
            //输出-使用率=已使用/已领取
            $v['use_rate'] = (intval($v['have_received']) <= 0 ? 0 : ceil((intval($v['already_used']) / intval($v['have_received'])) * 100)) . '%';

            /**
             * 领取状态
             * 1：可领取（但此会员没有这样优惠券）
             * 2：可领取（但此会员拥有这张优惠券，并且是可领取的）
             * 3：已领取（只有会员登陆状态下，才有这种状态，此会员领取此优惠券的数量大于优惠券会员限制领取数）
             * 4. 已抢光（优惠券总数量有限制，已经可领取数少于等于0）
             * 先判断已抢光，再判断已领取，剩下就是都可以领取的
             */
            //如果有会员的情况下
            if ($v['amount_type'] == 1 && $v['can_received'] <= 0) {
                $v['received_status'] = 4;
            } else {
                if ($params['member_id']) {
                    //$member_coupon_count = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'member_id' => $params['member_id'], 'coupon_id' => $v['id']])->count();
                    $member_coupon_count = $v['member_received'];
                    //已拥有的优惠券数量
                    $v['member_coupon_count'] = $member_coupon_count;
                    if ($v['receive_limit_type'] == 1 && $member_coupon_count >= $v['receive_limit_num']) {
                        $v['received_status'] = 3;
                    } else if ($member_coupon_count > 0) {
                        $v['received_status'] = 2;
                    } else {
                        $v['received_status'] = 1;
                    }
                } else {
                    $v['received_status'] = 1;
                }
            }
            $v = $this->convertOutputData($v);
        }

        return [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];

    }

    /**
     * 输出表达式供getList以及dataCheck使用
     * @param array $params 参数
     * @return $expression
     */
    public function getExpression(array $params)
    {
        $expression = CouponModel::from('tbl_coupon as coupon')
            ->where([
                ['coupon.site_id', $this->siteID]
            ]);

        if ($params['id']) {
            $expression = $expression->where('id', intval($params['id']));
        }

        // 标题
        if ($params['title']) {
            $expression = $expression->where('coupon.title', 'like', '%' . $params['title'] . '%');
        }
        // 过滤失效的
        if ($params['valid'] == 1) {
            $expression = $expression->whereRaw('coupon.status = 1 AND (effective_type = 1 OR (effective_type = 0 AND effective_endtime > ' . time() . '))');
        }
        // 终端显示
        if (($params['terminal_type'] || $params['terminal_type'] === '0') && $params['terminal_type'] != -1) {
            $expression = $expression->where('coupon.terminal_type', 'like', '%,' . $params['terminal_type'] . ',%');
        }
        //使用门槛
        if ($params['doorsill_type']) {
            $expression = $expression->where('coupon.doorsill_type', intval($params['doorsill_type']));
        }
        //门槛，订单金额满XX元
        if ($params['doorsill_type'] == '1' && $params['doorsill_full_money']) {
            $expression = $expression->where('coupon.doorsill_full_money', intval($params['doorsill_full_money']));
        }
        //优惠卷类型
        if (($params['coupon_type'] || $params['coupon_type'] === '0') && $params['coupon_type'] != -1) {
            $expression = $expression->where('coupon.coupon_type', intval($params['coupon_type']));
        }
        //优惠券金额
        if ($params['coupon_money']) {
            $expression = $expression->where('coupon.coupon_money', intval($params['coupon_money']));
        }
        //有效期类型
        if ($params['effective_type']) {
            $expression = $expression->where('coupon.effective_type', intval($params['effective_type']));
        }

        // 时间开始
        if ($params['effective_starttime']) {
            $expression = $expression->where('coupon.effective_starttime', '>=', $params['effective_starttime']);
        }
        // 时间结束
        if ($params['effective_endtime']) {
            //$expression = $expression->where('coupon.effective_endtime', '<=', strtotime($params['effective_endtime']));
            $expression = $expression->whereRaw("(coupon.effective_type = 1 OR (coupon.effective_type = 0 AND coupon.effective_endtime <= '" . strtotime($params['effective_endtime']) . "'))");
        }
        // 过期时间在某日期以后的
        if ($params['expiry_time']) {
            $expression = $expression->whereRaw("(coupon.effective_type = 1 OR (coupon.effective_type = 0 AND coupon.effective_endtime >= '" . strtotime($params['expiry_time']) . "'))");
        }
        // 指定用户群
        if ($params['member_type']) {
            $expression = $expression->where('coupon.member_type', intval($params['member_type']));
        }
        //指定商品
        if ($params['product_type']) {
            $expression = $expression->where('coupon.product_type', intval($params['product_type']));
        }
        //指定商品信息
        if ($params['product_info'] && $params['product_type'] == 1) {
            $expression = $expression->where('coupon.product_info', 'like', '%,' . $params['product_info'] . ',%');
        }
        // 状态
        if (($params['status'] || $params['status'] === '0') && $params['status'] != -1) {
            $expression = $expression->where('coupon.status', intval($params['status']));
        }
        //是否已经删除
        if (is_numeric($params['is_delete'])) {
            $expression = $expression->where('coupon.is_delete', intval($params['is_delete']));
        }
        if (isset($params['receivie_status'])) {
            $expression = $expression->where('coupon.receivie_status', intval($params['receivie_status']));
        }
        return $expression;
    }

    /**
     * 根据条件，返回优惠券记录数据
     * $param 查询条件
     * @return $list
     */
    function getCouponItem($param)
    {
        //只查询未使用，已锁定
        $param['status'] = Constants::CouponStatus_NoUse . ',' . Constants::CouponStatus_NoUse;
        return $this->couponItemEntity->getAffectCounponItem($param);
    }

    /**
     * 检测发放的会员数量是否大于优惠券的数量
     * $param 查询条件
     * id 优惠券id
     * member_id 会员id
     * level_id 会员等级id
     * type  0：所有会员 1：会员等级 2：会员
     * @return boolean
     */
    function checkCouponAmount($param)
    {
        //可领取的优惠券
        $can_received_field = '(CAST(amount as SIGNED)-(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id)) AS can_received';
        $coupon_expression = $this->getExpression($param);
        $coupon_expression = $coupon_expression->select(\DB::raw('*,' . $can_received_field));
        $coupon_data = $coupon_expression->first();
        if ($coupon_data['receive_limit_type'] == 0 && $coupon_data['amount_type'] == 0) {
            return makeServiceResult(200, 'ok');
        }
        $coupon_count = $coupon_data['amount_type'] == 0 ? 99999999 : $coupon_data['can_received'];

        $over = false;//标识是否用户超出限领数量
        if ($coupon_data['receive_limit_type'] == 1) {
            //判断是否有会员超出限领张数
            //找出所有的会员符合条件的会员ID
            if ($param['type'] == 0) {
                $member_id = \DB::table('tbl_member')->where(['site_id' => $this->siteID])->select(['id'])->get()->toArray();
            } elseif ($param['type'] == 1) {
                $member_id = \DB::table('tbl_member')->where(['site_id' => $this->siteID])->whereIn('level', explode(',', $param['level_id']))->select(['id'])->get()->toArray();
            } elseif ($param['type'] == 2) {
                $member_id = \DB::table('tbl_member')->where(['site_id' => $this->siteID])->whereIn('id', explode(',', $param['member_id']))->select(['id'])->get()->toArray();
            }

            foreach ($member_id as $k => $v) {
                if ($over) {
                    break;
                }
                $params['member_id'] = $v->id;
                $params['coupon_id'] = $param['id'];
                $member_received_count = $this->couponItemEntity->getMemberCouponItem($params);
                if ($member_received_count >= $coupon_data['receive_limit_num']) {
                    $over = true;
                }
            }
        }
        $member_expression = $this->getMemberExpression($param);
        if ($param['type'] == 0) {
            $member_count = $member_expression->count();
            if ($coupon_count < $member_count || $over) {
                return makeServiceResult(511, '发放的会员数量大于优惠券的数量或者发放会员超出优惠券可领取数量', ['member_count' => $member_count, 'coupon_count' => $coupon_count, 'over' => $over]);
            }
        } else if ($param['type'] == 1) {
            $member_count = $member_expression->count();
            if ($coupon_count < $member_count || $over) {
                return makeServiceResult(511, '发放的会员数量大于优惠券的数量', ['member_count' => $member_count, 'coupon_count' => $coupon_count, 'over' => $over]);
            }
        } else if ($param['type'] = 2) {
            $member_count = $member_expression->count();
            if ($coupon_count < $member_count || $over) {
                return makeServiceResult(511, '发放的会员数量大于优惠券的数量', ['member_count' => $member_count, 'coupon_count' => $coupon_count, 'over' => $over]);
            }
        }
        return makeServiceResult(200, 'ok');
    }

    /**
     * 根据条件，返回优惠券记录数据
     * $param
     * id 优惠券id
     * member_id 会员id
     * level_id 会员等级id
     * type  0：所有会员 1：会员等级 2：会员
     * @return $list
     */
    function addCouponItem($param)
    {
        $member = $this->getMemberExpression($param)->select(['id'])->orderBy('id', 'desc')->get();
        $add_param = [];
        $can_received_field = '(CAST(amount as SIGNED)-(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id)) AS can_received';
        $coupon = $this->getExpression(['id' => $param['id']])->select(\DB::raw('*,' . $can_received_field))->get();
        $coupon_data = $coupon->toArray();
        $num = 0;
        foreach ($member as $k => $v) {
            if ($coupon_data[0]['amount_type'] != 0) {
                $num++;
                if ($num > $coupon_data[0]['can_received']) {
                    break;
                }
            }
            //选中的用户已达到限领张数时，则不发送
            $already_member = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'member_id' => $v->id, 'coupon_id' => $param['id']])->count();
            if ($coupon_data[0]['receive_limit_type'] == 1 && ($already_member >= $coupon_data[0]['receive_limit_num'])) {
                continue;
            }
            $add_param[$k]['site_id'] = $this->siteID;
            $add_param[$k]['member_id'] = $v->id;
            $add_param[$k]['code'] = $this->couponEntity::genUuid(8);
            if ($coupon_data[0]['effective_type'] == 1) {
                $add_param[$k]['expiry_time'] = date('Y-m-d', strtotime('+' . $coupon_data[0]['effective_endtime'] . ' day')) . ' 23:59:59';
                $add_param[$k]['start_time'] = date('Y-m-d', time());
            } else {
                $add_param[$k]['expiry_time'] = date('Y-m-d', $coupon_data[0]['effective_endtime']) . ' 23:59:59';
                $add_param[$k]['start_time'] = date('Y-m-d', strtotime($coupon_data[0]['effective_starttime']) . ' 00:00:00');
            }
            $add_param[$k]['coupon_id'] = $param['id'];
            $add_param[$k]['status'] = 2;
            $add_param[$k]['receive_time'] = date("Y-m-d H:i:s");
            if (!$param['system_send_coupon']) {
                $add_param[$k]['receive_terminal_type'] = getCurrentTerminal();
            }
        }
        return $this->couponItemEntity->batchAdd($add_param);
    }

    /**
     * 返回会员查询表达式
     * $param 查询条件
     * @return $Expression
     */
    private function getMemberExpression($param)
    {
        $member_expression = \DB::table('tbl_member')->where(['site_id' => $this->siteID]);
        if ($param['type'] == 1) {
            $level = explode(',', $param['level_id']);
            $member_expression = $member_expression->whereIn('level', $level);
        } elseif ($param['type'] == 2) {
            $level = explode(',', $param['member_id']);
            $member_expression = $member_expression->whereIn('id', $level);
        }
        return $member_expression;
    }

    /**
     * 返回会员列表
     * $param 查询条件
     * @return $list
     */
    public function getMemberlist(array $param)
    {
        $memberEntity = new Member(0, $this->siteID);
        return $memberEntity->getlist($param);
    }

    /**
     * 返回会员等级列表
     * @return $list
     */
    public function getMemberLevel()
    {
        $memberLevelEntity = new MemberLevel();
        return $memberLevelEntity->getList();
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertOutputData($item)
    {
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
                $item['terminal_type'] = rtrim($terminal_string, '、');
            }
        }

        if ($item['effective_type'] == 0) {
            $item['effective_time'] = date('Y.m.d', strtotime($item['effective_starttime'])) . '-' . date('Y.m.d', $item['effective_endtime']);
        } else if ($item['effective_type'] == 1) {
            $item['effective_time'] = '领取后' . $item['effective_endtime'] . '天内';
        }

        if ($item['member_type'] !== null) {
            //目前只有一种用户群
            $item['member_type'] = '所有用户';
        }

        //分析优惠券可用分类
        if (!$this->proClass) {
            $this->proClass = DB::table('tbl_product_class')
                ->where('site_id', '=', getCurrentSiteId())
                ->get();
        }

        if ($item['product_type'] !== null) {
            if ($item['product_type'] == 0) {
                $item['product_info'] = '全场商品';
            } else if ($item['product_type'] == 1) {
                $product_class = explode(',', $item['product_info']);
                //如果拥有父类，则删除所有子类，只显示父类
                $parentClass = $this->proClass
                    ->where('parent_id', '=', 0)
                    ->whereIn('id', $product_class)
                    ->values();
                //子类ID
                $subClass = $this->proClass
                    ->where('parent_id', '<>', 0)
                    ->whereIn('id', $product_class)
                    ->values();
                //剔除掉已选父类的子类
                $product_class_new = [];
                if ($subClass) {
                    foreach ($subClass as $k => $v) {
                        $parentExists = $parentClass->where('id', '=', $v->parent_id)->first();
                        if (!$parentExists) {
                            array_push($product_class_new, $v->id);
                        }
                    }
                }
                if ($parentClass) {
                    foreach ($parentClass as $k => $v) {
                        array_push($product_class_new, $v->id);
                    }
                }
                //输出产品信息数据
                $data = $this->proClass
                    ->whereIn('id', $product_class_new)
                    ->pluck('class_name')->toArray();
                $item['product_info'] = implode(',', $data);
            }else if ($item['product_type'] == 2)
            {
                $item['product_info'] = '指定商品';
            }
        }

        if ($item['coupon_type'] == 0) {
            $item['coupon_money'] = moneyCent2Yuan(intval($item['coupon_money']));
            $item['coupon_money'] = preg_replace('/\.00?0?/', '', $item['coupon_money']);
        }

        $item['doorsill_full_money'] = moneyCent2Yuan(intval($item['doorsill_full_money']));
        $item['doorsill_full_money'] = preg_replace('/\.00?0?/', '', $item['doorsill_full_money']);

        return $item;
    }


    /**
     * 筛选某个会员，某个产品下，可领取的优惠券，以及已领取的优惠券。
     * @param
     * product_id:产品ID
     * @return mixed
     */
    public function couponProduct($param)
    {
        $product_class = \DB::table('tbl_product_relation_class')->where(['site_id' => $this->siteID, 'product_id' => $param['product_id']])->select('class_id')->get()->toArray();
        $new_producy_class = [];
        foreach ($product_class as $k => $v) {
            array_push($new_producy_class, $v->class_id);
        }
        if (!count($new_producy_class)) $new_producy_class = ['nono']; //当 $new_producy_class 为空时，保证后面不会出错
        $expression = $this->getExpression($param);
        $new_producy_class[] = $param['product_id'];
        //所有可领的优惠券
        $can_received_field = '(CAST(amount AS SIGNED)-(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id)) AS can_received';
        $expression = $expression->whereRaw("(product_info REGEXP '" . implode('|', $new_producy_class) . "' or product_info is Null)");
        $expression->where('receivie_status', 1);
        //所有可领的优惠券
        $expression = $expression->addSelect(\DB::raw('*,' . $can_received_field));

        $coupon_data = $expression->get()->toArray();
        /**
         * 领取状态
         * 1：可领取（但此会员没有这样优惠券）
         * 2. 可领取（但此会员拥有这张优惠券，并且是可领取的）
         * 3：已领取（只有会员登陆状态下，才有这种状态，此会员领取此优惠券的数量大于优惠券会员限制领取数）
         * 4. 已抢光（优惠券总数量有限制，已经可领取数少于等于0）
         * 先判断已抢光，再判断已领取，剩下就是都可以领取的
         */
        foreach ($coupon_data as $k => &$v) {
            //如果有会员的情况下
            if ($v['amount_type'] == 1 && $v['can_received'] <= 0) {
                $v['received_status'] = 4;
            } else {
                if ($param['member_id']) {
                    $member_coupon_count = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'member_id' => $param['member_id'], 'coupon_id' => $v['id']])->count();
                    //已拥有的优惠券数量
                    $v['member_coupon_count'] = $member_coupon_count;
                    if ($v['receive_limit_type'] == 1 && $member_coupon_count >= $v['receive_limit_num']) {
                        $v['received_status'] = 3;
                    } else if ($member_coupon_count > 0) {
                        $v['received_status'] = 2;
                    } else {
                        $v['received_status'] = 1;
                    }
                } else {
                    $v['received_status'] = 1;
                }
            }
            if ($v['effective_type'] == 0) {
                if ($v['effective_endtime'] < time()) {
                    unset($coupon_data[$k]);
                } else {
                    $v['effective_endtime'] = date('Y-m-d', $v['effective_endtime']);
                }
            }
            if ($v['product_type'] == 1) {
                $product_class = explode(',', $v['product_info']);

                //如果拥有父类，则删除所有子类，只显示父类
                $parent_id = \DB::table('tbl_product_class')
                    ->where('site_id', '=', $this->siteID)
                    ->where('parent_id', '=', 0)
                    ->whereIn('id', $product_class)
                    ->select((\DB::raw('GROUP_CONCAT(id) as id')))
                    ->first();
                //子类ID
                $son_id = \DB::table('tbl_product_class')
                    ->where('site_id', '=', $this->siteID)
                    ->where('parent_id', '<>', 0)
                    ->whereIn('id', $product_class)
                    ->get();
                //剔除掉已选父类的子类
                $product_class_new = [];
                foreach ($son_id as $k1 => $v1) {
                    if (strpos($parent_id->id, (string)$v1->parent_id) === false) {
                        array_push($product_class_new, $v1->id);
                    }
                }
                if ($parent_id->id) {
                    $product_class_new = array_merge($product_class_new, explode(',', $parent_id->id));
                }

                //输出产品信息数据
                $data = \DB::table('tbl_product_class')
                    ->where('site_id', '=', $this->siteID)
                    ->whereIn('id', $product_class_new)
                    ->select((\DB::raw('GROUP_CONCAT(class_name) as class_name')))
                    ->first();
                $v['product_info'] = $data->class_name;
            } elseif ($v['product_type'] == 2)
            {
                $v['product_info'] = '指定商品';
            }else {
                $v['product_info'] = "全场通用";
            }
            if ($v['coupon_type'] == 0) {
                $v['coupon_money'] = intval($v['coupon_money']) / 100;
            }
            if ($v['doorsill_type'] == 1) {
                $v['doorsill_full_money'] = intval($v['doorsill_full_money']) / 100;
            }
            if ($v['terminal_type'] != '') {
                $v['terminal_type'] = ltrim(rtrim($v['terminal_type'], ','), ',');
                $terminal_type = explode(',', $v['terminal_type']);
                $terminal_string = '';
                foreach ($terminal_type as $k => $item) {
                    switch ($item) {
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
                    $v['terminal_type'] = rtrim($terminal_string, '、');
                }
            }
        }
        //按照状态进行排序，可领取的排前面。
        array_multisort(array_column($coupon_data, 'received_status'), SORT_ASC, $coupon_data);
        return $coupon_data;
    }

    /**
     * receivedCoupon 优惠券领取接口
     * @param
     * member_id 会员ID
     * coupon_id 优惠券ID
     * @return mixed
     */
    public function receivedCoupon($params)
    {
        $member_id = $params['member_id'];
        //判断这张优惠券这个会员是否还可以领
        $expression = $this->getExpression(['id' => $params['coupon_id']]);
        //所有可领的优惠券  优惠券总数-已领取的优惠券数目=可领优惠券的数目
        $can_received_field = '(CAST(amount AS SIGNED)-(SELECT count(*) from tbl_coupon_item where tbl_coupon_item.coupon_id=coupon.id)) AS can_received';
        $coupon_data = $expression->select(\DB::raw('*,' . $can_received_field))->first()->toArray();
        //该会员已领取优惠券的数目
        $member_coupon_count = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'member_id' => $member_id, 'coupon_id' => $coupon_data['id']])->count();
        // 领取总数量是无限 && 总数少于0
        if ($coupon_data['amount_type'] == 1 && $coupon_data['can_received'] <= 0) {
            return makeServiceResult(511, '优惠券已被抢光');
        }
        // 会员领取数量是有限的 && 会员领取数量已经达到领取数量了
        if (($member_id && $coupon_data['receive_limit_type'] == 1 && $member_coupon_count >= $coupon_data['receive_limit_num'])) {
            return makeServiceResult(514, '您已领取过啦');
        }
        if ($coupon_data['status'] == 0 || ($coupon_data['effective_type'] == 0 && time() > $coupon_data['effective_endtime'])) {
            return makeServiceResult(513, '优惠券失效');
        }
        $locker = new \YZ\Core\Locker\Locker($params['coupon_id']);
        if (!$locker->lock()) {
            return makeServiceResult(500, 'can not init coupon locker');
        }
        $this->addCouponItem(['type' => 2, 'member_id' => $member_id, 'id' => $params['coupon_id']]);
        $locker->unlock();
        //发放之后，再次检测此张优惠券的总数
        $member_coupon_again_count = \DB::table('tbl_coupon_item')->where(['site_id' => $this->siteID, 'member_id' => $member_id, 'coupon_id' => $coupon_data['id']])->count();
        if ($member_id && $coupon_data['receive_limit_type'] == 1 && $member_coupon_again_count >= $coupon_data['receive_limit_num']) {
            //用户已经领取成功，但此张优惠券已经无法再领取了
            return makeServiceResult(201, '已领取成功');
        }
        return makeServiceResult(200, '发放成功');
    }


}