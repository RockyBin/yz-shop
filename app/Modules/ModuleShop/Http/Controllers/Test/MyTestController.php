<?php

namespace App\Modules\ModuleShop\Http\Controllers\Test;

use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\Agent\OtherReward\GratelFulReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplySettingModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Model\DistributionSettingModel;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ShoppingCartModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierAdminModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants;
use YZ\Core\Finance\Finance;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Model\PointModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Plugin\PluginHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Schema;

class MyTestController extends BaseTestController
{
    use DispatchesJobs;

    
}


