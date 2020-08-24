<?php
namespace App\Modules\ModuleShop\Libs\Live;

use App\Modules\ModuleShop\Libs\Model\LiveChatModel;

/**
 * 直播聊天记录业务类
 * Class Viewer
 * @package App\Modules\ModuleShop\Libs\Live
 */
class Chat
{
    /**
     * @var 直播ID|int
     */
    private $_liveId = 0;

    /**
     * Viewer constructor.
     * @param $liveId 直播ID
     */
    public function __construct($liveId)
    {
        $this->_liveId = $liveId;
    }

    /**
     * 增加聊天记录
     * @param $clientId 客户端标识(websocket的标识)
     * @param $memberId 会员ID
     * @param $nickname 会员昵称
     * @param $headurl 会员头像
     * @param $content 聊天内容，纯文本或数组
     * @throws \Exception
     */
    public function add($clientId, $memberId, $nickname, $headurl, $content,$link = ""){
        //保存入数据库
        $vModel = new LiveChatModel();
        $vModel->client_id = $clientId;
        $vModel->live_id = $this->_liveId;
        $vModel->site_id = getCurrentSiteId();
        $vModel->member_id = $memberId;
        $vModel->nickname = $nickname;
        $vModel->headurl = $headurl;
        $vModel->created_at = date('Y-m-d H:i:s');
        $vModel->content = is_array($content) ? json_encode($content,JSON_UNESCAPED_UNICODE) : $content;
		$vModel->link = $link;
        $vModel->save();
    }
}