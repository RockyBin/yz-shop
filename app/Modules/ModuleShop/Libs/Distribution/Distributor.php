<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\Model\DistributionSettingModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\OpLog\OpLog;
use Carbon\Carbon;
use YZ\Core\Common\DataCache;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberAuth;
use YZ\Core\Member\Member;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForDistributionBecome;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForDistributionRecommend;
use App\Modules\ModuleShop\Libs\Model\UniqueLogModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use Illuminate\Support\Collection;
use YZ\Core\Weixin\WxUser;

/**
 * 分销等级
 * @author Administrator
 */
class Distributor
{
    use DispatchesJobs;
    private $_model = null;
    private $autoCreate = false;
    const ListType_Review = 1; // 审核用的数据

    /**
     * 初始化分销等级对象
     * Distributor constructor.
     * @param $idOrModel 菜单的 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel = '', $autoCreate = false)
    {
        $this->autoCreate = $autoCreate;
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->_model = $this->find($idOrModel);
            } else {
                $this->_model = $idOrModel;
            }
        }
    }

    /**
     * 返回数据库记录模型
     * @return null|DistributorModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 添加分销商
     * @param array $info 分销商信息
     * @param bool $reload 是否重新加载数据，默认否
     * @param bool $returnExist 是否返回已存在的数据
     * @return Distributor|\Illuminate\Database\Eloquent\Model|null|object|bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add(array $info, $reload = false, $returnExist = false)
    {
        $memberId = $info['member_id'];

        $member = new Member($memberId);
        if (!$member->checkExist()) {
            throw new \Exception("会员不存在");
        }
        // 检测是否绑定了手机号
        $member->checkBindMobile();

        // 检查是否已经有记录
        $check = $this->find($memberId);
        // 已存在 = 未删除 并且 已通过审核
        if (
            $check
            && intval($check->is_del) == LibsConstants::DistributorIsDel_No
            && intval($check->status) == LibsConstants::DistributorStatus_Active
        ) {
            throw new \Exception('分销商已存在，不能重复添加');
        }
        if (!$check) {
            $this->_model = new DistributorModel();
            // 如果没有设定指定分销商等级，拿默认等级
            if (!$info['level']) {
                $defaultLevelModel = DistributionLevelModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('weight', 0)
                    ->first();
                if ($defaultLevelModel) {
                    $info['level'] = $defaultLevelModel->id;
                }
            }
        } else {
            $this->_model = $check;
            // 待审核和已取消资格的 后台让用户自己处理 这里返回数据即可
            if (
                $returnExist
                && intval($check->is_del) == LibsConstants::DistributorIsDel_No
                && intval($check->status) != LibsConstants::DistributorStatus_RejectReview
            ) {
                return $check;
            }
        }
        $info['is_del'] = LibsConstants::DistributorIsDel_No;
        $this->_model->fill($info);
        $this->_model->save();
        // 更新会员是否为分销商的标志
        if (intval($info['status']) === 1) {
            $member->edit(['is_distributor' => 1]);
        }
        // 重新加载这个数据
        if ($reload) {
            $this->_model = $this->find($memberId);
            // 处理相关事情
            $this->eventForDistributorActive($member);
        }
        return true;
    }

    /**
     * 更新分销商信息
     * @param array $info
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function edit(array $info)
    {
        $oldLevel = intval($this->_model->level);
        $oldStatus = intval($this->_model->status);
        // 如果状态变为生效
        if (key_exists('status', $info) && $this->_model->status != $info['status']) {
            $info['passed_at'] = date('Y-m-d H:i:s');
        }

        $this->_model->fill($info);
        $this->_model->save();
        // 更新会员是否为分销商的标志
        $member = new Member($this->_model->member_id);
        if (key_exists('status', $info)) {
            if (intval($info['status']) === 1) {
                // 此全局变量用雨
                $GLOBALS['is_distributor_' . $this->_model->member_id] = ($member->getModel())->is_distributor;
                $member->edit(['is_distributor' => 1]);
                // 处理相关事情
                if (!$oldStatus) $this->eventForDistributorActive($member);
            } else {
                $member->edit(['is_distributor' => 0]);
            }
        }

        //如果更改了上级
        if (key_exists('parent_id', $info)) {
            if ($info['parent_id'] != $member->getModel()->invite1) {
                $member->setParent($info['parent_id']);
            }
        }
        // 如果修改了等级
        if (key_exists('level', $info) && intval($info['level']) != $oldLevel) {
            $oldDistributionLevel = DistributionLevelModel::query()->where('site_id',
                Site::getCurrentSite()->getSiteId())->where('id', $oldLevel)->first();
            $newDistributionLevel = DistributionLevelModel::query()->where('site_id',
                Site::getCurrentSite()->getSiteId())->where('id', intval($info['level']))->first();
            //记录用户操作 $oldLevel 更改前的分销等级ID $parentId 更改后的分销等级ID
            OpLog::Log(LibsConstants::OpLogType_DistributorLevelChange, $this->_model->member_id, $oldLevel,
                $info['level']);
            //改为用队列处理 相关代理升级
            $this->dispatch(new UpgradeAgentLevelJob($this->getMemberId()));
            $this->dispatch(new UpgradeDistributionLevelJob($this->getMemberId()));
            // 发送分销商升级通知
            if ($oldDistributionLevel && $newDistributionLevel && intval($newDistributionLevel->weight) > intval($oldDistributionLevel->weight)) {
                MessageNoticeHelper::sendMessageDistributorLevelUpgrade($this->_model, $oldDistributionLevel->name);
            }
        }
    }

    /**
     * 恢复分销商资格
     *
     * @return void
     */
    function reActive()
    {
        $this->_model->fill(['status' => 1]);
        $this->_model->save();
        $member = new Member($this->_model->member_id);
        $member->edit(['is_distributor' => 1]);
        $this->eventForDistributorActive();
    }

    /**
     * 取消分销商资格
     *
     * @return void
     */
    public function deActive()
    {
        // 处理当前分销商
        $this->_model->status = LibsConstants::DistributorStatus_DeActive;
        $this->_model->save();
        // 重置会员是否为分销商的标志
        $member = new Member($this->getMemberId());
        if ($member->checkExist()) {
            $member->edit(['is_distributor' => 0]);
        }
    }

    /**
     * 重新计算分佣
     * @params int $member_id 会员ID
     * @param  array $orderId 需要改变的订单ID
     */
    function doDistribution($member_id, $orderId = [])
    {
        if (count($orderId) <= 0) {
            $orderHelp = new OrderHelper();
            // 订单成功，但没过维权期的状态
            $status = BaseShopOrder::getNoFinishStatusList();
            $orderId = $orderHelp->getOrder($status, $member_id, true);
        }
        foreach ($orderId as $k => $v) {
            ShopOrderFactory::createOrderByOrderId($v['id'])->doDistribution();
        }
    }

    /**
     * 在审核列表中删除
     */
    public function deleteInReview()
    {
        $this->_model->is_del = LibsConstants::DistributorIsDel_Yes;
        $this->_model->save();
    }

    /**
     * 删除分销商(软删)
     */
    public function delete()
    {
        $this->deActive();
        $this->_model->is_del = LibsConstants::DistributorIsDel_Yes;
        $this->_model->save();
    }

    /**
     * 根据会员id查找
     * @param $memberId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function find($memberId)
    {
        $model = DistributorModel::query()
            ->where('member_id', $memberId)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->first();
        if ($this->autoCreate && !$model) {
            $model = $this->initDistributor($memberId);
        }
        return $model;
    }

    /**
     * 初始化分销商模型
     * @param int $memberId
     * @return DistributorModel
     */
    private function initDistributor($memberId)
    {
        $model = new DistributorModel();
        $model->member_id = $memberId;
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->status = 0;
        return $model;
    }

