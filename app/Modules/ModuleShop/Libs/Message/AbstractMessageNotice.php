<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Message;


use App\Modules\ModuleShop\Jobs\SendMessageJob;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;
use YZ\Core\Common\ShortUrl;
use YZ\Core\Logger\Log;
use YZ\Core\Model\WxTemplateModel;
use YZ\Core\Site\Site;
use YZ\Core\Sms\SmsTemplateMessage;
use YZ\Core\Task\TaskHelper;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxTemplateMessage;
use EasyWeChat\Factory;

abstract class AbstractMessageNotice
{

    /**
     * 重置模板id
     * @param bool $reset 是否完全重置，false=只初始化没有生成id的，true=完全初始化所有数据
     * @param bool $clearNotUsed 是否清理微信端没有用到的template_id
     * @return bool
     */
    protected static function resetWxTemplateId($reset = false, $clearNotUsed = false)
    {
        $wxConfig = new WxConfig();
        if (!$wxConfig->infoIsFull()) return false;
        $options = [
            'app_id' => $wxConfig->getModel()->appid,
            'secret' => $wxConfig->getModel()->appsecret,
        ];
        // 获取微信端有哪些 template_id
        $app = Factory::officialAccount($options);
        $remoteTemplateList = $app->template_message->getPrivateTemplates();
        $remoteTemplateIdList = [];
        foreach ($remoteTemplateList['template_list'] as $remoteTemplateItem) {
            $remoteTemplateIdList[] = $remoteTemplateItem['template_id'];
        }
        if ($reset) {
            // 把所有 template_id 置空
            WxTemplateModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->update(['template_id' => null]);
        }
        // 把存在于 DB 但不存在于微信端的 template_id 清空
        if (count($remoteTemplateIdList)) {
            WxTemplateModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->whereNotNull('template_id')
                ->where('template_id', '!=', '')
                ->whereNotIn('template_id', $remoteTemplateIdList)
                ->update(['template_id' => null]);
        }
        // 查找DB里的 template_id
        $dbWxTemplateIdList = [];
        $dbWxTemplateList = WxTemplateModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->get();
        foreach ($dbWxTemplateList as $dbWxTemplateItem) {
            if ($dbWxTemplateItem->template_id) {
                $dbWxTemplateIdList[] = $dbWxTemplateItem->template_id;
            }
        }
        if ($clearNotUsed) {
            // 清理微信端没有用到的 template_id
            foreach ($remoteTemplateIdList as $remoteTemplateId) {
                if (count($dbWxTemplateIdList) == 0 || !in_array($remoteTemplateId, $dbWxTemplateIdList)) {
                    $app->template_message->deletePrivateTemplate($remoteTemplateId);
                }
            }
        }
        // 处理相同的 short_id
        if (count($dbWxTemplateIdList) > 0) {
            foreach ($dbWxTemplateIdList as $dbWxTemplateId) {
                $lastData = WxTemplateModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('template_id', $dbWxTemplateId)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->first();
                if ($lastData && $lastData->short_id) {
                    WxTemplateModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('short_id', $lastData->short_id)
                        ->update([
                            'template_id' => $dbWxTemplateId,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            }
        }
        // 再处理没有 template_id 的
        $shortIdList = WxTemplateModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('template_id')->orWhere('template_id', '=', '');
            })->select('short_id')
            ->distinct()
            ->get()->pluck('short_id');
        if (count($shortIdList) > 0) {
            // 新建 template_id
            foreach ($shortIdList as $shortId) {
                if (!$shortId) continue;
                $res = $app->template_message->addTemplate($shortId);
                if (intval($res['errcode']) == 0) {
                    $newTemplateId = $res['template_id'];
                    WxTemplateModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('short_id', $shortId)
                        ->update([
                            'template_id' => $newTemplateId,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            }
        }

        return true;
    }

    /**
     * 发送通知
     * @param $messgeType
     * @param array $param
     * @param bool $wxSend
     * @param bool $smsSend
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessage($messageType, array $param, $wxSend = true, $smsSend = true)
    {
        TaskHelper::addTask(new SendMessageJob(getCurrentSiteId(),$messageType, $param, $wxSend, $smsSend));
    }

    /**
     * 发送通知
     * @param $siteId
     * @param $messageType
     * @param array $param
     * @param bool $wxSend
     * @param bool $smsSend
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAct($siteId, $messageType, array $param, $wxSend = true, $smsSend = true)
    {
        $messageType = intval($messageType);
        if ($messageType <= 0) return;
        $url = Self::parseUrl($param['url'],$siteId);
        // 发送微信模板消息
        if ($wxSend) {
            $openId = $param['openId'];
            if ($openId) {
                $wxMessage = new WxTemplateMessage($messageType,$siteId);
                $wxMessage->send($openId, $param, $url);
            }
        }
        // 发送短信模板消息
        if ($smsSend) {
            $mobile = $param['mobile'];
            if ($mobile) {
                $smsMessage = new SmsTemplateMessage($messageType,$siteId);
                if ($url) {
                    // 转换成短地址
                    $url = ShortUrl::getShortUrl($url);
                }
                $smsMessage->send($mobile, $param, $url);
            }
        }
    }

    /**
     * 返回商城名称
     * @return string
     */
    protected static function getShopName()
    {
        $shopConfig = new ShopConfig();
        $shopConfigModel = $shopConfig->getInfo()['info'];
        return $shopConfigModel ? trim($shopConfigModel->name) : '';
    }

    /**
     * 返回商家手机
     * @return mixed|string
     */
    protected static function getBusinessMobile()
    {
        $smsConfigModel = Site::getCurrentSite()->getConfig()->getSmsConfig();
        if ($smsConfigModel) return $smsConfigModel->mobile;
        return '';
    }

    /**
     * 获取会员手机号码
     * @param $memberId
     * @return string
     */
    protected static function getMemberMobile($memberId)
    {
        if ($memberId) {
            $member = new Member($memberId);
            return $member->getMobile();
        }
        return '';
    }

    /**
     * 获取会员openId
     * @param $memberId
     * @return mixed|string
     */
    protected static function getMemberWxOpenId($memberId)
    {
        if ($memberId) {
            $member = new Member($memberId);
            return $member->getWxOpenId();
        }
        return '';
    }

    /**
     * 处理url
     * @param $url
     * @return string
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected static function parseUrl($url,$siteId = 0)
    {
        if ($url) {
            $host = self::getHost($siteId);
            if ($host) {
                return $host . $url;
            }
        }
        return '';
    }

    /**
     * 获取host，http://example.com
     * @return string
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    protected static function getHost($siteId = 0)
    {
        $protocol = 'http';
        // 先从公众号里获取域名
        $wxConfig = new WxConfig($siteId);
        $domain = $wxConfig->getDomain();
        if (!isInCli()) {
          // 先全部默认使用HTTP，因为小程序访问规定要使用HTTPS，网站如果没有SSL证书则会跳转链接失败。
          //  $protocol = getHttpProtocol();
            if (!$domain) {
                $domain = Request::getHost();
            }
        }
        if (!$domain) {
            // TODO 获取第一个非赠送的域名
            $domainList = (new Site($siteId))->getUserDomain();
            if ($domainList) {
                $domain = $domainList[0];
            }
        }
        if ($domain) {
            return $protocol . '://' . $domain;
        } else {
            return '';
        }
    }
}