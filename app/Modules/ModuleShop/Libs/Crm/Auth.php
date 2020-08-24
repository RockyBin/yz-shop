<?php

namespace App\Modules\ModuleShop\Libs\Crm;

use App\Modules\ModuleShop\Libs\Model\CrmAuthModel;
use Illuminate\Support\Facades\Session;
use YZ\Core\License\SNUtil;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use Illuminate\Support\Facades\Hash;

/**
 * 授权登录类
 * Class Auth
 * @package App\Modules\ModuleShop\Libs\Crm
 */
class Auth
{
    /**
     * @param string $code 小程序端的获取到的授权码
     * @return array 授权结果列表,实际上是返回 CrmAuthModel 的列表
     */
    public static function wxAppAuth($code)
    {
        $app = WxApp::getInstance();
        $config = $app->getConfig();
        $session = $app->auth->session($code);
        $openId = $session['openid'];
        static::logAuthWay(1, $config['app_id'], $openId);
        $list = static::getAuthList();
        $session['auth_list'] = $list;
        //当授权记录只有一条时，自动登录此管理员
        if (count($list)) {
            $result = SiteAdmin::loginWithIdOrModel($list[0]->admin_id);
            if ($result) {
                $admin = SiteAdmin::getLoginedAdmin();
                $session['admin_id'] = $list[0]->admin_id;
                $session = array_merge($session, $admin);
                static::wxAppAuthLoginAfter($list[0]->id);
            }
        }
        return $session;
    }

    /**
     * @param string $code 小程序端的获取到的授权码
     * @return 获取手机号码
     */
    public static function wxAppAuthGetMobile($code)
    {
        $app = WxApp::getInstance();
        $session = $app->auth->session($code);
        return $session;
    }


    /**
     * 小程序端切换管理帐户
     * @param $authId CrmAuthModel 的主键
     */
    public static function wxAppSwitchAdmin($authId)
    {
        $authWay = static::getAuthWay();
        $openId = $authWay['openId'];
        $model = CrmAuthModel::query()->where(['id' => $authId, 'openid' => $openId, 'auth_type' => $authWay['authType'], 'app_id' => $authWay['appId']])->first();
        if ($model) {
            $result = SiteAdmin::loginWithIdOrModel($model->admin_id);
            if ($result) {
                static::wxAppAuthLoginAfter($authId);
                $admin = SiteAdmin::getLoginedAdmin();
                $session['admin_id'] = $model->admin_id;
                $session['auth_list'] = static::getAuthList();
                $session = array_merge($session, $admin);
                return $session;
            }
        }
        return [];
    }

    public static function wxAppAuthLoginAfter($authId)
    {
        // 更新最后登录时间
        CrmAuthModel::query()->where('id', $authId)->update(['lastlogin' => date('Y-m-d H:i:s')]);
    }

    /**
     * 小程序端登录管理用户
     * @param $siteId   网站ID
     * @param $userName 员工用户名 手机号
     * @param $password 员工密码
     * @param string $openId 相关的openid,和授权方式相关
     * @return array
     * @throws \Exception
     */
    public static function login($siteId, $userName, $password, $openId = '', $headurl = null)
    {
        //\\SiteAdmin::login() 是需要初始化当前站的ID的, 统一通过URL传 InitSiteID 过来的时候自动处理，这是并不需要手动处理
        $result = SiteAdmin::loginByMobile($userName, $password);
        if ($result) {
            $admin = SiteAdmin::getLoginedAdmin();
            $session['admin_id'] = $admin['id'];
            $session = array_merge($session, $admin);
            $authWay = static::getAuthWay();
            if ($openId && $openId == $authWay['openId']) {
                // 检测此用户是否已经绑定过了
                $authModel = CrmAuthModel::query()->where(['openid' => $openId, 'auth_type' => $authWay['authType'], 'app_id' => $authWay['appId'], 'site_id' => $siteId])->first();
                if (!$authModel) {
                    $authModel = new CrmAuthModel();
                    $authModel->fill([
                        'openid' => $openId,
                        'auth_type' => $authWay['authType'],
                        'app_id' => $authWay['appId'],
                        'site_id' => $siteId,
                        'admin_id' => $session['admin_id'],
                    ]);
                    $authModel->save();
                    $siteAdminModel = SiteAdminModel::find($admin['id']);
                    if (!$siteAdminModel->headurl && $headurl) {
                        $siteAdminModel->headurl = $headurl;
                        $siteAdminModel->save();
                    }
                }
                // 返回此用户可切换的管理员列表
                $list = static::getAuthList();
                $session['auth_list'] = $list;
            }
            return $session;
        } else {
            throw new \Exception("用户名不存在或密码错误");
        }
    }

