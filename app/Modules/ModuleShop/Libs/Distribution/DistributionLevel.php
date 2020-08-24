<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Product\Product;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use Illuminate\Support\Facades\DB;

/**
 * 分销等级
 * @author Administrator
 */
class DistributionLevel
{
    private $_model = null;
    protected static $levelsObj = [];

    /**
     * 初始化分销等级对象
     * WxMenu constructor.
     * @param $idOrModel 菜单的 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel = '')
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->_model = DistributionLevelModel::where([
                    'id' => $idOrModel,
                    'site_id' => Site::getCurrentSite()->getSiteId()
                ])->first();
            } else {
                $this->_model = $idOrModel;
            }
        }
    }

    /**
     * 返回数据库记录模型
     * @return null|WxMenuModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 返回等级的信息，其中 condition 和 commission 属性为经过 json_decode 后的值
     * @return stdClass
     * @throws \Exception
     */
    public function getInfo()
    {
        $obj = clone $this->_model;
        $obj->condition = json_decode($obj->condition, true);
        $obj->commission = json_decode($obj->commission, true);
        $obj->product_list = [];
        // 有可能有商品列表需要获取
        if ($obj->condition['product_id']) {
            $product_list = Product::getList(['product_ids' => myToArray($obj->condition['product_id'])]);
            $obj->product_list = $product_list['list'];
        }
        return $obj;
    }

    /**
     * 添加默认的默认等级
     * @throws Exception
     */
    public function addDefaultLevel()
    {
        $this->add("默认等级", 0, ['1' => 0, '2' => 0, '3' => 0], ["upgrade" => [], "product_id" => []], 0);
    }

    /**
     * 添加分销等级
     * @param string $name 等级名称
     * @param int $weight 等级权重
     * @param array $levelCommission 等级佣金，格式如 ['1' => '一级佣金比例','2' => '二级佣金比例',...]
     * @param array $upgradeCondition 升级条件,格式如，目前暂时不支持 and or 的组合条件
     * [
     *  0 => ['type' => 条件类型,'value' => 条件值],1 => 'or',2 => ['type' => 条件类型,'value' => 条件值]
     * ]
     * @param int $autoUpgrade 是否允许会员直接升级到此等级
     * @return mixed    返回新加的等级的id
     * @throws Exception
     * @throws \Exception
     */
    public function add($name, $weight, array $levelCommission, array $upgradeCondition = [], $autoUpgrade = 0)
    {
        //检测佣金总和是否大于100
        $this->checkCommission($levelCommission);
        //检测是否有相同条件
        $this->checkCondition($upgradeCondition);
        //检测是否有相同权重
        $this->checkWeight($weight);
        $this->_model = new DistributionLevelModel();
        $this->_model->name = $name;
        $this->_model->weight = $weight;
        $this->_model->status = 1;
        $this->_model->new_open = 1;
        $this->_model->condition = json_encode($this->checkUpgradeCondition($upgradeCondition));
        $this->_model->commission = json_encode($levelCommission);
        $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        $this->_model->auto_upgrade = $autoUpgrade;
        $this->_model->save();
        return $this->_model->id;
    }

    /**
     * 获取此等级的分销商人数
     */
    public function getUserCount()
    {
        return DistributorModel::where([
            'level' => $this->_model->id,
            'site_id' => Site::getCurrentSite()->getSiteId()
        ])->count('member_id');
    }

    /**
     * 将此等级的分销商转移到其它等级
     * @param int $newLevelId 新的等级id
     */
    public function transToLevel($newLevelId)
    {
        return DistributorModel::where([
            'level' => $this->_model->id,
            'site_id' => Site::getCurrentSite()->getSiteId()
        ])->update(['level' => $newLevelId]);
    }

    /**
     * 禁用当前等级
     */
    public function disable()
    {
        if ($this->_model->weight == 0) {
            throw new \Exception("默认等级不能禁用");
        }
        if ($count = $this->getUserCount()) {
            throw new \Exception("该等级下有 $count 个分销商，不能禁用");
        }
        $this->_model->status = 0;
        $this->_model->save();
    }

    /**
     * 删除当前等级
     */
    public function delete()
    {
        if ($this->_model->weight == 0) {
            throw new \Exception("默认等级不能删除");
        }
        if ($this->_model->status != 0) {
            throw new \Exception("正在启用的等级不能删除");
        }
        $this->_model->delete();
    }

    /**
     * 启用当前等级
     */
    public function enable()
    {
        //检测是否有相同权重
        $this->checkWeight($this->_model->weight);
        $this->_model->status = 1;
        $this->_model->save();
    }

