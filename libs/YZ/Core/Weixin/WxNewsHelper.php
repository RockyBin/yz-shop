<?php

namespace YZ\Core\Weixin;

use YZ\Core\Site\Site;

/**
 * Class WxNewsHelper 微信图文消息静态工具类
 * @package YZ\Core\Weixin
 */
class WxNewsHelper
{
    /**
     * 群发图文到全部粉丝
     * @param $mediaId
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public static function sendToAll($mediaId)
    {
        $res = Site::getCurrentSite()->getOfficialAccount()->getBroadcastObj()->sendNews($mediaId);
        if ($res['errcode'] > 0) throw new \Exception('send broadcast news error: ' . $res['errmsg']);
    }

    /**
     * 群发图文到指定的粉丝(必须两个以上)
     * @param $mediaId
     * @param array $openids
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public static function sendToUsers($mediaId, array $openids)
    {
        $res = Site::getCurrentSite()->getOfficialAccount()->getBroadcastObj()->sendNews($mediaId, $openids);
        if ($res['errcode'] > 0) throw new \Exception('send broadcast news error: ' . $res['errmsg']);
    }

    /**
     * 群发图文到带有指定标签的粉丝
     * @param $mediaId
     * @param int $tagId
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public static function sendToTag($mediaId, int $tagId)
    {
        $res = Site::getCurrentSite()->getOfficialAccount()->getBroadcastObj()->sendNews($mediaId, $tagId);
        if ($res['errcode'] > 0) throw new \Exception('send broadcast news error: ' . $res['errmsg']);
    }

    /**
     * 删除永久素材
     * @param $mediaId
     * @return bool
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function deleteMaterialMedia($mediaId)
    {
        $res = Site::getCurrentSite()->getOfficialAccount()->getMaterialObj()->delete($mediaId);
        if (intval($res['errcode']) > 0) {
            return false;
        } else {
            return true;
        }
    }

}