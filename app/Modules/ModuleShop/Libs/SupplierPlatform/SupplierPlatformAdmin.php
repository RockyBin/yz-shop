<?php

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;

use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierAdminModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use App\Modules\ModuleShop\Libs\Supplier\SupplierConstants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use YZ\Core\Constants;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\SiteAdminModel;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Site\Site;

class SupplierPlatformAdmin
{
    private $_model = null;

    /**
     * 初始化供应商对象
     * WxMenu constructor.
     * @param int $idOrModel 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            // 删除的不去查询
            $this->_model = SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('status', '=', Constants::MemberStatus_Active)
                ->find($idOrModel);
        } elseif ($idOrModel) $this->_model = $idOrModel;
        if (!$this->_model) throw new \Exception('账号不存在');
        if ($this->_model->status <= 0) throw new \Exception('账号已被封号');
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
     * 获取当前登录供应商会员
     * @return array AdminInfo
     */
    public static function getLoginedSupplierPlatformAdmin()
    {
        return Session::get('SupplierPlatformAdmin');
    }

    /**
     * 获取当前供应商登录员工id
     * @return array AdminInfo
     */
    public static function getLoginedSupplierPlatformAdminId()
    {
        if (self::hasLogined()) {
            return self::getLoginedSupplierPlatformAdmin()['id'];
        } else {
            return false;
        }
    }

    /**
     * 获取当前登录所属供应商id
     * @return array AdminInfo
     */
    public static function getLoginedSupplierPlatformAdminMemberId()
    {
        if (self::hasLogined()) {
            return self::getLoginedSupplierPlatformAdmin()['member_id'];
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
        $supplierPlatformAdmin = self::getLoginedSupplierPlatformAdmin();
        if (!is_array($supplierPlatformAdmin)) return false;
        // 判断是否当前站点 判断员工状态
        if (
            $supplierPlatformAdmin['site_id']
            && intval($supplierPlatformAdmin['site_id']) == intval(Site::getCurrentSite()->getSiteId())
            && self::checkAdminStatus($supplierPlatformAdmin['id'])
        ) {
            return true;
        }

        return false;
    }


    /**
     * 判断管理是否有某个权限
     * @param $perms 权限列表，多个权限用英文逗号隔开
     * @return bool|int
     */
    public static function hasPerm($perms)
    {
        if (!self::hasLogined()) return false;
        $supplierAdmin = self::getLoginedSupplierPlatformAdmin();
        if ($supplierAdmin['role_id'] == 0) return true; // 它代表系统管理员
        $perms = myToArray($perms);
        $flag = false;
        foreach ($perms as $perm) {
            if (in_array(strtolower(trim($perm)), $supplierAdmin['perms'])) {
                $flag = true;
                break;
            };
        }
        return $flag;
    }

    /**
     * 检测供应商会员状态
     * @param $id
     * @return bool
     */
    public static function checkAdminStatus($id)
    {
        // 检测员工状态
        $status = SupplierAdminModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $id)
            ->value('status');
        if (intval($status) === Constants::MemberStatus_Active) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * 使用账号登录
     * @param $username
     * @param $password
     * @return bool
     * @throws \Exception
     */
    public static function loginByMobile($mobile, $password)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $supplierAdmin = SupplierAdminModel::query()
            ->where('mobile', $mobile)
            ->where('site_id', $siteId)
            ->first();
        if ($supplierAdmin) {
            if (!Hash::check($password, $supplierAdmin->password)) {
                throw new \Exception('手机号和密码不匹配，请重新输入');
            } else {
                return static::loginWithIdOrModel($supplierAdmin);
            }
        }
        return false;
    }

