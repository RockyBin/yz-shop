<?php


namespace App\Modules\ModuleShop\Libs\Crm;


use App\Modules\ModuleShop\Libs\Model\StaffVisitLogModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;

class StaffVisitLog
{
    private $_model;

    public function __construct($idOrModel)
    {
        if (is_numeric($idOrModel)) {
            $this->_model = StaffVisitLogModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $idOrModel)
                ->first();
        } else {
            $this->_model = $idOrModel;
        }
    }

    public static function add(array $info)
    {
        try {
            (new StaffVisitLogModel())->fill($info)->save();
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function edit(array $info)
    {
        try {
            $this->_model->content = $info['content'];
            $this->_model->save();
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    function delete()
    {
        try {
            $this->_model->delete();
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     **  检测权限
     **/
    public static function checkPerm($adminId, $memberId)
    {
        // 如果这个管理员是拥有后台操作权限的，则可以增删改权限
        if (SiteAdmin::hasPerm('member.detail.operate')) {
            return true;
        };
        // 如果这个会员是员工所有的，那他不需要有权限，可以更改自己所属的员工的记录
        $member = MemberModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $memberId)
            ->where('admin_id', $adminId)
            ->count();
        if ($member > 0) return true;
        return false;
    }

    public static function getList($params)
    {
        $page = intval($params['page']);
        $pageSize = intval($params['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $expression = StaffVisitLogModel::query();
        $expression->where('site_id', Site::getCurrentSite()->getSiteId());
        if (isset($params['member_id'])) {
            $expression->where('member_id', $params['member_id']);
        }
        $expression->orderBy('created_at', 'desc');
        $total = $expression->count();
        $expression->forPage($page, $pageSize);
        $last_page = ceil($total / $pageSize);

        $list = $expression->get();
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