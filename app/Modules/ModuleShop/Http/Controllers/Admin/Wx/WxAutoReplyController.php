<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Wx;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Model\WxReplyModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxAutoReply;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxNews;
use App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;

class WxAutoReplyController extends BaseAdminController
{
    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            // 图文信息
            $newsItemList = [];
            $newsItems = WxNews::getItemList([
                'show_all' => true
            ])['list'];
            foreach ($newsItems as $item) {
                $newsItemList[$item['id']] = $item;
            }
            // 主信息
            $param = $request->toArray();
            $wxAutoReply = new WxAutoReply();
            $data = $wxAutoReply->getList($param);
            foreach ($data['list'] as $item) {
                $this->parseItemData($item);
                // 处理图文信息
                $paramData = $item->reply_data;
                if ($item->type == Constants::Weixin_Menu_Rich && $paramData['news_item_id']) {
                    $newsItemModel = WxNews::getItem($paramData['news_item_id']);
                    if ($newsItemModel) {
                        $paramData['news_item_image'] = $newsItemModel->image;
                        $paramData['news_item_title'] = $newsItemModel->title;
                        $item->reply_data = $paramData;
                    }
                }
            }
            $data['config_full'] = WxConfig::checkConfig();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->id;
            $wxAutoReply = new WxAutoReply($id);
            if (!$wxAutoReply->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $wxAutoReply->getModel();
            $this->parseItemData($model);
            // 处理图文信息
            $paramData = $model->reply_data;
            if ($model->reply_type == Constants::Weixin_AutoReplyType_Rich && $paramData['news_item_id']) {
                $newsItemModel = WxNews::getItem($paramData['news_item_id']);
                if ($newsItemModel) {
                    $paramData['news_item_image'] = $newsItemModel->image;
                    $paramData['news_item_title'] = $newsItemModel->title;
                    $model->reply_data = $paramData;
                }
            }elseif ($model->reply_type==Constants::Weixin_AutoReplyType_Callback && $model->data_extra){
               $data= json_decode($model->data_extra,true);
               $paper_id=$data[0]['paper_id'];
               if ($paper_id){
                   $preview_image=(new Paper($paper_id))->getModel()->preview_image;
                   $model->paper_image=$preview_image;
                   $model->paper_id=$paper_id;
               }

            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $id = $request->id;
            $type = intval($request->type);
            $replyType = intval($request->reply_type);
            $name = trim($request->name);
            $wxAutoReply = null;
            if ($id) {
                // 有传id，代表修改
                $wxAutoReply = new WxAutoReply($id);
                if (!$wxAutoReply->checkExist()) {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
                $type = intval($wxAutoReply->getModel()->type);
            } else {
                $wxAutoReply = new WxAutoReply();
                // 关注自动回复 和 消息不匹配回复 只允许一条
                if (in_array($type, [Constants::Weixin_AutoReply_Subscribe, Constants::Weixin_AutoReply_Notmatch])) {
                    $total = intval($wxAutoReply->getList(['type' => $type])['total']);
                    if ($total > 0) {
                        return makeApiResponseFail(trans('shop-admin.wx.data_only_one'));
                    }
                }
                $wxAutoReply->setType($type);
                $wxAutoReply->setCreatedAt(date('Y-m-d H:i:s'));
                $wxAutoReply->setSiteId(Site::getCurrentSite()->getSiteId());
            }
            // 保存关键词
            if ($type == Constants::Weixin_AutoReply_Keyword) {
                $keywords = $request->keyword;
                if (!is_array($keywords)) $keywords = [];
                if (count($keywords) == 0) {
                    return makeApiResponseFail('关键词不能为空');
                }
                $checkRes = wxAutoReply::checkKeyword($keywords, $id);
                if ($checkRes['code'] != 200) return $checkRes;
                $wxAutoReply->setKeyword($keywords);
            } else {
                $wxAutoReply->setKeyword([]);
            }
            // 保存回复信息
            switch ($replyType) {
                case Constants::Weixin_AutoReplyType_Image:
                    $image = trim($request->image);
                    if (empty($image)) {
                        return makeApiResponseFail(trans('shop-admin.common.data_error'));
                    }
                    $wxAutoReply->setReplyImage($image);
                    break;
                case Constants::Weixin_AutoReplyType_Rich:
                    $newsItemId = trim($request->news_item_id);
                    if ($newsItemId) {
                        $wxAutoReply->setReplyRichItem($newsItemId);
                    } else {
                        $newsId = trim($request->news_id);
                        $wxNews = new WxNews($newsId);
                        if (!$wxNews->checkExist()) {
                            return makeApiResponseFail(trans('shop-admin.common.data_error'));
                        }
                        $wxAutoReply->setReplyRich($newsId);
                    }
                    break;
                case Constants::Weixin_Menu_Callback:
                    // 自定义回复 获取海报
                    if ($request->callback_type == Constants::Weixin_Callback_Poster) {
                        $paper_id=$request->paper_id;
                        $param=[['paper_id'=>$paper_id]];
                        $callback = '\App\Modules\ModuleShop\Libs\SharePaper\Mobi\WeixinMessageHelper@sendWeixinPaperImage';
                        $wxAutoReply->setReplyCallback($callback, Constants::Weixin_Callback_Poster,$param);
                    } else {
                        return makeApiResponseFail('自定义回复类型错误');
                    }
                    break;
                default:
                    $content = trim($request->input('content'));
                    if (empty($content)) {
                        return makeApiResponseFail(trans('shop-admin.common.data_error'));
                    }
                    $wxAutoReply->setReplyText($content);
            }
            // 保存
            $wxAutoReply->setUpdatedAt(date('Y-m-d H:i:s'));
            $wxAutoReply->setName($name);
            $wxAutoReply->save();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 检测关键词（供于海报以及公众号的自动回复）
     * @param $keywords
     * @param $id 如果有ID代表要修改，不用检测自身
     * @return boolean
     */
    public function checkKeyword(Request $request)
    {
        $keywords = $request->keyword;
        $id = $request->id ? $request->id : 0;
        if (!is_array($keywords)) $keywords = [];
            if (count($keywords) == 0) {
                return makeApiResponseFail('关键词不能为空');
            }
        return wxAutoReply::checkKeyword($keywords, $id);
    }

    /**
     * 删除
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxAutoReply = new WxAutoReply($id);
            if (!$wxAutoReply->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxAutoReply->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 处理数据
     * @param $item
     */
    private function parseItemData($item)
    {
        if ($item) {
            $item->data = json_decode($item->data, true);
            $item->reply_data = json_decode($item->reply_data, true);
        }
    }
}