    /**
     * 供应商登录
     * @param $supplierAdminId 供应商管理员ID
     * @return bool
     */
    public static function loginWithIdOrModel($supplierAdminId)
    {
        if ($supplierAdminId instanceof SupplierAdminModel) {
            $supplierAdmin = $supplierAdminId;
        } else {
            $supplierAdmin = SupplierAdminModel::query()->where('id', $supplierAdminId)->first();
        }
        if ($supplierAdmin) {
            $sn = SNUtil::getSNInstanceBySite($supplierAdmin->site_id);
            if (!$sn->hasPermission(LibsConstants::FunctionPermission_ENABLE_SUPPLIER)) {
                throw new \Exception("该站没有供应商版本");
            }

            if (!$supplierAdmin) {
                throw new \Exception("该账号不存在");
            }
            // 检测状态
            if ($supplierAdmin->status == Constants::MemberStatus_UnActive) {
                throw new \Exception("该账号已被禁用");
            }
            // 处理权限
            $Admin = new SupplierPlatformAdmin($supplierAdmin);
            $perms = $Admin->getPermList();
            // 处理Session数据
            $loginSession = [
                'id' => $supplierAdmin->id,
                'member_id' => $supplierAdmin->member_id,
                'name' => $supplierAdmin->name,
                'username' => $supplierAdmin->username,
                'mobile' => $supplierAdmin->mobile,
                'role_id' => $supplierAdmin->role_id,
                'site_id' => $supplierAdmin->site_id,
                'headurl' => $supplierAdmin->headurl,
                'supplier_status' => $supplierAdmin->status
            ];
            foreach ($perms as $perm) {
                $perm = strtolower(trim($perm));
                if (empty($perm)) continue;
                $loginSession['perms'][] = $perm;
            }
            Session::put('SupplierPlatformAdmin', $loginSession);
            // self::addLog('用户 ' . $member->nickname ? $member->nickname : $member->name . ' 登录成功');
            return true;
        }
        return false;
    }

    /**
     * 登出
     */
    public static function logout()
    {
        Session::remove('SupplierPlatformAdmin');
    }


    /**
     * 根据手机获取信息
     * @param $userName
     * @param $member_id 所属供应商的ID
     * @return bool|\Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getByMobile($mobile, $siteId = 0)
    {
        if (empty($mobile)) {
            return false;
        }
        $mobile = strtolower(trim($mobile));
        if (!$siteId) $siteId = Site::getCurrentSite()->getSiteId();
        return SupplierAdminModel::query()
            ->where('site_id', $siteId)
            ->where('status', '<>', Constants::SiteAdminStatus_Delete)
            ->where('mobile', $mobile)
            ->first();
    }

    /**
     * 检测当前登录供应商状态 （获取数据库检测）
     * @return mixed
     * @throws \Exception
     */
    public static function checkCurrentSupplierStatus()
    {
        $supplierId = self::getLoginedSupplierPlatformAdminMemberId();
        if ($supplierId) {
            $supplier = SupplierModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('member_id', $supplierId)
                ->select('status')
                ->first();
            if ($supplier) {
                if ($supplier['status'] === SupplierConstants::SupplierStatus_Active) {
                    return true;
                } elseif ($supplier['status'] === SupplierConstants::SupplierStatus_Cancel) {
                    throw new \Exception('已被禁用', 410);
                } else {
                    throw new \Exception('状态错误');
                }
            } else {
                throw new \Exception('供应商不存在');
            }
        } else {
            throw new \Exception('请先登录', 403);
        }
    }

    public static function checkSupplier($mobile)
    {
        $supplierAdmin = SupplierAdminModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('mobile', $mobile)
            ->select('status', 'member_id')
            ->first();
        if ($supplierAdmin->status == SupplierConstants::SupplierAdminStatus_Active) {
            $supplier = SupplierModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('member_id', $supplierAdmin->member_id)
                ->first();
            if ($supplier->status === SupplierConstants::SupplierStatus_Active) {
                $member = MemberModel::query()
                    ->where('site_id', getCurrentSiteId())
                    ->where('id', $supplierAdmin->member_id)
                    ->select('status')
                    ->first();
                if ($member && $member->status == LibsConstants::MemberStatus_UnActive) {
                    throw new \Exception('已被禁用', 410);
                }
                return true;
            } elseif ($supplier->status === SupplierConstants::SupplierStatus_Cancel) {
                throw new \Exception('该供应商已被禁用', 410);
            } else {
                throw new \Exception('状态错误');
            }
        } else {
            if ($supplierAdmin->status === SupplierConstants::SupplierAdminStatus_UnActive) {
                throw new \Exception('该账号已被禁用');
            } else {
                throw new \Exception('该账号不存在');
            }
        }
    }

    /**
     * 密码是否为空（自动生成的当作空）
     * @return bool
     */
    public function passwordIsNull()
    {
        if (!$this->checkExist()) return true;
        // 如果密码为空或者密码加密后长度小于16（没有填写密码的时候生成的随机窜是8位的）
        if (!$this->getModel()->password || strlen($this->getModel()->password) < 16) return true;
        return false;
    }

