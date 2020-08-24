<?

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform;

use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;

class PermCheck
{
    public static function check()
    {
        //检测登录状态
        $admin = SupplierPlatformAdmin::getLoginedSupplierPlatformAdmin();
        if (!$admin || !SupplierPlatformAdmin::hasLogined()) {
            return makeApiResponse(403, '请先登录', ['needlogin' => 1]);
        }

        $action = Route::currentRouteAction();
        $action = strtolower($action);
        if (substr($action, 0, 1) == '\\') $action = substr($action, 1);
        $supplierPermconfig = config('supplieradminperm');
        $needPerm = '';
        foreach ($supplierPermconfig as $key => $val) {
            $key = trim(strtolower($key));
            $key = ltrim($key, '\\');
            //nocheck 是一个特殊的值，它用来定义哪些包含特定路径的名称空间不需要检测登录
            if (strtolower($val) == 'nocheck' && stripos($action, $key) !== false) {
                break;
            }
            if (stripos($action, $key) !== false && !SupplierPlatformAdmin::hasLogined()) {
                return new JsonResponse(makeApiResponse(403, '请先登录'));
            }
            if ($key == $action) {
                $needPerm = trim(strtolower($val));
                break;
            }
        }
        if ($needPerm) {
            // 必须要登录的
            if (!SupplierPlatformAdmin::hasLogined()) {
                return new JsonResponse(makeApiResponse(403, '请先登录'));
            }
            if (!SupplierPlatformAdmin::hasPerm($needPerm)) {
                return makeApiResponse(406, '您暂时没有此操作权限，请联系超级管理员！', [
                    'perm' => $needPerm
                ]);
            }
        }
        return true;
    }
}

?>