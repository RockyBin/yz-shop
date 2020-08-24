<?php

namespace App\Http\Controllers\SysManage\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\SysManage\BaseSysManageController;
use function Sodium\add;
use YZ\Core\Model\SysAdminModel;
use YZ\Core\SysManage\SysAdmin;

class AdminController extends BaseSysManageController
{
    /**
     * 获取管理员列表
     * @return array
     */
    public function getList()
    {
        $query = SysAdminModel::query();
        $keyword = trim(Request::get('keyword'));
        if ($keyword) {
            $query->where('username', 'like', '%' . $keyword . '%');
            $query->orWhere('name', 'like', '%' . $keyword . '%');
        }
        $count = $query->count('id');
        $pageSize = Request::get('pageSize');
        if (!$pageSize) $pageSize = 50;
        $pageCount = ceil($count / $pageSize);
        $currentPage = Request::get('page');
        if (!$currentPage) $currentPage = 1;
        $offset = ($currentPage - 1) * $pageSize;
        //DB::enableQueryLog();
        $list = $query->limit($pageSize)->offset($offset)->orderBy('id', 'desc')->get()->toArray();
        //dd(DB::getQueryLog());
        return makeApiResponse(200, 'ok', ['list' => $list, 'pageCount' => $pageCount, 'currentPage' => $currentPage, 'pageSize' => $pageSize, 'total' => $count]);
    }

    /**
     * 获取单个管理员的信息
     * @return array
     */
    public function getUserInfo(){
        try {
            $id = Request::get('id');
            $info = (new SysAdmin($id))->getModel();
            return makeApiResponse(200, 'ok', ['info' => $info]);
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 添加管理员
     * @return array
     */
    public function addUser()
    {
        try {
            $name = Request::get('name');
            $perms = Request::get('perms');
            $username = Request::get('username');
            $password = Request::get('password');
            (new SysAdmin(0))->add($name, $username, $password, $perms);
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 修改网站
     * @return array
     */
    public function editUser()
    {
        try {
            $id = Request::get('id');
            $name = Request::get('name');
            $perms = Request::get('perms');
            $username = Request::get('username');
            $password = Request::get('password');
            $status = Request::get('status');
            $info = [
                'name' => $name,
                'perms' => $perms,
                'username' => $username,
                'password' => $password,
                'status' => $status,
            ];
            (new SysAdmin($id))->edit($info);
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }

    /**
     * 删除网站
     * @return array
     */
    public function deleteUser()
    {
        try {
            $id = Request::get('id');
            (new SysAdmin($id))->delete();
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(500, $ex->getMessage());
        }
    }
}