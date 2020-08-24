<?php
/**
 * Aison
 */

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Libs\Member\LevelUpgrade\UpgradeConditionHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Model\ProductPriceRuleModel;


/**
 * 会员等级类
 * Class MemberLevel
 * @package App\Modules\ModuleShop\Libs\Member
 */
class MemberLevel
{
    private $siteID = 0; // 站点ID
    private $priceRule = []; // 折扣信息
    private $productPriceRuleModel; // 折扣对象
    protected static $levelsObj = [];

    /**
     * 初始化
     * MemberLevel constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteID = $siteId;
        } else if ($siteId == 0) {
            $this->siteID = Site::getCurrentSite()->getSiteId();
        }
        $this->productPriceRuleModel = $this->getProductPriceRule();
        if ($this->productPriceRuleModel) {
            if ($this->productPriceRuleModel->rule_info) {
                $this->priceRule = json_decode($this->productPriceRuleModel->rule_info, true);
            }
        } else {
            $this->productPriceRuleModel = new ProductPriceRuleModel();
            $this->productPriceRuleModel->site_id = $this->siteID;
            $this->productPriceRuleModel->type = Constants::ProductPriceRuleType_MemberLevel;
            $this->productPriceRuleModel->rule_for = 0;
            $this->productPriceRuleModel->created_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * 获取价格规则
     * @return array|mixed
     */
    public function getPriceRule()
    {
        return collect($this->priceRule);
    }

    /**
     * 获取有效的价格规则
     * @return mixed
     */
    public function getActivePriceRule()
    {
        $list = $this->getList(['status', Constants::CommonStatus_Active])['list'];
        return $list->where('discount', '>', 0)->all();
    }

    /**
     * 获取单条数据
     * @param $id
     * @return bool
     */
    public function detail($id)
    {
        if (empty($id)) return false;
        $memberLevel = MemberLevelModel::where([
            ['id', $id],
            ['site_id', $this->siteID]
        ])->first();
        if ($memberLevel) {
            $memberLevelId = $memberLevel->id;
            if ($this->priceRule[$memberLevelId]) {
                $memberLevel->discount = $this->priceRule[$memberLevelId]['discount'];
                if (!$memberLevel->discount) $memberLevel->discount = 100;
            } else {
                $memberLevel->discount = 100;
            }
        }
        return empty($memberLevel) ? false : $memberLevel;
    }

    /**
     * 新增数据
     * @param array $data
     * @return array
     */
    public function add(array $data)
    {
        if (empty($data)) {
            return makeServiceResultFail('数据为空');
        }

        // 检查是否有相同权限且生效的权重
        $weight = intval($data['weight']);
        $result = $this->checkSameWeight($weight);
        if (!$this->isSuccess($result)) {
            return $result;
        }

        // 检查是否有相同的升级条件
        $upgrade_value = intval($data['upgrade_value']);
        $upgrade_type = intval($data['upgrade_type']);
        if ($upgrade_value > 0) {
            $result = $this->checkSameUpgradeCondition($upgrade_value, $upgrade_type);
            if (!$this->isSuccess($result)) {
                return $result;
            }
        }

        // 填充数据
        $data['status'] = 1; // 生效的
        $data['for_newmember'] = 1; // 新人用受该等级影响影响
        $model = new MemberLevelModel();
        $model->fill($data);
        $model->site_id = $this->siteID;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        // 成功返回插入的ID，失败返回false
        if ($model->save()) {
            // 保存折扣规则
            $this->priceRule[$model->id] = [
                'discount' => floatval($data['discount']),
                'weight' => intval($data['weight'])
            ];
            $this->saveProductPriceRule();
            // 返回数据
            return makeServiceResultSuccess('成功', [
                'id' => $model->id
            ]);
        } else {
            return makeServiceResultFail('操作失败');
        }
    }

    /**
     * 修改数据
     * @param array $data
     * @return array
     */
    public function edit(array $data)
    {
        $memberLevel = $this->checkDataExist($data);
        if (!$memberLevel) {
            return makeServiceResultFail('数据不存在');
        }

        // 检查是否有相同权限且生效的权重
        $weight = intval($data['weight']);
        if ($weight > 0 && $weight != $memberLevel->weight) {
            $result = $this->checkSameWeight($weight);
            if (!$this->isSuccess($result)) {
                return $result;
            }
        }

        // 检查是否有相同的升级条件，升级条件变更才检查
        $upgrade_value = intval($data['upgrade_value']);
        $upgrade_type = intval($data['upgrade_type']);
        if ($upgrade_value > 0 && ($upgrade_value != $memberLevel->upgrade_value || $upgrade_type != $memberLevel->upgrade_type)) {
            $result = $this->checkSameUpgradeCondition($upgrade_value, $upgrade_type);
            if (!$this->isSuccess($result)) {
                return $result;
            }
        }

        // 填充数据
        unset($data['created_at']);
        unset($data['status']); // 不能修改状态
        $memberLevel->fill($data);
        $memberLevel->updated_at = date('Y-m-d H:i:s');
        if ($memberLevel->save()) {
            // 保存折扣规则
            $this->priceRule[$memberLevel->id] = [
                'discount' => floatval($data['discount']),
                'weight' => intval($data['weight'])
            ];
            $this->saveProductPriceRule();
            return makeServiceResultSuccess('成功');
        } else {
            return makeServiceResultFail('操作失败');
        }
    }