    /**
     * 修改等级
     * @param string $name 等级名称
     * @param int $weight 等级权重
     * @param int $new_open 新用户是否能升级
     * @param array $levelCommission 等级佣金，格式如 ['1' => '一级佣金比例','2' => '二级佣金比例',...]
     * @param array $upgradeCondition 升级条件,格式如，目前暂时不支持 and or 的组合条件
     * * [
     *  0 => ['type' => 条件类型,'value' => 条件值],1 => 'or',2 => ['type' => 条件类型,'value' => 条件值]
     * ]
     * @param int $autoUpgrade 是否允许会员自动升级
     * @throws Exception
     * @throws \Exception
     */
    public function edit(
        $name,
        $weight,
        $new_open,
        array $levelCommission,
        array $upgradeCondition = [],
        $autoUpgrade = 0
    )
    {
        //检测佣金总和是否大于100
        $this->checkCommission($levelCommission);
        //检测是否有相同条件 因为现在条件改成与或所以不需要了
        //$this->checkCondition($upgradeCondition);
        //检测是否有相同权重
        $this->checkWeight($weight);
        $this->_model->name = $name;
        $this->_model->weight = $weight;
        $this->_model->new_open = $new_open;
        $this->_model->condition = json_encode($this->checkUpgradeCondition($upgradeCondition));
        $this->_model->commission = json_encode($levelCommission);
        $this->_model->auto_upgrade = $autoUpgrade;
        $this->_model->save();
    }

    /**
     * 检测并格式化升级条件
     * @param $condition
     * @return mixed
     * @throws \Exception
     */
    public function checkUpgradeCondition($condition)
    {
        //新需求，暂时不限制是否有传升级条件
        /*if (!$condition || !is_array($condition) || !$condition['upgrade']) {
            throw new \Exception('请选择升级条件');
        }*/
        foreach ($condition['upgrade'] as &$item) {
            if (!$item['value']) {
                throw new \Exception('请填写完整升级条件');
            }
            if (self::isMustProductCondition($item['type'])) {
                if (!$condition['product_id']) {
                    throw new \Exception('请选择商品');
                }
            }
            if (self::isMustDistributionLevelCondition($item['type'])) {
                if (!$item['value']['distribution_level_id'] || !$item['value']['member_count']) {
                    throw new \Exception('请填写完整升级条件');
                }
            }
            if ($item['logistic'] != 'and') {
                $item['logistic'] = 'or';
            }
        }
        return $condition;
    }

