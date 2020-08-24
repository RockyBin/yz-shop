<?php
/**
 * 经销商等级业务逻辑
 * User: liyaohui
 * Date: 2019/11/28
 * Time: 17:00
 */

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\Upgrade\ConditionSelfBuyProduct;
use App\Modules\ModuleShop\Libs\Dealer\Upgrade\UpgradeConditionHelper;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Product\Product;
use Illuminate\Support\Facades\DB;
use YZ\Core\Common\DataCache;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class DealerLevel
{
    private $siteId = null;
    protected static $levelsObj = [];

    public function __construct()
    {
        $this->siteId = getCurrentSiteId();
    }

    /**
     * 新增等级 包括隐藏等级
     * @param $info
     * @return array|bool
     * @throws \Exception
     */
    public function add($info)
    {
        $check = $this->levelSaveBefore($info);
        if ($check !== true) {
            return $check;
        }
        $levelModel = new DealerLevelModel();
        $info['site_id'] = $this->siteId;
        return $levelModel->fill($info)->save();
    }

    /**
     * 编辑等级
     * @param $info
     * @return array|bool
     * @throws \Exception
     */
    public function edit($info)
    {
        if (!$info['id']) {
            return makeServiceResult(400, "请输入等级id");
        }
        $check = $this->levelSaveBefore($info);
        if ($check !== true) {
            return $check;
        }
        $levelModel = DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where('id', $info['id'])
            ->first();
        if ($levelModel) {
            // 如果状态是禁用 要检测是否有经销商在使用该等级
            if ($info['status'] == Constants::DealerLevelStatus_Unactive) {
                $count = $this->getLevelDealerCount($info['id'], $levelModel->parent_id > 0);
                $count = array_sum($count);
                if ($count > 0) {
                    return makeServiceResult(400, "当前等级共拥有【{$count}位经销商】,无法禁用");
                }
            } else {
                $info['status'] = Constants::DealerLevelStatus_Active;
            }
            $info['site_id'] = $this->siteId;
            return $levelModel->fill($info)->save();
        } else {
            return makeServiceResult(404, "找不到该等级");
        }
    }

    /**
     * 保存等级之前的处理
     * @param $info
     * @return array|bool
     * @throws \Exception
     */
    public function levelSaveBefore(&$info)
    {
        // 如果有关联等级 则是隐藏等级
        if ($info['parent_id']) {
            $parent = $this->getParentInfo($info['parent_id']);
            if (!$parent) {
                return makeServiceResult(404, '关联等级不存在');
            }
            if (!$parent->has_hide) {
                return makeServiceResult(400, '关联等级未开启隐藏等级');
            }
            // 如果有父级 说明是隐藏等级 强制把隐藏等级字段为0
            $info['has_hide'] = 0;
        } else {
            $info['parent_id'] = 0;
        }
        // 检测权重是否被占用
        $checkLevel = $this->checkLevelInfo($info, $info['parent_id'], $info['id']);
        if ($checkLevel !== true) {
            if ($checkLevel['has_weight']) {
                return makeServiceResult(405, '权重已被使用');
            }
            if ($checkLevel['has_name']) {
                return makeServiceResult(406, '等级名称已存在');
            }
        }
        // 加盟费转为分
        $info['initial_fee'] = $info['initial_fee'] > 0 ? moneyYuan2Cent($info['initial_fee']) : 0;
        // 首购最小金额
        $info['min_purchase_money_first'] = isset($info['min_purchase_money_first']) && $info['min_purchase_money_first'] > 0 ? moneyYuan2Cent($info['min_purchase_money_first']) : 0;
        // 复购最小金额
        $info['min_purchase_money'] = isset($info['min_purchase_money']) && $info['min_purchase_money'] > 0 ? moneyYuan2Cent($info['min_purchase_money']) : 0;
        // 检测升级条件
        if ($info['upgrade_condition'] && is_array($info['upgrade_condition'])) {
            foreach ($info['upgrade_condition']['upgrade'] as &$item) {
                if (!$item['value']) {
                    throw new \Exception('请填写完整升级条件');
                }
                if (self::isNeedProductCondition($item['type'])) {
                    if (!Product::hasActiveProduct($info['upgrade_condition']['product_id'])) {
                        throw new \Exception('请至少选择一个有效商品');
                    }
                }
                if ($item['logistic'] != 'and') {
                    $item['logistic'] = 'or';
                }
            }
            $info['upgrade_condition'] = json_encode($info['upgrade_condition']);
        } else {
            $info['upgrade_condition'] = '[]';
        }
        return true;
    }

    /**
     * 是否是需要商品的升级条件
     * @param $type
     * @return bool
     */
    public function isNeedProductCondition($type)
    {
        if (in_array($type, [
            Constants::DealerLevelUpgradeCondition_SelfBuyProduct
        ])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检测等级数据是否合法
     * @param array $info 等级信息
     * @param int $parentId 父级id
     * @param int $levelId 等级id 编辑时用
     * @return bool|array
     */
    public function checkLevelInfo($info, $parentId = 0, $levelId = 0)
    {
        $level = DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where(function ($query) use ($info) {
                $query->where('weight', $info['weight'])
                    ->orWhere('name', $info['name']);
            });
        // 如果有父级 则去查找对应的父级下权重是否可用
        $level->where('parent_id', $parentId);
        if ($levelId) {
            $level->where('id', '<>', $levelId);
        }
        $level = $level->get();
        if (!$level) {
            return true;
        }
        $hasWeight = $level->where('weight', $info['weight'])->count();
        $hasName = $level->where('name', $info['name'])->count();
        return [
            'has_weight' => $hasWeight,
            'has_name' => $hasName
        ];
    }


    /**
     * 获取父级等级
     * @param $levelId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getParentInfo($levelId)
    {
        return DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where('id', $levelId)
            ->first();
    }

    /**
     * 获取等级信息
     * @param $levelId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getInfo($levelId)
    {
        return DealerLevelModel::query()->where('site_id', $this->siteId)
        ->where('id', $levelId)
        ->first();
    }

    /**
     * 获取等级列表
     * @param array $params
     * @param bool $canUse 是否是获取可以使用的基础等级 为false则获取所有
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getLevelList($params, $canUse = false)
    {
        $query = DealerLevelModel::query()
            ->where('tbl_dealer_level.site_id', getCurrentSiteId());
        if (isset($params['status'])) {
            $query->where('tbl_dealer_level.status', $params['status']);
        }
        // 排序规则
        if (isset($params['order_by'])) {
            $query->orderBy('tbl_dealer_level.' . $params['order_by'][0], $params['order_by'][1]);
        }
        // 传会员ID意指想知道某会员所属那个等级
        if ($params['member_id']) {
            $member = (new Member($params['member_id']))->getModel();
            $dealer_level = $member->dealer_level;
            $dealer_hide_level = $member->dealer_hide_level;
            // 因为暂时是单选，当有隐藏等级并且要获取隐藏等级的时候，父级不用选中
            if ($dealer_hide_level == 0 && $params['get_hide_level']) {
                $query->selectRaw(DB::raw('if(id=' . $dealer_level . ',1,0) as is_check'));
            }

        }
        if ($params['get_hide_level']) {
            // 如果只想获取隐藏等级，并不期望知道这个会员是否有选中
            if (!isset($dealer_hide_level) && !isset($params['member_id'])) {
                $dealer_hide_level = null;
            }
            $query->with(['children' => function ($query) use ($dealer_hide_level) {
                $query->where('status', 1);
                if ($dealer_hide_level != null) {
                    $query->selectRaw(DB::raw('*,if(id=' . $dealer_hide_level . ',1,0) as is_check'));
                }
            }]);
        }
        // 获取所有的时候 一般是一些下拉框需要
        if ($params['get_all']) {
            return $query->select(['id', 'name', 'weight', 'parent_id', 'status', 'has_hide'])->get();
        }
        if ($params['is_hide']) {
            // 记录笔记
            $query->leftJoin('tbl_dealer_level as dl_p', 'dl_p.id', 'tbl_dealer_level.parent_id')
                ->where('tbl_dealer_level.parent_id', '>', 0)
                ->select(['tbl_dealer_level.*', 'dl_p.name as parent_name', 'dl_p.weight as parent_weight'])
                ->withCount(['dealersHide as dealers' => function ($q) {
                    $q->where('status', Constants::MemberStatus_Active);
                }]);
        } else {
            $query->where('tbl_dealer_level.parent_id', 0);
            // 获取可使用列表时 不需要统计使用人数
            if (!$canUse) {
                $query->withCount([
                    'dealers' => function ($q) {
                        $q->where('status', Constants::MemberStatus_Active);
                    },
                    'applyingDealers' => function ($q) {
                        $q->where('status', Constants::DealerStatus_WaitReview);
                    }
                ]);
            }
            $query->addSelect(['id', 'name', 'weight', 'parent_id', 'status', 'has_hide', 'discount']);
        }
        $list = $query->get();
        if (!$canUse) {
            // 获取所有等级的名称 给升级条件文案使用
            foreach ($list as &$item) {
                $condition = json_decode($item->upgrade_condition, true);
                $item->condition_text = $condition['upgrade']
                    ? self::getLevelConditionsText($condition['upgrade'], []) : '';
            }
        }
        return $list;
    }

    /**
     * 返回主等级与子等级的树形结构
     * @param array $params 参数
     * @return array;
     */
    public static function getLevelTree($params = [])
    {
        $query = DealerLevelModel::query()->where(['site_id' => getCurrentSiteId()]);
        if ($params['order_by']) $query->orderBy($params['order_by'][0], $params['order_by'][1]);
        else $query->orderBy('weight', 'asc');
        $levels = $query->get();
        $mainLevels = $levels->where('parent_id', '0')->values()->toArray();
        $site = Site::getCurrentSite();
        foreach ($mainLevels as &$item) {
            if ($site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_DEALER_HIDE_LEVEL)) {
                $item['sub_levels'] = $levels->where('parent_id', $item['id'])->values()->toArray();
            } else $item['sub_levels'] = [];
        }
        unset($item);
        return $mainLevels;
    }

    /**
     * 返回缓存的经销商等级信息，以便在N个地方都可以调用，目前主要用在进货算价那里
     * @param array $params
     * @param array $idToKey 返回以等级ID作为键的数组，否则返回普通数组
     * @return array() 以等级ID作为键的数组，方便外层通过等级ID快速查找
     */
    public static function getCachedLevels($params = [], $idToKey = 1)
    {
        if (!DataCache::has('CachedDealerLevels')) {
            $query = DealerLevelModel::query()->where(['site_id' => getCurrentSiteId()]);
            $levels = $query->get();
            DataCache::setData('CachedDealerLevels', $levels);
        }
        $list = clone DataCache::getData('CachedDealerLevels');
        if ($params['order_by'] && stripos($params['order_by'][1], 'desc') !== false) {
            $list = $list->sortByDesc($params['order_by'][0])->values();
        } else {
            $list = $list->sortBy($params['order_by'][0])->values();

        }
        if ($idToKey) {
            $arr = [];
            foreach ($list as $item) {
                $arr[$item->id] = $item->toArray();
            }
            return $arr;
        } else {
            return $list->toArray();
        }
    }

    /**
     * 获取等级详情
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getLevelInfo($id)
    {
        if ($id) {
            $level = DealerLevelModel::query()->where('site_id', $this->siteId)
                ->where('id', $id)
                ->first();
            if (!$level) {
                throw new \Exception('等级不存在');
            }
            $level->upgrade_condition = json_decode($level->upgrade_condition, true);
            if ($level->upgrade_condition && $level->upgrade_condition['product_id']) {
                $level['product_list'] = Product::getList(['product_ids' => $level->upgrade_condition['product_id']]);
                $level['product_list'] = $level['product_list'] ? $level['product_list']['list'] : [];
            }
            $level->initial_fee = moneyCent2Yuan($level->initial_fee);
            $level->min_purchase_money_first = floatval(moneyCent2Yuan($level->min_purchase_money_first));
            $level->min_purchase_money = floatval(moneyCent2Yuan($level->min_purchase_money));
            $isHide = $level->parent_id > 0;
            $level['hide_dealer_count'] = 0;
            // 主等级去查找隐藏等级的个数
            if (!$isHide) {
                $hideLevels = DealerLevelModel::query()->where('site_id', $this->siteId)
                    ->where('parent_id', $id)
                    ->pluck('id');
                $level['hide_count'] = $hideLevels->count();
                if ($level['hide_count'] > 0) {
                    $level['hide_dealer_count'] = MemberModel::query()->where('site_id', $this->siteId)
                        ->whereIn('dealer_hide_level', $hideLevels->toArray())
                        ->where('status', Constants::MemberStatus_Active)
                        ->count();
                }
            }
            // 获取当前等级使用的人数
            $count = $this->getLevelDealerCount($id, $isHide);
            $level['dealer_count'] = $count['active_count'] + $count['apply_count'];
            return $level;
        } else {
            throw new \Exception('请输入等级id');
        }
    }

    /**
     * 获取等级的使用人数统计 包括申请中的
     * @param int $levelId 主等级id
     * @param bool $isHide 是否是隐藏等级
     * @return array
     */
    public function getLevelDealerCount($levelId, $isHide = false)
    {
        $activeCount = MemberModel::query()->where('site_id', $this->siteId)
            ->where('status', Constants::MemberStatus_Active);
        $applyCount = 0;
        // 主等级才会去查找这些
        if (!$isHide) {
            $activeCount->where('dealer_level', $levelId);
            // 查找申请中的经销商数量 隐藏等级无法申请 所以直查主等级的即可
            $applyCount = DealerModel::query()->where('site_id', $this->siteId)
                ->where('dealer_apply_level', $levelId)
                ->where('status', Constants::DealerStatus_WaitReview)
                ->count();
        } else {
            $activeCount->where('dealer_hide_level', $levelId);
        }
        $activeCount = $activeCount->count();

        return ['active_count' => $activeCount, 'apply_count' => $applyCount];
    }

    /**
     * 禁用等级
     * @param $id
     * @return bool
     * @throws \Exception
     */
    public function levelDisable($id)
    {
        if (!$id) {
            throw new \Exception('请选择要禁用的等级');
        }
        $level = DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where('id', $id)
            ->first();
        if (!$level) {
            throw new \Exception('等级不存在');
        }
        $count = $this->getLevelDealerCount($id, $level->parent_id > 1);
        // 是否有在使用该等级的会员 申请中的也要统计
        if ($count['active_count'] > 0 || $count['apply_count'] > 0) {
            throw new \Exception('该等级下还有经销商，无法删除');
        }
        $level->status = Constants::DealerLevelStatus_Unactive;
        return $level->save();
    }

    /**
     * 启用等级
     * @param $id
     * @return int
     */
    public function levelEnable($id)
    {
        return DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where('id', $id)
            ->update(['status' => Constants::DealerLevelStatus_Active]);
    }

    /**
     * 删除等级
     * @param $id
     * @return bool|mixed|null
     * @throws \Exception
     */
    public function levelDelete($id)
    {
        $level = DealerLevelModel::query()->where('site_id', $this->siteId)
            ->where('id', $id)->first();
        if (!$level) {
            throw new \Exception('等级不存在');
        }
        if ($level->status != Constants::DealerLevelStatus_Unactive) {
            throw new \Exception('只能删除禁用状态的等级');
        }
        // 如果是主等级 要同时删除隐藏等级
        if (!$level->parent_id) {
            DealerLevelModel::query()->where('site_id', $this->siteId)
                ->where('parent_id', $id)->delete();
        }
        return $level->delete();
    }

    /**
     * 获取已使用的权重
     * @param int $parentId
     * @return array
     */
    public function getEnabledLevelWeight($parentId = 0)
    {
        return DealerLevelModel::query()
            ->where('site_id', $this->siteId)
            ->where('parent_id', $parentId)
            ->pluck('weight')->toArray();
    }

    /**
     * 获取升级条件快照的文案
     * @param $upgrade
     * @param $productId
     * @return array
     */
    public static function getLevelConditionsText($upgrade, $productId)
    {
        $conditions = ['and' => [], 'or' => []];
        // 按 and和or 分组
        foreach ($upgrade as $con) {
            $conIns = UpgradeConditionHelper::createInstance($con['type'], $con['value'], $productId);
            $title = $conIns->getNameText();
            // and和or条件分组
            if ($title) {
                $conditions[$con['logistic']][] = $title;
            }
        }
        return $conditions;
    }

    /**
     * 经销商是否满足该等级的升级条件
     * @param DealerLevelModel $levelModel 经销商等级
     * @param int $memberId 会员id
     * @param array $params 额外的参数
     * @return bool
     */
    public static function canUpgrade($levelModel, $memberId, $params = [])
    {
        $flag = false;
        $conditions = $levelModel->upgrade_condition;
        $conditions = json_decode($conditions, true);
        $autoUpgradeType = [Constants::DealerLevelUpgradeCondition_DirectlyDealerNum, Constants::DealerLevelUpgradeCondition_OneReChargeMoney, Constants::DealerLevelUpgradeCondition_TotalReChargeMoney];
        $member = new Member($memberId);
        $memberModel = $member->getModel();
        if (is_array($conditions) && is_array($conditions['upgrade']) && count($conditions['upgrade']) > 0) {
            $conditionsData = ['and' => [], 'or' => []];
            // 把或和与的条件分组
            foreach ($conditions['upgrade'] as $con) {
                if ($memberModel->dealer_level) {
                    $conditionsData[$con['logistic']][] = $con;
                } else {
                    // 如果会员不是经销商，先判断系同是否允许会员升级，并且升级条件中含有允许升级的类型
                    if ($levelModel->auto_upgrade && in_array($con['type'], $autoUpgradeType)) {
                        $conditionsData[$con['logistic']][] = $con;
                    }
                }
            }
            // 条件有可能是空，当条件为空的时候返回FALSE
            if (!$conditionsData['and'] && !$conditionsData['or']) return false;
            $andFlag = true;
            // 执行and条件
            foreach ($conditionsData['and'] as $and) {
                // 只要有一个and条件不满足 则整个都不会满足 直接返回false
                if (!$andFlag) {
                    return false;
                }
                $conIns = UpgradeConditionHelper::createInstance($and['type'], $and['value'], $conditions['product_id']);
                $andFlag = $andFlag && $conIns->canUpgrade($memberId, $params);
            }
            // 执行or条件
            // 没有or条件的时候 or的计算结果默认为true 有的时候默认为false
            $orFlag = count($conditionsData['or']) === 0;
            foreach ($conditionsData['or'] as $or) {
                // 当or条件有一个满足时即可
                if ($orFlag) {
                    break;
                }
                $conIns = UpgradeConditionHelper::createInstance($or['type'], $or['value'], $conditions['product_id']);
                $orFlag = $orFlag || $conIns->canUpgrade($memberId, $params);
            }
            // 如果没有or条件 and条件自己成立即可
            $flag = $orFlag && $andFlag;
        }
        return $flag;
    }

    /**
     * 检测权重是否可用
     * @param int $params
     * @return bool
     */
    public static function checkLevel($params)
    {
        $level = DealerLevelModel::query()->where('site_id', getCurrentSiteId())
            ->where('id', $params['id']);
        if ($params['status']) {
            $level->where('status', '=', $params['status']);
        }
        return $level->first();
    }

    /**
     * 获取等级名称 隐藏等级会同时获取主等级名称
     * @param $levelId
     * @return string
     */
    public static function getLevelName($levelId)
    {
        if (!self::$levelsObj) {
            // 获取所有的等级名称和父级id
            self::$levelsObj = DealerLevelModel::query()
                ->where('site_id', getCurrentSiteId())
                ->select(['id', 'name', 'parent_id'])
                ->get()
                ->keyBy('id');
        }
        if (!self::$levelsObj || !self::$levelsObj[$levelId]) return '';
        $parentName = '';
        $parentId = self::$levelsObj[$levelId]['parent_id'];
        if ($parentId && $parent = self::$levelsObj[$parentId]) {
            $parentName = $parent['name'] . '-';
        }
        return $parentName . self::$levelsObj[$levelId]['name'];
    }
}