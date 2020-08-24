<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Message;

use App\Modules\ModuleShop\Libs\Model\LogisticsModel;
use Illuminate\Support\Collection;
use YZ\Core\Constants as CodeConstants;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class MessageNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $type;
    private $model;
    private $extArgs = [];

    public function __construct(int $type, $model, $extArgs = [])
    {
        $this->type = $type;
        $this->model = $model;
        $this->extendData = [];
        //为什么扩展参数要分解到类属性里，而不是直接放到一个数组，是因为 laravel 在 dispatch 时
        //会调用序列化方法，如果扩展属性不是一个简单对象数组(如数据模型数组等)，将会出现 Serialization of 'Closure' is not allowed 的错误
        $this->extArgs = $extArgs;
        // = array_slice(func_get_args(),2);
        /*foreach ($extArgs as $key => $value) {
            $this->extArgs[] = $value;
        }*/
    }

    public function handle()
    {
        if ($this->model->site_id) {
            Site::initSiteForCli($this->model->site_id);
        } else {
            Log::writeLog('MessageJobError', '该模型没有Site_id');
            throw new \Exception('该模型没有Site_id');
            return false;
        }
        switch (true) {
            case  $this->type == CodeConstants::MessageType_Agent_Agree:
                AgentMessageNotice::sendMessageAgentAgree($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_Dealer_Agree:
                DealerMessageNotice::sendMessageDealerAgree($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_DealerSubMember_LevelUpgrade:
                DealerMessageNotice::sendMessageDealerSubMemberLevelUpgrade($this->model, $this->extArgs);
                break;
            case  $this->type == CodeConstants::MessageType_Dealer_Verify:
                DealerMessageNotice::sendMessageDealerVerify($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_Order_PaySuccess:
                if ($this->extArgs['order_send']) {
                    CloudStockMessageNotice::sendMessageCloudStockPurchaseAdminVerifyOrderPaySuccess($this->model);
                } else {
                    CloudStockMessageNotice::sendMessageCloudStockPurchaseFrontOrderPaySuccess($this->model);
                }
                break;
            case  $this->type == CodeConstants::MessageType_Order_Send:
                if ($this->model instanceof LogisticsModel) {
                    CloudStockMessageNotice::sendMessageCloudStockTakeDeliverySend($this->model);
                } else {
                    CloudStockMessageNotice::sendMessageCloudStockPurchaseOrderMatch($this->model);
                }
                break;
            case  $this->type == CodeConstants::MessageType_Order_NoPay:
                CloudStockMessageNotice::sendMessageCloudStockPurchaseOrderNoPay($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Inventory_Change:
                CloudStockMessageNotice::sendMessageCloudStockInventoryChange($this->model, $this->extArgs);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_ILevelUpgrade:
                CloudStockMessageNotice::sendMessageCloudStockILevelUpgrade($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Purchase_Commission_Under:
                CloudStockMessageNotice::sendMessageCloudStockPurchaseCommissionUnder($this->model, $this->extArgs);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Retail_Commission:
                CloudStockMessageNotice::sendMessageCloudStockRetailCommission($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Withdraw_Commission:
                CloudStockMessageNotice::sendMessageCloudStockWithdrawCommission($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Open:
                CloudStockMessageNotice::sendMessageCloudStockOpen($this->model);
                break;
            case  $this->type == CodeConstants::MessageType_CloudStock_Inventory_Not_Enough:
                CloudStockMessageNotice::sendMessageCloudStockInventoryNotEnough($this->model);
                break;
            case $this->type == CodeConstants::MessageType_Order_NewPay :
                CloudStockMessageNotice::sendMessageCloudStockOrderNewPay($this->model);
                break;
            case $this->type == CodeConstants::MessageType_Dealer_LevelUpgrade :
                DealerMessageNotice::sendMessageDealerLevelUpgrade($this->model, $this->extArgs);
                break;
            case $this->type == CodeConstants::MessageType_Area_Agent_Agree :
                AreaAgentMessageNotice::sendMessageAreaAgentAgree($this->model);
                break;
            case $this->type == CodeConstants::MessageType_Area_Agent_Reject :
                AreaAgentMessageNotice::sendMessageAreaAgentReject($this->model);
                break;
            case $this->type == CodeConstants::MessageType_AreaAgent_Withdraw_Commission :
                AreaAgentMessageNotice::sendMessageAreaAgentWithdrawCommission($this->model);
                break;
        }
    }
}