    /**
     * 变更状态
     * @param $id 主键
     * @param $status 状态
     * @return array
     */
    public function status($id, $status)
    {
        $memberLevel = $this->checkDataExist($id);
        if (!$memberLevel) {
            return makeServiceResultFail('数据不存在');
        }

        if ($status != $memberLevel->status) {
            $status = intval($status);
            if ($status == 0) {
                // 禁用时，检测是否有会员
                $memberCountData = $this->memberCount($memberLevel->id);
                $memberCount = intval($memberCountData[$memberLevel->id]);
                if ($memberCount > 0) {
                    return makeServiceResult(513, '当前会员等级下已拥有会员', [
                        'member_count' => $memberCount
                    ]);
                }
            } else if ($status == 1) {
                // 启用时，检查是否有相同权重
                $result = $this->checkSameWeight($memberLevel->weight);
                if (!$this->isSuccess($result)) {
                    return $result;
                }

                // 启用时，检查是否有相同升级条件
                $result = $this->checkSameUpgradeCondition($memberLevel->upgrade_value, $memberLevel->upgrade_type);
                if (!$this->isSuccess($result)) {
                    return $result;
                }
            }
        }

        // 保存数据
        $memberLevel->status = $status;
        if ($memberLevel->save()) {
            return makeServiceResultSuccess('成功');
        } else {
            return makeServiceResultFail('操作失败');
        }
    }

    /**
     * 删除状态
     * @param $id 主键
     * @return array
     */
    public function delete($id)
    {
        $memberLevel = $this->checkDataExist($id);
        if (!$memberLevel) {
            return makeServiceResultFail('数据不存在');
        }
        $memberCountData = $this->memberCount($memberLevel->id);
        $memberCount = intval($memberCountData[$memberLevel->id]);
        if ($memberCount > 0) {
            return makeServiceResult(513, '当前会员等级下已拥有会员', [
                'member_count' => $memberCount
            ]);
        }

        // 删除数据
        if ($memberLevel->delete()) {
            return makeServiceResultSuccess('成功');
        } else {
            return makeServiceResultFail('操作失败');
        }
    }