    /**
     * 检查数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->member_id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 分销商是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && $this->getModel()->status == 1 && $this->getModel()->is_del == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否取消了分销商
     * @return bool
     */
    public function isDel()
    {
        if ($this->checkExist() && $this->getModel()->is_del == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 返回会员id
     * @return int
     */
    public function getMemberId()
    {
        if ($this->_model) {
            return $this->_model->member_id;
        } else {
            return 0;
        }
    }

    /**
     * 检测并对此分销商进行升级
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function upgrade($params)
    {
        // 取消分销资格 拒绝分销申请并且未删除的的无法自动升级
        if (
            ($this->_model->status == LibsConstants::DistributorStatus_RejectReview && $this->_model->is_del == LibsConstants::DistributorIsDel_No)
            || $this->_model->status == LibsConstants::DistributorStatus_DeActive
        ) {
            return false;
        }
        // 从权重大的开始匹配
        $levels = DistributionLevel::getList(false, [
            'order_by' => 'weight',
            'order_by_desc' => true,
            'weight' => ($this->_model->level > 0 && $this->_model->status == LibsConstants::DistributorStatus_Active)
                ? $this->_model->levelInfo->weight : 0
        ]);
        // 获取当前设置的分销层级
        $settingLevel = DistributionSettingModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->value('level');
        if (!$settingLevel) {
            return false;
        }
        // 升级需要的额外参数
        $params['is_distributor'] = $this->_model->level;
        $params['setting_level'] = $settingLevel;

        foreach ($levels as $level) {
            // 禁用或不应用新分销商的等级不能升级
            if (intval($level->status) !== 1 || intval($level->new_open) !== 1) {
                continue;
            }
            // 如果没有开启会员自动升级 并且当前不是分销商 不能升级
            if ($level->auto_upgrade == 0 && $this->_model->status !== LibsConstants::DistributorStatus_Active) {
                continue;
            }
            $cacheKey = 'DistributorUpgrade_' . $this->getMemberId() . '_' . $level->id;
            if (DataCache::getData($cacheKey)) continue; //由于在队列里有N个地方可能涉及到分销商升级，可能会重复调用升级过程，这里做一下重复调用的验证
            $instance = new DistributionLevel($level);
            if ($this->_model->level != $level->id && $instance->canUpgrade($this->getMemberId(), $params)) {
                // 判断是否是会员直接升级的情况 拒绝后删掉的 也可以直接升级
                $isMember = ($this->_model->status == LibsConstants::DistributorStatus_WaitReview);
                // 现在会员可以直接升级到分销商
                if (
                    $isMember
                    || ($this->_model->level > 0 && $this->_model->levelInfo->weight < $level->weight)
                    || ($this->_model->is_del == LibsConstants::DistributorIsDel_Yes && $this->_model->status == LibsConstants::DistributorStatus_RejectReview)
                ) {
                    $editData = [
                        'level' => $level->id,
                        'is_del' => LibsConstants::DistributorIsDel_No,
                    ];
                    if (intval($this->_model->status) === 0) {
                        $editData['status'] = LibsConstants::DistributorStatus_Active; //只有当原来的状态是待审时，才自动改变状态，否则会出现重复调用成为分销商的某些过程
                    }
                    // 会员自动升级
                    if ($isMember) {
                        $this->saveAutoUpgradeDistributor($level);
                        $logText = 'member_id[' . $this->getMemberId() . '] from member auto upgrade to ' . $level->name . '[' . $level->id . ']';
                    } else {
                        // 先记录下当前分销商等级
                        $curDistributionLevel = new DistributionLevel($this->_model->level);
                        $distributionLevelModel = $curDistributionLevel->getModel();
                        $curDistributionLevelName = $distributionLevelModel ? $distributionLevelModel->name : '默认';
                        $logText = 'member_id[' . $this->getMemberId() . '] from ' . $curDistributionLevelName . '[' . ($distributionLevelModel ? $distributionLevelModel->id : 0) . '] upgrade to ' . $level->name . '[' . $level->id . ']';
                    }
                    // 保存
                    $this->edit($editData);

                    // 写日志
                    Log::writeLog('distributorLevelUpgrade', $logText);
                    // 匹配成功则退出循环
                    DataCache::setData($cacheKey, 1);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 保存自动升级的分销商信息
     * @param $level
     * @return mixed
     */
    private function saveAutoUpgradeDistributor($level)
    {
        $this->_model->level = $level->id;
        $this->_model->status = LibsConstants::DistributorStatus_Active;
        $now = Carbon::now();
        $this->_model->created_at = $now;
        $this->_model->passed_at = $now;
        $conditions = is_string($level->condition) ? json_decode($level->condition, true) : $level->condition;
        $cache = [
            'data' => $conditions['upgrade'],
            'text' => DistributionLevel::getLevelConditionsTitle($conditions['upgrade'], []),
            'level_info' => ['level_id' => $level->id, 'level_name' => $level->name]
        ];
        $this->_model->auto_upgrade_data = json_encode($cache, JSON_UNESCAPED_UNICODE);

        return $this->_model->save();
    }

    /**
     * 返回分销商的信息
     * @param array $params
     * @return array
     */
    public function getInfo(array $params = [])
    {
        $params['member_id'] = $this->_model->member_id;
        $baseInfo = self::getList($params)[0];
        // 相关会员的信息
        $memberInfo = $this->_model->memberInfo;
        if (substr($memberInfo->headurl, 0, 1) == '/') {
            $memberInfo->headurl = Site::getSiteComdataDir() . $memberInfo->headurl;
        }
        $memberInfo['provname'] = $memberInfo->provInfo->name;
        $memberInfo['cityname'] = $memberInfo->cityInfo->name;
        $memberInfo['areaname'] = $memberInfo->areaInfo->name;
        $memberInfo['buy_money'] = moneyCent2Yuan($memberInfo->buy_money);
        $memberInfo['deal_money'] = moneyCent2Yuan($memberInfo->deal_money);
        unset($memberInfo->provInfo);
        unset($memberInfo->cityInfo);
        unset($memberInfo->areaInfo);
        // 上级分销商的信息
        if ($params['return_parent_info']) {
            $parentInfo = DistributorModel::query()
                ->from('tbl_distributor as distributor')
                ->leftJoin('tbl_member as member', 'member.id', '=', 'distributor.member_id')
                ->leftJoin('tbl_distribution_level as distribution_level', 'distribution_level.id', '=',
                    'distributor.level')
                ->where('distributor.is_del', 0)
                ->where('distributor.member_id', $baseInfo->parent_id)
                ->select('distributor.status', 'member.name', 'member.nickname', 'member.headurl', 'member.mobile',
                    'distribution_level.name as distribution_level_name')
                ->first();
        }
        // 查找绑定的微信
        if ($params['return_bind_weixin']) {
            /*$auth = $memberInfo->authList()->where('type', '=', \YZ\Core\Constants::MemberAuthType_WxOficialAccount)->first();
            if ($auth && $auth->openid) {
                $wxuser = new \YZ\Core\Weixin\WxUser($auth->openid);
                $memberInfo['bind_weixin'] = $wxuser->getModel()->nickname;
            }*/
            $wxOpenId = (new Member($memberInfo))->getOfficialAccountOpenId();
            if ($wxOpenId) {
                $wxuser = new WxUser($wxOpenId);
                if ($wxuser->getModel()) {
                    $memberInfo['bind_weixin'] = $wxuser->getModel()->nickname;
                }
            }
        }
        return ['base_info' => $baseInfo, 'member_info' => $memberInfo, 'parent_info' => $parentInfo];
    }

    /**
     * 返回分销商的审核信息
     * @param array $params
     * @return array
     */
    public function getReviewDistributorInfo(array $params = [])
    {
        $params['member_id'] = $this->_model->member_id;
        $baseInfo = $this->_model;
        // 相关会员的信息
        $memberInfo = $this->_model->memberInfo;
        if (substr($memberInfo->headurl, 0, 1) == '/') {
            $memberInfo->headurl = Site::getSiteComdataDir() . $memberInfo->headurl;
        }
        $memberInfo['provname'] = $memberInfo->provInfo->name;
        $memberInfo['cityname'] = $memberInfo->cityInfo->name;
        $memberInfo['areaname'] = $memberInfo->areaInfo->name;
        $memberInfo['buy_money'] = moneyCent2Yuan($memberInfo->buy_money);
        $memberInfo['deal_money'] = moneyCent2Yuan($memberInfo->deal_money);
        unset($memberInfo->provInfo);
        unset($memberInfo->cityInfo);
        unset($memberInfo->areaInfo);
        if ($baseInfo->extend_fields) {
            $baseInfo->extend_fields = json_decode($baseInfo->extend_fields);
        }
        $expression = DistributorModel::query();
        $expression->where('member_id', '=', $this->_model->member_id);
        $expression->leftJoin('tbl_district as tbl_district_prov', 'tbl_distributor.prov', '=', 'tbl_district_prov.id');
        $expression->leftJoin('tbl_district as tbl_district_city', 'tbl_distributor.city', '=', 'tbl_district_city.id');
        $expression->leftJoin('tbl_district as tbl_district_area', 'tbl_distributor.area', '=', 'tbl_district_area.id');
        $expression->addSelect([
            'tbl_district_prov.name as prov_text',
            'tbl_district_city.name as city_text',
            'tbl_district_area.name as area_text'
        ]);
        $adress_text = $expression->first();
        if ($adress_text) {
            $baseInfo->prov = $adress_text->prov_text;
            $baseInfo->city = $adress_text->city_text;
            $baseInfo->area = $adress_text->area_text;
        }
        $baseInfo->isBuyProduct = 0;
        if ($baseInfo->apply_condition) {
            $apply_condition = json_decode($baseInfo->apply_condition, true);
            $baseInfo->calc_apply_valid_condition = $apply_condition['calc_apply_valid_condition'];
            $baseInfo->buy_money = moneyCent2Yuan($apply_condition['buy_money']);
            $baseInfo->buy_times = $apply_condition['buy_times'];
            $baseInfo->directly_member = $apply_condition['directly_member'];
            if ($apply_condition['condition'] == LibsConstants::DistributionCondition_DirectlyMember) {
                $count = MemberParentsModel::query()
                    ->where('parent_id', $this->_model->member_id)
                    ->where('level', 1)
                    ->count();
                $baseInfo->now_directly_member = $count;
            }
            $baseInfo->condition = $apply_condition['condition'];
            if ($apply_condition['buy_product']) {
                $buy_product = myToArray($apply_condition['buy_product']);
                $baseInfo->isBuyProduct = OrderItemModel::query()
                    ->leftJoin('tbl_order', 'tbl_order_item.order_id', '=', 'tbl_order.id')
                    ->where(['tbl_order.member_id' => $this->_model->member_id])
                    ->whereIn('product_id', $buy_product)
                    ->count();
            }
        }

        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        $baseInfo->isBuyProduct = 0;
        if ($distributionSetting) {
            if ($distributionSetting->buy_product) {
                $buy_product = myToArray($distributionSetting->buy_product);
                $baseInfo->isBuyProduct = OrderItemModel::query()
                    ->leftJoin('tbl_order', 'tbl_order_item.order_id', '=', 'tbl_order.id')
                    ->where(['tbl_order.member_id' => $this->_model->member_id])
                    ->whereIn('product_id', $buy_product)
                    ->count();
            }
        }
        return $baseInfo;
    }

    /**
     * 查询分销商列表(用户于后台)
     * @param array $params
     * @return array|bool|int
     */
    public static function getList(array $params = [])
    {
        $listType = intval($params['list_type']);
        $setting = DistributionSetting::getCurrentSiteSetting();
        $maxLevel = $setting ? intval($setting['level']) : 0;
        $select = "select tbl_distributor.*, tbl_member.headurl, tbl_member.name, tbl_member.nickname, tbl_member.mobile as member_mobile, tbl_member.sex as member_sex, tbl_member.level as member_level, tbl_member.status as member_status,tbl_member.agent_level,tbl_member.dealer_level,
                    tbl_member.age as member_age, tbl_member.terminal_type as member_terminal_type, tbl_member.created_at as member_created_at, tbl_member.prov as member_prov, tbl_member.city as member_city, tbl_member.area as member_area, 
                    tbl_member_level.name as member_level_name, tbl_distribution_level.name as distributor_level_name, tbl_member.invite1 as parent_id ,tbl_member.buy_times as member_buy_times,tbl_member.buy_money as member_buy_money,tbl_member.deal_times as member_deal_times,tbl_member.deal_money as member_deal_money";
        $select .= ",tbl_site_admin.name as admin_name,tbl_site_admin.mobile as admin_mobile";

        // return_total_team return_commission_money return_directly_under_distributor 这几个是使用子查询进行统计，会员数据多时会很慢
        // 这里强制禁用，而在取出列表结果后再根据会员ID进行二次查询，减少扫表
        $subsqls = self::getRelationSql(array_merge($params, ['return_total_team' => 0, 'return_commission_money' => 0, 'return_directly_under_distributor' => 0]), $setting);
        if ($subsqls) {
            $select .= "," . implode(",", $subsqls);
        }

        //构建一张关联统计的临时表
        $statistics_sql = self::getStatisticsRelationSql();
        //关联统计表的需要输出的字段
        $statistics_cloum = ',member_count.trade_money,member_count.trade_time';
        $select .= $statistics_cloum;

        $from = " from tbl_distributor left join tbl_member on tbl_member.id = tbl_distributor.member_id";
        $from .= " left join tbl_member_level on tbl_member_level.id = tbl_member.level";
        $from .= " left join tbl_distribution_level on tbl_distribution_level.id = tbl_distributor.level";
        $from .= " left join tbl_site_admin on tbl_member.admin_id = tbl_site_admin.id";
        $from .= " left join (" . $statistics_sql . ") as member_count on member_count.member_id = tbl_distributor.member_id";

        $where = " where tbl_distributor.site_id = " . Site::getCurrentSite()->getSiteId();
        $having = [];
        $queryparams = [];
        // 该字段show_in_review不再使用
//        if (intval($listType) == self::ListType_Review) {
//            // 审核
//            $where .= " and tbl_distributor.show_in_review = 1";
//        }
        if (!$params['show_del']) {
            $where .= " and tbl_distributor.is_del <> 1";
        }
        // 读取指定分销商的情况
        if (is_numeric($params['member_id'])) {
            $where .= " and tbl_distributor.member_id = :member_id";
            $queryparams['member_id'] = intval($params['member_id']);
        }
        // 用于更改分销上级时，不能列出此会员以下级会员
        if ($params['no_member_id']) {
            $where .= " and (tbl_distributor.member_id <> :member_id";
            $queryparams['member_id'] = $params['no_member_id'];
            // TODO: 不知道为什么腾讯数据库查询到了invite10就会很卡，暂且用 not like 代替
            for ($i = 1; $i <= 10; $i++) {
                // $where .= " and (tbl_member.invite" . $i . " <> :invite" . $i . " or tbl_member.invite" . $i . " is null)";
                $where .= " and (tbl_member.invite" . $i . " not like :invite" . $i . " or tbl_member.invite" . $i . " is null)";
                $queryparams['invite' . $i] = $params['no_member_id'];
            }
            $where .= ")";
        }
        // 列出某分销商的下级分销商的情况
        if ($params['parent_id']) {
            $where .= " and tbl_member.invite1 = :parent_id";
            $queryparams['parent_id'] = $params['parent_id'];
        }
        // 会员姓名
        if ($params['name']) {
            $where .= " and tbl_member.name like :name";
            $queryparams['name'] = '%' . $params['name'] . '%';
        }
        // 会员姓名
        if ($params['nickname']) {
            $where .= " and tbl_member.nickname like :nickname";
            $queryparams['nickname'] = '%' . $params['nickname'] . '%';
        }
        // 会员手机
        if ($params['mobile']) {
            $where .= " and tbl_member.mobile like :mobile";
            $queryparams['mobile'] = '%' . $params['mobile'] . '%';
        }
        // 关键字模糊搜索
        if ($params['keyword']) {
            if ($params['keyword_type'] == 2) {
                $where .= " and (tbl_site_admin.mobile like :keyword1 or  tbl_site_admin.name like :keyword2)";
                $queryparams['keyword1'] = '%' . $params['keyword'] . '%';
                $queryparams['keyword2'] = '%' . $params['keyword'] . '%';
            } else {
                $where .= " and (tbl_member.mobile like :keyword1 or tbl_member.nickname like :keyword2 or tbl_member.name like :keyword3)";
                $queryparams['keyword1'] = '%' . $params['keyword'] . '%';
                $queryparams['keyword2'] = '%' . $params['keyword'] . '%';
                $queryparams['keyword3'] = '%' . $params['keyword'] . '%';
            }

        }
        // 分销商等级
        if (is_numeric($params['level']) && intval($params['level']) > 0) {
            $where .= " and tbl_distributor.level = :level";
            $queryparams['level'] = intval($params['level']);
        }
        // 会员等级
        if (is_numeric($params['member_level'])) {
            $where .= " and tbl_member.level = :member_level";
            $queryparams['member_level'] = intval($params['member_level']);
        }
        // 分销商状态
        if (is_numeric($params['status'])) {
            $where .= " and tbl_distributor.status = :status";
            $queryparams['status'] = intval($params['status']);
        }
        if (is_array($params['status']) && count($params['status'])) {
            foreach ($params['status'] as $key => $item) {
                $params['status'][$key] = intval($item);
            }
            $where .= " and tbl_distributor.status in (" . implode(',', $params['status']) . ")";
        }
        // 会员的注册时间
        if ($params['reg_time_start']) {
            $where .= " and tbl_member.created_at >= :reg_time_start";
            $queryparams['reg_time_start'] = $params['reg_time_start'];
        }
        if ($params['reg_time_end']) {
            $where .= " and tbl_member.created_at <= :reg_time_end";
            $queryparams['reg_time_end'] = $params['reg_time_end'];
        }
        // 申请分销商的时间
        if ($params['apply_time_start']) {
            $where .= " and tbl_distributor.created_at >= :apply_time_start";
            $queryparams['apply_time_start'] = $params['apply_time_start'];
        }
        if ($params['apply_time_end']) {
            $where .= " and tbl_distributor.created_at <= :apply_time_end";
            $queryparams['apply_time_end'] = $params['apply_time_end'];
        }
        // 成为分销商的时间
        if ($params['passed_time_start']) {
            $where .= " and tbl_distributor.passed_at >= :passed_time_start";
            $queryparams['passed_time_start'] = $params['passed_time_start'];
        }
        if ($params['passed_time_end']) {
            $where .= " and tbl_distributor.passed_at <= :passed_time_end";
            $queryparams['passed_time_end'] = $params['passed_time_end'];
        }
        // 申请分销商的时间
        if ($params['created_time_start']) {
            $where .= " and tbl_distributor.created_at >= :created_time_start";
            $queryparams['created_time_start'] = $params['created_time_start'];
        }
        if ($params['created_time_end']) {
            $where .= " and tbl_distributor.created_at <= :created_time_end";
            $queryparams['created_time_end'] = $params['created_time_end'];
        }
        // 消费次数
//        if (intval($params['buy_times_min'])) {
//            $having[] = " total_buy_times >= :buy_times_min";
//            $queryparams['buy_times_min'] = $params['buy_times_min'];
//        }
//        if (intval($params['buy_times_max'])) {
//            $having[] = " total_buy_times <= :buy_times_max";
//            $queryparams['buy_times_max'] = $params['buy_times_max'];
//        }
// 成交次数
//        if (intval($params['deal_times_min'])) {
//            $having[] = " total_deal_times >= :deal_times_min";
//            $queryparams['deal_times_min'] = $params['deal_times_min'];
//        }
//        if (intval($params['deal_times_max'])) {
//            $having[] = " total_deal_times <= :deal_times_max";
//            $queryparams['deal_times_max'] = $params['deal_times_max'];
//        }
//        // 消费金额
//        if ($params['buy_money_min']) {
//            $params['buy_money_min'] = $params['buy_money_min'] * 100;
//            $having[] = " total_buy_money >= :buy_money_min";
//            $queryparams['buy_money_min'] = $params['buy_money_min'];
//        }
//        if ($params['buy_money_max']) {
//            $params['buy_money_max'] = $params['buy_money_max'] * 100;
//            $having[] = " total_buy_money <= :buy_money_max";
//            $queryparams['buy_money_max'] = $params['buy_money_max'];
//        }
//        // 成交金额
//        if ($params['deal_money_min']) {
//            $params['deal_money_min'] = $params['deal_money_min'] * 100;
//            $having[] = " total_deal_money >= :deal_money_min";
//            $queryparams['deal_money_min'] = $params['deal_money_min'];
//        }
//        if ($params['deal_money_max']) {
//            $params['deal_money_max'] = $params['deal_money_max'] * 100;
//            $having[] = " total_deal_money <= :deal_money_max";
//            $queryparams['deal_money_max'] = $params['deal_money_max'];
//        }

        //交易金额
        if ($params['trade_money_min']) {
            $params['trade_money_min'] = $params['trade_money_min'] * 100;
            $where .= " and member_count.trade_money >= :trade_money_min";
            $queryparams['trade_money_min'] = intval($params['trade_money_min']);
        }
        if ($params['trade_money_max']) {
            $params['trade_money_max'] = $params['trade_money_max'] * 100;
            $where .= " and member_count.trade_money <= :trade_money_max ";
            $queryparams['trade_money_max'] = intval($params['trade_money_max']);
        }

        //交易金额
        if ($params['trade_time_min']) {
            $params['trade_time_min'] = $params['trade_time_min'];
            $where .= " and member_count.trade_time >= :trade_time_min";
            $queryparams['trade_time_min'] = intval($params['trade_time_min']);
        }
        if ($params['trade_time_max']) {
            $params['trade_time_max'] = $params['trade_time_max'];
            $where .= " and member_count.trade_time <= :trade_time_max ";
            $queryparams['trade_time_max'] = intval($params['trade_time_max']);
        }

        // 消费金额
        if ($params['buy_money_min']) {
            $params['buy_money_min'] = $params['buy_money_min'] * 100;
            $where .= " and tbl_member.buy_money >= :buy_money_min";
            $queryparams['buy_money_min'] = intval($params['buy_money_min']);
        }
        if ($params['buy_money_max']) {
            $params['buy_money_max'] = $params['buy_money_max'] * 100;
            $where .= " and tbl_member.buy_money <= :buy_money_max";
            $queryparams['buy_money_max'] = intval($params['buy_money_max']);
        }

        // 成交金额
        if ($params['deal_money_min']) {
            $params['deal_money_min'] = $params['deal_money_min'] * 100;
            $where .= " and tbl_member.deal_money >= :deal_money_min";
            $queryparams['deal_money_min'] = intval($params['deal_money_min']);
        }
        if ($params['deal_money_max']) {
            $params['deal_money_max'] = $params['deal_money_max'] * 100;
            $where .= " and tbl_member.deal_money <= :deal_money_max";
            $queryparams['deal_money_max'] = intval($params['deal_money_max']);
        }

        // 消费次数
        if ($params['buy_times_min']) {
            $params['buy_times_min'] = $params['buy_times_min'];
            $where .= " and tbl_member.buy_times >= :buy_times_min";
            $queryparams['buy_times_min'] = intval($params['buy_times_min']);
        }
        if ($params['buy_times_max']) {
            $params['buy_times_max'] = $params['buy_times_max'];
            $where .= " and tbl_member.buy_times <= :buy_times_max";
            $queryparams['buy_times_max'] = intval($params['buy_times_max']);
        }
        // 成交次数
        if ($params['deal_times_min']) {
            $params['deal_times_min'] = $params['deal_times_min'];
            $where .= " and tbl_member.deal_times >= :deal_times_min";
            $queryparams['deal_times_min'] = intval($params['deal_times_min']);
        }
        if ($params['deal_times_max']) {
            $params['deal_times_max'] = $params['deal_times_max'];
            $where .= " and tbl_member.deal_times <= :deal_times_max";
            $queryparams['deal_times_max'] = intval($params['deal_times_max']);
        }

        // 团队的成交次数
        if ($params['sub_deal_time_min']) {
            $where .= " and subordinate_deal_times >= :sub_deal_time_min";
            $queryparams['sub_deal_time_min'] = $params['sub_deal_time_min'];
        }
        // 团队的成交次数
        if ($params['sub_deal_time_max']) {
            $where .= " and subordinate_deal_times <= :sub_deal_time_max";
            $queryparams['sub_deal_time_max'] = $params['sub_deal_time_max'];
        }
        // 团队的付款次数
        if ($params['sub_buy_time_min']) {
            $where .= " and subordinate_buy_times >= :sub_buy_time_min";
            $queryparams['sub_buy_time_min'] = $params['sub_buy_time_min'];
        }
        // 团队的付款次数
        if ($params['sub_buy_time_max']) {
            $where .= " and subordinate_buy_times <= :sub_buy_time_max";
            $queryparams['sub_buy_time_max'] = $params['sub_buy_time_max'];
        }
        // 团队的成交金额
        if ($params['sub_deal_money_min']) {
            $params['sub_deal_money_min'] = $params['sub_deal_money_min'] * 100;
            $where .= " and subordinate_deal_money >= :sub_deal_money_min";
            $queryparams['sub_deal_money_min'] = $params['sub_deal_money_min'];
        }
        // 团队的成交金额
        if ($params['sub_deal_money_max']) {
            $params['sub_deal_money_max'] = $params['sub_deal_money_max'] * 100;
            $where .= " and subordinate_deal_money <= :sub_deal_money_max";
            $queryparams['sub_deal_money_max'] = $params['sub_deal_money_max'];
        }
        // 团队的付款金额
        if ($params['sub_buy_money_min']) {
            $params['sub_buy_money_min'] = $params['sub_buy_money_min'] * 100;
            $where .= " and subordinate_buy_money >= :sub_buy_money_min";
            $queryparams['sub_buy_money_min'] = $params['sub_buy_money_min'];
        }
        // 团队的付款金额
        if ($params['sub_buy_money_max']) {
            $params['sub_buy_money_max'] = $params['sub_buy_money_max'] * 100;
            $where .= " and subordinate_buy_money <= :sub_deal_money_max";
            $queryparams['sub_buy_money_max'] = $params['sub_buy_money_max'];
        }
        // 返回总数量
        if ($params['return_total_record']) {
            $sql = "select count(1) as count from (" . $select . $from . $where . (count($having) ? " having " . implode(" and ",
                        $having) : "") . ") as tbl_table ";
            $count = DistributorModel::runSql($sql, $queryparams);
            return $count[0]->count;
        }
        $sql = $select . $from . $where . (count($having) ? " having " . implode(" and ", $having) : "");
        // 待审核的时候要以创建时间为主，因为拒绝时间也是用passed_at字段，所以要判断一下
        if ($params['status'] == 0) {
            $orderByStr = ' order by tbl_distributor.created_at desc';
        } else {
            $orderByStr = ' order by tbl_distributor.passed_at desc, tbl_distributor.created_at desc';
        }

        // 自定义排序字段
        if ($params['order_by'] && Schema::hasColumn('tbl_distributor', $params['order_by'])) {
            $orderByStr = ' order by tbl_distributor.' . $params['order_by'] . ' ' . ($params['order_by_asc'] ? 'asc' : 'desc');
        }
        $sql .= $orderByStr;
        //echo $sql."\r\n\r\n\r\n";
        if ($params['page_size']) {
            $sql .= " limit " . ($params['page'] - 1) * $params['page_size'] . "," . $params['page_size'];
        }
        $list = DistributorModel::runSql($sql, $queryparams);
        $memberIds = [];
        foreach ($list as $k => $item) {
            $memberIds[] = $item->member_id;
        }
        //对一些会员数据进行二次统计查询
        if (count($memberIds) && ($params['return_total_team'] || $params['return_commission_money'] || $params['return_directly_under_distributor'])) {
            $subsqls = self::getRelationSql(array_merge($params,
                ['return_total_team' => $params['return_total_team'],
                    'return_commission_money' => $params['return_commission_money'],
                    'return_directly_under_distributor' => $params['return_directly_under_distributor']]), $setting);
            $sql = "select member_id, " . implode(",", $subsqls) . " from tbl_distributor left join tbl_member on tbl_member.id = tbl_distributor.member_id where tbl_distributor.member_id IN (" . implode(',', $memberIds) . ")";
            $listsub = new Collection(DistributorModel::runSql($sql));
            foreach ($list as $k => $item) {
                $tmp = (array)($listsub->where('member_id', $item->member_id)->first());
                foreach ($tmp as $sk => $sv) $list[$k]->$sk = $sv;
            }
        }

        foreach ($list as $k => $item) {
            $list[$k]->trade_money = $list[$k]->trade_money ? moneyCent2Yuan($list[$k]->trade_money) : 0; // 自身的交易金额
            $list[$k]->trade_time = $list[$k]->trade_time ? $list[$k]->trade_time : 0; // 自身的交易次数
            $list[$k]->total_buy_money = moneyCent2Yuan($list[$k]->total_buy_money); // 自身的购买金额(付款成功算起)
            $list[$k]->total_deal_money = moneyCent2Yuan($list[$k]->total_deal_money); // 自身的成交金额(过维权算起)
            $list[$k]->member_buy_money = moneyCent2Yuan($list[$k]->member_buy_money); // 自身的购买金额(付款成功算起)
            $list[$k]->member_deal_money = moneyCent2Yuan($list[$k]->member_deal_money); // 自身的成交金额(过维权算起)
            $list[$k]->total_commission = moneyCent2Yuan($list[$k]->total_commission);
            $list[$k]->total_commission_balance = moneyCent2Yuan($list[$k]->total_commission_balance);
            $list[$k]->directly_under_deal_money = moneyCent2Yuan($list[$k]->directly_under_deal_money);
            $list[$k]->subordinate_deal_money = moneyCent2Yuan($list[$k]->subordinate_deal_money);
            $list[$k]->directly_under_buy_money = moneyCent2Yuan($list[$k]->directly_under_buy_money);
            $list[$k]->subordinate_buy_money = moneyCent2Yuan($list[$k]->subordinate_buy_money);
            $list[$k]->business_license_file = Site::getSiteComdataDir() . $list[$k]->business_license_file;
            $list[$k]->idcard_file = Site::getSiteComdataDir() . $list[$k]->idcard_file;
            $list[$k]->member_mobile = \App\Modules\ModuleShop\Libs\Member\Member::memberMobileReplace($list[$k]->member_mobile);
            if (substr($list[$k]->headurl, 0, 1) == '/') {
                $list[$k]->headurl = Site::getSiteComdataDir() . $list[$k]->headurl;
            }
            if ($list[$k]->extend_fields) {
                $list[$k]->extend_fields = json_decode($list[$k]->extend_fields, true);
            } else {
                $list[$k]->extend_fields = [];
            }
            if ($list[$k]->apply_condition) {
                $applyConditionData = json_decode($list[$k]->apply_condition, true);
                $applyConditionData['buy_money'] = moneyCent2Yuan(intval($applyConditionData['buy_money']));
                $list[$k]->apply_condition = $applyConditionData;
            } else {
                $list[$k]->apply_condition = [];
            }
            if ($params['return_sub_team_commission']) {
                $list[$k]->sub_team_commission = $list[$k]->sub_team_commission ? moneyCent2Yuan($list[$k]->sub_team_commission) : moneyCent2Yuan(0);
            }
            if ($params['return_sub_self_purchase_commission']) {
                $list[$k]->sub_self_purchase_commission = $list[$k]->sub_self_purchase_commission ? moneyCent2Yuan($list[$k]->sub_self_purchase_commission) : moneyCent2Yuan(0);
            }
            if ($params['return_sub_directly_commission']) {
                $list[$k]->sub_directly_commission = $list[$k]->sub_directly_commission ? moneyCent2Yuan($list[$k]->sub_directly_commission) : moneyCent2Yuan(0);
            }
            if ($params['return_sub_subordinate_commission']) {
                $list[$k]->sub_subordinate_commission = $list[$k]->sub_subordinate_commission ? moneyCent2Yuan($list[$k]->sub_subordinate_commission) : moneyCent2Yuan(0);
            }
            if ($params['return_sub_team_order_num']) {
                $list[$k]->sub_team_order_num = $list[$k]->sub_team_order_num ? $list[$k]->sub_team_order_num : 0;
            }
            if ($params['return_sub_self_purchase_order_num']) {
                $list[$k]->sub_self_purchase_order_num = $list[$k]->sub_self_purchase_order_num ? $list[$k]->sub_self_purchase_order_num : 0;
            }
            if ($params['return_sub_directly_order_num']) {
                $list[$k]->sub_directly_order_num = $list[$k]->sub_directly_order_num ? $list[$k]->sub_directly_order_num : 0;
            }
            if ($params['return_sub_subordinate_order_num']) {
                $list[$k]->sub_subordinate_order_num = $list[$k]->sub_subordinate_order_num ? $list[$k]->sub_subordinate_order_num : 0;
            }
            if ($params['return_every_level_data'] && $maxLevel > 0) {
                for ($i = 1; $i <= $maxLevel; $i++) {
                    $keyTmp = 'sub_team_commission_' . $i;
                    $list[$k]->$keyTmp = moneyCent2Yuan($list[$k]->$keyTmp);
                    $distributorNumKey = 'distributor_num_' . $i;
                    $memberNumKey = 'member_num_' . $i;
                    $teamNumKey = 'team_num_' . $i;
                    $list[$k]->$teamNumKey = intval($list[$k]->$distributorNumKey) + intval($list[$k]->$memberNumKey);
                }
            }
        }
        return $list;
    }

    /**
     * 目前用于后台，用来获取分销商相关统计数据
     * @param array $params
     * @param $distributionSetting
     * @return array
     */
    private static function getStatisticsRelationSql()
    {
        $memberCountQuery = MemberModel::query()
            ->whereRaw("site_id=" . Site::getCurrentSite()->getSiteId())
            //->groupBy('member_id')
            ->addSelect('id as member_id');

        //若情况过多的话，需要生成一个方法
        $sqls = [];
        // 交易金额
        $sqls[] = "(select value from tbl_statistics where site_id=" . Site::getCurrentSite()->getSiteId() . " and tbl_statistics.member_id = tbl_member.id  and type=" . LibsConstants::Statistics_member_tradeMoney . " ) as trade_money";

        //交易次数
        $sqls[] = "(select value from tbl_statistics where site_id=" . Site::getCurrentSite()->getSiteId() . " and tbl_statistics.member_id = tbl_member.id and type=" . LibsConstants::Statistics_member_tradeTime . " ) as trade_time";

        foreach ($sqls as $extendColumn) {
            $memberCountQuery->addSelect(DB::raw($extendColumn));
        }

        $memberCountQuery->mergeBindings($memberCountQuery->getQuery());
        return $memberCountQuery->toSql();
    }


    /**
     * 目前用于后台，用来获取分销商相关关联表的子查询
     * @param array $params
     * @param $distributionSetting
     * @return array
     */
    private static function getRelationSql(array $params = [], $distributionSetting)
    {
        $maxLevel = 0;
        if ($distributionSetting) {
            $maxLevel = intval($distributionSetting['level']);
        }

        $subsqls = [];
        if ($params['return_buy_times']) { // 返回消费次数
            $subsqls[] = "(select count(1) from tbl_order where tbl_order.member_id = tbl_distributor.member_id and tbl_order.status in (" . implode(',',
                    BaseShopOrder::getPaidStatusList()) . ")) as total_buy_times";
        }
        if ($params['return_deal_times']) { // 返回成交次数
            $subsqls[] = "(select count(1) from tbl_order where tbl_order.member_id = tbl_distributor.member_id and tbl_order.has_after_sale=0 and tbl_order.status in (" . implode(',',
                    BaseShopOrder::getDealStatusList()) . ")) as total_deal_times";
        }
        if ($params['return_buy_money']) { // 返回消费金额
            $subsqls[] = "(select sum(money) from tbl_order where tbl_order.member_id = tbl_distributor.member_id and tbl_order.status in (" . implode(',',
                    BaseShopOrder::getPaidStatusList()) . ")) as total_buy_money";
        }
        if ($params['return_deal_money']) { // 返回成交金额
            $subsqls[] = "(select sum(money) + sum(after_sale_money) from tbl_order where tbl_order.member_id = tbl_distributor.member_id and tbl_order.has_after_sale=0 and tbl_order.status in (" . implode(',',
                    BaseShopOrder::getDealStatusList()) . ")) as total_deal_money";
        }
        if ($params['return_directly_deal_times']) { // 返回直属下级的成交次数，预留，目前此数据是在 tbl_distributor 里有冗余字段记录
        }
        if ($params['return_directly_deal_money']) { // 返回直属下级的成交金额，预留，目前此数据是在 tbl_distributor 里有冗余字段记录
        }
        if ($params['return_sub_deal_times']) { // 返回下属下级的成交次数，预留，目前此数据是在 tbl_distributor 里有冗余字段记录
        }
        if ($params['return_sub_deal_money']) { //返回下属下级的成交金额，预留，目前此数据是在 tbl_distributor 里有冗余字段记录
        }
        if ($params['return_commission_money']) { // 返回佣金总收入
            $subsqls[] = "(select sum(money) from tbl_finance where tbl_finance.member_id = tbl_distributor.member_id and tbl_finance.type = 9 and tbl_finance.status = 1 and tbl_finance.money > 0) as total_commission";
            $subsqls[] = "(select  sum(if(`status`=1 and money>0,money,0) + if(`status`<>2 and money<0,money,0)) from tbl_finance where tbl_finance.member_id = tbl_distributor.member_id and tbl_finance.type = 9 ) as total_commission_balance";
        }
        if ($params['return_commission_order_num']) { // 分销订单数
            $subsqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance where tbl_finance.member_id = tbl_distributor.member_id and tbl_finance.type = 9 and tbl_finance.status = 1 and tbl_finance.money > 0) as total_commission_order_num";
        }
        if ($params['return_total_team']) { // 团队总人数
            $subsqls[] = "(select count(1) from tbl_member where status = 1 and " . self::getSubUserSql("tbl_distributor.member_id",
                    $maxLevel) . ") + 1 as total_team";
        }
        if ($params['return_directly_under_distributor']) { // 直属下级分销商数量
            $subsqls[] = "(select count(1) from tbl_member as submember where submember.is_distributor = 1 and submember.invite1 = tbl_distributor.member_id) as directly_under_distributor";
        }
        if ($params['return_directly_under_member']) { // 直属下级会员数量
            $subsqls[] = "(select count(1) from tbl_member as submember where submember.is_distributor <> 1 and submember.invite1 = tbl_distributor.member_id) as directly_under_member";
        }
        if ($params['return_subordinate_distributor']) { // 下属下级分销商数量
            $subsqls[] = "(select count(1) from tbl_member as submember where submember.is_distributor = 1 and (" . self::getSubUserSql("tbl_distributor.member_id",
                    $maxLevel) . ")) as subordinate_distributor";
        }
        if ($params['return_subordinate_member']) { // 下属下级会员数量
            $subsqls[] = "(select count(1) from tbl_member as submember where submember.is_distributor <> 1 and (" . self::getSubUserSql("tbl_distributor.member_id",
                    $maxLevel) . ")) as subordinate_member";
        }
        if ($params['return_every_level_data']) { // 每一层数据都返回
            $levelSqls = Self::getRelationSqlEveryLevel(1, $maxLevel, intval($params['finance_member_id']));
            if (count($levelSqls) > 0) {
                $subsqls = array_merge($subsqls, $levelSqls);
            }
        }
        if (intval($params['finance_member_id']) > 0) {
            // 佣金所属者，基于当前登录的用户（前台用）
            $financeMemberId = intval($params['finance_member_id']);
            $activeCommissionSql = " tbl_finance.member_id = '" . $financeMemberId . "' and tbl_finance.type = " . Constants::FinanceType_Commission . " and tbl_finance.status = " . Constants::FinanceStatus_Active . " and tbl_finance.in_type = " . Constants::FinanceInType_Commission . " and tbl_finance.money > 0";
            if ($params['return_sub_team_commission']) { // 团队带来的佣金
                $subsqls[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql("tbl_distributor.member_id",
                        $maxLevel, 1,
                        'order_member') . " or tbl_distributor.member_id = tbl_order.member_id)) as sub_team_commission";
            }
            if ($params['return_sub_team_order_num']) { // 团队带来的分销订单数
                $subsqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql("tbl_distributor.member_id",
                        $maxLevel, 1,
                        'order_member') . " or tbl_distributor.member_id = tbl_order.member_id)) as sub_team_order_num";
            }
            if ($params['return_sub_self_purchase_order_num']) { // 自购带来的分销订单数
                $subsqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id where" . $activeCommissionSql . " and tbl_distributor.member_id = tbl_order.member_id) as sub_self_purchase_order_num";
            }
            if ($params['return_sub_self_purchase_commission']) { // 自购带来的佣金
                $subsqls[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id where " . $activeCommissionSql . " and tbl_distributor.member_id = tbl_order.member_id) as sub_self_purchase_commission";
            }
            if ($params['return_sub_directly_order_num']) { // 直属下级带来的分销订单数
                $subsqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        1, 1, 'order_member') . ")) as sub_directly_order_num";
            }
            if ($params['return_sub_directly_commission']) { // 直属下级带来的佣金
                $subsqls[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        1, 1, 'order_member') . ")) as sub_directly_commission";
            }
            if ($params['return_sub_subordinate_order_num']) { // 下属下级带来的分销订单数
                $subsqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        $maxLevel, 1, 'order_member') . ")) as sub_subordinate_order_num";
            }
            if ($params['return_sub_subordinate_commission']) { // 下属下级带来的佣金
                $subsqls[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        $maxLevel, 1, 'order_member') . ")) as sub_subordinate_commission";
            }
        }
        return $subsqls;
    }

    /**
     * 返回每一层的数据的子查询
     * @param int $maxLevel 最大查询层数
     * @return array
     */
    private static function getRelationSqlEveryLevel($minLevel = 1, $maxLevel = 3, $financeMemberId = 0)
    {
        $subSqls = [];
        $setting = DistributionSetting::getCurrentSiteSetting();
        $configMaxLevel = intval($setting->level);
        if ($maxLevel > $configMaxLevel) {
            $maxLevel = $configMaxLevel;
        }
        if ($minLevel > $maxLevel) {
            $minLevel = $maxLevel;
        }
        if ($minLevel > 0 && $maxLevel > 0) {
            $activeCommissionSql = " tbl_finance.member_id = '" . $financeMemberId . "' and tbl_finance.type = " . Constants::FinanceType_Commission . " and tbl_finance.status = " . Constants::FinanceStatus_Active . " and tbl_finance.in_type = " . Constants::FinanceInType_Commission . " and tbl_finance.money > 0";
            for ($i = $minLevel; $i <= $maxLevel; $i++) {
                // 分销商数量
                $subSqls[] = "(select count(1) from tbl_member as sub_member where sub_member.is_distributor = 1 and sub_member.invite" . $i . " = tbl_distributor.member_id) as distributor_num_" . $i;
                // 会员数量
                $subSqls[] = "(select count(1) from tbl_member as sub_member where sub_member.is_distributor <> 1 and sub_member.invite" . $i . "  = tbl_distributor.member_id) as member_num_" . $i;
                // 分销订单数量
                $subSqls[] = "(select count(distinct(tbl_finance.order_id)) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        $i, $i, 'order_member') . ")) as sub_team_order_num_" . $i;
                // 分销金额
                $subSqls[] = "(select sum(tbl_finance.money) from tbl_finance left join tbl_order on tbl_finance.order_id = tbl_order.id left join tbl_member as order_member on tbl_order.member_id = order_member.id where" . $activeCommissionSql . " and (" . self::getSubUserSql('tbl_distributor.member_id',
                        $i, $i, 'order_member') . ")) as sub_team_commission_" . $i;
            }
        }
        return $subSqls;
    }

    /**
     * 返回此分销商的佣金总收入
     */
    public function getTotalCommission()
    {
        $money = FinanceHelper::getMemberTotalCommission($this->_model->member_id);
        return $money;
    }

    /**
     * 获取查找某会员的下级会员的SQL
     * @param $memberId 会员ID
     * @param int $maxLevel 最大层级
     * @param int $startLevel 从第几层级开始获取，默认为第一级
     * @param string $table
     * @return string
     */
    public static function getSubUserSql($memberId, $maxLevel = 0, $startLevel = 1, $table = '')
    {
        // 默认从配置读取
        if ($maxLevel < 1) {
            $setting = DistributionSetting::getCurrentSiteSetting();
            $maxLevel = $setting->level;
        }

        return Member::getSubUserSql($memberId, $maxLevel, $startLevel, $table);
    }

    /**
     * 获取查找某会员的下级佣金的SQL
     * @param $memberId
     * @param int $startLevel
     * @param string $table
     * @return string
     */
    public static function getSubFinanceSql($memberId, $maxLevel = 0, $startLevel = 1, $table = '')
    {
        // 默认从配置读取
        if ($maxLevel <= 0) {
            $setting = DistributionSetting::getCurrentSiteSetting();
            $maxLevel = $setting->level;
        }

        return FinanceHelper::getSubUserSql($memberId, $maxLevel, $startLevel, $table);
    }

    /**
     * 累加相关分销员的成交次数
     * @param int $memberId 购买者的会员ID
     */
    public static function accumulateDealTimes($orderId, $memberId)
    {
        if (!UniqueLogModel::newLog('accumulateDealTimes_' . $orderId)) {
            return;
        }
        $memberIds = self::getRelationMemberId($memberId);
        // 自购的业绩计算需要跟随配置，计算才把自购的业绩计算到团队里面
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        if ($distributionSetting->calc_performance_valid_condition == 1) {
            $memberIds[] = $memberId;
        }
        if ($memberIds) {
            if (count($memberIds) > 0) {
                DistributorModel::whereIn('member_id', $memberIds)->where('status', 1)->increment("subordinate_deal_times");
            }
            // 根据会员ID查找相应的分销商，并更新直属下级成交次数
            if (count($memberIds) > 1) {
                DistributorModel::where('member_id', '=', $memberIds[0])->where('status',
                    1)->increment("directly_under_deal_times");
            }
        }
    }

    /**
     * 累加相关分销员的付款次数
     * @param int $memberId 购买者的会员ID
     * @return bool
     */
    public static function accumulateBuyTimes($orderId, $memberId)
    {
        if (!UniqueLogModel::newLog('accumulateBuyTimes_' . $orderId)) {
            return false;
        }

        $memberIds = self::getRelationMemberId($memberId);
        // 自购的业绩计算需要跟随配置，计算才把自购的业绩计算到团队里面
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        if ($distributionSetting->calc_performance_valid_condition == 1) {
            $memberIds[] = $memberId;
        }
        if ($memberIds) {
            if (count($memberIds) > 0) {
                DistributorModel::whereIn('member_id', $memberIds)->where('status', 1)->increment("subordinate_buy_times");
            }
            // 根据会员ID查找相应的分销商，并更新直属下级成交次数
            if (count($memberIds) > 1) {
                DistributorModel::where('member_id', '=', $memberIds[0])->where('status',
                    1)->increment("directly_under_buy_times");
            }
        }
    }

    /**
     * 自动升级相关分销员的等级
     * @param int $memberId 购买者的会员ID
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function upgradeRelationDistributorLevel($memberId, $params = [])
    {
        $memberIds = self::getRelationMemberId($memberId, false);
        $memberIds[] = $memberId;// 因为有自购成交次数和自购成交金额，所以将当前会员也压入处理一下
        foreach ($memberIds as $memberId) {
            $m = MemberModel::find($memberId);
            if ($m) {
                // 因为会员也可以自动升级为分销商 所以需要自动初始化不是分销商的会员
                $d = new Distributor($memberId, true);
                $d->upgrade($params);
            }
        }
    }

    /**
     * 累加相关分销员的成交金额
     * @param int $memberId 购买者的会员ID
     * @param int $money 金额，单位：分
     */
    public static function accumulateDealMoney($orderId, $memberId, $money)
    {
        if (!UniqueLogModel::newLog('accumulateDealMoney_' . $orderId)) {
            return;
        }
        $memberIds = self::getRelationMemberId($memberId);
        // 自购的业绩计算需要跟随配置，计算才把自购的业绩计算到团队里面
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        if ($distributionSetting->calc_performance_valid_condition == 1) {
            $memberIds[] = $memberId;
        }
        if ($memberIds) {
            if (count($memberIds) > 0) {
                DistributorModel::whereIn('member_id', $memberIds)->where('status', 1)->increment("subordinate_deal_money",
                    $money);
            }
            // 根据会员ID查找相应的分销商，并更新直属下级成交次数
            if (count($memberIds) > 1) {
                DistributorModel::where('member_id', '=', $memberIds[0])->where('status',
                    1)->increment("directly_under_deal_money", $money);
            }
        }
    }

    /**
     * 累加相关分销员的付款金额
     * @param int $memberId 购买者的会员ID
     * @param int $money 金额，单位：分
     */
    public static function accumulateBuyMoney($orderId, $memberId, $money)
    {
        if (!UniqueLogModel::newLog('accumulateBuyMoney_' . $orderId)) {
            return;
        }
        $memberIds = self::getRelationMemberId($memberId);
        // 自购的业绩计算需要跟随配置，计算才把自购的业绩计算到团队里面
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        if ($distributionSetting->calc_performance_valid_condition == 1) {
            $memberIds[] = $memberId;
        }
        if ($memberIds) {
            if (count($memberIds) > 0) {
                DistributorModel::whereIn('member_id', $memberIds)->where('status', 1)->increment("subordinate_buy_money",
                    $money);
            }
            // 根据会员ID查找相应的分销商，并更新直属下级成交次数
            if (count($memberIds) > 1) {
                DistributorModel::where('member_id', '=', $memberIds[0])->where('status',
                    1)->increment("directly_under_buy_money", $money);
            }
        }
    }


    /**
     * 查找分销相关的上级会员ID
     * @param int $memberId
     * @param bool $isLevel 是否限制层级
     * @return array
     */
    public static function getRelationMemberId($memberId, $isLevel = true)
    {
        $memberId = intval($memberId);
        if ($isLevel) {
            $setting = DistributionSetting::getCurrentSiteSetting();
            $maxLevel = $setting->level;
            return Member::getRelationMemberId($memberId, $maxLevel);
        } else {
            return Member::findParentIds($memberId);
        }
        //$internal_purchase = $setting->internal_purchase;
        //$maxLevel = $maxLevel - $internal_purchase; //开启内购时，因为购买者本身占了一层，所以要减少一层（先注释，人员不受内购设置的影响）
        //return Member::getRelationMemberId($memberId, $maxLevel);
        //return Member::findParentIds($memberId); //因为分销关系链条是无限的，最末端的人升级，有可能导致所有上线都升级，所以这里不应该限制层数
    }

    /**
     * 处理成为分销商的事件
     * @param null $member
     * @param int $gift 是否赠送东西，如积分等，一般在恢复分销资格时，应该传0，其它情况传1
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    private function eventForDistributorActive($member = null, $gift = 1)
    {
        if ($this->checkExist()) {
            if (!$member) {
                $member = new Member($this->getMemberId());
            }
            // 相关分销商自动升级
            $this->dispatch(new UpgradeDistributionLevelJob($this->getMemberId()));
//            Distributor::upgradeRelationDistributorLevel($this->getMemberId());
            if ($gift) {
                // 成为分销商赠送积分
                $pointGive = new PointGiveForDistributionBecome($member);
                $pointGive->addPoint();
                // 推荐分销商送积分
                $pointGive = new PointGiveForDistributionRecommend($member);
                $pointGive->addPoint();
            }
            // 成为分销商再发送
            if (($member->getModel())->is_distributor == 1) {
                MessageNoticeHelper::sendMessageDistributorBecomeAgree($this->_model);
                if ($this->isActive()) {
                    MessageNoticeHelper::sendMessageSubMemberNew($member->getModel());
                }
            }
            //改为用队列处理 相关代理升级
            $this->dispatch(new UpgradeAgentLevelJob($this->getMemberId()));
        }
    }

}
