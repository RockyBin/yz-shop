<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Site\Site;

/**
 * 团队业绩
 */
class AgentPerformance
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
            $time = date('Y-m-d H:i:s');
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['created_at'] = $time;
            $param['updated_at'] = $time;
            $model = new AgentPerformanceModel();
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
            $model = AgentPerformanceModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $id)
                ->first();
            $this->init($model);
        }
    }

    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;

        $query = AgentPerformanceModel::query()
            ->from('tbl_agent_performance')
            ->where('tbl_agent_performance.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_agent_performance', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy('tbl_agent_performance.' . $param['order_by']);
            } else {
                $query->orderByDesc('tbl_agent_performance.' . $param['order_by']);
            }
        } else {
            $query->orderByDesc('tbl_agent_performance.created_at');
        }
        $list = $query->get();
        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 统计数量
     * @param array $param
     * @return int
     */
    public static function count(array $param)
    {
        $query = AgentPerformanceModel::query()
            ->from('tbl_agent_performance')
            ->where('tbl_agent_performance.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        return $query->count();
    }

    /**
     * 金额求和
     * @param array $param
     * @return int
     */
    public static function sumMoney(array $param)
    {
        $query = AgentPerformanceModel::query()
            ->from('tbl_agent_performance')
            ->where('tbl_agent_performance.site_id', Site::getCurrentSite()->getSiteId());
        // 搜索条件
        self::setQuery($query, $param);
        // 总数据量
        return intval($query->sum('money'));
    }

    /**
     * 根据参数计算 开始时间 和 结束时间
     * @param int $givePeriod 方式：0=月，1=季度，2=年
     * @param int $year 年份
     * @param int $num 第几季度 或 第几个月
     * @return array
     */
    public static function parseTime($givePeriod = 0, $year = 0, $num = 0)
    {
        $givePeriod = intval($givePeriod);
        $num = intval($num);
        $year = intval($year);
        if ($num <= 0) $num = 1;
        if ($year <= 0) $year = intval(date('Y'));

        if ($givePeriod == 2) {
            // 按年
            $timeStart = $year . '-01-01';
            $timeEnd = $year . '-12-31';
        } else if ($givePeriod == 1) {
            // 按季度
            $timeStart = date('Y-m-d', strtotime($year . '-' . (1 + (intval($num) - 1) * 3) . '-01'));
            $endMonth = intval($num) * 3;
            $timeEnd = date('Y-m-d', strtotime($year . '-' . $endMonth . '-' . date('t', strtotime($year . '-' . $endMonth))));
        } else {
            // 按月
            $givePeriod = 0;
            $timeStart = date('Y-m-d', strtotime($year . '-' . $num . '-01'));
            $timeEnd = date('Y-m-d', strtotime($year . '-' . $num . '-' . date('t', strtotime($year . '-' . $num))));
        }
        return [
            'start' => $timeStart,
            'end' => $timeEnd,
            'start_time' => $timeStart . ' 00:00:00',
            'end_time' => $timeEnd . ' 23:59:59',
            'sign' => $givePeriod . '-' . $year . '-' . $num,
        ];
    }

    /**
     * 查询条件设置
     * @param Builder $query
     * @param array $param
     */
    private static function setQuery(Builder $query, array $param)
    {
        // 会员id
        if (is_numeric($param['member_id'])) {
            $query->where('tbl_agent_performance.member_id', intval($param['member_id']));
        }
        // 订单id
        if ($param['order_id']) {
            $query->where('tbl_agent_performance.order_id', $param['order_id']);
        }
        // 统计时期
        if (is_numeric($param['count_period'])) {
            $query->where('tbl_agent_performance.count_period', intval($param['count_period']));
        }
        // 时间范围
        if ($param['created_at_min']) {
            $query->where('tbl_agent_performance.created_at', '>=', $param['created_at_min']);
        }
        if ($param['created_at_max']) {
            $query->where('tbl_agent_performance.created_at', '<=', $param['created_at_max']);
        }
        // 关键词
        if ($param['keyword']) {
            $keyword = '%' . $param['keyword'] . '%';
            $query->where(function (Builder $subQuery) use ($keyword) {
                $subQuery->where('tbl_agent_performance.order_id', 'like', $keyword)
                    ->orWhere('tbl_member.nickname', 'like', $keyword)
                    ->orWhere('tbl_member.mobile', 'like', $keyword);
            });
        }
        // 会员代理等级
        if ($param['agent_level']) {
            $query->where('tbl_member.agent_level', intval($param['agent_level']));
        }
        // 会员的团队代理上级领导
        if (is_numeric($param['agent_parent_id'])) {
            $agentParentId = intval($param['agent_parent_id']);
            if ($agentParentId >= 0) {
                $query->where('tbl_member.agent_parent_id', $agentParentId);
            } else if ($agentParentId == -2) {
                // 非总店
                $query->where('tbl_member.agent_parent_id', '>', 0);
            }
        }
    }
}