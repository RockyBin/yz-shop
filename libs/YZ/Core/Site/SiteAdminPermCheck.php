<?
//phpcodelock
namespace YZ\Core\Site;

use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;

use Closure;
use YZ\Core\Constants;

/**
 * 检查权限
 * Class SiteAdminPermCheck
 * @package YZ\Core\Site
 */
class SiteAdminPermCheck
{
    /**
     * @return bool|JsonResponse
     */
    public static function check()
    {
        // 后台路由或者供应商后台路由才需要验证
        $action = Route::currentRouteAction();
        $action = strtolower($action);
        if (substr($action, 0, 1) == '\\') $action = substr($action, 1);
        $sitePermconfig = config('siteadminperm');
        $needPerm = '';
        foreach ($sitePermconfig as $key => $val) {
            $key = trim(strtolower($key));
            $key = ltrim($key, '\\');
            //nocheck 是一个特殊的值，它用来定义哪些包含特定路径的名称空间不需要检测登录
            if (strtolower($val) == 'nocheck' && stripos($action, $key) !== false) {
                break;
            }
            //site.login 是一个特殊的值，它用来定义哪些包含特定路径的名称空间需要检测登录
            if (strtolower($val) == 'site.login' && stripos($action, $key) !== false && !SiteAdmin::hasLogined()) {
                return new JsonResponse(makeApiResponse(403, '请先登录'));
            }
            if ($key == $action) {
                $needPerm = trim(strtolower($val));
                break;
            }
        }
        if ($needPerm) {
            // 必须要登录的
            if (!SiteAdmin::hasLogined()) {
                return new JsonResponse(makeApiResponse(403, '请先登录'));
            }
            // SiteRole_OnlyLogin 为只需后台登录，无需其他权限
            if ($needPerm != Constants::SiteRole_OnlyLogin) {
                if (!SiteAdmin::hasPerm($needPerm)) {
                    return new JsonResponse(makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！', [
                        'perm' => $needPerm
                    ]));
                }
            }
        }
        return true;
    }
}

?>