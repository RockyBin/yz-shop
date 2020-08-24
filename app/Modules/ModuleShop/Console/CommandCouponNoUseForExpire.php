<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CouponItemModel;

/**
 * 自动关闭未支付订单
 * Class CommandOrderCloseForNoPay
 * @package App\Modules\ModuleShop\Console
 */
class CommandCouponNoUseForExpire extends Command
{
    protected $name = 'CouponNoUseForExpire';
    protected $description = 'for expire when status for coupon is NoUser and the coupon have expire';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandCouponNoUseForExpire', 'start');

        $couponItemExpression=CouponItemModel::query()
                    ->where('status',Constants::CouponStatus_NoUse)
                    ->where('expiry_time','<',date('Y-m-d H:i:s', time()));

        $list=$couponItemExpression->get();

        $taskNum=count($list);

        Log::writeLog('CommandCouponNoUseForExpire', 'list ' . $taskNum);

        foreach ($list as $item){
            try{
                Log::writeLog('CommandCouponNoUseForExpire', $item->id . ' is close created_at ' . date('Y-m-d H:i:s', time()));
                $item->status=Constants::CouponStatus_Expiry;
                $item->save();
            }catch (\Exception $ex){
                Log::writeLog('CommandCouponNoUseForExpire', $item->id . ' is Error:' . $ex->getMessage());
            }

        }
        Log::writeLog('CommandCouponNoUseForExpire', 'finish');
    }
}