    /**
     * 检查密码是否正确
     * @param $passwordCheck
     * @return bool
     */
    public function passwordCheck($passwordCheck)
    {
        if (!$this->checkExist() || empty($passwordCheck) || $this->passwordIsNull()) return false;
        return Hash::check($passwordCheck, $this->getModel()->password);
    }

    /**
     * 员工是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) return true;
        else return false;
    }

    public static function addOrEditStaff($params)
    {
        if ($params['id']) {
            $model = SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
                ->find($params['id']);
            if (!$model) throw new \Exception('员工不存在');
        } else {
            $model = new SupplierAdminModel();
            $model->site_id = Site::getCurrentSite()->getSiteId();
            $model->member_id = $params['member_id'];
        }
        $model->fill($params);
        $model->save();
    }

    public static function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 1) $page = 1;
        if ($pageSize <= 1) $pageSize = 20;
        $isShowAll = isset($param['show_all']) ? true : false;

        $query = SupplierAdminModel::query()
            ->leftJoin('tbl_supplier_role', 'tbl_supplier_admin.role_id', '=', 'tbl_supplier_role.id')
            ->where('tbl_supplier_admin.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_supplier_admin.member_id', $param['member_id'])
            ->where('tbl_supplier_admin.status', '>', Constants::SiteAdminStatus_Delete); // 排除删除了的
        // 姓名
        if (trim($param['name'])) {
            $query->where('tbl_supplier_admin.name', 'like', '%' . trim($param['name']) . '%');
        }
        // 用户名
        if (trim($param['username'])) {
            $query->where('tbl_supplier_admin.username', 'like', '%' . trim($param['username']) . '%');
        }
        // 关键字
        if (trim($param['keyword'])) {
            $keyword = trim($param['keyword']);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('tbl_supplier_admin.username', 'like', '%' . $keyword . '%')
                    ->orWhere('tbl_supplier_admin.name', 'like', '%' . $keyword . '%')
                    ->orWhere('tbl_supplier_admin.mobile', 'like', '%' . $keyword . '%');
            });
        }


        // 指定ID
        if (is_array($param['ids']) && ($param['ids']) > 0) {
            $query->whereIn('tbl_supplier_admin.id', $param['ids']);
        }
        // 状态
        if (is_numeric($param['status']) && intval($param['status']) >= 0) {
            $query->where('tbl_supplier_admin.status', intval($param['status']));
        }
        // 角色id
        if (is_numeric($param['role_id']) && intval($param['role_id']) >= 0) {
            $query->where('tbl_supplier_admin.role_id', intval($param['role_id']));
        }

        $query->addSelect('tbl_supplier_admin.*', 'tbl_supplier_role.name as role_name');
        // 总数据量
        $total = $query->count();

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
            $query->orderBy('tbl_supplier_admin.id', 'desc');
        }

        $list = $query->get();

        $last_page = ceil($total / $pageSize);

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    public static function getInfo($id)
    {

        return SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
            ->find($id);
    }

    public static function checkMobile($mobile, $id = 0)
    {
        $query = SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId());
        if ($id) {
            $query->whereNotIn('id', [$id]);
        }
        $query->where('mobile', $mobile);
        $query->where('status', '<>', SupplierConstants::SupplierAdminStatus_Delete);
        $check = $query->count();
        return $check > 0 ? true : false;
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
        $savePath = '/supplierAdmin/headImage/';
        // 保存名称
        $saveName = time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(500, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 获取权限列表（私有权限 + 角色权限）
     * @return array
     */
    public function getPermList()
    {
        $perms = [];
        if ($this->checkExist()) {
            // 角色权限
            $roleId = intval($this->_model->role_id);
            if ($roleId > 0) {
                $supplierRole = new SupplierPlatformRole($roleId, $this->_model->site_id);
                $rolePerm = $supplierRole->getPermList();
                if ($rolePerm) {
                    $perms = array_merge($perms, $rolePerm);
                }
            }
        }

        return $perms;
    }


    /**
     * 删除员工
     * @param int $id 要删除的员工id
     * @throws \Exception
     */
    public static function delete($id)
    {
        $admin = SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $id)
            ->first();
        if (!$admin) {
            throw new \Exception('该员工不存在');
        }
        if ($admin->status != Constants::SiteAdminStatus_UnActive) {
            throw new \Exception('只能删除禁用的员工');
        }
        if ($admin->role_id == 0) {
            throw new \Exception('系统管理员不能删除');
        }
        SupplierAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $id)
            ->delete();
    }
}