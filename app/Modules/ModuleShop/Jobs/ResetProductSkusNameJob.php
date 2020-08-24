<?php

namespace App\Modules\ModuleShop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Modules\ModuleShop\Libs\Product\ProductSku;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class ResetProductSkusNameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    var $_siteId = 0;
    var $_productId = 0;
    var $_skuValueId = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($siteId,$productId,$skuValueId)
    {
        $this->_siteId = $siteId;
        $this->_productId = $productId;
        $this->_skuValueId = $skuValueId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Site::initSiteForCli($this->_siteId);
        $list = ProductSkusModel::where(['site_id' => $this->_siteId,'product_id' => $this->_productId])->where('sku_code','like','%,'.$this->_skuValueId.',%')->get();
        foreach ($list as $item) {
            ProductSku::refreshSkuRedundancyData($item->id);
        }
        Log::writeLog('ResetProductSkusNameJob', 'sku value id : '.$this->_skuValueId.' finished');
    }
}
