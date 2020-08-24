<?php

namespace YZ\Core\VisitCount;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use YZ\Core\Member\Auth;
use YZ\Core\Model\CountClientMapModel;
use YZ\Core\Model\CountVisitLogModel;
use YZ\Core\Site\Site;

class VisitLog
{
    /**
     * 记录访问记录
     * @param $requestInfo 请求信息，大部分情况下是用 Request->all() 接收的数据
     */
    public static function log($requestInfo = array())
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        //确定 client_id
        $clientId = Request::cookie('ClientId');
        if (!$clientId) {
            $clientId = md5(mt_rand() . $siteId);
        }
        $memberId = Auth::hasLogin() ? Auth::hasLogin() : Request::cookie('member_id');
        //如果已经登录，以根据会员ID查找client_id
        if ($memberId) {
            $info = CountClientMapModel::where(['site_id' => $siteId, 'member_id' => $memberId])->first();
            if ($info) {
                $clientId = $info['client_id'];
            }
        }
        //更新client_id和会员的对照表
        $clientMap = CountClientMapModel::where(['site_id' => $siteId, 'client_id' => $clientId])->first();
        if ($clientMap) {
            if ($memberId) {
                $clientMap->member_id = $memberId;
                $clientMap->updated_at = date('Y-m-d H:i:s');
                $clientMap->save();
            }
        } else {
            $clientMap = new CountClientMapModel();
            $clientMap->site_id = $siteId;
            $clientMap->client_id = $clientId;
            if ($memberId) $clientMap->member_id = $memberId;
            $clientMap->save();
        }
        Cookie::queue('ClientId', $clientId, 0, '/', null, null, false, false); // 更新客户端的client_id
        //记录访问日志
        $log = new CountVisitLogModel();
        $log->site_id = $siteId;
        $log->client_id = $clientMap->id;
        $log->ip = getClientIP();
        $ipLocation = \Ipower\Common\Util::getIpLocation($log->ip);
        $log->country = $ipLocation['country_name'];
        $log->prov = $ipLocation['region_name'];
        $log->city = $ipLocation['city_name'];
        $log->channel = getCurrentTerminal();
        $log->referer = $requestInfo['referer'];
        $log->page = $requestInfo['page'];
        $log->save();
    }
}