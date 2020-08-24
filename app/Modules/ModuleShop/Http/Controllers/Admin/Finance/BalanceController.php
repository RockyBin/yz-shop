<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Finance;

use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Common\Export;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Finance\Balance;
use Illuminate\Support\Collection;

class BalanceController extends BaseAdminController
{
    private $siteId = 0;
    private $balance;
    private $level_china = ['1' => '一', '2' => '二', '3' => '三'];

    /**
     * 初始化
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->balance = New balance($this->siteId);
    }

    /**
     * 结算列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $param['after_sale_detail'] = true;
            $param['show_distribution_level'] = true;
            //$param['commission']=true;

            //1:待结算 2：结算失败 3：结算成功
            if ($param['balance_status'] == 1) {
                $param['commission'] = [1];
                //  $param['status']=[Constants::OrderStatus_OrderPay,Constants::OrderStatus_OrderSend,Constants::OrderStatus_OrderReceive,Constants::OrderStatus_OrderSuccess];
                // $param['has_after_sale']=0;
            } else if ($param['balance_status'] == 2) {
                $param['commission'] = [3];
                // $param['has_after_sale']=1;
            } else if ($param['balance_status'] == 3) {
                $param['commission'] = [2];
                // $param['status']=Constants::OrderStatus_OrderFinished;
                //$param['has_after_sale']=0;
            } else {
                $param['commission'] = [1, 2, 3];
                // $param['status']=[Constants::OrderStatus_OrderPay,Constants::OrderStatus_OrderSend,Constants::OrderStatus_OrderReceive,Constants::OrderStatus_OrderClosed,Constants::OrderStatus_OrderSuccess,Constants::OrderStatus_OrderFinished];
            }

            $data = $this->balance->getList($param);
            // 处理数据
            foreach ($data['list'] as $item) {
                $item->money = moneyCent2Yuan($item->money);
                foreach ($item['item_list'] as $items) {
                    $items->total_money = moneyCent2Yuan($items->total_money);
                    $items->cost = moneyCent2Yuan($items->cost);
                    $items->price = moneyCent2Yuan($items->price);
                    $items->sub_total = moneyCent2Yuan($items->sub_total);
                    $items->profit = moneyCent2Yuan($items->profit);
                    $items->discount = moneyCent2Yuan($items->discount);
                    if ($items->commission) {
                        $items_commission = json_decode($items->commission, true);
                        foreach ($items_commission as &$commission_items) {
                            $commission_items['money'] = moneyCent2Yuan($commission_items['money']);
                        }
                        $items->commission = new Collection($items_commission);
                    }
                }

                if ($item->commission) {
                    $item_commission = json_decode($item->commission, true);
                    foreach ($item_commission as &$commission_item) {
                        $commission_item['money'] = moneyCent2Yuan($commission_item['money']);
                    }
                    $item->commission = new Collection($item_commission);
                }
                //结算管理状态，需要两个状态来订 balance_status 1:待结算 2：结算失败 3：结算成功
                if ($item->has_commission === 1) {
                    //待结算
                    $item->balance_status = 1;
                } else if ($item->has_commission === 3) {
                    //结算失败
                    $item->balance_status = 2;
                } else if ($item->has_commission === 2) {
                    //已结算，结算成功
                    $item->balance_status = 3;
                }
                $item->inout_type_text = FinanceHelper::getFinanceInOutTypeText($item->in_type, $item->out_type);
            }

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 结算列表导出
     * $param 搜索条件
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $param['show_distribution_level'] = true;
            $param['commission'] = false;
            //1:待结算 2：结算失败 3：结算成功
            //1:待结算 2：结算失败 3：结算成功
            if ($param['balance_status'] == 1) {
                $param['commission'] = [1];
            } else if ($param['balance_status'] == 2) {
                $param['commission'] = [3];
            } else if ($param['balance_status'] == 3) {
                $param['commission'] = [2];
            } else {
                $param['commission'] = [1, 2, 3];
            }
            $data = $this->balance->getList($param);
            $exportHeadings = [
                '总订单号', // A
                '下单时间', // B
                '终端来源', // C
                '买家ID', // D
                '买家昵称', // E
                '买家姓名', // F
                '买家手机号',    // G
                '总订单状态',    // H
                '商品名称', // I
                '规格',   // J
                '成本价',  // K
                '单价',   // L
                '数量',   // M
                '优惠',   // N
                '小计(优惠后)',  // O
                '利润',   // P
                '总佣金',  // Q
                '一级分销ID',// R
                '一级分销信息',   //  S
                '一级分销总佣金',  // T
                '二级分销ID',     // U
                '二级分销信息',   //  V
                '二级分销总佣金',  //  W
                '三级分销ID',   // X
                '三级分销信息',   //  Y
                '三级分销总佣金',  //  Z
                '商品佣金-一级',  //  AA
                '商品佣金-二级',  //  AB
                '商品佣金-三级',  //  AC
                '结算状态'  //  AD
            ];
            $exportData = [];
            if ($data['list']) {
                $row_num = 2;
                $merge = [];
                foreach ($data['list'] as $item) {
                    $this->convertOutputData($item);
                    $item_list_length = count($item['item_list']);
                    $merge_false = false;//是否需要记录合并单元格的标识
                    $items_commission_str = [];
                    //结算管理状态，需要两个状态来订 balance_status 1:待结算 2：结算失败 3：结算成功
                    for ($i = 0; $i < $item_list_length; $i++) {
                        if ($item['item_list'][$i]->commission) {
                            $items_commission = json_decode($item['item_list'][$i]->commission, true);
                            $items_commission_total = 0;
                            foreach ($items_commission as &$commission_items) {
                                $items_commission_str[$i][$commission_items['floor_level']] = $commission_items;
                                $items_commission_total += $commission_items['money'];
                            }
                        }
                        if ($item->commission) {
                            $commission = [];
                            foreach ($item->commission as &$v) {
                                $commission[$v['floor_level']] = $v;
                            }
                        }
                        $exportData[] = [
                            "\t" . $item->id . "\t",
                            $item->created_at,
                            CoreConstants::getTerminalTypeText($item->terminal_type),
                            $i == 0 ? $item->member_id : '',
                            $i == 0 ? $item->member_nickname : '',
                            $i == 0 ? $item->member_name : '',
                            $i == 0 ? "\t" . $item->member_mobile . "\t" : '',
                            Constants::getOrderStatusText($item->status),
                            $item['item_list'][$i]->name,
                            $item['item_list'][$i]->sku_names != [] ? implode(json_decode($item['item_list'][$i]->sku_names), ' ') : '',
                            moneyCent2Yuan($item['item_list'][$i]->cost),
                            moneyCent2Yuan($item['item_list'][$i]->price),
                            $item['item_list'][$i]->num,
                            moneyCent2Yuan($item['item_list'][$i]->discount),
                            moneyCent2Yuan($item['item_list'][$i]->sub_total),
                            moneyCent2Yuan($item['item_list'][$i]->profit),
                            $item->total_commission,
                            $commission[1] ? $commission[1]['member_id'] : '',
                            $commission[1] ? $commission[1]['nickname'] . ($commission[1]['name'] ? ('/' . $commission[1]['name']) : '') . '/' . $commission[1]['mobile'] : '',
                            $commission[1] ? moneyCent2Yuan($commission[1]['money']) : 0,
                            $commission[2] ? $commission[2]['member_id'] : '',
                            $commission[2] ? $commission[2]['nickname'] . ($commission[2]['name'] ? ('/' . $commission[2]['name']) : '') . '/' . $commission[2]['mobile'] : '',
                            $commission[2] ? moneyCent2Yuan($commission[2]['money']) : 0,
                            $commission[3] ? $commission[3]['member_id'] : '',
                            $commission[3] ? $commission[3]['nickname'] . ($commission[3]['name'] ? ('/' . $commission[3]['name']) : '') . '/' . $commission[3]['mobile'] : '',
                            $commission[3] ? moneyCent2Yuan($commission[3]['money']) : 0,
                            $items_commission_str[$i][1] ? moneyCent2Yuan($items_commission_str[$i][1]['money']) : 0,
                            $items_commission_str[$i][2] ? moneyCent2Yuan($items_commission_str[$i][2]['money']) : 0,
                            $items_commission_str[$i][3] ? moneyCent2Yuan($items_commission_str[$i][3]['money']) : 0,
                            $i == 0 ? $item->balance_status : '',
                        ];
                        if ($merge_false == false) {
                            if ($item_list_length > 1) {
                                //一次循环，只需记录一次合并的参数
                                $merge_false = true;
                                $start_row_num = $row_num;
                                $end_row_num = $row_num + $item_list_length - 1;
                                $merge = array_merge($merge, ['A' . $start_row_num . ':' . 'A' . $end_row_num, 'B' . $start_row_num . ':' . 'B' . $end_row_num, 'C' . $start_row_num . ':' . 'C' . $end_row_num, 'D' . $start_row_num . ':' . 'D' . $end_row_num, 'E' . $start_row_num . ':' . 'E' . $end_row_num, 'F' . $start_row_num . ':' . 'F' . $end_row_num, 'G' . $start_row_num . ':' . 'G' . $end_row_num, 'Q' . $start_row_num . ':' . 'Q' . $end_row_num, 'R' . $start_row_num . ':' . 'R' . $end_row_num, 'S' . $start_row_num . ':' . 'S' . $end_row_num, 'T' . $start_row_num . ':' . 'T' . $end_row_num, 'U' . $start_row_num . ':' . 'U' . $end_row_num, 'V' . $start_row_num . ':' . 'V' . $end_row_num, 'W' . $start_row_num . ':' . 'W' . $end_row_num, 'X' . $start_row_num . ':' . 'X' . $end_row_num, 'Y' . $start_row_num . ':' . 'Y' . $end_row_num, 'Z' . $start_row_num . ':' . 'Z' . $end_row_num, 'AD' . $start_row_num . ':' . 'AD' . $end_row_num]);
                            }
                            $row_num = $row_num + $item_list_length;
                        }
                    }
                }
            }
            $exportObj = new Export(new Collection($exportData), 'FenXiao-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $exportObj->setMerge($merge);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }


    /**
     * 导出列表数据转换
     */
    public function convertOutputData($item)
    {
        //数据转换
        if ($item->commission) {
            $item_commission = json_decode($item->commission, true);
            $commission_str = '分销总金额：' . $item->total_commission;
            if ($item_commission) {
                foreach ($item_commission as &$commission_item) {
                    $commission_str .= '  |' . $this->level_china[$commission_item['floor_level']] . '级分销商：' . moneyCent2Yuan($commission_item['money']) . '   昵称：' . $commission_item['nickname'] . '   ID：' . $commission_item['member_id'];
                }
            }

            $item->commission_str = $commission_str;
            $item->commission = new Collection($item_commission);
        }
        $item->money = moneyCent2Yuan($item->money);

        if ($item->has_commission === 1) {
            //待结算
            $item->balance_status = '预计发放';
        } else if ($item->has_commission === 3) {
            //结算失败
            $item->balance_status = '失效';
        } else if ($item->has_commission === 2) {
            //已结算，结算成功
            $item->balance_status = '已发放';
        }
        return $item;
    }
}