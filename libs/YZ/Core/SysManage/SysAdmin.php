<?php
namespace YZ\Core\SysManage;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use YZ\Core\Model\SysAdminModel;
use YZ\Core\Model\SysAdminLogModel;

class SysAdmin
{
    private $_model = null;

    /**
     * 初始化菜单对象
     * WxMenu constructor.
     * @param $idOrModel 菜单的 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel)
    {
        if(is_numeric($idOrModel)) $this->_model = SysAdminModel::find($idOrModel);
        else $this->_model = $idOrModel;
        if(!$this->_model) $this->_model = new SysAdminModel();
    }

    /**
     * 返回数据库记录模型
     * @return null|SysAdminModel
     */
    public function getModel(){
        return $this->_model;
    }

    /**
     * 检测密码复杂度
     * @param $password
     * @throws \Exception
     */
    private function checkPassword($password){
        if(strlen($password) < 8) throw new \Exception('密码必须8位以上');
        if(!preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/[`!~@#\$%^&\*\(\)=\+\-_,\.\?\[\]\{\}\<\>]/',$password)){
            throw new \Exception('密码必须包含大小写字母和特殊字符');
        }
    }

    /**
     * 添加管理员
     * @param $username 用户名
     * @param $password 密码
     * @param string $perms 权限
     * @throws \Exception
     */
    public function add($name,$username,$password,$perms = ''){
        //检查用户名是否被占用
        $count = SysAdminModel::where('username','=',$username)->count('id');
        if($count){
            throw new \Exception('用户名 '.$username.'  已经被占用');
        }
        $this->checkPassword($password);
        $this->_model = new SysAdminModel();
        $this->_model->name = $name;
        $this->_model->username = $username;
        $this->_model->password = Hash::make($password);
        $this->_model->status = 1;
        $this->save();
        $this->addPerm($perms);
    }

    /**
     * 修改管理员信息
     * @param array $info 管理员信息
     * @throws \Exception
     */
    public function edit(array $info){
        //检查用户名是否被占用
        if($info['username']) {
            $count = SysAdminModel::where('username', '=', $info['username'])->where('id', '<>', $this->_model->id)->count('id');
            if ($count) {
                throw new \Exception('用户名 ' . $info['username'] . '  已经被占用');
            }
            $this->_model->username = $info['username'];
        }
        if($info['password']){
            $this->checkPassword($info['password']);
            $this->_model->password = Hash::make($info['password']);
        }
        if($info['name']) $this->_model->name = $info['name'];
        if(array_key_exists('status',$info)) $this->_model->status = $info['status'];
        $this->save();
        if($info['perms']){
            $this->addPerm($info['perms'],true);
        }
    }

    /**
     * 保存管理员数据
     */
    public function save(){
        $this->_model->save();
    }

    /**
     * 删除管理员
     * @throws \Exception
     */
    public function delete(){
        if($this->_model->username == 'admin') throw new \Exception('此用户不允许删除');
        $this->_model->delete();
    }

    /**
     * 添加权限
     * @param string $perms 要添加的权限
     * @param bool $replace 是否替换掉原来的权限，而非添加
     */
    public function addPerm($perms,$replace = false){
        $row = SysAdminModel::where('id','=',$this->_model->id)->first();
        $arr = explode(',',$row->perm);
        if($replace) $arr = [];
        if(is_string($perms)) $perms = explode(",",$perms);
        foreach ($perms as $perm) {
            if (!in_array($perm, $arr)) $arr[] = $perm;
        }
        $row->perm = implode(',',$arr);
        $row->save();
    }

    /**
     * 获取当前登录用户
     * @return array AdminInfo
     */
    public static function getLoginedAdmin(){
        return Session::get('SysAdmin');
    }

    /**
     * 判断是否已经登录
     * @return bool
     */
    public static function hasLogined(){
        $sysadmin = self::getLoginedAdmin();
        return is_array($sysadmin);
    }

    /**
     * 判断管理是否有某个权限
     * @param $perms 权限列表，多个权限用英文逗号隔开
     * @return bool|int
     */
    public static function hasPerm($perms){
        if(!self::hasLogined()) return false;
        $sysadmin = self::getLoginedAdmin();
        if($sysadmin['perms']['SYSADMIN']) return 1; //SYSADMIN 是一个特殊权限，它代表系统管理员
        $perms = explode(',',$perms);
        $flag = 0;
        foreach ($perms as $perm) {
            if($sysadmin['perms'][$perm] == 1){
                $flag = 1;break;
            };
        }
        return $flag;
    }

    /**
     * 用户登录
     * @param $username 用户名
     * @param $password 密码
     * @return bool
     */
    public static function login($username,$password){
        $admin = SysAdminModel::where('username','=',$username)->first();
        if(!$admin) throw new \Exception('用户不存在');
        if(intval($admin->status) != 1) throw new \Exception('用户未生效');
        if(!Hash::check($password,$admin->password)) throw new \Exception('密码不正确');
        $perms = explode(',',$admin->perm);
        $loginSession = [
            'id' => $admin->id,
            'name' => $admin->name,
			'perms' => []
        ];
        foreach ($perms as $item) {
            $loginSession['perms'][$item] = 1;
        }
        $admin->lastlogin = date('Y-m-d H:i:s');
        $admin->save();
        Session::put('SysAdmin',$loginSession);
        self::addLog('用户 '.$admin->name.' 登录成功');
        return true;
    }

	public static function logout(){
		Session::remove('SysAdmin');
	}

    /**
     * 添加管理员操作日志
     * @param $logstr 日志信息
     */
    public static function addLog($logstr){
        $admin = self::getLoginedAdmin();
        $log = new SysAdminLogModel();
        $log->admin_id = $admin['id'];
        $log->about = $logstr;
        $log->save();
    }
}