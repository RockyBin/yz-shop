<?php
/**
 * 代理商相关业务
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 14:54
 */

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\DealerMessageNotice;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use App\Modules\ModuleShop\Libs\VerifyLog\DealerVerifyLog;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use const Grpc\CALL_ERROR_NOT_ON_SERVER;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Member;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Constants as CoreConstants;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Modules\ModuleShop\Libs\Member\Member as AppMember;
use App\Modules\ModuleShop\Libs\Model\DealerModel;


class Dealer
{
    use DispatchesJobs;
    protected $siteId = 0;

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * 获取代理列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getDealerList($params, $page = 1, $pageSize = 20)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $dealer = DealerModel::query()->where('tbl_dealer.site_id', $siteId);
        // 手机号 昵称搜索
        $dealer->leftJoin('tbl_member as member', 'member.id', '=', 'tbl_dealer.member_id');

        $dealer->leftJoin('tbl_dealer_level as dealer_level', 'dealer_level.id', '=', 'member.dealer_level');

        $dealer->leftJoin('tbl_dealer_level as cancel_dealer_level', 'cancel_dealer_level.id', '=', 'tbl_dealer.cancel_history_dealer_level');
        $dealer->leftJoin('tbl_dealer_level as cancel_dealer_hide_level', 'cancel_dealer_hide_level.id', '=', 'tbl_dealer.cancel_history_dealer_hide_level');


