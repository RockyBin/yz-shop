<?php
/**
 * crm员工逻辑类
 * User: liyaohui
 * Date: 2020/3/5
 * Time: 10:22
 */

namespace App\Modules\ModuleShop\Libs\Crm;


use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Site\Site;

class Staff
{
    private $_model = null;
    protected $siteId = 0;

    /**
     * Staff constructor.
     * @param int $idOrModel 员工id 或 员工表模型
     * @param bool $needCheck 是否需要检测存不存在
     * @throws \Exception
     */
    public function __construct($idOrModel = 0, $needCheck = true)
    {
        $this->siteId = getCurrentSiteId();
        if (is_numeric($idOrModel) && $idOrModel > 0) {
            $this->_model = SiteAdminModel::query()->where('site_id', $this->siteId)->find($idOrModel);
        } else if ($idOrModel instanceof SiteAdminModel) {
            $this->_model = $idOrModel;
        }
        if ($needCheck && !$this->_model) {
            throw new \Exception('员工不存在');
        }
    }

    /**
     * 获取员工首页需要的数据
     * @param $params
     * @return array
     */
    public function getHomePageData($params)
    {
        // 基础数据
        $data = [
            'name' => $this->_model->name,
            'mobile' => $this->_model->mobile,
            'position' => $this->_model->position,
            'headurl' => $this->_model->headurl,
        ];
        // 客户统计
        $memberCount = $this->getHomePageMemberCount($params['member_count_all']);
        // 获取新增排行
        $colleagueMemberNewCount = MemberModel::query()
            ->from('tbl_member as m')
            ->leftJoin('tbl_site_admin as admin', 'admin.id', 'm.admin_id')
            ->where('m.site_id', $this->siteId)
            ->where('m.created_at', '>=', date('Y-m-d'))
            ->where('m.admin_id', '>', 0)
            ->groupBy('m.admin_id')
            ->orderByDesc('member_count_today')
            ->selectRaw('count(*) as member_count_today,admin.name,admin.headurl,admin.id as admin_id')
            ->limit(10)
            ->get();
        // 如果没有新增的 则不输出
        $data['colleague_new_list'] = $colleagueMemberNewCount->where('member_count_today', '>', 0)->values()->toArray();
        // 获取同事动态
        $colleagueMemberCount = MemberModel::query()
            ->from('tbl_member as m')
            ->leftJoin('tbl_site_admin as admin', 'admin.id', 'm.admin_id')
            ->where('m.site_id', $this->siteId)
            ->where('m.admin_id', '>', 0)
            ->groupBy('m.admin_id')
            ->orderByDesc('member_count_all')
            ->selectRaw('count(*) as member_count_all,admin.name,admin.headurl,admin.id as admin_id')
            ->limit(10)
            ->get()->toArray();
        $data['colleague_all_list'] = $colleagueMemberCount;
        return array_merge($data, $memberCount);
    }

    /**
     * 获取员工首页需要的会员统计
     * @param int $isAll 是否获取所有的会员统计
     * @return mixed
     */
    public function getHomePageMemberCount($isAll = 0)
    {
        $memberCountQuery = MemberModel::query()
            ->where('site_id', $this->siteId);
        // 只获取当前员工的
        if ($isAll == 0) {
            $memberCountQuery->where('admin_id', $this->_model->id);
        }
        $data['member_count'] = $memberCountQuery->count();
        // 今天的时间
        $today = date('Y-m-d');
        $data['member_count_today'] = $memberCountQuery->where('created_at', '>=', $today)->count();
        return $data;
    }


    /**
     * 列表数据
     * @param $param
     * @return array
     */
    public static function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 1) $page = 1;
        if ($pageSize <= 1) $pageSize = 20;

        $query = SiteAdminModel::query()
            ->from('tbl_site_admin')
            ->leftJoin('tbl_member as m', function ($q) {
                $q->on('m.admin_id', 'tbl_site_admin.id')
                    ->where('m.status', 1);
            })
            ->where('tbl_site_admin.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_site_admin.status', '>', Constants::SiteAdminStatus_Delete); // 排除删除了的

        // 关键字搜索-用于员工端
        if (trim($param['front_keyword'])) {
            $frontKeyword = trim($param['front_keyword']);
            $query->where(function ($subQuery) use ($frontKeyword) {
                $subQuery->where('tbl_site_admin.mobile', 'like', '%' . $frontKeyword . '%')
                    ->orWhere('tbl_site_admin.name', 'like', '%' . $frontKeyword . '%');
            });
        }
        // 指定ID
        if (is_array($param['ids']) && count($param['ids']) > 0) {
            $query->whereIn('tbl_site_admin.id', $param['ids']);
        }
        // 状态
        if (is_numeric($param['status']) && intval($param['status']) >= 0) {
            $query->where('tbl_site_admin.status', intval($param['status']));
        }

        // 总数据量
        $total = $query->count(DB::raw('DISTINCT(tbl_site_admin.id)'));
        $query->groupBy('tbl_site_admin.id');
        // 查询
        $query->selectRaw('count(m.id) as member_count');
        $query->addSelect('tbl_site_admin.*');
        //数据统计
//        $query->withCount('member');
        $query->withCount(['member as new_member' => function ($query) {
            $query->where('tbl_member.created_at', '>', date("Y-m-d"));
        }]);

        $query->forPage($page, $pageSize);
        if ($param['order_by']) {
            foreach ($param['order_by'] as $item) {
                $query->orderBy($item['field'], $item['sort_rule']);
            }
        } else {
            $query->orderBy('tbl_site_admin.id', 'desc');
        }

        //DB::enableQueryLog();
        $list = $query->get();
        //print_r(DB::getQueryLog());

        $last_page = ceil($total / $pageSize);

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function getModel()
    {
        return $this->_model;
    }
}