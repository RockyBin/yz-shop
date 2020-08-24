<?php

namespace YZ\Core\Weixin;

use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Events\Event;
use YZ\Core\Model\WxMenuModel;
use  App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;

class WxMenu
{
    private $_model = null;

    /**
     * 初始化菜单对象
     * WxMenu constructor.
     * @param int $idOrModel
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            if (intval($idOrModel) > 0) {
                $this->_model = WxMenuModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('id', $idOrModel)
                    ->first();
            }
        } else $this->_model = $idOrModel;
        if (!$this->_model) $this->_model = new WxMenuModel();
    }

    /**
     * 返回数据库记录模型
     * @return null|WxMenuModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 检查是否存在
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
     * 设置菜单名称
     * @param $name
     */
    public function setName($name)
    {
        $this->_model->name = $name;
    }

    /**
     * 设置菜单排序顺序
     * @param $order
     */
    public function setShowOrder($order)
    {
        $this->_model->show_order = $order;
    }

    /**
     * 设置父菜单
     * @param $parentId
     */
    public function setParent($parentId)
    {
        $this->_model->parent_id = $parentId;
    }

    /**
     * 返回回复的具体内容
     * @return \EasyWeChat\Kernel\Messages\Image|\EasyWeChat\Kernel\Messages\News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     */
    public function getReply($message)
    {
        $res = '';
        $data = json_decode($this->_model->data, true);
        switch ($this->_model->type) {
            case Constants::Weixin_Menu_Text:
                $res = WxReplyContentHandle::parseContent($data['content']);
                break;
            case Constants::Weixin_Menu_Rich:
                if ($data['news_item_id']) {
                    $res = MessageHelper::makeArticlesMessageByNewsItem($data['news_item_id']);
                } else if ($data['news_id']) {
                    $res = MessageHelper::makeArticlesMessageByNews($data['news_id']);
                }
                break;
            case Constants::Weixin_Menu_Image:
                $res = MessageHelper::makeImageMessage($data['image']);
                break;
            case Constants::Weixin_Menu_Callback:
                $args = json_decode($this->_model->data_extra, true);
                if (is_array($args)) $res = Event::fireEvent(static::class . '@getReply', $data['callback'],$message, ...$args);
                else $res = Event::fireEvent(static::class . '@getReply', $data['callback'],$message, $this->_model->data_extra);
                break;
            default:
                break;
        }
        return $res;
    }

    /**
     * 保存设置
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
     * 根据数据库信息生成公众号的菜单项
     * @param array $menu
     * @return array
     */
    private static function buildMenuItem(array $menu)
    {
        $wx = new OfficialAccount($menu['site_id']);
        $item = ["name" => $menu['name']];
        $menuType = intval($menu['type']);
        $data = json_decode($menu['data'], true);
        if ($menuType == Constants::Weixin_Menu_Url) {
            $item['type'] = 'view';
            $url = $data['url'];
            if (!preg_match('@^https?://@', $url)) $url = getHttpProtocol() . "://" . $wx->getConfig()->getModel()->domain . $url;
            $item['url'] = $url;
        } elseif ($menuType == Constants::Weixin_Menu_Rich && $data['media_id']) {
            $item['type'] = 'media_id';
            $item['media_id'] = $data['media_id'];
        } elseif ($menuType == Constants::Weixin_Menu_MiniApp && $data['appid']) {
            $item['type'] = 'miniprogram';
            $item['url'] = 'http://mp.weixin.qq.com';
			$item['appid'] = $data['appid'];
			$item['pagepath'] = $data['page'] ? $data['page'] : 'pages/index' ;
        } else {
            $item['type'] = 'click';
            $item['key'] = $menu['id'];
        }
        return $item;
    }

