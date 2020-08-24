<?php

namespace YZ\Core\Weixin;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use EasyWeChat\Kernel\Messages\Message;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Events\Event;
use YZ\Core\Model\WxReplyModel;

class WxAutoReply
{
    private $_model = null;

    /**
     * 初始化自动回复对象
     * WxAutoReply constructor.
     * @param $idOrModel 自动回复的 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            if (intval($idOrModel) > 0) {
                $this->_model = WxReplyModel::query()
                    ->where('id', $idOrModel)
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->first();
            }
        } else $this->_model = $idOrModel;
        if (!$this->_model) $this->_model = new WxReplyModel();
    }

    /**
     * 返回数据库记录模型
     * @return null|WxReplyModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * @param $siteId
     */
    public function setSiteId($siteId)
    {
        $this->_model->site_id = $siteId;
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) return true;
        return false;
    }

    /**
     * 删除
     * @throws \Exception
     */
    public function delete()
    {
        if ($this->checkExist()) {
            $this->_model->delete();
            $this->_model = null;
        }
    }

    /**
     * 保存回复设置
     */
    public function save()
    {
        if (!$this->_model->site_id) {
            $this->setSiteId(Site::getCurrentSite()->getSiteId());
        }
        $this->_model->save();
    }

    /**
     * 获取列表
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = WxReplyModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        $query->addSelect('*');
        //查询某个ID是否有海报
        $query->addSelect(\DB::Raw('(select sp.id from tbl_share_paper as sp where sp.keyword_id=tbl_wx_reply.id) as paper_id'));
        // 类型
        if (is_numeric($param['type'])) {
            $query->where('type', intval($param['type']));
        }
        // 回复类型
        if (is_numeric($param['reply_type']) && intval($param['reply_type']) >= 0) {
            $query->where('reply_type', intval($param['reply_type']));
        }
        // 关键字
        if (trim($param['keyword'])) {
            $query->where('data', 'like', '%' . trim($param['keyword']) . '%');
        }
        // 更新时间开始
        if (trim($param['updated_at_start'])) {
            $query->where('updated_at', '>=', trim($param['updated_at_start']));
        }
        // 更新时间结束
        if (trim($param['updated_at_end'])) {
            $query->where('updated_at', '<=', trim($param['updated_at_end']));
        }

        // 总数据量
        $total = $query->count();
        $last_page = ceil($total / $pageSize); // 总页数
        $query->forPage($page, $pageSize)->orderBy('id', 'desc');
        $list = $query->get();

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 根据类型查找自动回复对象
     * @param $siteId
     * @param $type 自动回复类型
     * @param string $data 与类型有关的相关数据，目前只有关键词回复才需要传此变量
     * @return null|WxAutoReply
     */
    public static function findByType($siteId, $type, $data = '')
    {
        $reply = null;
        $model = null;
        $query = WxReplyModel::query()->where('type', intval($type))->where('site_id', $siteId);
        if ($type == Constants::Weixin_AutoReply_Keyword) {
            $list = $query->orderBy('id', 'asc')->get();
            // 多个关键字，循环匹配
            $isMatch = false;
            foreach ($list as $item) {
                $dataItem = json_decode($item->data, true);
                if (is_array($dataItem['keyword'])) {
                    // 多个规则，循环匹配
                    foreach ($dataItem['keyword'] as $keyData) {
                        $value = trim($keyData['value']);
                        if (empty($value)) continue;
                        if (intval($keyData['type']) == 0) {
                            $pattern = '`^' . $value . '$`';
                        } else {
                            $pattern = '`' . $value . '`';
                        }
                        if (preg_match($pattern, $data)) {
                            $model = $item;
                            $isMatch = true;
                            break;
                        }
                    }
                }
                if ($isMatch) break;
            }
        } else {
            $model = $query->orderBy('id', 'asc')->first();
        }
        if ($model) $reply = new WxAutoReply($model);
        return $reply;
    }

    /**
     * 返回回复的具体内容
     * @return \EasyWeChat\Kernel\Messages\Image|\EasyWeChat\Kernel\Messages\News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     */
    public function getReply($message)
    {
        try {
            $res = '';
            if ($this->checkExist()) {
                $reply_data = json_decode($this->_model->reply_data, true);
                switch ($this->_model->reply_type) {
                    case Constants::Weixin_AutoReplyType_Text:
                        $res = $this->parseContent($reply_data['content']);
                        break;
                    case Constants::Weixin_AutoReplyType_Rich:
                        if ($reply_data['news_item_id']) {
                            $res = MessageHelper::makeArticlesMessageByNewsItem($reply_data['news_item_id']);
                        } else if ($reply_data['news_id']) {
                            $res = MessageHelper::makeArticlesMessageByNews($reply_data['news_id']);
                        }
                        break;
                    case Constants::Weixin_AutoReplyType_Image:
                        $res = MessageHelper::makeImageMessage($reply_data['image']);
                        break;
                    case Constants::Weixin_AutoReplyType_Callback:
                        $args = json_decode($this->_model->data_extra, true);
                        if (is_array($args)) $res = Event::fireEvent(static::class . '@getReply', $reply_data['callback'],$message, ...$args);
                        else $res = Event::fireEvent(static::class . '@getReply', $reply_data['callback'],$message, $this->_model->data_extra);
                        break;
                    default:
                        break;
                }
            }
            return $res;
        } catch (\Exception $e) {
            Log::writeLog('paperError',$e->getMessage());
            throw $e;
        }

    }

    /**
     * 规格名称
     * @param $name
     */
    public function setName($name)
    {
        $this->_model->name = $name;
    }

    /**
     * 设置自动回复的类型，类型的值请参考常量 Constants::Weixin_AutoReply
     * @param $type
     */
    public function setType($type)
    {
        $this->_model->type = $type;
    }

    /**
     * 创建时间
     * @param $createTime
     */
    public function setCreatedAt($createTime)
    {
        $this->_model->created_at = $createTime;
    }

    /**
     * 更新时间
     * @param $updateTime
     */
    public function setUpdatedAt($updateTime)
    {
        $this->_model->updated_at = $updateTime;
    }

    /**
     * 设置关键词
     * @param array $keywords
     */
    public function setKeyword(array $keywords)
    {
        $data = json_decode($this->_model->data, true);
        $data['keyword'] = []; // 清空原有数据
        foreach ($keywords as $keyword) {
            $data['keyword'][] = [
                'type' => intval($keyword['type']),
                'value' => trim($keyword['value'])
            ];
        }
        $this->_model->data = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 设置回复数据类型为文本类型
     * @param $text 回复的文本内容
     */
    public function setReplyText($text)
    {
        $this->_model->reply_type = Constants::Weixin_AutoReplyType_Text;
        $this->_model->reply_data = json_encode([
            'content' => $text
        ]);
    }

    /**
     * 设置回复数据类型为图片类型
     * @param $imageUrl 图片的路径
     */
    public function setReplyImage($imageUrl)
    {
        $this->_model->reply_type = Constants::Weixin_AutoReplyType_Image;
        $this->_model->reply_data = json_encode([
            'image' => $imageUrl
        ]);
    }

    /**
     * 设置回复数据类型为图文类型
     * @param $newsId
     */
    public function setReplyRich($newsId)
    {
        $this->_model->reply_type = Constants::Weixin_AutoReplyType_Rich;
        $this->_model->reply_data = json_encode([
            'news_id' => $newsId
        ]);
    }

    /**
     * 设置回复数据类型为图文类型
     * @param $newsItemId
     */
    public function setReplyRichItem($newsItemId)
    {
        $this->_model->reply_type = Constants::Weixin_AutoReplyType_Rich;
        $this->_model->reply_data = json_encode([
            'news_item_id' => $newsItemId
        ]);
    }

    /**
     * 设置回复数据类型为自定义回调类型
     * @param string $callback 回调的类名和方法名
     * @param number $type     自定义回复类型 1为分享海报
     * @param string $args 传递给回调类的参数,可以是字符串或数组
     */
    public function setReplyCallback($callback, $type, $args = '')
    {
        $this->_model->reply_type = Constants::Weixin_AutoReplyType_Callback;
        $this->_model->callback_type = $type;
        $this->_model->reply_data = json_encode([
            'callback' => $callback
        ]);
        if ($args) {
            if (is_array($args)) $this->_model->data_extra = json_encode($args);
            else $this->_model->data_extra = $args;
        }
    }

    /**
     * 处理文本
     * @param $content
     * @return mixed
     */
    private function parseContent($content)
    {
        return WxReplyContentHandle::parseContent($content);
    }

    /**
     * 检测关键词是否重复
     * @param $keywords
     * @param $id 如果有ID代表要修改，不用检测自身
     * @return boolean
     */
    public static function checkKeyword($keywords,$id=0){
        if (!is_array($keywords)) $keywords = [];
        if (count($keywords) == 0) {
            return  makeServiceResult('200','ok');//因为用户并没有填写关键词，所以不需再次检测
        }
        $keywordValues = [];
        // 读取已有的关键词
        $replyListQuery = WxReplyModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('type', Constants::Weixin_AutoReply_Keyword);
        if ($id) {
            $replyListQuery->where('id', '!=', $id);
        }
        $replyList = $replyListQuery->select('data')->get();
        if ($replyList) {
            foreach ($replyList as $replyItem) {
                if (!$replyItem->data) continue;
                $replyItemData = json_decode($replyItem->data, true);
                if (is_array($replyItemData) && is_array($replyItemData['keyword'])) {
                    foreach ($replyItemData['keyword'] as $replyItemDataKeyword) {
                        if ($replyItemDataKeyword['value']) {
                            $keywordValues[] = trim($replyItemDataKeyword['value']);
                        }
                    }
                }
            }
        }
        // 检查关键词是否有重复或空
        foreach ($keywords as $keyword) {
            $value = trim($keyword['value']);
            if (!$value) {
               return  makeServiceResult('404','关键词不能为空');
                return makeApiResponseFail('关键词不能为空');
            }
            if (in_array($value, $keywordValues)) {
                return  makeServiceResult('401','已存在相同关键词'.$value);
                return makeApiResponseFail('已存在相同关键词：' . $value);
            } else {
                $keywordValues[] = $value;
            }
        }
         return  makeServiceResult('200','ok');
    }
}