<?php

namespace App\Modules\ModuleShop\Jobs;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use YZ\Core\Site\Site;
use YZ\Core\Task\QueueTask;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueTask;

    var $siteId = 0;
    var $messageType = 0;
    var $param = [];
    var $wxSend = true;
    var $smsSend = true;

    /**
     * 发送通知
     * @param $messgeType
     * @param array $param
     * @param bool $wxSend
     * @param bool $smsSend
     */
    public function __construct($siteId, $messageType, array $param, $wxSend = true, $smsSend = true)
    {
        $this->siteId = $siteId;
        $this->messageType = $messageType;
        $this->param = $param;
        $this->wxSend = $wxSend;
        $this->smsSend = $smsSend;
    }

    /**
     * Execute
     */
    public function handle()
    {
        MessageNoticeHelper::sendMessageAct($this->siteId,$this->messageType, $this->param, $this->wxSend, $this->smsSend);
    }
}
