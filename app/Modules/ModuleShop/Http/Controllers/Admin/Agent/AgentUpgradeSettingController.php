<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Libs\Product\Product;
use  App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use  App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting;
use  App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;

/**
 * 后台积分Controller
 * Class PointController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Point
 */
class AgentUpgradeSettingController extends BaseAdminController
{
    use DispatchesJobs;
    private $agentUpgradeSetting;

    /**
     * 初始化
     * CouponController constructor.
     */
    public function __construct()
    {
        $this->agentUpgradeSetting = new \App\Modules\ModuleShop\Libs\Agent\AgentUpgradeSetting();
        $this->agentLevel = new \App\Modules\ModuleShop\Libs\Agent\AgentLevel();
    }


    /**
     * 编辑代理设置
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            //这里需要用事务
            DB::beginTransaction();
            $agentUpgradeInfo['status']=$request->agentUpgrade['status'];
            $agentUpgradeInfo['order_valid_condition']=$request->agentUpgrade['order_valid_condition'];
            $agentUpgradeInfo['auto_upgrade'] = $request->input('agentUpgrade.auto_upgrade', 0);
            $this->agentUpgradeSetting->save($agentUpgradeInfo);
            $agentLevelInfo=$request->agentLevel;
            foreach ($agentLevelInfo as  &$v){
                $needProduct = false;
                foreach ($v['upgrade_condition']['upgrade'] as &$item){
                    if(in_array($item['type'],[Constants::AgentLevelUpgradeCondition_SelfOrderMoney,Constants::AgentLevelUpgradeCondition_DirectlyOrderMoney,Constants::AgentLevelUpgradeCondition_IndirectOrderMoney,Constants::AgentLevelUpgradeCondition_TeamOrderMoney,Constants::AgentLevelUpgradeCondition_TotalChargeMoney,Constants::AgentLevelUpgradeCondition_OnceChargeMoney]) && $item['value']){
                        $item['value']=moneyYuan2Cent($item['value']);
                    }
                    // 检测是否是需要商品的条件
                    if (!$needProduct && AgentUpgradeSetting::isNeedProductCondition($item)) {
                        $needProduct = true;
                    }
                }
                // 如果有需要商品的条件 检测商品id是否有效
                if ($needProduct
                    &&
                    (!$v['upgrade_condition']['product_id']
                        || !Product::hasActiveProduct(myToArray($v['upgrade_condition']['product_id']))
                    )
                ) {
                    throw new \Exception('请至少添加一个有效商品');
                }
                $v['upgrade_condition']=json_encode($v['upgrade_condition']);
            }
            $this->agentLevel->save($agentLevelInfo);
            DB::commit();
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            DB::rollBack();
            return makeApiResponseError($ex);
        }
    }

    /**
     * 查看详情
     * @param Request $request
     * @return array
     */
    public function getInfo()
    {
        try {
            $data= $this->agentUpgradeSetting->info();
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function upgradelevel(){
        $member_id=387;
        Agentor::upgradeRelationAgentLevel($member_id);
     //   $this->dispatch(new UpgradeAgentLevelJob($member_id));
    }

    public function getOrderAgentData(Request $request){
        $memberId=$request->member_id;
        $productid=$request->product_id;
        $agentUpgradeSetting = AgentUpgradeSetting::getCurrentSiteSetting();
        $orderStatue = $agentUpgradeSetting->order_valid_condition == 1 ? [Constants::OrderStatus_OrderFinished] : Constants::getPaymentOrderStatus();
        $siteId = $request->site_id ?: Site::getCurrentSite()->getSiteId();
        //如果代理升级是在维权期后的，需要把退款的钱减去
        $select = $agentUpgradeSetting->order_valid_condition == 1 ? 'sum( o.money + o.after_sale_money ) AS total' : 'sum(o.money) as total';
        // 直推订单金额
        $zhituisql = 'SELECT
                    ' . $select . ' 
                FROM
                    tbl_order o 
                    JOIN tbl_order_members_history as h1 on h1.order_id=o.id and h1.member_id=' . $memberId . ' and h1.`level`=1 and h1.type=0 
                    JOIN tbl_order_members_history as h2 on h2.order_id=o.id and h2.member_id=' . $memberId . ' and h2.type=1
                WHERE
                    o.site_id = '. $siteId .' 
                    AND o.`status` IN (' . implode(',', $orderStatue) . ')';

        $zhituicount = \DB::select($zhituisql);
        $zhituiOrder=moneyCent2Yuan($zhituicount[0]->total);

        // 间推订单金额
        $jiantuisql = 'SELECT
                    ' . $select . ' 
                FROM
                    tbl_order o 
                    JOIN tbl_order_members_history as h1 on h1.order_id=o.id and h1.member_id=' . $memberId . ' and h1.`level`>1 and h1.type=0 
                    JOIN tbl_order_members_history as h2 on h2.order_id=o.id and h2.member_id=' . $memberId . ' and h2.type=1
                WHERE
                    o.site_id = '. $siteId .' 
                    AND o.`status` IN (' . implode(',', $orderStatue) . ')';

        $jiantuicount = \DB::select($jiantuisql);
        $jiantuiOrder=moneyCent2Yuan($jiantuicount[0]->total);

        // 团队交易金额
        $teamsql = 'SELECT  
                    ' . $select . ' 
                    FROM
                    `tbl_order_members_history` AS omh
                    JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id` AND `o`.`status` IN (' . implode(',', $orderStatue) . ') 
                    WHERE
                    omh.site_id='. $siteId .' 
                    AND omh.`level` >=0
                    AND omh.member_id = ' . $memberId . '
                    AND omh.type = 1';

        $teamcount = \DB::select($teamsql);
        $teamOrder=moneyCent2Yuan($teamcount[0]->total);

        $productselect = $agentUpgradeSetting->order_valid_condition == 1 ? 'oi.num - oi.after_sale_over_num' : 'oi.num';

        // 直推购买指定商品数量
        $zhituiproductsql = 'SELECT
	                sum( ' . $productselect . ' ) as count 
                    FROM
                        tbl_order_item oi
                        JOIN tbl_order AS o ON oi.order_id = o.id 
                        AND o.`status` IN (' . implode(',', $orderStatue) . ') 
                    WHERE
                        oi.site_id='. $siteId .' 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND `level` = 1 AND type = 0 ) 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND type = 1 ) 
                        AND oi.product_id IN (' . $productid . ')';

        $zhituiproductcount = \DB::select($zhituiproductsql);
        $zhituiproduct= $zhituiproductcount[0]->count ;


        // 间推购买指定商品数量
        $jiantuiproductsql = 'SELECT
	                sum( ' . $productselect . ' ) as count 
                    FROM
                        tbl_order_item oi
                        JOIN tbl_order AS o ON oi.order_id = o.id 
                        AND o.`status` IN (' . implode(',', $orderStatue) . ') 
                    WHERE
                        oi.site_id='. $siteId .' 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND `level` > 1 AND type = 0 ) 
                        AND order_id IN ( SELECT order_id FROM tbl_order_members_history WHERE member_id = ' . $memberId . ' AND type = 1 ) 
                        AND oi.product_id IN (' . $productid . ')';

        $jiantuicount = \DB::select($jiantuiproductsql);
        $jiantuiproduct= $jiantuicount[0]->count ;

        // 团队购买指定商品数量
        $teamproductsql = 'select 
                    sum( ' . $productselect . ' ) as count 
                    FROM 
                    `tbl_order_members_history` AS omh 
                    JOIN `tbl_order` AS `o` ON `o`.`id` = `omh`.`order_id` AND `o`.`status` IN (' . implode(',', $orderStatue) . ') 
                    JOIN `tbl_order_item` AS oi ON oi.order_id = o.id AND `oi`.`product_id` IN (' . $productid . ') 
                    WHERE 
                    omh.site_id='. $siteId .' 
                    AND omh.`level` >=0
                    AND omh.member_id = ' . $memberId . '
                    AND omh.type = 1';
        $teamproductcount = \DB::select($teamproductsql);
        $teamproduct= $teamproductcount[0]->count ;

        // 直推团队人数
        $memberCount = MemberParentsModel::query()->where('site_id', $siteId)
            ->where('parent_id', $memberId)
            ->selectRaw('sum(if(`level`=1,1,0)) as zhitui, sum(if(`level`>1,1,0)) as jiantui')
            ->first();
        // 间推分销商和直推分销商
        $distributorCount = MemberParentsModel::query()->from('tbl_member_parents as p')
            ->join('tbl_distributor as dis', function ($join) {
                $join->on('dis.member_id', 'p.member_id')
                    ->where('dis.status', 1)
                    ->where('dis.is_del', 0);
            })
            ->where('p.site_id', $siteId)
            ->where('p.parent_id', $memberId)
            ->selectRaw('sum(if(p.`level`=1,1,0)) as zhitui, sum(if(p.`level`>1,1,0)) as jiantui')
            ->first();
        echo '直推订单金额：'.$zhituiOrder.'<br /> 间推订单金额：'.$jiantuiOrder.
            '<br />团队订单金额：'.$teamOrder.'<br />直推指定商品：'.$zhituiproduct.
            '<br />间推指定商品：',$jiantuiproduct,'<br />团队指定商品：'.$teamproduct.
            '<br />直推团队成员数：'.$memberCount['zhitui'].'<br />间推团队成员数：'.$memberCount['jiantui'].
            '<br />直推分销人数：'.$distributorCount['zhitui'].'<br />间推分销人数：'.$distributorCount['jiantui'];
    }
}