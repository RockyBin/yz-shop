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
use App\Modules\ModuleShop\Libs\Model\CouponModel;

/**
 * 处理优惠券过期
 * Class CommandCouponExpire
 * @package App\Modules\ModuleShop\Console
 */
class CommandCouponExpire extends Command
{
    protected $name = 'CouponExpire';
    protected $description = 'deal the coupon expire';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandCouponExpire', 'start');

        $couponExpression=CouponModel::query()
                    ->whereIn('status',[Constants::Coupon_Unactive,Constants::Coupon_Active])
                    ->where('effective_type',0)
                    ->where('effective_endtime','<',time());

        $list=$couponExpression->get();

        $taskNum=count($list);

        Log::writeLog('CommandCouponExpire', 'list ' . $taskNum);

        foreach ($list as $item){
            try{
                Log::writeLog('CommandCouponExpire', $item->id . ' is close created_at ' . date('Y-m-d H:i:s', time()));
                $item->status=Constants::Coupon_Expiry;
                $item->save();
            }catch (\Exception $ex){
                Log::writeLog('CommandCouponExpire', $item->id . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandCouponExpire', 'finish');
    }
}