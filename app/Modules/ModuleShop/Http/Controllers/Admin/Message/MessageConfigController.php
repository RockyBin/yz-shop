<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Message;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Sms\SmsTemplateMessage;
use YZ\Core\Sms\SmsTemplateTpl;
use YZ\Core\Weixin\WxTemplateMessage;
use YZ\Core\Weixin\WxTemplateTpl;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

/**
 * 消息管理
 * Class MessageConfigController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Message
 */
class MessageConfigController extends BaseAdminController
{
    /**
     * 获取所有配置
     * @return array
     */
    public function getList()
    {
        try {
            $maxType = Constants::MessageType_Max_Num;
            $data = [
                'list' => []
            ];
            for ($i = 1; $i <= $maxType; $i++) {
                $data['list'][$i] = [
                    'wx' => [
                        'status' => 1
                    ],
                    'sms' => [
                        'status' => 0
                    ]
                ];
            }

            $wxMessageList = WxTemplateMessage::getList();
            foreach ($wxMessageList as $wxMessageItem) {
                $data['list'][intval($wxMessageItem->type)]['wx'] = $wxMessageItem->toArray();
            }
            $smsMessageList = SmsTemplateMessage::getList();
            foreach ($smsMessageList as $smsMessageItem) {
                $data['list'][intval($smsMessageItem->type)]['sms'] = $smsMessageItem->toArray();
            }
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 配置信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $type = intval($request->type);
            $data = [];
            if ($type > 0) {
                if (array_key_exists($type, WxTemplateTpl::TemplateConfig)) {
                    $wxMessage = new WxTemplateMessage($type);
                    $wxModel = $wxMessage->getModel();
                    if ($wxModel) {
                        $data['wx'] = $wxModel;
                    }
                }
                if (array_key_exists($type, SmsTemplateTpl::TemplateConfig)) {
                    $smsMessage = new SmsTemplateMessage($type);
                    $smsModel = $smsMessage->getModel();
                    if ($smsModel) {
                        $data['sms'] = $smsModel;
                    }
                }
            }
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public
    function save(Request $request)
    {
        try {
            $type = intval($request->type);
            if ($type > 0) {
                // 保存微信设置
                $wxStatus = $request->wx_status ? 1 : 0;
                if (array_key_exists($type, WxTemplateTpl::TemplateConfig)) {
                    $wxMessage = new WxTemplateMessage($type);
                    $wxMessage->save([
                        'status' => $wxStatus
                    ]);
                }
                // 保存短信设置
                $smsStatus = $request->sms_status ? 1 : 0;
                if (array_key_exists($type, SmsTemplateTpl::TemplateConfig)) {
                    $smsTemplateMessage = new SmsTemplateMessage($type);
                    $smsTemplateMessage->save([
                        'status' => $smsStatus
                    ]);
                }
            }

            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}