    private static function getAuthList()
    {
        $authWay = static::getAuthWay();
        $sql = "select auth.*,admin.name,shop.name as company from tbl_crm_auth as auth ";
        $sql .= "left join tbl_site_admin as admin on admin.id = auth.admin_id ";
        $sql .= "left join tbl_shop_config as shop on shop.site_id = auth.site_id ";
        $sql .= "where auth.openid = :openId and auth.auth_type = :authType and auth.app_id = :appId ";
        $sql .= "order by auth.lastlogin desc";
        $list = BaseModel::runSql($sql, ['openId' => $authWay['openId'], 'authType' => $authWay['authType'], 'appId' => $authWay['appId']]);
        return $list;
    }

    /**
     * 记录当前的授权方式的信息
     * @param $authType 授权方式，1=微信小程序
     * @param $appId 授权的 appId, 如微信小程序的 appId 等
     * @param $openId 授权获取到的 openid
     */
    private static function logAuthWay($authType, $appId, $openId)
    {
        Session::put('CrmAuthType', $authType);
        Session::put('CrmAuthAppId', $appId);
        Session::put('CrmOpenId', $openId);
    }

    /**
     * 获取当前授权方式的信息
     */
    private static function getAuthWay()
    {
        return [
            'authType' => Session::get('CrmAuthType'),
            'appId' => Session::get('CrmAuthAppId'),
            'openId' => Session::get('CrmOpenId'),
        ];
    }

    /**
     * 绑定
     */
    public static function bind(array $info)
    {
        $authWay = static::getAuthWay();
        $openId = $authWay['openId'];
        $admin = SiteAdminModel::query()
            ->where('mobile', $info['mobile'])
            ->where('site_id', $info['site_id'])
            ->first();
        if (!$admin) {
            throw new \Exception('该员工不存在');
        }
        if ($admin->status === 0) {
            throw new \Exception('该员工已被禁用');
        }
        if (Hash::check($info['password'], $admin->password)) {
            if ($openId) {
                // 检测此用户是否已经绑定过了
                $authModel = CrmAuthModel::query()->where(['openid' => $openId, 'auth_type' => $authWay['authType'], 'app_id' => $authWay['appId'], 'site_id' => $admin->site_id])->first();
                if (!$authModel) {
                    $authModel = new CrmAuthModel();
                    $authModel->fill([
                        'openid' => $openId,
                        'auth_type' => $authWay['authType'],
                        'app_id' => $authWay['appId'],
                        'site_id' => $admin->site_id,
                        'admin_id' => $admin->id,
                    ]);
                    $authModel->save();
                    $list = static::getAuthList();

                    if (!$admin->headurl) {
                        $admin->headurl = $info['headurl'];
                        $admin->save();
                    }

                    return ['auth_list' => $list, 'site_id' => $admin->site_id, 'auth_id' => $authModel->id];
                }
            }
        } else {
            throw new \Exception('密码错误');
        }
        return false;
    }

    /**
     * 解除绑定
     */
    public
    static function unbind($id, $site_id)
    {
        $authInfo = static::getAuthWay();
        CrmAuthModel::query()
            ->where('id', $id)
            ->where('site_id', $site_id)
            ->where('openid', $authInfo['openId'])
            ->where('app_id', $authInfo['appId'])
            ->where('auth_type', $authInfo['authType'])
            ->delete();
    }

}