    /**
     * 是否是需要商品id的升级条件
     * @param $type
     * @return bool
     */
    public static function isMustProductCondition($type)
    {
        if (in_array($type, [
            Constants::DistributionLevelUpgradeCondition_TeamBuyProduct,
            Constants::DistributionLevelUpgradeCondition_SelfBuyProduct,
            Constants::DistributionLevelUpgradeCondition_DirectlyBuyProduct,
            Constants::DistributionLevelUpgradeCondition_IndirectBuyProduct,
        ])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否是需要分销商等级的升级条件
     * @param $type
     * @return bool
     */
    public static function isMustDistributionLevelCondition($type)
    {
        if (in_array($type, [
            Constants::DistributionLevelUpgradeCondition_SubordinateDistributor,
            Constants::DistributionLevelUpgradeCondition_DirectlyUnderDistributor,
            Constants::DistributionLevelUpgradeCondition_IndirectUnderDistributor
        ])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检测某分销商是否符合此等级的升级条件
     * @param int $memberId 分销商ID
     * @param array $params 额外参数
     * @return bool
     */
    public function canUpgrade($memberId, $params = [])
    {
        $flag = false;
        $conditions = $this->_model->condition;
        if (!is_array($this->_model->condition)) {
            $conditions = json_decode($this->_model->condition, true);
        }
        if (is_array($conditions) && is_array($conditions['upgrade']) && count($conditions['upgrade']) > 0) {
            $conditionsData = ['and' => [], 'or' => []];
            // 把或和与的条件分组
            foreach ($conditions['upgrade'] as $con) {
                $conditionsData[$con['logistic']][] = $con;
            }
            $andFlag = true;
            // 执行and条件
            foreach ($conditionsData['and'] as $and) {
                // 只要有一个and条件不满足 则整个都不会满足 直接返回false
                if (!$andFlag) {
                    return false;
                }
                $conIns = UpgradeConditionHelper::createInstance($and['type'], $and['value'],
                    $conditions['product_id']);
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
            return $orFlag && $andFlag;
        }
        return $flag;
    }

    /**
     * 检测佣金比例总和是否有超100%
     * @param array $levelCommission 等级佣金，格式如 ['1' => '一级佣金比例','2' => '二级佣金比例',...]
     * @throws Exception
     */
    private function checkCommission(array $levelCommission)
    {
        $total = 0;
        foreach ($levelCommission as $v) {
            $total += $v;
        }
        if ($total > 100) {
            throw new \Exception('操作失败：佣金比例超过100%');
        }
    }

    /**
     * 检测是否已经有相同升级条件的生效等级
     * @param array $upgradeCondition
     * @throws Exception
     */
    private function checkCondition(array $upgradeCondition)
    {
        if (!count($upgradeCondition) || !$upgradeCondition['upgrade']) return; //升级条件为空时，不检测
        $str = json_encode($upgradeCondition);
        $query = DistributionLevelModel::where(['condition' => $str, 'site_id' => Site::getCurrentSite()->getSiteId()]);
        if ($this->_model) {
            $query->where("id", "<>", $this->_model->id);
        }
        if ($query->count('id')) {
            throw new \Exception('操作失败：已经存在相同升级条件的等级');
        }
    }

    /**
     * 检测是否已经有相同权重的生效等级
     * @param int $weight
     * @throws Exception
     */
    private function checkWeight($weight)
    {
        $query = DistributionLevelModel::where([
            'weight' => $weight,
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'status' => 1
        ]);
        if ($this->_model) {
            $query->where("id", "<>", $this->_model->id);
        }
        if ($query->count('id')) {
            throw new \Exception('操作失败：当前权重已被使用，请重新设置权重');
        }
    }

    /**
     * 获取当前网站的分销等级列表
     * @param bool $needCount 是否需要统计等级下的分销商个数
     * @param array $param 查询参数
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getList($needCount = false, array $param = [])
    {
        $query = DistributionLevelModel::query();
        $query->where('site_id', Site::getCurrentSite()->getSiteId());
        // 自定义排序
        if ($param['order_by'] && Schema::hasColumn('tbl_distribution_level', $param['order_by'])) {
            if ($param['order_by_desc']) {
                $query->orderByDesc($param['order_by']);
            } else {
                $query->orderBy($param['order_by']);
            }
        } else {
            $query->orderBy("weight");
        }
        if (isset($param['status'])) {
            $query->where('status', $param['status']);
        }
        // 如果传入weight 则去查找比当前weight大的等级
        if (isset($param['weight']) && $param['weight'] > 0) {
            $query->where('weight', '>', $param['weight']);
        }
        $query->addSelect('*');
        if ($param['member_id']) {
            $distributorLevel = DistributorModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('member_id', $param['member_id'])->value('level');
            if ($distributorLevel) {
                $query->selectRaw(DB::raw('if(id=' . $distributorLevel . ',1,0) as is_check'));
            }
        }

        $list = $query->get();
        foreach ($list as $item) {
            $item->condition = json_decode($item->condition, true);
            $item->commission = json_decode($item->commission, true);
            // 获取当前等级的文案
            $item->conditionText = $item->condition ? self::getLevelConditionsTitle($item->condition['upgrade'],
                []) : '';
        }
        if ($needCount) {
            $memberCount = self::distributorCount();
            foreach ($list as &$item) {
                if (array_key_exists($item['id'], $memberCount)) {
                    $item->member_count = intval($memberCount[$item['id']]);
                } else {
                    $item->member_count = 0;
                }
            }
            unset($item);
        }
        return $list;
    }

    /**
     * 统计会员数量
     * @param int $ 默认统计全部等级，可传入等级ID 或 等级ID数组
     * @return array
     */
    public static function distributorCount($levelIds = '')
    {
        $levels = [];
        if (is_array($levelIds)) {
            $levels = $levelIds;
        } else {
            if (is_numeric($levelIds)) {
                $levels[] = $levelIds;
            }
        }

        $query = DistributorModel::where(['site_id' => Site::getCurrentSite()->getSiteId()]);
        $query->whereIn('status', [1, -2]);
        if (!empty($levels)) {
            $query = $query->whereIn('level', $levels);
        }

        $data = [];
        $list = $query->groupBy('level')->select('level', DB::raw('count(1) as count'))->get();
        foreach ($list as $item) {
            $data[$item['level']] = intval($item['count']);
        }
        return $data;
    }

    /**
     * 获取网站默认的分销级别
     */
    public static function getDefaultLevel()
    {
        $level = DistributionLevelModel::where([
            'site_id' => Site::getCurrentSite()->getSiteId(),
            'weight' => 0
        ])->first();
        return $level;
    }

    /**
     * 获取升级条件快照的文案
     * @param $upgrade
     * @param $productId
     * @return array
     */
    public static function getLevelConditionsTitle($upgrade, $productId)
    {
        $conditions = ['and' => [], 'or' => []];
        // 按 and和or 分组
        foreach ($upgrade as $con) {
            $conIns = UpgradeConditionHelper::createInstance($con['type'], $con['value'], $productId);
            $title = $conIns->getDesc();
            // and和or条件分组
            if ($title) {
                $conditions[$con['logistic']][] = $title;
            }
        }
        return $conditions;
    }

    /**
     * 获取等级名称 隐藏等级会同时获取主等级名称
     * @param $levelId
     * @return string
     */
    public static function getLevelName($levelId)
    {
        $levelsObj = DistributionLevelModel::query()
            ->where('site_id', getCurrentSiteId())
            ->select(['id', 'name'])
            ->get()
            ->keyBy('id');
        if (!$levelsObj || !$levelId) return '';
        $levelName = '';
        $ids = myToArray($levelId);
        foreach ($ids as $id) {
            $levelName .= $levelsObj[$id]['name'] . ",";
        }
        return substr($levelName, 0, strlen($levelName) - 1);
    }
}