        $dealer->leftJoin('tbl_dealer_level as dealer_hide_level', 'dealer_hide_level.id', '=', 'member.dealer_hide_level');
        $dealer->leftJoin('tbl_dealer_level as dealer_parenr_level', 'dealer_level.parent_id', '=', 'dealer_parenr_level.id');
        $dealer->leftJoin('tbl_site_admin as admin', 'member.admin_id', '=', 'admin.id');
        if (isset($params['keyword'])) {
            if ($params['keyword_type'] == 2) {
                $dealer->where(function ($query) use ($params) {
                    $query->where('admin.mobile', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('admin.name', 'like', '%' . $params['keyword'] . '%');
                });
            } else {
                $dealer->where(function ($query) use ($params) {
                    $query->where('member.nickname', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('member.mobile', 'like', '%' . $params['keyword'] . '%');
                    $query->orWhere('member.name', 'like', '%' . $params['keyword'] . '%');
                });
            }

        }
        if (isset($params['status']) && $params['status'] != -99) {
            $dealer->where('tbl_dealer.status', $params['status']);
        } else {
            $dealer->whereIn('tbl_dealer.status', [Constants::DealerStatus_Active, Constants::DealerStatus_Cancel]);
        }
        // 等级搜索
        if (isset($params['dealer_hide_level'])) {
            $params['dealer_hide_level'] = myToArray($params['dealer_hide_level']);
            $dealer->whereIn('member.dealer_hide_level', $params['dealer_hide_level']);
        }
        // 隐藏等级搜索
        if (isset($params['dealer_level'])) {
            $params['dealer_level'] = myToArray($params['dealer_level']);
            $dealer->whereIn('member.dealer_level', $params['dealer_level']);
        }

        // 成为经销商时间搜索
        if (isset($params['passed_at_start'])) {
            $dealer->where('passed_at', '>=', $params['passed_at_start']);
        }
        if (isset($params['passed_at_end'])) {
            $dealer->where('passed_at', '<=', $params['passed_at_end']);
        }

        // 统计数据条数
        $total = $dealer->count();
        $last_page = ceil($total / $pageSize);
        // 父级数据
        $dealer->leftJoin('tbl_member as parent', 'member.dealer_parent_id', '=', 'parent.id');
        //推荐人数据
        $dealer->leftJoin('tbl_member as parent_review', 'tbl_dealer.parent_review_member', '=', 'parent_review.id');
        //上级人数据
        $dealer->leftJoin('tbl_member as invite_review', 'tbl_dealer.invite_review_member', '=', 'invite_review.id');

        // 统计下级数量
        $dealer->leftJoin('tbl_member as sub_count', function ($join) {
            $join->on('sub_count.dealer_parent_id', '=', 'tbl_dealer.member_id');
            $join->where('sub_count.dealer_level', '>', 0);
        });

        // 使用member_id分组
        $dealer->groupBy('tbl_dealer.member_id');
        // 要查找的字段 使用子查询统计分红
        $nativeSubQueryString = '';
        $nativeSubQueryBindings = [];
        //团队数量需要加上自己，所以加1
        $dealer->selectRaw('
            (select sum(total_money) from `tbl_cloudstock_purchase_order` as cpo where `cpo`.`member_id` = `tbl_dealer`.`member_id` and cpo.status in (' . implode(',', Constants::getCloudStockPurchaseOrderPayStatus()) . ')) as performance_reward_count,
            COUNT(sub_count.id) AS dealer_total,
            ' . $nativeSubQueryString . '
            tbl_dealer.member_id,
            member.dealer_level as dealer_level,
            tbl_dealer.status,
            tbl_dealer.passed_at,
            dealer_level.name as dealer_level_name,
            cancel_dealer_level.name as cancel_dealer_level_name,
            cancel_dealer_hide_level.name as cancel_dealer_level_hide_name,
            dealer_hide_level.name as dealer_hide_level_name,
            dealer_parenr_level.name as dealer_parent_level_name,
            member.nickname,
            member.mobile,
            member.name,
            admin.name as admin_name,
            admin.mobile as admin_mobile,
            member.dealer_parent_id,
            parent.nickname as parent_nickname,
            parent_review.nickname as parent_review_nickname,
            invite_review.nickname as invite_review_nickname,
            member.headurl,
            tbl_dealer.cancel_history_dealer_level,
            tbl_dealer.cancel_history_dealer_hide_level,
            parent.mobile as parent_mobile', $nativeSubQueryBindings);
        $dealer->forPage($page, $pageSize);
        $dealer->orderBy('tbl_dealer.passed_at', 'desc');
        $list = $dealer->get();
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 获取申请经销商的列表
     * @param $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function getApplyDealerList($params, $page = 1, $pageSize = 20)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $dealer = DealerModel::query()->where('tbl_dealer.site_id', $siteId);
        // 默认按审核时间排序
        if (isset($params['status'])) {
            $dealer->where('tbl_dealer.status', $params['status']);
            if ($params['status'] == Constants::AgentStatus_WaitReview) {
                $dealer->orderBy('tbl_dealer.created_at', 'desc');
            } else {
                $dealer->orderBy('tbl_dealer.passed_at', 'desc');
            }
        } else {
            $dealer->where('tbl_dealer.status', Constants::AgentStatus_WaitReview);
            $dealer->orderBy('tbl_dealer.created_at', 'desc');
        }

        // 手机号 昵称搜索
        $dealer->leftJoin('tbl_member as member', 'member.id', '=', 'tbl_dealer.member_id');

        if (isset($params['keyword'])) {
            $dealer->where(function ($query) use ($params) {
                $query->where('member.nickname', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('member.mobile', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('member.name', 'like', '%' . $params['keyword'] . '%');
            });
        }
        // 等级搜索
        if (isset($params['dealer_apply_level'])) {
            $dealer->where('dealer_apply_level', $params['dealer_apply_level']);
        }
        // 申请成为经销商时间搜索
        if (isset($params['created_at_start'])) {
            $dealer->where('tbl_dealer.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end'])) {
            $dealer->where('tbl_dealer.created_at', '<=', $params['created_at_end']);
        }
        // 申请成为经销商时间搜索
        if (isset($params['passed_at_start'])) {
            $dealer->where('tbl_dealer.passed_at', '>=', $params['passed_at_start']);
        }
        if (isset($params['passed_at_end'])) {
            $dealer->where('tbl_dealer.passed_at', '<=', $params['passed_at_end']);
        }
        if (isset($params['review_process'])) {
            // 1: 公司审核中 2:上级审核中 3:推荐人审核中
            if ($params['review_process'] == 1) {
                $dealer->where(function ($query) {
                    $query->where('tbl_dealer.parent_review_status', '=', Constants::DealerStatus_WaitReview);
                    $query->where('tbl_dealer.invite_review_member', '<>', '0');
                    $query->where('tbl_dealer.invite_review_status', '=', Constants::DealerStatus_Active);
                    $query->where('tbl_dealer.parent_review_member', '=', '0');
                    $query->orWhere(function ($query) {
                        $query->where('tbl_dealer.parent_review_status', '=', Constants::DealerStatus_WaitReview);
                        $query->where('tbl_dealer.invite_review_member', '=', '0');
                        $query->where('tbl_dealer.invite_review_status', '=', Constants::DealerStatus_WaitReview);
                        $query->where('tbl_dealer.parent_review_member', '=', '0');
                    });
                    $query->orWhere(function ($query) {
                        $query->where('tbl_dealer.parent_review_status', '=', Constants::DealerStatus_Active);
                        $query->where('tbl_dealer.invite_review_member', '<>', '0');
                        $query->where('tbl_dealer.invite_review_status', '=', Constants::DealerStatus_Active);
                        $query->where('tbl_dealer.parent_review_member', '<>', '0');
                        $query->where('tbl_dealer.verify_process', '=', '0');
                        $query->where('tbl_dealer.status', '=', Constants::DealerStatus_WaitReview);
                    });
                });
            } elseif ($params['review_process'] == 2) {

                $dealer->where(function ($query) {
                    $query->where('tbl_dealer.parent_review_status', '=', Constants::DealerStatus_WaitReview);
                    $query->where('tbl_dealer.invite_review_status', '=', Constants::DealerStatus_Active);
                    $query->where('tbl_dealer.parent_review_member', '<>', '0');
                    $query->orWhere(function ($query) {
                        $query->whereRaw('tbl_dealer.invite_review_member = tbl_dealer.parent_review_member');
                        $query->where('tbl_dealer.parent_review_status', '=', Constants::DealerStatus_WaitReview);
                        $query->where('tbl_dealer.parent_review_member', '<>', '0');
                    });
                });

            } elseif ($params['review_process'] == 3) {
                $dealer->whereRaw('tbl_dealer.invite_review_member <> tbl_dealer.parent_review_member');
                $dealer->where('tbl_dealer.invite_review_status', '=', Constants::DealerStatus_WaitReview);
                $dealer->where('tbl_dealer.invite_review_member', '<>', '0');
            }
        }
        // 统计数据条数
        $total = $dealer->count();
        $last_page = ceil($total / $pageSize);
        // 会员等级
        $dealer->leftJoin('tbl_dealer_level as dlevel', 'dlevel.id', '=', 'tbl_dealer.dealer_apply_level');

        // 父级数据
        $dealer->leftJoin('tbl_member as invite', 'tbl_dealer.invite_review_member', '=', 'invite.id');
        $dealer->leftJoin('tbl_member as parent', 'tbl_dealer.parent_review_member', '=', 'parent.id');

        // 要查找的字段
        $dealer->select([
            'tbl_dealer.member_id',
            'dealer_apply_level',
            'member.nickname',
            'member.name',
            'member.mobile as member_mobile',
            'member.headurl',
            'invite.nickname as invite_nickname',
            'invite.dealer_level as invite_dealer_level',
            'invite.mobile as invite_mobile',
            'parent.nickname as parent_nickname',
            'parent.dealer_level as parent_dealer_level',
            'parent.mobile as parent_mobile',
            'dlevel.name as level_name',
            'member.invite1',
            'tbl_dealer.*'
        ]);
        $dealer->forPage($page, $pageSize);
        $list = $dealer->get();
        foreach ($list as &$item) {
            if ($item->initial_pay_history_info) {
                $item->initial_pay_history_info = json_decode($item->initial_pay_history_info);
            }
            if ($item->initial_pay_certificate) {
                $item->initial_pay_certificate = explode(',', $item->initial_pay_certificate);
            }
            if ($item->initial_money) {
                $item->initial_money = moneyCent2Yuan($item->initial_money);
            }
            $item->invite_nickname = $item->invite_nickname ? $item->invite_nickname : '公司';
            $item->parent_nickname = $item->parent_nickname ? $item->parent_nickname : '公司';
            if ($item->status == Constants::DealerStatus_WaitReview) {
                $item->dealer_review_process_text = self::dealerReviewProcessText($item, true);
            } elseif ($item->status == Constants::DealerStatus_Active) {
                $item->dealer_review_process_text = ['审核通过'];
            }
            $item->is_show_review = self::isShowDealerReview($item);
            if ($item->apply_condition) $item->apply_condition = json_decode($item->apply_condition, true);
            $item->member_mobile = \App\Modules\ModuleShop\Libs\Member\Member::memberMobileReplace($item->member_mobile);
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    public function getInfo($params)
    {
        $deal = DealerModel::query()
            ->where('member_id', $params['dealer_id'])
            ->where('tbl_dealer.site_id', $this->siteId)
            ->leftJoin('tbl_member as member', 'member.id', '=', 'tbl_dealer.member_id')
            ->leftJoin('tbl_dealer_level as dlevel', 'dlevel.id', '=', 'tbl_dealer.dealer_apply_level')
            ->leftJoin('tbl_member as invite', 'tbl_dealer.invite_review_member', '=', 'invite.id')
            ->leftJoin('tbl_member as parent', 'tbl_dealer.parent_review_member', '=', 'parent.id')
            ->select([
                'tbl_dealer.member_id',
                'dealer_apply_level',
                'member.nickname',
                'member.mobile as member_mobile',
                'member.headurl',
                'invite.nickname as invite_nickname',
                'invite.mobile as invite_mobile',
                'parent.nickname as parent_nickname',
                'parent.mobile as parent_mobile',
                'dlevel.name as level_name',
                'tbl_dealer.*'
            ])
            ->first();
        if ($deal->initial_pay_history_info) {
            $deal->initial_pay_history_info = json_decode($deal->initial_pay_history_info);
        }
        if ($deal->initial_pay_certificate) {
            $deal->initial_pay_certificate = explode(',', $deal->initial_pay_certificate);
        }
        if ($deal->initial_money) {
            $deal->initial_money = moneyCent2Yuan($deal->initial_money);
        }
        $deal->invite_nickname = $deal->invite_nickname ? $deal->invite_nickname : '总店';
        $deal->parent_nickname = $deal->parent_nickname ? $deal->parent_nickname : '总店';
        return $deal;
    }

    /**
     * 获取申请经销商的列表审核进度文案
     * @param $dealer
     * @return array
     */
    public static function dealerReviewProcessText($dealer)
    {
        // 推荐人以及上级领导是同一个人，只需要一次审核即可
        if ($dealer->invite_review_member == $dealer->parent_review_member) {
            if ($dealer->invite_review_member > 0) {
                if ($dealer->parent_review_status == Constants::DealerStatus_WaitReview) {
                    $textArr = ['上级审核中'];
                } else if ($dealer->parent_review_status == Constants::DealerStatus_Active) {
                    $textArr = ['上级审核通过'];
                    if ($dealer->verify_process == 0) array_push($textArr, ['公司审核中']);
                }
                return $textArr;
            } else {
                return ['公司审核中'];
            }
        } else {
            //当推荐人与上级领导不是同一个人，且推荐人是经销商，那需要经销商先去审核
            if ($dealer->invite_review_member > 0) {
                if ($dealer->invite_review_status == Constants::DealerStatus_WaitReview) {
                    return ['推荐人审核中'];
                } else if ($dealer->invite_review_status == Constants::DealerStatus_Active) {
                    $textArr = ['推荐人审核通过'];
                    // 如果上级领导不是公司，那就是需要上级去审核
                    if ($dealer->parent_review_member > 0) {
                        if ($dealer->parent_review_status == Constants::DealerStatus_WaitReview) {
                            array_push($textArr, '上级审核中');
                        } else if ($dealer->parent_review_status == Constants::DealerStatus_Active) {
                            array_push($textArr, '上级审核通过');
                            if ($dealer->verify_process == 0) array_push($textArr, ['公司审核中']);
                        }
                    } else {
                        array_push($textArr, '公司审核中');
                    }
                }
            } else {
                //当推荐人是公司，上级领导是经销商，那直接上级审核即可。
                if ($dealer->parent_review_member > 0) {
                    if ($dealer->parent_review_status == Constants::DealerStatus_WaitReview) {
                        $textArr = ['上级审核中'];
                    } else if ($dealer->parent_review_status == Constants::DealerStatus_Active) {
                        $textArr = ['上级审核通过'];
                        if ($dealer->verify_process == 0) array_push($textArr, ['公司审核中']);
                    }
                    return $textArr;
                } else {
                    return ['公司审核中'];
                }
            }
        }
    }

    /**
     * 获取申请经销商的列表审核按钮是否显示
     * @param $dealer
     * @return boolean  true : 显示 false : 不显示
     */
    public static function isShowDealerReview($dealer)
    {
        $dealerApplySetting = (new DealerApplySetting())->getInfo();
        //状态为待审核
        if ($dealer->status == Constants::DealerStatus_WaitReview) {
            // 当推荐人和上级领导都是【总店】或者 当推荐人和上级领导不是同一个人，并上级领导是【总店】，且推荐人已通过审核，则显示审核按钮 ，其他情况均不显示
            if (($dealer->invite_review_member == $dealer->parent_review_member && $dealer->invite_review_member == 0)
                ||
                ($dealer->invite_review_member != $dealer->parent_review_member && $dealer->parent_review_member == 0 && $dealer->invite_review_status == Constants::DealerStatus_Active)) {
                return true;
            }
            // 公司必须审核
            if ($dealer->verify_process == 0) {
                if ($dealer->invite_review_status == Constants::DealerStatus_Active && $dealer->parent_review_status == Constants::DealerStatus_Active) return true;
            }
        }
        return false;
    }

    /**
     * 后台添加代理
     * @param array $params 参数
     * @param bool $returnCheck 是否返回检测数据
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function adminAddDealer($params, $returnCheck = false)
    {
        $dealerCheck = $this->becomeDealerBefore($params, true);
        if ($dealerCheck) {
            if ($dealerCheck->status == Constants::DealerStatus_Active) {
                return makeServiceResult(500, '经销商已存在');
            } elseif ($dealerCheck->status == Constants::DealerStatus_WaitReview) {
                return makeServiceResult(502, '经销商已在审核列表');
            } elseif ($dealerCheck->status == Constants::DealerStatus_Cancel) {
                return makeServiceResult(504, '经销商已被禁用');
            }
            // throw new \Exception("经销商已存在");
        }

        $nowDate = Carbon::now();
        // 如果经销商记录存在 直接改变状态为生效即可
        if ($dealerCheck) {
            // 待审核和已取消资格的 后台让用户自己处理 这里返回数据即可
            if (
                $returnCheck
                && ($dealerCheck->status == Constants::DealerStatus_WaitReview
                    || $dealerCheck->status == Constants::DealerStatus_Cancel)
            ) {
                return $dealerCheck;
            }
            $dealer = $dealerCheck;
        } else {
            $dealer = new DealerModel();
            $dealer->site_id = $this->siteId;
            $dealer->member_id = $params['member_id'];
            // 申请和通过时间一样
            $dealer->created_at = $nowDate;
        }

        // 后台添加的 默认状态为生效
        $dealer->status = Constants::DealerStatus_Active;
        $dealer->invite_review_status = Constants::DealerStatus_Active;
        $dealer->parent_review_status = Constants::DealerStatus_Active;
        $dealer->dealer_apply_level = $params['dealer_apply_level'];
        // 查找会员的管理上级和推荐上级
        $parents = DealerHelper::findDealerParent($dealer->member_id, $dealer->dealer_apply_level);
        $dealer->invite_review_member = $parents['invite_parent'] ? $parents['invite_parent']->id : 0;
        $dealer->invite_review_status = Constants::DealerStatus_Active;
        $dealer->invite_review_passed_at = Carbon::now();
        $dealer->parent_review_member = $parents['manage_parent'] ? $parents['manage_parent']->id : 0;
        $dealer->parent_review_status = Constants::DealerStatus_Active;
        $dealer->parent_review_passed_at = Carbon::now();
        // 通过时间
        $dealer->passed_at = $dealer->upgrade_at = $dealer->invite_review_passed_at = $dealer->parent_review_passed_at = Carbon::now();
        $save = $dealer->save();
        if ($save) {
            $this->becomeDealerAfter($dealer);
            return true;
        } else {
            throw new \Exception("新增经销商失败");
        }
    }

    /**
     * 前台申请经销商
     * @param $params
     * @return array|bool
     * @throws \Exception
     */
    public function applyDealer($params)
    {
        $dealerCheck = $this->becomeDealerBefore($params, true);
        $applySetting = new DealerApplySetting();
        if ($params['is_invite'] == '1') $canLevels = $applySetting->getCanInviteLevel();
        else $canLevels = $applySetting->getCanApplyLevel();
        if (!$canLevels) {
            throw new \Exception("申请经销商等级错误");
        }
        // 先获取需要的字段
        $dealerApplySetting = new DealerApplySetting();
        $agentForm = $dealerApplySetting->getApplyForm();
        if (!$agentForm['defaultFields'] && !$agentForm['extendFields']) {
            return makeServiceResult(400, "表单字段数据错误");
        }
        // 检测是否有加盟协议 并同意
        if ($agentForm['agreement']['show'] == 1 && !$params['agreement']) {
            return makeServiceResult(400, "请先同意协议");
        }
        // 检测旧有的代理状态
        $dealer = $dealerCheck;
        if (!$dealer) $dealer = new DealerModel();
        if ($dealerCheck && in_array($dealer->status, [Constants::DealerStatus_WaitReview, Constants::DealerStatus_Active])) {
            return makeServiceResult(400, "您已经申请过经销商，请不要重复申请");
        }
        // 用来标识是个人申请还是公司申请 0为个人 1为公司
        $applyType = $params['business_type'] ?: 0;
        // 如果是个人 把不需要的字段剔除
        if ($applyType == 0) {
            unset($agentForm['defaultFields']['company']);
            unset($agentForm['defaultFields']['business_license']);
            unset($agentForm['defaultFields']['business_license_file']);
        }
        // 预设字段
        foreach ($agentForm['defaultFields'] as $key => $val) {
            $formVal = trim($params[$key]);
            // 必填项不能为空
            if ($val && $formVal === '') {
                return makeServiceResult(400, "必填项不能为空");
            }
            $dealer->$key = $formVal;
        }
        $extendForm = [];
        $extendFieldsValue = collect($params['extend_fields'] ?: []);
        // 自定义字段
        foreach ($agentForm['extendFields'] as $key => $val) {
            $formExtVal = $extendFieldsValue->where('name', $val['name'])->first();
            $formExtVal = trim($formExtVal['value']);
            // 必填项
            if ($val['show'] && $val['require'] && $formExtVal === '') {
                return makeServiceResult(400, "必填项不能为空");
            }
            $extendForm[] = ['name' => $val['name'], 'value' => $formExtVal];
        }
        // 如果有地址
        if (isset($agentForm['defaultFields']['address'])) {
            if ($agentForm['defaultFields']['address'] && (!$params['prov'] || !$params['city'] || !$params['area'])) {
                return makeServiceResult(400, "请选择地址");
            }
            $dealer->prov = $params['prov'] ?: 0;
            $dealer->city = $params['city'] ?: 0;
            $dealer->area = $params['area'] ?: 0;
        }
        $dealer->extend_fields = $extendForm ? json_encode($extendForm) : null;
        $dealer->business_type = $applyType; // 申请类型
        // 用户申请的 默认为等待审核
        $dealer->status = Constants::DealerStatus_WaitReview;
        $dealer->initial_pay_status = 0;
        $dealer->site_id = $this->siteId;
        $dealer->dealer_apply_level = $params['dealer_apply_level'];
        // 存储历史记录
        $dealerApplyLevel = DealerLevelModel::query()->where('site_id', $this->siteId)->where('id', $params['dealer_apply_level'])->first();
        $apply_condition['dealer_level'] = $dealerApplyLevel->id;
        $apply_condition['dealer_apply_level_name'] = $dealerApplyLevel->name;
        if ($apply_condition) $dealer->apply_condition = json_encode($apply_condition);
        // 控制器去保证是当前登录的会员id
        $dealer->member_id = $params['member_id'];
        // 申请时间
        $dealer->created_at = Carbon::now();
        // 如果申请过并且被拒绝 先删掉拒绝的申请记录
        if ($dealerCheck) {
            $dealerCheck->delete();
        }
        // 查找会员的管理上级和推荐上级
        $parents = DealerHelper::findDealerParent($dealer->member_id, $dealer->dealer_apply_level);
        $dealer->invite_review_member = $parents['invite_parent'] ? $parents['invite_parent']->id : 0;
        $dealer->invite_review_status = Constants::DealerStatus_WaitReview;
        $dealer->parent_review_member = $parents['manage_parent'] ? $parents['manage_parent']->id : 0;
        $dealer->parent_review_status = Constants::DealerStatus_WaitReview;
        $dealerApplySetting = (new DealerApplySetting())->getInfo();
        $dealer->verify_process = $dealerApplySetting['verify_process'];
        // 加盟费
        $baseSetting = DealerBaseSetting::getCurrentSiteSetting();
        $applySetting = new DealerApplySetting();
        if ($params['is_invite'] == '1') $canLevels = $applySetting->getCanInviteLevel();
        else $canLevels = $applySetting->getCanApplyLevel();
        $dealerLevel = $canLevels
            ->where('id', '=', $params['dealer_apply_level'])
            ->where('status', '=', 1)
            ->first();
        $initial_money = $dealerLevel->initial_fee;
        if ($initial_money) {
            $dealer->initial_money = $initial_money;
            $dealer->initial_pay_type = $params['initial_pay_type'];
            $dealer->initial_pay_history_info = json_encode($this->initialHistoryInfo($params['initial_pay_type']));
            if ($params['initial_pay_certificate']) $dealer->initial_pay_certificate = $params['initial_pay_certificate'];
            if ($initial_money && ($dealer->initial_pay_type == CoreConstants::PayType_Balance || in_array($dealer->initial_pay_type, CoreConstants::getOnlinePayType()))) {
                $dealer->status = Constants::DealerStatus_Applying;
                $dealer->initial_pay_status = 0;
            }
            if (in_array($dealer->initial_pay_type, CoreConstants::getOfflinePayType())) {
                $dealer->initial_pay_status = 1;
            }
            //确定收款对象
            $payee = 0;
            if (intval($baseSetting->initial_money_target) === 1) {
                $payee = $dealer->parent_review_member;
                $parentAccount = (new DealerAccount($payee))->getAccount($params['initial_pay_type']);
                $payInfo = Payment::makeOffLinePaymentReceiptInfo($params['initial_pay_type'], $parentAccount->account, $parentAccount->bank, $parentAccount->account_name);
                $dealer->initial_pay_history_info = json_encode($payInfo);
            }
            $dealer->initial_payee = $payee;
        }
        $save = $dealer->save();
        if ($save) {
            $Dealermodel = self::checkDealerExist($dealer->member_id);
            if ($Dealermodel->invite_review_member != 0 || $Dealermodel->parent_review_member != 0) {
                $dealerVerifyLog = DealerVerifyLog::save(Constants::VerifyLogType_DealerVerify, $Dealermodel);
                if ($dealerVerifyLog['invite_log_id']) {
                    $Dealermodel->invite_log_id = $dealerVerifyLog['invite_log_id'];
                    $inviteVerifyLog = VerifyLogModel::find($dealerVerifyLog['invite_log_id']);
                    // 经销商审核通知
                    DealerMessageNotice::sendMessageDealerVerify($inviteVerifyLog);
                }
                if ($dealerVerifyLog['parent_log_id']) {
                    $Dealermodel->parent_log_id = $dealerVerifyLog['parent_log_id'];
                    // 没有推荐人的时候,直接发信息给上级,有推荐人,只发给推荐人就行了
                    if (!$dealerVerifyLog['invite_log_id']) {
                        $parentVerifyLog = VerifyLogModel::find($dealerVerifyLog['parent_log_id']);
                        // 经销商审核通知
                        DealerMessageNotice::sendMessageDealerVerify($parentVerifyLog);
                    }
                }
                $Dealermodel->save();
            }
            return true;
        } else {
            return makeServiceResultFail("提交失败");
        }
    }

    /**
     * 支付代理申请表单
     * @param int $memberId 会员ID
     * @param int $payType 支付类型
     * @param $vouchers 支付凭证
     *   当使用余额支付时，它是支付密码，此时数据格式为字符串
     *   当使用线下支付时，它是用户上传的线下支付凭证图片(最多三张)，此时数据格式为 \Illuminate\Http\UploadedFile|array $voucherFiles
     *   当使用线上支付时，它是支付成功后的入账财务记录
     * @param integer $feeType 1=加盟费，2=保证金(未实现)
     * @return void
     */
    public function payFee($memberId, $payType, $vouchers, $feeType = 1)
    {
        $dealer = DealerModel::query()->where('member_id', $memberId)->first();
        // 余额支付的情况
        if ($payType == CoreConstants::PayType_Balance) {
            // 如果是余额支付 要验证支付密码
            $member = new AppMember($memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            if (!$member->payPasswordCheck($vouchers)) {
                return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
            }
            // 扣钱
            if ($feeType === 1) {
                FinanceHelper::payDealerInitialMoneyWithBalance($member->site_id, $memberId, 'DealerInitial' . date('YmdHis'), $dealer->initial_money);
                $dealer->initial_pay_status = 1;
                $dealer->status = Constants::DealerStatus_WaitReview;
            }
            $dealer->save();
        }
        // 线下支付的情况(无需处理，在保存申请表单时已处理)

        // 线上支付的情况
        if (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) {
            // 验证线上支付记录
            if ($vouchers['money'] == $dealer->initial_money && intval($vouchers['status']) == 1) {
                if ($feeType === 1) $dealer->initial_pay_status = 1;
                $dealer->status = Constants::DealerStatus_WaitReview;
                $dealer->initial_pay_certificate = $vouchers['tradeno'];
            }
            $dealer->save();
        }

        return makeApiResponse(200, 'ok');
    }

    /**
     * 申请代理时，加盟费的相关历史数据
     * @return array
     * @throws \Exception
     */
    public function initialHistoryInfo($initialPayType)
    {
        $initialPayInfo = self::getInitialPayInfo($initialPayType);
        $bank = null;
        $accountName = null;
        $account = null;
        switch (true) {
            case $initialPayType == CoreConstants::PayType_WeixinQrcode:
                $account = $initialPayInfo->wx_qrcode;
                break;
            case $initialPayType == CoreConstants::PayType_AlipayQrcode :
                $account = $initialPayInfo->alipay_qrcode;
                break;
            case $initialPayType == CoreConstants::PayType_AlipayAccount:
                $account = $initialPayInfo->alipay_account;
                $accountName = $initialPayInfo->alipay_name;
                break;
            case $initialPayType == CoreConstants::PayType_Bank :
                $account = $initialPayInfo->bank_account;
                $accountName = $initialPayInfo->bank_card_name;
                $bank = $initialPayInfo->bank;
                break;
            default:
                $account = null;
        }
        $info = Payment::makeOffLinePaymentReceiptInfo($initialPayType, $account, $bank, $accountName);
        return $info;
    }

    /**
     * 获取加盟费详细信息
     * @param $params
     */
    public static function getInitialPayInfo($paytype)
    {
        switch (true) {
            case $paytype == CoreConstants::PayType_WeixinQrcode:
                $type = Constants::PayConfigType_WxPay;
                break;
            case $paytype == CoreConstants::PayType_AlipayQrcode || $paytype == CoreConstants::PayType_AlipayAccount:
                $type = Constants::PayConfigType_AliPay;
                break;
            case $paytype == CoreConstants::PayType_Bank :
                $type = Constants::PayConfigType_BankPay;
                break;
            case $paytype == CoreConstants::PayType_Balance :
                $type = Constants::PayConfigType_BankPay;
                break;
            case $paytype == CoreConstants::PayType_Weixin :
                $type = Constants::PayConfigType_WxPay;
                break;
            case $paytype == CoreConstants::PayType_Alipay :
                $type = Constants::PayConfigType_AliPay;
                break;
            case $paytype == CoreConstants::PayType_TongLian :
                $type = Constants::PayConfigType_TongLian;
                break;
            default:
                $type = null; //拿取所有
        }

        $payConfig = new PayConfig($type);
        $payInfo = $payConfig->getInfo();
        return $payInfo;
    }

    /**
     * 成为/申请 代理前的检测
     * @param $params
     * @param bool $returnDealerData 是否返回代理记录
     * @return Agent|bool|\Illuminate\Database\Eloquent\Model|null|object
     * @throws \Exception
     */
    public function becomeDealerBefore($params, $returnDealerData = false)
    {
        $dealerLevel = DealerLevelModel::query()
            ->where('id', '=', $params['dealer_apply_level'])
            ->where('status', '=', 1)
            ->first();
        if (!$dealerLevel) {
            throw new \Exception("经销商等级错误");
        }
        $member = new Member($params['member_id'], $this->siteId);
        // 会员是否存在
        if (!$member->checkExist()) {
            throw new \Exception("会员不存在");
        }
        // 是否绑定了手机号
        $member->checkBindMobile();
        // 是否已是代理
        $dealer = $this->checkDealerExist($params['member_id']);
        if ($dealer) {
            if ($returnDealerData) {
                return $dealer;
            }
            throw new \Exception("经销商记录已存在");
        }
    }

    /**
     * 成为代理之后的操作
     * @param $agent
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    private function becomeDealerAfter($dealer)
    {
        // 会员表更新一下代理等级
        MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $dealer->member_id)
            ->update(['dealer_level' => $dealer->dealer_apply_level]);
        // 自动生成云仓
        new CloudStock($dealer->member_id);
        // 更新团队关系
        DealerHelper::dispatchResetDealerParentsJob($dealer->member_id, 0, $dealer->dealer_apply_level);
        // 成为经销商通知
        $this->dispatch(new MessageNotice(CoreConstants::MessageType_Dealer_Agree, $dealer));
        //改为用队列处理经销商升级
        //$this->dispatch(new UpgradeAgentLevelJob($agent->member_id));
    }

    /**
     * 审核经销商
     * @param $param
     * @return int
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function verifyDealer($param)
    {
        // 审核人ID（推荐人 上级） 被审人ID 状态
        $dealerModel = $this->verifyDealerBefore($param['member_id']);
        // 构造更新的数据
        $update = [
            'passed_at' => Carbon::now(),
            'upgrade_at' => Carbon::now(),
            'invite_review_passed_at' => Carbon::now(),
            'parent_review_passed_at' => Carbon::now(),
        ];
        if ($param['status'] == Constants::DealerStatus_Active) {
            $update['status'] = Constants::DealerStatus_Active;
            //if()
            $update['parent_review_status'] = Constants::DealerStatus_Active;
            $update['invite_review_status'] = Constants::DealerStatus_Active;
        } else if ($param['status'] == Constants::DealerStatus_RejectReview) {
            $update['status'] = Constants::DealerStatus_RejectReview;
            if ($dealerModel->invite_review_member == 0) $update['invite_review_status'] = Constants::DealerStatus_RejectReview;
            if ($dealerModel->parent_review_member == 0) $update['parent_review_status'] = Constants::DealerStatus_RejectReview;
            // 拒绝原因
            $update['reject_reason'] = $param['reject_reason'] ?: '';
        }
        $query = DealerModel::query()
            ->where('site_id', $this->siteId)
            ->where('status', Constants::DealerStatus_WaitReview);
        if (is_array($param['member_id'])) {
            $query->whereIn('member_id', $param['member_id']);
        } else {
            $query->where('member_id', $param['member_id']);
        }
        $save = $query->update($update);
        if ($save) {
            $memberIds = myToArray($param['member_id']);
            foreach ($memberIds as $memberId) {
                $dealerModel = DealerModel::query()->where('site_id', $this->siteId)->where('member_id', $memberId)->first();
                $dealerModel->review_member_id = true;
                VerifyLog::Log(Constants::VerifyLogType_DealerVerify, $dealerModel);
                if ($dealerModel) {
                    if ($param['status'] == Constants::DealerStatus_Active) {
                        $this->becomeDealerAfter($dealerModel);
                        // 新增加盟费财务记录
                        if ($param['receive_initial_money']) $this->addDealerInitialFinance($dealerModel);
                    } else if ($param['status'] == Constants::DealerStatus_RejectReview) {
                        // 申请经销商被拒通知
                        DealerMessageNotice::sendMessageDealerReject($dealerModel);
                    }
                }
            }
        }
        return $save;
    }

    /**
     * 后台审核前的检测
     * @param $dealer
     * @return mixed
     */
    public function verifyDealerBefore($memberId)
    {
        $dealer = DealerModel::query()
            ->where('member_id', $memberId)
            ->where('site_id', $this->siteId)
            ->where('status', Constants::DealerStatus_WaitReview)
            ->first();
        if (!$dealer) {
            throw  new \Exception('经销商不存在');
        }
        if ($dealer->invite_review_member > 0 && $dealer->invite_review_status == Constants::DealerStatus_WaitReview) {
            throw  new \Exception('需要推荐人先通过');
        }
        if ($dealer->parent_review_member > 0 && $dealer->parent_review_status == Constants::DealerStatus_WaitReview) {
            throw  new \Exception('需要上级通过');
        }
        return $dealer;
    }

    /**
     * 前台审核经销商
     * @param $param
     * @return int
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function frontVerifyDealer($param)
    {
        // 审核人ID（推荐人 上级） 被审人ID 状态
        $dealerModel = DealerModel::query()
            ->where('member_id', $param['member_id'])
            ->where('site_id', $this->siteId)
            ->first();
        if ($dealerModel->auto_upgrade_data && $dealerModel->status == Constants::DealerStatus_Active) {
            throw  new \Exception('该会员已自动完成升级');
        }
        if ($dealerModel->status == Constants::DealerStatus_Active) {
            throw  new \Exception('经销商已生效');
        }
        if (!$dealerModel) {
            throw  new \Exception('经销商不存在');
        }
        // 构造更新的数据
        $update = [
            'upgrade_at' => Carbon::now()
        ];

        if ($dealerModel->parent_review_member == $dealerModel->invite_review_member && $dealerModel->parent_review_member == $param['review_member_id']) {
            $update['parent_review_status'] = $param['status'] == Constants::DealerStatus_Active ? Constants::DealerStatus_Active : Constants::DealerStatus_RejectReview;
            $update['invite_review_status'] = $param['status'] == Constants::DealerStatus_Active ? Constants::DealerStatus_Active : Constants::DealerStatus_RejectReview;
            $update['invite_review_passed_at'] = Carbon::now();
            $update['parent_review_passed_at'] = Carbon::now();
            $update['invite_review_reject_reason'] = (Constants::DealerStatus_RejectReview && $param['reject_reason']) ? $param['reject_reason'] : '';
            $update['parent_review_reject_reason'] = (Constants::DealerStatus_RejectReview && $param['reject_reason']) ? $param['reject_reason'] : '';
        } else if ($dealerModel->parent_review_member == $param['review_member_id']) {
            $update['parent_review_status'] = $param['status'] == Constants::DealerStatus_Active ? Constants::DealerStatus_Active : Constants::DealerStatus_RejectReview;
            if ($dealerModel->invite_review_member == 0) {
                $update['invite_review_status'] = $param['status'] == Constants::DealerStatus_Active ? Constants::DealerStatus_Active : Constants::DealerStatus_RejectReview;
            }
            $update['parent_review_passed_at'] = Carbon::now();
            $update['parent_review_reject_reason'] = (Constants::DealerStatus_RejectReview && $param['reject_reason']) ? $param['reject_reason'] : '';
        } else if ($dealerModel->invite_review_member == $param['review_member_id']) {
            $update['invite_review_status'] = $param['status'] == Constants::DealerStatus_Active ? Constants::DealerStatus_Active : Constants::DealerStatus_RejectReview;
            $update['invite_review_passed_at'] = Carbon::now();
            $update['invite_review_reject_reason'] = (Constants::DealerStatus_RejectReview && $param['reject_reason']) ? $param['reject_reason'] : '';
        }

        $query = DealerModel::query()
            ->where('site_id', $this->siteId)
            ->where('status', Constants::DealerStatus_WaitReview);
        $query->where('member_id', $param['member_id']);
        $save = $query->update($update);
        if ($save) {
            $dealerModel = DealerModel::query()->where('site_id', $this->siteId)->where('member_id', $param['member_id'])->first();
            if ($dealerModel->invite_review_status == Constants::DealerStatus_Active && $dealerModel->parent_review_status == Constants::DealerStatus_Active && $dealerModel->verify_process == 1) {
                $dealerModel->status = Constants::DealerStatus_Active;
                $dealerModel->passed_at = date("Y-m-d H:i:s", time());
                $dealerModel->save();
                $this->becomeDealerAfter($dealerModel);
            } else if ($dealerModel->invite_review_status == Constants::DealerStatus_RejectReview || $dealerModel->parent_review_status == Constants::DealerStatus_RejectReview) {
                $dealerModel->status = Constants::DealerStatus_RejectReview;
                $dealerModel->passed_at = date("Y-m-d H:i:s", time());
                $dealerModel->save();
                // 申请经销商被拒通知
                DealerMessageNotice::sendMessageDealerReject($dealerModel);
            }
            $dealerModel->review_member_id = $param['review_member_id'];
            $dealerModel->log_id = $param['log_id'];
            VerifyLog::Log(Constants::VerifyLogType_DealerVerify, $dealerModel);
        }
        return $save;
    }

    /**
     * 新增加盟费财务记录
     * @param $dealer
     * @return mixed
     */
    public function addDealerInitialFinance($dealer)
    {
        if ($dealer->initial_money && $dealer->initial_pay_type) {
            $siteId = $dealer->site_id;
            $memberId = $dealer->member_id;
            $orderId = 'JMF_' . date('YmdHis');
            $money = $dealer->initial_money;
            $payType = $dealer->initial_pay_type;
            FinanceHelper::addDealerInitialMoney($siteId, $memberId, $orderId, $money, $payType);
        }
    }

    /**
     * 删除拒绝申请经销商的记录
     * @param $memberId
     * @return mixed
     */
    public function delDealerRejectApplyData($memberId)
    {
        $del = DealerModel::query()->where('site_id', $this->siteId)
            ->where('status', Constants::DealerStatus_RejectReview)
            ->where('member_id', $memberId)
            ->delete();
        return $del;
    }

    /**
     * 恢复代理
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function resumeDealer($memberId, $dealerLevel, $dealerHideLevel = 0)
    {
        $dealer = $this->checkDealerExist($memberId);
        $dealer->status = Constants::DealerStatus_Active;
        $dealer->cancel_history_dealer_level = 0;
        $dealer->cancel_history_dealer_hide_level = 0;
        $member = MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $dealer->member_id)
            ->first();
        $member->dealer_level = $dealerLevel;
        $member->dealer_hide_level = $dealerHideLevel;
        $member->save();
        $save = $dealer->save();
        if ($save) {
            $dealer->level = $dealerLevel;
            $this->resumeDealerAfter($dealer);
            // 成为经销商通知
            //   MessageNoticeHelper::sendMessageAgentAgree($agent);
        } else {
            throw new \Exception("恢复代理出错");
        }
    }

    /**
     * 恢复代理之后的操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function resumeDealerAfter($dealer)
    {
        // 修改云仓状态
        (new CloudStock($dealer->member_id, 0))->setStatus(1);
        // 更新团队关系
        DealerHelper::dispatchResetDealerParentsJob($dealer->member_id);
        //改为用队列处理  代理商升级
        //  $this->dispatch(new UpgradeAgentLevelJob($agent->member_id));
    }


    /**
     * 取消代理
     * @param $memberId
     * @return bool
     * @throws \Exception
     */
    public function cancelDealer($memberId)
    {
        $agent = $this->checkDealerExist($memberId);
        $agent->status = Constants::DealerStatus_Cancel;
        $save = $agent->save();
        if ($save) {
            $this->cancelDealerAfter($agent);
        } else {
            throw new \Exception("取消代理出错");
        }
    }

    /**
     * 取消代理之后的操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancelDealerAfter($dealer)
    {
        // 会员表更新一下代理等级为0
        $this->cancelDealerUpdateMember($dealer);
        // 修改云仓状态
        (new CloudStock($dealer->member_id))->setStatus(0);
        // 更新团队关系
        DealerHelper::dispatchResetDealerParentsJob($dealer->member_id);
        //改为用队列处理  代理商升级
        //  $this->dispatch(new UpgradeAgentLevelJob($dealer->member_id));
    }

    /**
     * 取消代理之后对会员代理的相关操作
     * @param $agent
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancelDealerUpdateMember($dealer)
    {
        $member = MemberModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $dealer->member_id)
            ->first();
        $historyDealerLevel = $member->dealer_level;
        $historyDealerHideLevel = $member->dealer_hide_level;
        $member->dealer_level = 0;
        $member->dealer_hide_level = 0;
        $member->save();
        $dealerModel = DealerModel::query()
            ->where('site_id', $this->siteId)
            ->where('member_id', $dealer->member_id)
            ->first();
        $dealerModel->cancel_history_dealer_level = $historyDealerLevel;
        $dealerModel->cancel_history_dealer_hide_level = $historyDealerHideLevel;
        $dealerModel->save();
    }

    /**
     * 检测代理是否存在
     * @param int $memberId 会员id
     * @param bool $throwError 是否抛出错误
     * @return bool|\Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function checkDealerExist($memberId, $throwError = false)
    {
        $dealer = DealerModel::query()->where('site_id', $this->siteId)
            ->where('member_id', $memberId)
            ->first();
        if (!$dealer) {
            if ($throwError) {
                throw new \Exception("经销商记录不存在");
            } else {
                return false;
            }
        }
        return $dealer;
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
        $subPath = '/dealer/';
        $upload_filename = "{$type}_" . $memberId . '_' . genUuid(8);
        $upload_filepath = Site::getSiteComdataDir('', true) . $subPath;
        $upload_handle = new FileUpload($file, $upload_filepath, $upload_filename);
        $upload_handle->reduceImageSize(800);
        $file = $subPath . $upload_handle->getFullFileName();
        return $file;
    }

    /**
     * 获取经销商详情
     * @param $memberId
     * @return array
     * @throws \Exception
     */
    public function getDealerInfo($memberId)
    {
        $member = (new Member($memberId))->getModel();
        if (!$member) {
            throw new \Exception('会员不存在');
        }
        // 获取等级名称
        $levelName = [];
        if ($member->dealer_level) {
            $levels = [$member->dealer_level];
            // 如果有隐藏等级 要把隐藏等级也查询出来
            if ($member->dealer_hide_level) {
                $levels[] = $member->dealer_hide_level;
            }
            // 这里用parent_id 做排序 用来区分主等级和隐藏等级
            $levelName = DealerLevelModel::query()->where('site_id', $this->siteId)
                ->whereIn('id', $levels)
                ->orderBy('parent_id')
                ->pluck('name')->toArray();
        }
        if ($member->headurl && !preg_match('@^(http:|https:)@i', $member->headurl)) {
            $member->headurl = Site::getSiteComdataDir() . $member->headurl;
        }
        if ($member) {
            $dealer_parent = (new Member($member->dealer_parent_id))->getModel();;
        }
        // 获取库存总量
        $info = [
            'nickname' => $member->nickname,
            'headurl' => $member->headurl,
            'name' => $member->name,
            'member_id' => $memberId,
            'mobile' => $member->mobile,
            'level_name' => $levelName,
            'total_inventory' => $this->getTotalInventory($memberId),
            'dealer_parent_nickname' => $dealer_parent->nickname ? $dealer_parent->nickname : '总店',
            'dealer_parent_name' => $dealer_parent->name,
            'dealer_parent_id' => $member->dealer_parent_id

        ];
        return $info;
    }

    /**
     * 返回云仓的总库存
     * @param int $memberId 会员id
     * @return int
     */
    public function getTotalInventory($memberId)
    {
        return CloudStockSkuModel::query()->where('member_id', $memberId)->sum('inventory');
    }

    /**
     * 返回团队人数
     * @param int $memberId 会员id
     * @param boolean $groupby
     * @param 搜索体条件
     * @return int
     */
    public static function getDealerTeam($memberId, $groupby = true, $params = [])
    {
        //获取该会员等级
        $deal = (new Member($memberId))->getModel();
        $dealLevelHash = (new DealerLevel())->getCachedLevels();
        $dealLevel = $dealLevelHash[$deal->dealer_level];
        $dealLevelWeight = $dealLevel['weight'];
        $siteId = Site::getCurrentSite()->getSiteId();
        //跨级推荐人
        $crossLevelDealer = MemberModel::query()
            ->leftJoin('tbl_dealer_level as dl', 'tbl_member.dealer_level', '=', 'dl.id')
            ->leftJoin('tbl_dealer', 'tbl_dealer.member_id', 'tbl_member.id')
            ->where('dl.site_id', $siteId)
            ->where('invite1', $memberId)
            ->where('tbl_member.dealer_level', '>', 0)
            ->where('dl.weight', '>', $dealLevel['weight'])
            ->selectRaw('tbl_member.*,dl.name as level_name')
            ->withCount(['dealerCloudStockPurchasePerformance as performance_reward_count' => function ($query) {
                $query->whereIn('status', [Constants::CloudStockPurchaseOrderStatus_Reviewed, Constants::CloudStockPurchaseOrderStatus_Finished]);
                $query->select(DB::raw("sum(total_money) as member_performance_reward"));
            }])
            ->orderBy('tbl_dealer.passed_at', 'desc')
            ->get();
        //同级推荐人
        $sameLevelDealer = MemberModel::query()
            ->leftJoin('tbl_dealer_level as dl', 'tbl_member.dealer_level', '=', 'dl.id')
            ->leftJoin('tbl_dealer', 'tbl_dealer.member_id', 'tbl_member.id')
            ->where('dl.site_id', $siteId)
            ->where('invite1', $memberId)
            ->where('tbl_member.dealer_level', '>', 0)
            ->where('dl.weight', '=', $dealLevelWeight)
            ->selectRaw('tbl_member.*,dl.name as level_name')
            ->withCount(['dealerCloudStockPurchasePerformance as performance_reward_count' => function ($query) {
                $query->whereIn('status', [Constants::CloudStockPurchaseOrderStatus_Reviewed, Constants::CloudStockPurchaseOrderStatus_Finished]);
                $query->select(DB::raw("sum(total_money) as member_performance_reward"));
            }])
            ->orderBy('tbl_dealer.passed_at', 'desc')
            ->get();
        //下级经销商
        $teamDealer = MemberModel::query()
            ->rightJoin('tbl_dealer_parents', 'tbl_dealer_parents.member_id', '=', 'tbl_member.id')
            ->leftJoin('tbl_dealer_level as dl', 'tbl_member.dealer_level', '=', 'dl.id')
            ->leftJoin('tbl_dealer', 'tbl_dealer.member_id', 'tbl_member.id')
            ->where('tbl_member.dealer_level', '>', 0)
            ->where('tbl_dealer_parents.parent_id', $memberId)
            ->where('tbl_dealer_parents.level', 1)
            ->selectRaw('tbl_member.*,dl.name as level_name')
            ->withCount(['dealerCloudStockPurchasePerformance as performance_reward_count' => function ($query) {
                $query->whereIn('status', [Constants::CloudStockPurchaseOrderStatus_Reviewed, Constants::CloudStockPurchaseOrderStatus_Finished]);
                $query->select(DB::raw("sum(total_money) as member_performance_reward"));
            }])
            ->orderBy('tbl_dealer.passed_at', 'desc')
            ->get();
        //各等级的人
        $dealLevelTeamMemberQuery = MemberModel::query()
            ->leftJoin('tbl_dealer_level as dl', 'tbl_member.dealer_level', '=', 'dl.id')
            ->leftJoin('tbl_dealer_parents as dp', 'dp.member_id', 'tbl_member.id')
            ->leftJoin('tbl_dealer', 'tbl_dealer.member_id', 'tbl_member.id')
            ->where('tbl_member.site_id', $siteId)
            ->where(function ($q) use ($memberId, $dealLevelWeight) {
                $q->where(function ($q) use ($memberId, $dealLevelWeight) {
                    $q->where('invite1', '=', $memberId)
                        ->where('dl.weight', '>=', $dealLevelWeight);
                });
                $q->orWhere(function ($q) use ($memberId) {
                    $q->where('dp.level', 1)
                        ->where('dp.parent_id', '=', $memberId);
                });
            });
        if ($params['level']) {
            $dealLevelTeamMemberQuery->where('tbl_member.dealer_level', '=', $params['level']);
        } else {
            $dealLevelTeamMemberQuery->where('tbl_member.dealer_level', '>', 0);
        }
        if ($groupby) {
            $dealLevelTeamMemberQuery->groupBy('dl.id')
                ->orderBy('dl.weight', 'Desc')
                ->selectRaw('dl.id,dl.name,count(tbl_member.id) as member_count');
        } else {
            $dealLevelTeamMemberQuery->selectRaw('tbl_member.*,dl.name as level_name');
            $dealLevelTeamMemberQuery->withCount(['dealerCloudStockPurchasePerformance as performance_reward_count' => function ($query) {
                $query->whereIn('status', [Constants::CloudStockPurchaseOrderStatus_Reviewed, Constants::CloudStockPurchaseOrderStatus_Finished]);
                $query->select(DB::raw("sum(total_money) as member_performance_reward"));
            }]);
        }
        $dealLevelTeamMemberQuery->orderBy('tbl_dealer.passed_at', 'desc');
        $dealLevelTeamMember = $dealLevelTeamMemberQuery->get();
        return [
            'crossLevelDealer' => $crossLevelDealer,
            'sameLevelDealer' => $sameLevelDealer,
            'teamDealer' => $teamDealer,
            'dealLevelTeamMember' => $dealLevelTeamMember
        ];
    }

}