    /**
     * 将菜单配置推送到公众号
     * @param int $siteId
     * @throws \Exception
     */
    public static function push($siteId = 0)
    {
        if (!WxConfig::checkConfig(true)) {
            return ;
        }
        if (!$siteId) {
            $siteId = Site::getCurrentSite()->getSiteId();
        }
        $menus = WxMenuModel::query()
            ->where('site_id', $siteId)
            ->orderBy('show_order', 'asc')
            ->get()->toArray();
        $wx = Site::getCurrentSite()->getOfficialAccount();
        // 不传入时  则删除全部菜单
        if (count($menus) < 1) {
            $wx->deleteMenu();
            return ;
        }
        $buttons = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == 0) {
                $item = self::buildMenuItem($menu);
                $sub_button = [];
                foreach ($menus as $subMenu) {
                    if ($subMenu['parent_id'] == $menu['id']) {
                        $subItem = self::buildMenuItem($subMenu);
                        $sub_button[] = $subItem;
                    }
                }
                if (count($sub_button)) {
                    $item['sub_button'] = $sub_button;
                    unset($item['type']);
                }
                $buttons[] = $item;
            }
        }
        if (count($buttons)) {
            $wx->pushMenu($buttons);
        }
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
     * 保存空的data（主要用于有子菜单的主菜单）
     */
    public function setNullData()
    {
        $this->_model->type = Constants::Weixin_Menu_Url;
        $this->_model->data = '{}';
    }

    /**
     * 设置菜单类型为跳转网址
     * @param $url 网址
     * @param string $urlName 网站名称
     */
    public function setUrl($url, $urlName = '')
    {
        $this->_model->type = Constants::Weixin_Menu_Url;
        $this->_model->data = json_encode([
            'url' => $url,
            'url_name' => trim($urlName)
        ]);
    }

	/**
     * 设置菜单类型为跳转小程序
     * @param $data 小程序的相关数据 ['appid' => '2222','page' => '']
     */
    public function setMiniApp($data)
    {
        $this->_model->type = Constants::Weixin_Menu_MiniApp;
        $this->_model->data = json_encode($data);
    }

    /**
     * 设置菜单类型为文本类型
     * @param $text 回复的文本内容
     */
    public function setReplyText($text)
    {
        $this->_model->type = Constants::Weixin_Menu_Text;
        $this->_model->data = json_encode([
            'content' => $text,
        ]);
    }

    /**
     * 设置菜单类型为图片类型
     * @param $image 图片的路径，它应该是 /comdata/XXX 开头的路径
     */
    public function setReplyImage($image)
    {
        $this->_model->type = Constants::Weixin_Menu_Image;
        $this->_model->data = json_encode([
            'image' => trim($image),
        ]);
    }

    /**
     * 设置菜单据类型为图文类型
     * @param array $richData 图文素材相关数据
     */
    public function setReplyRich($richData)
    {
        $this->_model->type = Constants::Weixin_Menu_Rich;
        $this->_model->data = json_encode([
            'news_id' => intval($richData['news_id']),
            'media_id' => $richData['media_id']
        ]);
    }

    /**
     * 设置菜单据类型为图文类型
     * @param $richItemId 图文素材ID
     */
    public function setReplyRichItem($richItemId)
    {
        $this->_model->type = Constants::Weixin_Menu_Rich;
        $this->_model->data = json_encode([
            'news_item_id' => intval($richItemId),
        ]);
    }

    /**
     * 设置菜单类型为自定义回调类型
     * @param string $callback 回调的类名和方法名
     * @param number $type     回调类型 1为分享海报
     * @param boolean|array|string $args 传递给回调类的参数,可以是字符串或数组
     */
    public function setReplyCallback($callback, $type, $args = false)
    {
        $this->_model->type = Constants::Weixin_Menu_Callback;
        $this->_model->callback_type = $type;
        $this->_model->data = json_encode([
            'callback' => trim($callback),
        ]);
        if ($args) {
            if (is_array($args)) $this->_model->data_extra = json_encode($args);
            else $this->_model->data_extra = $args;
        }
    }

    /**
     * 返回树形结构
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getTree()
    {
        $rootList = $this->getList(['parent_id' => 0]);
        foreach ($rootList as $rootItem) {
            $rootItem->items = $this->getList(['parent_id' => $rootItem->id]);
        }
        return $rootList;
    }

    /**
     * 返回树形结构（数组）
     * @return array
     */
    public function getTreeArray()
    {
        $rootList = $this->getList(['parent_id' => 0])->toArray();
        foreach ($rootList as &$rootItem) {
            $rootItem['items'] = $this->getList(['parent_id' => $rootItem['id']])->toArray();
        }
        return $rootList;
    }

    /**
     * 获取列表
     * @param $param
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getList($param)
    {
        $query = WxMenuModel::query();
        $this->getQuery($query, $param);
        return $query->orderBy('show_order', 'asc')->orderBy('id', 'asc')->get();
    }

    /**
     * 获取最大的排序值
     * @param $param
     * @return int
     */
    public function getMaxOrder($param)
    {
        $query = WxMenuModel::query();
        $this->getQuery($query, $param);
        return intval($query->max('show_order'));
    }

    /**
     * 统计
     * @param $param
     * @return int
     */
    public function getCount($param)
    {
        $query = WxMenuModel::query();
        $this->getQuery($query, $param);
        return intval($query->count());
    }

    /**
     * 删除子菜单
     */
    public function deleteSub()
    {
        if ($this->checkExist()) {
            WxMenuModel::query()
                ->where('parent_id', $this->_model->id)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->delete();
        }
    }

    /**
     * 删除
     * @throws \Exception
     */
    public function delete($subDelete = true)
    {
        if ($subDelete) {
            $this->deleteSub();
        }
        if ($this->checkExist()) {
            $this->_model->delete();
            $this->_model = null;
        }
    }

    /**
     * 获取搜索条件
     * @param $query
     * @param $param
     */
    private function getQuery($query, $param)
    {
        $query->where('site_id', Site::getCurrentSite()->getSiteId());
        if (is_numeric($param['parent_id'])) {
            $query->where('parent_id', intval($param['parent_id']));
        }
    }

    /**
     * 获取海报预览图
     * @param $paperId
     */
    public static  function getPaper($paperId){
        $paper=new Paper($paperId);
        $paperData=$paper->getModel();
        $paperArr['paper_id']=$paperData->id;
        $paperArr['paper_image']=$paperData->preview_image;
        $paperArr['paper_name']=$paperData->name;
        return $paperArr;
    }
}