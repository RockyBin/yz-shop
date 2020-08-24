<?php

namespace YZ\Core\Site;

use App\Modules\ModuleShop\Libs\Model\CrmAuthModel;
use App\Modules\ModuleShop\Libs\Model\ShopConfigModel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\Statistics\Statistics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use YZ\Core\Constants;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Model\SiteAdminPermModel;
use YZ\Core\Model\SiteAdminLogModel;

class SiteAdmin
{
    private $_model = null;

    /**
     * 初始化员工对象
     * WxMenu constructor.
     * @param int $idOrModel 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            // 删除的不去查询
            $this->_model = SiteAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('status', '>', Constants::SiteAdminStatus_Delete)
                ->find($idOrModel);
        } elseif ($idOrModel) $this->_model = $idOrModel;
        if (!$this->_model) $this->_model = new SiteAdminModel();
    }

    /**
     * 返回数据库记录模型
     * @return null|SiteAdminModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 修改管理员
     * @param array $info 管理员信息
     */
    public function setInfo(array $info)
    {
        $this->_model->fill($info);
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) {
            return true;
        }
        return false;
    }

    /**
     * 保存管理员数据
     */
    public function save($param = [])
    {
        if ($param) {
            $this->_model->fill($param);
        }
        $result = $this->_model->save();
        if ($result) {
            return $this->_model->id;
        } else {
            return false;
        }
    }

    /**
     * 添加私有权限
     * @param $perm
     */
    public function addPerm($perm)
    {
        $perm = strtolower(trim($perm));
        if (empty($perm)) return;
        $permModel = SiteAdminPermModel::where('admin_id', $this->_model->id)->where('perm', $perm)->first();
        if (!$permModel) {
            $permModel = new SiteAdminPermModel();
            $permModel->site_id = $this->_model->site_id;
            $permModel->admin_id = $this->_model->id;
        }
        $permModel->perm = $perm;
        $permModel->save();
    }

    /**
     * 是否系统超级管理员
     * @return bool
     */
    public function isSystemAdmin()
    {
        if ($this->checkExist()) {
            return intval($this->_model->role_type) == Constants::SiteRoleType_Admin;
        }
        return false;
    }

    /**
     * 获取权限列表（私有权限 + 角色权限）
     * @return array
     */
    public function getPermList()
    {
        $perms = [];
        if ($this->checkExist()) {
            // 私有权限
            $personalPerm = $this->_model->perms()->pluck('perm')->all();
            if (count($personalPerm) > 0) {
                $perms = array_merge($perms, $personalPerm);
            }
            // 角色权限
            $roleId = intval($this->_model->role_id);
            if ($roleId > 0) {
                $siteRole = new SiteRole($roleId, $this->_model->site_id);
                $rolePerm = $siteRole->getPermList();
                if ($rolePerm) {
                    $perms = array_merge($perms, $rolePerm);
                }
            }
        }

        return $perms;
    }

    /**
     * 列表数据
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 1) $page = 1;
        if ($pageSize <= 1) $pageSize = 20;
        $isShowAll = isset($param['show_all']) ? true : false;

        $query = SiteAdminModel::query()
            ->from('tbl_site_admin')
            ->leftJoin('tbl_site_role', 'tbl_site_admin.role_id', '=', 'tbl_site_role.id')
            ->leftJoin('tbl_site_admin_department', 'tbl_site_admin.department_id', '=', 'tbl_site_admin_department.id')
            ->leftJoin('tbl_member as m', function ($q) {
                $q->on('m.admin_id', 'tbl_site_admin.id')
                    ->where('m.status', 1);
            })
            ->where('tbl_site_admin.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_site_admin.status', '>', Constants::SiteAdminStatus_Delete); // 排除删除了的
        // 姓名
        if (trim($param['name'])) {
            $query->where('tbl_site_admin.name', 'like', '%' . trim($param['name']) . '%');
        }
        // 用户名
        if (trim($param['username'])) {
            $query->where('tbl_site_admin.username', 'like', '%' . trim($param['username']) . '%');
        }
        // 关键字
        if (trim($param['keyword'])) {
            $keyword = trim($param['keyword']);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('tbl_site_admin.username', 'like', '%' . $keyword . '%')
                    ->orWhere('tbl_site_admin.name', 'like', '%' . $keyword . '%')
                    ->orWhere('tbl_site_admin.mobile', 'like', '%' . $keyword . '%');
            });
        }

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
        // 角色id
        if (is_numeric($param['role_id']) && intval($param['role_id']) >= 0) {
            $query->where('tbl_site_admin.role_id', intval($param['role_id']));
        }
        // 部门id
        if (is_numeric($param['department_id']) && $param['department_id'] > 0) {
            $departmentId = intval($param['department_id']);
            // 要同时查询该部门的所有下级部门
            if ($departmentId) {
                $department = new SiteAdminDepartment($departmentId);
                $departmentIds = array_merge($department->getSubDepartmentIds(), [$departmentId]);
                $query->whereIn('tbl_site_admin.department_id', $departmentIds);
            }
        }
        // 排查的用户名
        if ($param['exclude_username']) {
            $excludeUserName = myToArray($param['exclude_username']);
            if ($excludeUserName) {
                $query->whereNotIn('tbl_site_admin.username', $excludeUserName);
            }
        }

        // 排除已选中的员工，用于会员列表员工选择处
        if ($param['exclude_admin_id']) {
            $excludeAdminId = myToArray($param['exclude_admin_id']);
            if ($excludeAdminId) {
                $query->whereNotIn('tbl_site_admin.id', $excludeAdminId);
            }
        }

        // 总数据量
        $total = $query->count(DB::raw('DISTINCT(tbl_site_admin.id)'));
        $query->groupBy('tbl_site_admin.id');
        // 查询
        $query->selectRaw('count(m.id) as member_count, 
            convert(sum(if(m.is_distributor > 0, 1, 0)),UNSIGNED) as distributor_count,
            convert(sum(if(m.agent_level > 0, 1, 0)),UNSIGNED) as agent_count,
            convert(sum(if(m.dealer_level > 0, 1, 0)),UNSIGNED) as dealer_count,
            convert(sum(if(m.is_area_agent > 0, 1, 0)),UNSIGNED) as area_agent_count,
            convert(sum(if(m.is_supplier > 0, 1, 0)),UNSIGNED) as supplier_count');
        $query->addSelect('tbl_site_admin.*', 'tbl_site_role.name as role_name', 'tbl_site_admin_department.name as department_name');
        //数据统计
//        $query->withCount('member');
        $query->withCount(['member as new_member' => function ($query) {
            $query->where('tbl_member.created_at', '>', date("Y-m-d"));
        }]);


        // 总数据量
        if ($isShowAll) {
            // 显示全部
            $pageSize = $total > 0 ? $total : 1;
            $page = 1;
        }

        $query->forPage($page, $pageSize);
        $query->orderByRaw(" role_id <> 0");
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

    /**
     * 获取当前登录用户
     * @return array AdminInfo
     */
    public static function getLoginedAdmin()
    {
        return Session::get('SiteAdmin');
    }

    /**
     * 获取当前登录用户id
     * @return array AdminInfo
     */
    public static function getLoginedAdminId()
    {
        if (self::hasLogined()) {
            return self::getLoginedAdmin()['id'];
        } else {
            return false;
        }
    }

    /**
     * 判断是否已经登录
     * @return bool
     */
    public static function hasLogined()
    {
        $siteAdmin = self::getLoginedAdmin();
        if (!is_array($siteAdmin)) return false;
        // 判断是否当前站点 判断员工状态
        if (
            $siteAdmin['site_id']
            && intval($siteAdmin['site_id']) == intval(Site::getCurrentSite()->getSiteId())
            && self::checkAdminStatus($siteAdmin['id'])
        ) {
            return true;
        }

        return false;
    }

    /**
     * 检测员工状态
     * @param $id
     * @return bool
     */
    public static function checkAdminStatus($id)
    {
        // 检测员工状态
        $adminStatus = SiteAdminModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $id)
            ->value('status');
        if (intval($adminStatus) === Constants::SiteAdminStatus_Active) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断管理是否有某个权限
     * @param $perms 权限列表，多个权限用英文逗号隔开
     * @return bool|int
     */
    public static function hasPerm($perms)
    {
        if (!self::hasLogined()) return false;
        $siteAdmin = self::getLoginedAdmin();
        if (in_array(strtolower(trim(Constants::SiteRole_SiteAdmin)), $siteAdmin['perms'])) return true; // SYSADMIN 是一个特殊权限，它代表系统管理员
        $perms = myToArray($perms);
        $flag = false;
        foreach ($perms as $perm) {
            if (in_array(strtolower(trim($perm)), $siteAdmin['perms'])) {
                $flag = true;
                break;
            };
        }
        return $flag;
    }

    /**
     * 用户登录
     * @param $username 用户名
     * @param $password 密码
     * @param int $checkpwd 是否需要检查密码的正确性，只有在72ad后台一键登录的时候才设置为0
     * @return bool
     * @throws \Exception
     */
    public static function login($username, $password, $checkpwd = 1)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $admin = SiteAdminModel::query()
            ->where(function ($q) use ($username) {
                $q->where('username', $username)
                    ->orWhere('mobile', $username);
            })
            ->where('site_id', $siteId)
            ->where('status', '!=', Constants::SiteAdminStatus_Delete)
            ->first();
        if ($admin && (Hash::check($password, $admin->password) || !$checkpwd)) {
            return static::loginWithIdOrModel($admin);
        }
        return false;
    }

    /**
     * 使用手机号登录
     * @param $mobile
     * @param $password
     * @return bool
     * @throws \Exception
     */
    public static function loginByMobile($mobile, $password)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $admin = SiteAdminModel::query()
            ->where('mobile', $mobile)
            ->where('site_id', $siteId)
            ->where('status', '!=', Constants::SiteAdminStatus_Delete)
            ->first();
        if ($admin && (Hash::check($password, $admin->password))) {
            return static::loginWithIdOrModel($admin);
        }
        return false;
    }

    /**
     * 用户登录
     * @param $id 员工ID
     * @return bool
     */
    public static function loginWithIdOrModel($id)
    {
        if ($id instanceof SiteAdminModel) {
            $admin = $id;
        } else {
            $admin = SiteAdminModel::query()->where('id', $id)->first();
        }
        if ($admin) {
            // 检测状态
            if ($admin->status != Constants::SiteAdminStatus_Active) {
                throw new \Exception("该员工已被禁用或删除");
            }
            // 处理权限
            $siteAdmin = new SiteAdmin($admin);
            $perms = $siteAdmin->getPermList();
            // 处理Session数据
            $loginSession = [
                'id' => $admin->id,
                'name' => $admin->name,
                'username' => $admin->username,
                'mobile' => $admin->mobile,
                'site_id' => $admin->site_id,
                'headurl' => $admin->headurl,
                'role_type' => $admin->role_type,
                'perms' => [Constants::SiteRole_OnlyLogin],
            ];
            foreach ($perms as $perm) {
                $perm = strtolower(trim($perm));
                if (empty($perm)) continue;
                $loginSession['perms'][] = $perm;
            }
            // 获取网站的版本权限
            $sn = SNUtil::getSNInstanceBySite($admin->site_id);
            $LicensePerm = $sn->getPermission(1);
            $loginSession['license_perm'] = $LicensePerm;
            $loginSession['siteComdataPath'] = Site::getSiteComdataDir($admin->site_id);
            $admin->lastlogin = date('Y-m-d H:i:s');
            $admin->save();
            Session::put('SiteAdmin', $loginSession);
            self::addLog('用户 ' . $admin->name . ' 登录成功');
            return true;
        }
        return false;
    }

    /**
     * 登出
     */
    public static function logout()
    {
        Session::remove('SiteAdmin');
    }

    /**
     * 添加管理员操作日志
     * @param $logstr 日志信息
     */
    public static function addLog($logstr)
    {
        $site = Site::getCurrentSite();
        $admin = self::getLoginedAdmin();
        $log = new SiteAdminLogModel();
        $log->site_id = $site->getSiteId();
        $log->admin_id = $admin['id'];
        $log->about = $logstr;
        $log->save();
    }

    /**
     * 判断当前登录用户是否有权限管理某个数据库模型，目前只验证模型的 site_id 与 当前登录用户的 site_id 是否一致
     */
    public static function canManageModel(BaseModel $model)
    {
        if (!self::hasLogined()) return false;
        $siteadmin = self::getLoginedAdmin();
        if ($model->site_id && intval($siteadmin['site_id']) != intval($model->site_id)) return false;
        return true;
    }

    /**
     * 检查用户名是否存在
     * @param $userName
     * @return bool
     */
    public static function userNameExist($userName)
    {
        if (empty($userName)) {
            return false;
        }
        $userName = strtolower(trim($userName));
        $siteId = Site::getCurrentSite()->getSiteId();
        return SiteAdminModel::query()
                ->where('site_id', $siteId)
                ->where('username', $userName)
                ->where('status', '!=', Constants::SiteAdminStatus_Delete)
                ->count() > 0;
    }

    /**
     * 检查手机号是否存在
     * @param $mobile
     * @return bool
     */
    public static function mobileExist($mobile)
    {
        if (empty($mobile)) {
            return false;
        }
        $mobile = trim($mobile);
        $siteId = Site::getCurrentSite()->getSiteId();
        return SiteAdminModel::query()
                ->where('site_id', $siteId)
                ->where('mobile', $mobile)
                ->where('status', '!=', Constants::SiteAdminStatus_Delete)
                ->count() > 0;
    }

    /**
     * 根据用户名获取会员
     * @param $userName
     * @return bool|\Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getByUserName($userName, $siteId = 0)
    {
        if (empty($userName)) {
            return false;
        }
        $userName = strtolower(trim($userName));
        if (!$siteId) $siteId = Site::getCurrentSite()->getSiteId();
        return SiteAdminModel::query()
            ->where('site_id', $siteId)
            ->where(function ($q) use ($userName) {
                $q->where('username', $userName)
                    ->orWhere('mobile', $userName);
            })
            ->first();
    }

    /**
     * 更改site_admin表的头像和姓名，暂时严禁更改其他信息
     * @param $userName
     * @return bool
     */
    public function edit(array $info)
    {
        try {
            $model = $this->getModel();
            if ($info['admin_name']) {
                $model->name = $info['admin_name'];
            }
            if ($info['headurl']) {
                if (is_file($info['headurl'])) {
                    // 上传banner
                    $bannerSaveDir = Site::getSiteComdataDir('', true) . '/siteAdmin/headImage/';
                    $imageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                    $upload = new FileUpload($info['headurl'], $bannerSaveDir, $imageName);
                    $upload->save();
                    $info['headurl'] = '/siteAdmin/headImage/' . $upload->getFullFileName();
                }
                $model->headurl = $info['headurl'];
            }

            $model->save();
            $sessionSiteAdmin = self::getLoginedAdmin();
            $sessionSiteAdmin['name'] = $model->name;
            $sessionSiteAdmin['headurl'] = $model->headurl;
            Session::put('SiteAdmin', $sessionSiteAdmin);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 根据用户opinid获取绑定的企业列表
     * @param $userName
     * @return bool
     */
    public static function getShopList()
    {
        $openid = Session::get('CrmOpenId');
        $shop = CrmAuthModel::query()
            ->leftJoin('tbl_site_admin', 'tbl_site_admin.id', 'tbl_crm_auth.admin_id')
            ->leftJoin('tbl_shop_config as sc', 'sc.site_id', 'tbl_crm_auth.site_id')
            ->where('tbl_site_admin.status', 1)
            ->where('openid', $openid)
            ->select(['sc.name', 'sc.logo', 'sc.site_id', 'tbl_crm_auth.id as auth_id'])
            ->get();
        return $shop;
    }

    /**
     * 上传头像
     * @param UploadedFile $image
     * @return string                头像的路径
     * @throws \Exception
     */
    public static function uploadHeadImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/siteAdmin/headImage/';
        // 保存名称
        $saveName = time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(500, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 获取发展的员工数量
     * @param $id
     * @return int
     */
    public static function getMemberCount($id)
    {
        return MemberModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('admin_id', $id)
            ->where('status', Constants::MemberStatus_Active)
            ->count();
    }

    /**
     * 删除员工
     * @param int $id 要删除的员工id
     * @param int $deleteType 删除类型 0 直接解绑会员 1 转移给其他员工
     * @param int $adminId 要转移的员工id
     * @throws \Exception
     */
    public static function delete($id, $deleteType = 0, $adminId = 0)
    {
        $admin = new SiteAdmin($id);
        if (!$admin->checkExist()) {
            throw new \Exception('该员工不存在');
        }
        if ($admin->_model->status != Constants::SiteAdminStatus_UnActive) {
            throw new \Exception('只能删除禁用的员工');
        }
        if ($admin->isSystemAdmin()) {
            throw new \Exception('系统管理员不能删除');
        }
        $updateData = [];
        // 直接解绑
        if ($deleteType == 0) {
            $updateData['admin_id'] = 0;
        } else if ($deleteType == 1 && $adminId > 0) {
            // 转移到其他员工
            $otherAdmin = new SiteAdmin($adminId);
            if (!$otherAdmin->checkExist()) {
                throw new \Exception('要转移的员工不存在');
            }
            $updateData['admin_id'] = $adminId;
        }
        if (!$updateData) {
            throw new \Exception('请选择正确的删除方式');
        }
        // 名下的会员处理
        MemberModel::query()->where('site_id', getCurrentSiteId())
            ->where('admin_id', $id)
            ->update($updateData);
        // 修改状态
        $admin->setInfo(['status' => Constants::SiteAdminStatus_Delete]);
        $admin->save();
    }
}