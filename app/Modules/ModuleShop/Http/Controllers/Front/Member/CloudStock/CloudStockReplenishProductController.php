<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSku;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuLog;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuSettle;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * 补货提醒
 * Class CloudStockGeneralSituationController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock
 */
class CloudStockReplenishProductController extends BaseMemberController
{

    function __construct()
    {
        parent::__construct();
    }

    function getReplenishProduct(Request $request)
    {
        $list=CloudStockSku::getReplenishProduct($this->memberId,$request->page,$request->page_size);
        return makeApiResponseSuccess('', $list);
    }
}