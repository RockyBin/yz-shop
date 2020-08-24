<?php

namespace App\Modules\ModuleShop\Libs\Live;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\LiveViewerModel;
use YZ\Core\Common\WebSocket;
use YZ\Core\Site\Site;

/**
 * 直播观众业务类
 * Class Viewer
 * @package App\Modules\ModuleShop\Libs\Live
 */
class Viewer
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
     * 增加观看人数
     * @param $clientId 客户端标识(websocket的标识)
     * @param $groupId 聊天组ID
     * @param $memberId 会员ID
     * @param $nickname 会员昵称
     * @param $headurl 会员头像
     * @throws \Exception
     */
    public function add($clientId, $groupId, $memberId, $nickname, $headurl)
    {
        $live = new Live($this->_liveId);
        $liveModel = $live->getModel();
        if (!$liveModel) {
            throw new \Exception('直播不存在');
        }
        if (!$headurl) $headurl = "images/default_head.png";
        $memberInfo = (new Member($memberId))->getModel();
        if ($memberInfo) {
            $memberInfo->headurl = Member::getHeadUrl($memberInfo->headurl);
        }
        $viewerInfo = [
            'live_id' => $this->_liveId,
            'client_id' => $clientId,
            'group_id' => $groupId,
            'member_id' => $memberId,
            'name' => $memberInfo ? $memberInfo->nickname : $nickname,
            'headurl' => $memberInfo ? $memberInfo->headurl : $headurl,
            'disconnect_notice_url' => getHttpProtocol() . '://' . getHttpHost() . '/shop/front/live/viewer/reduce'
        ];

        //更新直播在线人数

        $live->changeOnlineNum(1);

        //设置websocket扩展信息
        $ws = new WebSocket();
        $ws->setExtInfo($clientId, $viewerInfo);

        //发送增加了人数的信息
        $ws->sendToGroup($groupId, [
            'type' => 'VIEWERCHANGE',
            'add' => 1,
            'viewerInfo' => $viewerInfo
        ]);

        //保存入数据库
        if ($memberId) {
            $vModel = LiveViewerModel::query()->where(['live_id' => $this->_liveId, 'member_id' => $memberId, 'site_id' => getCurrentSiteId()])->first();
        } else {
            $vModel = LiveViewerModel::query()->where(['live_id' => $this->_liveId, 'client_id' => $clientId, 'site_id' => getCurrentSiteId()])->first();
        }
        if ($vModel) {
            $vModel->client_id = $clientId;
        } else {
            $vModel = new LiveViewerModel();
            $vModel->client_id = $clientId;
            $vModel->live_id = $this->_liveId;
            $vModel->site_id = getCurrentSiteId();
            $vModel->member_id = $memberId;
            $vModel->created_at = date('Y-m-d H:i:s');
        }
        $vModel->updated_at = date('Y-m-d H:i:s');
        $vModel->status = 1;
        $vModel->save();
    }

    /**
     * 减少观众人数
     * @param $viewerInfo websocket 返回的观众信息
     * @throws \Exception
     */
    public function reduce($viewerInfo)
    {
        //发送减少了人数的信息
        $ws = new WebSocket();
        $ws->sendToGroup($viewerInfo['group_id'], [
            'type' => 'VIEWERCHANGE',
            'reduce' => 1,
            'viewerInfo' => $viewerInfo
        ]);

        //更新直播在线人数
        $live = new Live($this->_liveId);
        $live->changeOnlineNum(-1);

        //保存入数据库
        $memberId = $viewerInfo['member_id'];
        $clientId = $viewerInfo['client_id'];
        //保存入数据库
        if ($memberId) {
            $vModel = LiveViewerModel::query()->where(['live_id' => $this->_liveId, 'member_id' => $memberId, 'site_id' => getCurrentSiteId()])->first();
        } else {
            $vModel = LiveViewerModel::query()->where(['live_id' => $this->_liveId, 'client_id' => $clientId, 'site_id' => getCurrentSiteId()])->first();
        }
        if ($vModel) {
            $vModel->client_id = $clientId;
        } else {
            $vModel = new LiveViewerModel();
            $vModel->client_id = $clientId;
            $vModel->live_id = $this->_liveId;
            $vModel->site_id = getCurrentSiteId();
            $vModel->member_id = $memberId;
            $vModel->created_at = date('Y-m-d H:i:s');
        }
        $vModel->leave_at = date('Y-m-d H:i:s');
        $vModel->status = 0;
        $vModel->save();
    }
}