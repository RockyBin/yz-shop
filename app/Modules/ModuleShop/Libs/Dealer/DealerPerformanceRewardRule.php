<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Dealer;

use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardRuleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Site\Site;

/**
 * 经销商推荐奖设置
 */
class DealerPerformanceRewardRule
{
    private $_model = null;

    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            $this->findById($idOrModel);
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 添加数据
     * @param array $param
     * @param bool $reload
     * @return bool|mixed
     */
    public function add(array $param, $reload = false)
    {
        if ($param) {
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['created_at'] = date('Y-m-d H:i:s');
            $param['updated_at'] = date('Y-m-d H:i:s');
            $model = new DealerPerformanceRewardRuleModel();
            $model->fill($param);
            $model->save();
            if ($reload) {
                $this->findById($model->id);
            }
            return $model->id;
        } else {
            return false;
        }
    }

    /**
     * 修改数据
     * @param array $param
     * @param bool $reload
     * @return bool
     */
    public function edit(array $param, $reload = false)
    {
        if ($this->checkExist()) {
            unset($param['site_id']);
            $param['updated_at'] = date('Y-m-d H:i:s');
            $this->_model->fill($param);
            $this->_model->save();
            if ($reload) {
                $this->findById($this->_model->id);
            }
            return true;
        }
        return false;
    }

    /**
     * 删除数据
     */
    public function delete()
    {
        if ($this->checkExist()) {
            $this->_model->delete();
        }
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model && $this->_model->id ? true : false;
    }

    /**
     * 返回模型数据
     * @return bool|null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        }
    }

    /**
     * 根据id查找
     * @param $id
     */
    private function findById($id)
    {
        if ($id) {
            $model = DealerPerformanceRewardRuleModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $id)
                ->first();
            $this->init($model);
        }
    }

    /**
     * 获取列表
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $query = DealerPerformanceRewardRuleModel::query()
            ->where('tbl_dealer_performance_reward_rule.site_id', Site::getCurrentSite()->getSiteId())
        ->leftJoin('tbl_dealer_level as dl','dl.id','=','dealer_level');
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_dealer_performance_reward_rule', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy($param['order_by']);
            } else {
                $query->orderByDesc($param['order_by']);
            }
        } else {
            $query->orderByDesc('created_at');
        }
        $query->selectRaw('tbl_dealer_performance_reward_rule.*,dl.name as level_name');
        $list = $query->get();
        // 返回值
        return [
            'list' => $list,
            'total' => $total,
        ];
    }

    /**
     * 统计数量
     * @param array $param
     * @return int
     */
    public static function count(array $param)
    {
        $query = DealerPerformanceRewardRuleModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        return $query->count();
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery(Builder $query, array $param)
    {
        // 代理等级
        if (is_numeric($param['dealer_level'])) {
            $query->where('dealer_level', intval($param['dealer_level']));
        }
        // 奖励类型
        if (is_numeric($param['reward_type'])) {
            $query->where('reward_type', intval($param['reward_type']));
        }
        // 目标金额值
        if (is_numeric($param['target'])) {
            $query->where('target', intval($param['target']));
        }
    }
}