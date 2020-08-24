<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformWithdrawAccount;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class SupplierPlatformWithdrawConfigController extends BaseController
{
    protected $siteId = 0; // 站点id

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    public function getInfo()
    {
        try {
            $data = (new SupplierPlatformWithdrawAccount($this->memberId))->getInfo();
            return makeServiceResultSuccess('ok', $data);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function edit(Request $request)
    {
        try {
            if ($request->bank_account && $request->bank_account != 'null') {
                if (!is_numeric($request->bank_account)) {
                    return makeApiResponse(500, '银行账户必须全是数值');
                }
            }
            $param = $request->toArray();
            (new SupplierPlatformWithdrawAccount($this->memberId))->edit($param);
            return makeServiceResultSuccess('ok');
        } catch (\Exception $e) {
            throw $e;
        }

    }


}