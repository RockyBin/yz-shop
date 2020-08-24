<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Test;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * 测试基类
 * Class BaseMemberController
 */
class BaseTestController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, Closure $next) {
            // 验证是否生产环境
            if (config("app.env") == "production") {
                $data = makeApiResponse(401, 'Test Error');
                return new JsonResponse($data);
            } else {
                return $next($request);
            }
        });
    }
}