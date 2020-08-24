<?php

namespace YZ\Core\Weixin;

use YZ\Core\Constants;
use YZ\Core\Site\Site;
use YZ\Core\Events\Event;
use EasyWeChat\Kernel\Messages\Media;
use YZ\Core\Model\WxNewsModel;
use YZ\Core\Model\WxNewsItemModel;

/**
 * Class WxNews 微信图文消息业务类
 * @package YZ\Core\Weixin
 */
class WxNews
{
    private $_model = null;
    private $_items = [];

    /**
     * 初始化对象
     * WxNews constructor.
     * @param int $idOrModel
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            if (intval($idOrModel) > 0) {
                $this->_model = WxNewsModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('id', $idOrModel)
                    ->first();
                if ($this->checkExist()) {
                    $this->_items = $this->_model->items()->get();
                }
            }
        } else $this->_model = $idOrModel;
        if (!$this->_model) $this->_model = new WxNewsModel();
    }

    /**
     * 返回数据库记录模型
     * @return null|WxNewsModel
     */
    public function getModel()
    {
        return $this->_model;
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
     * 设置菜单所属站点
     * @param $siteId
     */
    public function setSiteId($siteId)
    {
        $this->_model->site_id = $siteId;
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
     * 添加图文
     * @param array $itemInfo
     */
    public function addItem(array $itemInfo)
    {
        if ($this->checkExist()) {
            $itemInfo['site_id'] = $this->_model->site_id;
            $itemInfo['news_id'] = $this->_model->id;
            $itemInfo['created_at'] = date('Y-m-d H:i:s');
            $itemInfo['updated_at'] = date('Y-m-d H:i:s');
            $item = new WxNewsItemModel();
            $item->fill($itemInfo);
            $item->save();
        }
    }

    /**
     * 修改图文
     * @param $itemId
     * @param array $itemInfo
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function editItem($itemId, array $itemInfo)
    {
        $itemModel = $this->findItem($itemId);
        if ($itemModel) {
            unset($itemInfo['site_id']);
            unset($itemInfo['news_id']);
            unset($itemInfo['created_at']);
            unset($itemInfo['comment_open']);
            unset($itemInfo['comment_only_fans']);
            $newImage = $itemInfo['image'];
            if ($itemModel->image && $newImage != $itemModel->image) {
                if ($itemModel->image_media_id) {
                    // 清理旧图片素材
                    WxNewsHelper::deleteMaterialMedia($itemModel->image_media_id);
                }
                // 更换图片，需要清理就数据，放push方法自动重新生成图片素材
                $itemInfo['image_media_id'] = null;
            }
            $itemInfo['updated_at'] = date('Y-m-d H:i:s');
            $itemModel->fill($itemInfo);
            $itemModel->save();
        }
    }

    /**
     * 删除整个素材
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \Exception
     */
    public function delete()
    {
        if ($this->checkExist()) {
            if (WxConfig::checkConfig(true)) {
                foreach ($this->_items as $item) {
                    // 清理图片素材
                    if ($item->image_media_id) {
                        WxNewsHelper::deleteMaterialMedia($item->image_media_id);
                    }
                }
                // 清理图文素材
                if ($this->_model->media_id) {
                    WxNewsHelper::deleteMaterialMedia($this->_model->media_id);
                }
            }
            $this->_model->delete();
        }
    }

    /**
     * 保存回复设置
     */
    public function save()
    {
        if (!$this->_model->site_id) {
            $site = Site::getCurrentSite();
            $this->setSiteId($site->getSiteId());
        }
        $this->_model->save();
    }

    /**
     * 将图文推送到公众号
     */
    public function push()
    {
        if ($this->checkExist()) {
            return MessageHelper::pushNews($this->getModel());
        }
    }

    /**
     * 发送图文到单个粉丝
     * @param $openid
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public function sendToUser($openid)
    {
        $message = MessageHelper::makeArticlesMessageByNews($this->_model, 1);
        Site::getCurrentSite()->getOfficialAccount()->sendMessage($openid, $message);
    }

    /**
     * 列表
     * @param $param
     * @return array
     */
    public static function getList($param = [])
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = WxNewsModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if (trim($param['keyword'])) {
            $query->where('title', 'like', '%' . trim($param['keyword']) . '%');
        }
        // 图文数量
        if (is_numeric($param['item_total'])) {
            $query->where('item_total', intval($param['item_total']));
        }
        // 是否已经推送到公众号
        if ($param['is_push']) {
            $query->whereNotNull('media_id')->where('media_id', '!=', '');
        }

        // 总数据量
        $total = $query->count();
        // 如果现实全部
        if ($param['show_all'] && $total > 0) {
            $pageSize = $total;
        }
        $last_page = ceil($total / $pageSize); // 总页数
        $query->forPage($page, $pageSize)->orderBy('id', 'desc');
        $list = $query->get();
        foreach ($list as $item) {
            $item->items = $item->items()->get();
            $item->items_num = count($item->items);
        }

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 图文列表
     * @param $param
     * @return array
     */
    public static function getItemList($param = [])
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = WxNewsItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if (trim($param['keyword'])) {
            $query->where('title', 'like', '%' . trim($param['keyword']) . '%');
        }
        // 是否已经推送到公众号
        if ($param['is_push']) {
            $query->whereNotNull('image_media_id')->where('image_media_id', '!=', '');
        }

        // 总数据量
        $total = $query->count();
        // 如果现实全部
        if ($param['show_all'] && $total > 0) {
            $pageSize = $total;
        }
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
     * 通过子图文id查找资图文信息
     * @param $newsItemId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public static function getItem($newsItemId)
    {
        if (!$newsItemId) return null;
        return WxNewsItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $newsItemId)
            ->first();
    }

    /**
     * 查找图文项
     * @param $itemId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function findItem($itemId)
    {
        if ($this->checkExist() && $itemId) {
            return WxNewsItemModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('news_id', $this->getModel()->id)
                ->where('id', intval($itemId))
                ->first();
        }

        return null;
    }
}