    /**
     * 获取所有数据，按权重从小到大
     * @param array $params
     * @return array
     */
    public function getList(array $params = [])
    {
        $query = MemberLevelModel::query()
            ->where('site_id', $this->siteID);
        // 权重
        if (is_numeric($params['weight']) && intval($params['weight']) >= 0) {
            $query->where('weight', intval($params['weight']));
        }
        // 状态
        if (is_numeric($params['status']) && intval($params['status']) >= 0) {
            $query->where('status', intval($params['status']));
        }
        // 升级类型
        if (is_numeric($params['upgrade_type']) && intval($params['upgrade_type']) >= 0) {
            $query->where('upgrade_type', intval($params['upgrade_type']));
        }
        // 是否对新用户开启
        if (is_numeric($params['for_newmember']) && intval($params['for_newmember']) >= 0) {
            $query->where('for_newmember', intval($params['for_newmember']));
        }
        // 最小权重
        if (is_numeric($params['weight_min']) && intval($params['weight_min']) >= 0) {
            $query->where('weight', '>=', intval($params['weight_min']));
        }
        // 最大权重
        if (is_numeric($params['weight_max']) && intval($params['weight_max']) >= 0) {
            $query->where('weight', '<=', intval($params['weight_max']));
        }

        // 这里无需分页
        if ($params['weight_order_desc']) {
            $query->orderBy('weight', 'desc');
        } else {
            $query->orderBy('weight', 'asc');
        }
        
        $query->addSelect('*');
        if ($params['member_id']) {
            $member = (new Member($params['member_id']))->getModel();
            $memberLevel = $member->level;
            $query->selectRaw(DB::raw('if(id=' . $memberLevel . ',1,0) as is_check'));
        }

        $list = $query->get();
        $total = count($list);


        foreach ($list as $item) {
            // 如果需要统计会员数量
            if ($params['memberCount']) {
                $memberCount = $this->memberCount();
                if (array_key_exists($item['id'], $memberCount)) {
                    $item->member_count = intval($memberCount[$item['id']]);
                } else {
                    $item->member_count = 0;
                }
            }

            if (array_key_exists($item->id, $this->priceRule)) {
                $item->discount = floatval($this->priceRule[$item->id]['discount']);
                if (!$item->discount) $item->discount = 100;
            } else {
                $item->discount = 100;
            }
            //     if(preg_match('/\.000?/',$item->discount)) $item->discount = intval($item->discount);
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 是否有生效的相同升级条件的等级
     * @param $memberLevel
     * @return bool
     */
    public function hasSameUpgradeCondition($memberLevel)
    {
        // 如果传值为ID，从数据库读取数据
        if (is_numeric($memberLevel)) {
            $memberLevel = $this->detail($memberLevel);
        }
        if (empty($memberLevel)) return false;

        $condition = [
            ['site_id', $this->siteID],
            ['upgrade_type', $memberLevel['upgrade_type']],
            ['upgrade_value', $memberLevel['upgrade_value']],
            ['status', 1]
        ];
        // 如果带id，要排除id本身
        if ($memberLevel['id']) {
            $condition[] = ['id', '<>', $memberLevel['id']];
        }

        $sameDataCount = MemberLevelModel::where($condition)->count();

        return $sameDataCount > 0;
    }

    /**
     * 是否有生效的相同权重的等级
     * @param $memberLevel
     * @return bool
     */
    public function hasSameWeight($memberLevel)
    {
        // 如果传值为ID，从数据库读取数据
        if (is_numeric($memberLevel)) {
            $memberLevel = $this->detail($memberLevel);
        }
        if (empty($memberLevel)) return false;

        $weight = intval($memberLevel['weight']);
        $sameWeightCount = MemberLevelModel::query()
            ->where([
                ['site_id', $this->siteID],
                ['weight', $weight],
                ['status', 1]
            ])->count();

        return $sameWeightCount > 0;
    }

    /**
     * 统计会员数量
     * @param int $memberLevelData 默认统计全部等级，可传入等级ID 或 等级ID数组
     * @return array
     */
    public function memberCount($memberLevelData = 0)
    {
        $memberLevels = [];
        if (is_array($memberLevelData)) {
            $memberLevels = $memberLevelData;
        } else if (is_numeric($memberLevelData) && $memberLevelData > 0) {
            $memberLevels[] = $memberLevelData;
        }

        $expression = MemberModel::where([
            ['site_id', $this->siteID]
        ]);
        if (!empty($memberLevels)) {
            $expression = $expression->whereIn('level', $memberLevels);
        }

        $data = [];
        $list = $expression->groupBy('level')->select('level', DB::raw('count(1) as count'))->get();
        foreach ($list as $item) {
            $data[$item['level']] = intval($item['count']);
        }
        return $data;
    }

    /**
     * 会员等级转移
     * @param $memberLevelSource 旧的会员等级ID
     * @param $memberLevelTarget 新的会员等级ID
     * @return bool
     */
    public function memberTransfer($memberLevelSource, $memberLevelTarget)
    {
        $memberLevelSource = intval($memberLevelSource);
        $memberLevelTarget = intval($memberLevelTarget);
        // 相同等级无效
        if (!$memberLevelSource || !$memberLevelTarget || $memberLevelTarget == $memberLevelSource) return false;
        // 检查目标等级是否存在
        $result = $this->checkDataExist($memberLevelTarget);
        // 不能转移到禁用的
        if (!$result || !$result['status']) return false;

        MemberModel::query()
            ->where([
                ['site_id', $this->siteID],
                ['level', $memberLevelSource]
            ])->update([
                'level' => $memberLevelTarget
            ]);

        return true;
    }

    /**
     * 检查是否有相同权重的数据，返回信息
     * @param $weight
     * @return array
     */
    private function checkSameWeight($weight)
    {
        $sameWeightCount = $this->hasSameWeight(['weight' => $weight]);
        if ($sameWeightCount > 0) {
            return makeServiceResult(511, '已存在一样等级权重的会员等级');
        } else {
            return makeServiceResultSuccess('成功');
        }
    }

    /**
     * 检查是否有相同的升级条件，返回信息
     * @param $upgrade_value
     * @param int $upgrade_type
     * @return array
     */
    private function checkSameUpgradeCondition($upgrade_value, $upgrade_type)
    {
        return makeServiceResultSuccess('成功');
        /*
        $sameUpgradeCondition = $this->hasSameUpgradeCondition([
            'upgrade_value' => $upgrade_value,
            'upgrade_type' => $upgrade_type
        ]);
        if ($sameUpgradeCondition) {
            return makeServiceResult(512, '已存在相同的升级通过条件');
        } else {
            return makeServiceResultSuccess('成功');
        }*/
    }

    /**
     * 检查数据是否存在
     * @param array $data
     * @return bool
     */
    private function checkDataExist($data)
    {
        // 检查数值是否不为空
        if (is_array($data)) {
            $id = $data['id'];
        } else {
            $id = $data;
        }
        if (empty($id)) {
            return false;
        }

        // 检查数据是否存在
        $memberLevel = MemberLevelModel::where([
            ['id', $id],
            ['site_id', $this->siteID]
        ])->first();
        return $memberLevel ? $memberLevel : false;
    }

    /**
     * 结果集是否代表成功
     * @param $result
     * @return bool
     */
    private function isSuccess($result)
    {
        return intval($result['code']) == 200 ? true : false;
    }

    /**
     * 获取会员折扣
     * @param $memberId 会员ID
     * @return float|int 折扣
     */
    public function getMemberDiscount($memberId)
    {
        if (empty($memberId)) return 0;

        $memberLevelId = MemberLevelModel::query()
            ->from('tbl_member_level as level')
            ->leftjoin('tbl_member as member', 'member.level', '=', 'level.id')
            ->where('member.id', $memberId)
            ->where('level.site_id', $this->siteID)
            ->pluck('level.id')
            ->first();

        return $this->getDiscountById($memberLevelId);
    }

    /**
     * 获取折扣
     * @param $id
     * @return float|int
     */
    public function getDiscountById($id)
    {
        $id = intval($id);
        if ($id && array_key_exists($id, $this->priceRule)) {
            return floatval($this->priceRule[$id]['discount']);
        } else {
            return 0;
        }
    }

    /**
     * 会员是否满足该等级的升级条件
     * @param MemberLevelModel $levelModel 经销商等级
     * @param int $memberId 会员id
     * @param array $params 额外的参数,根据不同的升级条件传不同的参数
     * @return boolean 不能升级时，返回false，否则返回 ['code' => 200,'condition' => '升级条件说明']
     */
    public static function canUpgrade($levelModel, $memberId, $params = [])
    {
        $flag = false;
        $conditions = $levelModel->condition;
        $conditions = json_decode($conditions, true);
        $successConditions = [];
        if (is_array($conditions) && count($conditions) > 0) {
            $conditionsData = ['and' => [], 'or' => []];
            // 把或和与的条件分组
            foreach ($conditions as $con) {
                $conditionsData[$con['logistic']][] = $con;
            }
            $andFlag = true;
            // 执行and条件
            foreach ($conditionsData['and'] as $and) {
                // 只要有一个and条件不满足 则整个都不会满足 直接返回false
                if (!$andFlag) {
                    return false;
                }
                $conIns = UpgradeConditionHelper::createInstance($and['type'], $and['value']);
                $andFlag = $andFlag && $conIns->canUpgrade($memberId, $params);
                if ($andFlag) $successConditions[] = $conIns->getNameText();
            }
            // 执行or条件
            // 没有or条件的时候 or的计算结果默认为true 有的时候默认为false
            $orFlag = count($conditionsData['or']) === 0;
            foreach ($conditionsData['or'] as $or) {
                // 当or条件有一个满足时即可
                if ($orFlag) {
                    break;
                }
                $conIns = UpgradeConditionHelper::createInstance($or['type'], $or['value']);
                $orFlag = $orFlag || $conIns->canUpgrade($memberId, $params);
                if ($orFlag) $successConditions[] = $conIns->getNameText();
            }
            // 如果没有or条件 and条件自己成立即可
            $flag = $orFlag && $andFlag;
        }
        if (!$flag) return false;
        return ['code' => 200, 'condition' => implode(';', $successConditions)];
    }

    /**
     * 获取产品的价格规则
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function getProductPriceRule()
    {
        return ProductPriceRuleModel::query()
            ->where('site_id', $this->siteID)
            ->where('type', Constants::ProductPriceRuleType_MemberLevel)
            ->where('rule_for', 0)
            ->first();
    }

    /**
     * 保存折扣
     */
    private function saveProductPriceRule()
    {
        $this->productPriceRuleModel->rule_info = json_encode($this->priceRule);
        $this->productPriceRuleModel->updated_at = date('Y-m-d H:i:s');
        $this->productPriceRuleModel->save();
    }

    /**
     * 获取等级名称
     * @param $levelId
     * @return string
     */
    public static function getLevelName($levelId)
    {
        if (!self::$levelsObj) {
            // 获取所有的等级名称和父级id
            self::$levelsObj = MemberLevelModel::query()
                ->where('site_id', getCurrentSiteId())
                ->select(['id', 'name'])
                ->get()
                ->keyBy('id');
        }
        if (!self::$levelsObj || !$levelId) return '';
        $levelName = '';
        $ids = myToArray($levelId);
        foreach ($ids as $id) {
            $levelName .= self::$levelsObj[$id]['name'] . ",";
        }
        return substr($levelName, 0, strlen($levelName) - 1);
    }
}