<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Wx;

use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Model\WxNewsItemModel;
use YZ\Core\Model\WxNewsModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxMenu;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\Weixin\WxNews;

class WxMenuController extends BaseAdminController
{
    /**
     * 列表树
     * @return array
     */
    public function getList()
    {
        try {
            // 主信息
            $wxMenu = new WxMenu();
            $list = $wxMenu->getTree();
            $itemIds = [];
            $newsIds = [];
            // 获取需要的news_item_id 和 news_id
            foreach ($list as &$root) {
                $rootData = json_decode($root->data);
                if ($root->type == Constants::Weixin_Menu_Rich) {
                    if ($rootData->news_item_id) {
                        $itemIds[] = $rootData->news_item_id;
                    } elseif ($rootData->news_id) {
                        $newsIds[] = $rootData->news_id;
                    }
                }
                $root->data = $rootData;
                foreach ($root->items as &$subItem) {
                    $subItemData = json_decode($subItem->data);
                    if ($subItem->type == Constants::Weixin_Menu_Rich) {
                        if ($subItemData->news_item_id) {
                            $itemIds[] = $subItemData->news_item_id;
                        } elseif ($subItemData->news_id) {
                            $newsIds[] = $subItemData->news_id;
                        }
                    }
                    $subItem->data = $subItemData;
                }
            }
            // 查找相关图文数据
            $newsItemList = [];
            $newsList = [];
            if ($itemIds) {
                $newsItemList = WxNewsItemModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->whereIn('id', $itemIds)
                    ->get();
            }
            if ($newsIds) {
                $newsList = WxNewsModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->whereIn('id', $newsIds)
                    ->get();
            }
            foreach ($list as $item) {
                $itemData = $item->data;
                if ($item->type == Constants::Weixin_Menu_Rich) {
                    if ($itemData->news_item_id) {
                        $newsData = $newsItemList->firstWhere('id', $itemData->news_item_id);
                    } elseif ($itemData->news_id) {
                        $newsData = $newsList->firstWhere('id', $itemData->news_id);
                    }
                    if ($newsData) {
                        $itemData->news_item_image = $newsData['image'];
                        $itemData->news_item_title = $newsData['title'];
                    }
                }
                if ($item->callback_type == Constants::Weixin_Callback_Poster && $item->data_extra) {
                    $paper = $this->getPaper($item->data_extra);
                    $item->paper_id = $paper['paper_id'];
                    $item->paper_image = $paper['paper_image'];
                    $item->paper_name = $paper['paper_name'];
                }
                $item->data = $itemData;
                foreach ($item->items as $subItem) {
                    $subItemData = $subItem->data;
                    if ($subItem->type == Constants::Weixin_Menu_Rich) {
                        if ($subItemData->news_item_id) {
                            $newsData = $newsItemList->firstWhere('id', $subItemData->news_item_id);
                        } elseif ($subItemData->news_id) {
                            $newsData = $newsList->firstWhere('id', $subItemData->news_id);
                        }
                        if ($newsData) {
                            $subItemData->news_item_image = $newsData['image'];
                            $subItemData->news_item_title = $newsData['title'];
                        }
                    }
                    if ($subItem->callback_type == Constants::Weixin_Callback_Poster && $subItem->data_extra) {
                        $paper = $this->getPaper($subItem->data_extra);
                        $subItem->paper_id = $paper['paper_id'];
                        $subItem->paper_image = $paper['paper_image'];
                        $subItem->paper_name = $paper['paper_name'];
                    }
                    $subItem->data = $subItemData;
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'list' => $list,
                'config_full' => WxConfig::checkConfig(),
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 用于列表，获取海报相关信息
     * @param $paperData 海报相关信息 [{"paper_id":587}]
     * @return array
     */
    private function getPaper($paperData)
    {
        $paper_data = json_decode($paperData, true);
        $paper_id = $paper_data[0]['paper_id'];
        if ($paper_id) {
            return WxMenu::getPaper($paper_id);
        }
        return [];
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $menus = $request->menus;
            if (!is_array($menus)) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $wxMenu = new WxMenu();
            $list = $wxMenu->getTreeArray();

            // 处理主此单
            $this->handleMenu($menus, $list, 0);
            // 循环处理
            foreach ($menus as $index => $menu) {
                $subMenus = is_array($menu['items']) ? $menu['items'] : [];
                $subList = is_array($list[$index]['items']) ? $list[$index]['items'] : [];
                $this->handleMenu($subMenus, $subList, $menu['id']);
            }
            // 推送到公众号
            WxMenu::push();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 处理菜单
     * @param $menus
     * @param $list
     * @param int $parentId
     * @throws \Exception
     */
    private function handleMenu(&$menus, $list, $parentId = 0)
    {
        // 删除菜单
        $deleteNum = count($list) - count($menus);

        if ($deleteNum > 0) {
            for ($i = 0; $i <= $deleteNum; $i++) {
                $wxMenuTmp = new wxMenu($list[count($menus) + $i]['id']);
                $wxMenuTmp->delete(true);
            }
        }
        // 添加菜单
        $addNum = count($menus) - count($list);
        if ($addNum > 0) {
            for ($i = 0; $i < $addNum; $i++) {
                $menuId = $this->saveMenu(0, $parentId, $menus[count($list) + $i]);
                $menus[count($list) + $i]['id'] = $menuId;
            }
        }

        // 更新菜单
        $updateNum = min(count($list), count($menus));
        if ($updateNum > 0) {
            for ($i = 0; $i < $updateNum; $i++) {
                $menuId = $list[$i]['id'];
                $this->saveMenu($menuId, $parentId, $menus[$i]);
                $menus[$i]['id'] = $menuId;
            }
        }
    }

    /**
     * 保存一条菜单数据
     * @param $id
     * @param $parentId
     * @param $param
     * @return mixed
     * @throws \Exception
     */
    private function saveMenu($id, $parentId, $param)
    {
        $wxMenu = null;
        if ($id) {
            $wxMenu = new WxMenu($id);
        } else {
            $wxMenu = new WxMenu();
            $wxMenu->setSiteId(Site::getCurrentSite()->getSiteId());
            $wxMenu->setCreatedAt(date('Y-m-d H:i:s'));
        }
        $wxMenu->setName($param['name']);
        $wxMenu->setParent($parentId);

        if (intval($parentId) == 0 && is_array($param['items']) && count($param['items']) > 0) {
            $wxMenu->setNullData();
        } else {
            $type = intval($param['type']);
            $data = $param['data'];
            switch ($type) {
                case Constants::Weixin_Menu_Text:
                    $wxMenu->setReplyText(trim($data['content']));
                    break;
                case Constants::Weixin_Menu_Rich:
                    if ($data['news_item_id']) {
                        $wxMenu->setReplyRichItem($data['news_item_id']);
                    } else {
                        $wxMenu->setReplyRich($data);
                    }
                    break;
                case Constants::Weixin_Menu_Image:
                    $wxMenu->setReplyImage($data['image']);
                    break;
				case Constants::Weixin_Menu_MiniApp:
                    $wxMenu->setMiniApp($data);
                    break;
                case Constants::Weixin_Menu_Callback:
                    // 自定义回复 获取海报
                    if ($param['callback_type'] == Constants::Weixin_Callback_Poster) {
                        $paper_id = $param['paper_id'];
                        $paper_id ? $params = [['paper_id' => $paper_id]] : false;
                        $callback = '\App\Modules\ModuleShop\Libs\SharePaper\Mobi\WeixinMessageHelper@sendWeixinPaperImage';
                        $wxMenu->setReplyCallback($callback, Constants::Weixin_Callback_Poster, $params);
                    } else {
                        throw new \Exception('自定义回复类型错误');
                    }
                    break;
                default:
                    if ($data['url']) {
                        $wxMenu->setUrl($data['url'], $data['url_name']);
                    } else {
                        $wxMenu->setNullData();
                    }
            }
        }
        // 保存
        $wxMenu->setUpdatedAt(date('Y-m-d H:i:s'));
        $wxMenu->save();
        return $wxMenu->getModel()->id;
    }

}