<?php

namespace App\Console;

use App\Modules\ModuleShop\Console\CommandDistributorChangeParent;
use App\Modules\ModuleShop\Console\CommandTaskRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //执行任务
        CommandTaskRun::class,
        CommandDistributorChangeParent::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /**
         * 商城定时任务
         */
        // 清理未支付订单
        $schedule->command(\App\Modules\ModuleShop\Console\CommandOrderCloseForNoPay::class)->everyMinute()->withoutOverlapping()->runInBackground();
        // 订单自动收货
        $schedule->command(\App\Modules\ModuleShop\Console\CommandOrderReceipt::class)->hourly()->withoutOverlapping()->runInBackground();
        // 订单自动完成
        $schedule->command(\App\Modules\ModuleShop\Console\CommandOrderFinish::class)->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        // 优惠券把未使用且过期的优惠券置为过期状态
        $schedule->command(\App\Modules\ModuleShop\Console\CommandCouponNoUseForExpire::class)->daily()->withoutOverlapping()->runInBackground();
        // 把已过期的优惠券的状态置为已过期
        $schedule->command(\App\Modules\ModuleShop\Console\CommandCouponExpire::class)->daily()->withoutOverlapping()->runInBackground();
        // 未支付订单通知客户
        $schedule->command(\App\Modules\ModuleShop\Console\CommandOrderNoticeForNoPay::class)->everyMinute()->withoutOverlapping()->runInBackground();
        // 云仓订单未支付订单通知客户
        $schedule->command(\App\Modules\ModuleShop\Console\CommandCloudStockOrderNoticeForNoPay::class)->everyMinute()->withoutOverlapping()->runInBackground();
        // 订单自动好评
        $schedule->command(\App\Modules\ModuleShop\Console\CommandOrderAutoComment::class)->hourly()->withoutOverlapping()->runInBackground();
        // 发放业绩奖励
        $schedule->command(\App\Modules\ModuleShop\Console\CommandAgentPerformanceReward::class)->monthlyOn(1, '1:00')->withoutOverlapping()->runInBackground();
		// 检测证书状态
        $schedule->command(\App\Modules\ModuleShop\Console\CommandCheckSslStatus::class)->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        // 处理拼团超时
        $schedule->command(\App\Modules\ModuleShop\Console\CommandGroupBuyingCloseForNoTime::class)->everyMinute()->withoutOverlapping()->runInBackground();
        //清理xlsxc错误数据文件
        $schedule->command(\App\Modules\ModuleShop\Console\CommandClearXlsx::class)->dailyAt('2:50');
        //清理上传图片后，格式错误，或xlsx无上传文件对应图片清理
        $schedule->command(\App\Modules\ModuleShop\Console\CommandClearTmpImg::class)->dailyAt('3:00');
		//供应商财务结算
        $schedule->command(\App\Modules\ModuleShop\Console\CommandSupplierOrderSettle::class)->dailyAt('3:30');
        /**
         * 测试用
         */
        // $schedule->command(\App\Modules\ModuleShop\Console\CommandTest::class)->everyMinute()->withoutOverlapping()->runInBackground();
        // $schedule->command(\App\Modules\ModuleShop\Console\CommandTest2::class)->everyFiveMinutes()->withoutOverlapping()->runInBackground();

        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
