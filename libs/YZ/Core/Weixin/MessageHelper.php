<?php

namespace YZ\Core\Weixin;

use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use EasyWeChat\Kernel\Messages\Article;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Raw;
use YZ\Core\Model\WxNewsItemModel;
use YZ\Core\Site\Site;

/**
 * Class MessageHelper 微信消息的工具类
 * @package YZ\Core\Weixin
 */
class MessageHelper
{
    /**
     * 生成图片消息（临时素材）
     * @param $imagePath
     * @return Image
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     */
    public static function makeImageMessage($imagePath)
    {
        if ($imagePath) {
            if (strpos($imagePath, public_path()) === false) {
                $imagePath = Site::getSiteComdataDir('', true) . $imagePath;
            }
            if (file_exists($imagePath)) {
                $wx = Site::getCurrentSite()->getOfficialAccount();
                $res = $wx->getMediaObj()->uploadImage($imagePath);
                if (intval($res['errcode']) == 0) {
                    $mediaId = $res['media_id'];
                    $image = new Image($mediaId);
                    return $image;
                }
            }
        }
        return new Text('获取图片失败');
    }

    /**
     * 生成图文消息
     * @param $newsIdOrModel
     * @param int $limit 图文的最大数量
     * @return News
     */
    public static function makeArticlesMessageByNews($newsIdOrModel, $limit = 999)
    {
        $wxNews = new WxNews($newsIdOrModel);
        if ($wxNews->checkExist()) {
            $list = $wxNews->getModel()->items()->orderBy('id', 'asc')->get()->toArray();
            $isMaterial = $wxNews->getModel()->media_id ? true : false;
            return self::makeArticlesMessageWithItems($list, $limit, $isMaterial);
        }
        return new Text('获取图文失败');
    }

    /**
     * 生成图文消息
     * @param $newsItemIdOrModel
     * @return News|Text
     */
    public static function makeArticlesMessageByNewsItem($newsItemIdOrModel)
    {
        $model = $newsItemIdOrModel;
        if (is_numeric($newsItemIdOrModel)) {
            $model = WxNewsItemModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $newsItemIdOrModel)
                ->first();
        }
        if ($model) {
            $isMaterial = $model->image_media_id ? true : false;
            return self::makeArticlesMessageWithItems([$model], 1, $isMaterial);
        }
        return new Text('获取图文失败');
    }

    /**
     * 生成图文消息
     * @param array $newsItem
     * @param int $limit
     * @param bool $isMaterial 是否永久性图文
     * @return News
     */
    public static function makeArticlesMessageWithItems(array $newsItem, $limit = 999, $isMaterial = false)
    {
        $site = Site::getCurrentSite();
        $wxDomain = $site->getOfficialAccount()->getConfig()->getModel()->domain;
        $items = [];
        foreach ($newsItem as $row) {
            if ($isMaterial) {
                $url = $row['url_wx'];
                $image = $row['image_wx'];
            } else {
                $url = $row['url'];
                $image = $row['image'];
                if (!preg_match('@^https?://@', $url)) $url = getHttpProtocol() . "://" . $wxDomain . Site::getSiteComdataDir() . $url;
                if (!preg_match('@^https?://@', $image)) $image = getHttpProtocol() . "://" . $wxDomain . Site::getSiteComdataDir() . $image;
            }
            $items[] = new NewsItem([
                'title' => $row['title'],
                'description' => $row['digest'],
                'url' => $url,
                'image' => $image,
            ]);
            if (count($items) >= $limit) break;
        }
        $news = new News($items);
        return $news;
    }

    /**
     * 推送客服永久图文消息
     * @param $openId
     * @param $idOrModol
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public static function pushCustomerMessageByNews($openId, $newsIdOrModol)
    {
        if ($openId && $newsIdOrModol) {
            $wxNews = new WxNews($newsIdOrModol);
            if ($wxNews->checkExist() && $wxNews->getModel()->media_id) {
                $message = new Raw(json_encode([
                    "touser" => $openId,
                    "msgtype" => "mpnews",
                    "mpnews" => [
                        "media_id" => $wxNews->getModel()->media_id
                    ]
                ]));
                Site::getCurrentSite()->getOfficialAccount()->sendMessage($openId, $message);
            }
        }
    }

    /**
     * 将图文内容上传到微信服务器的图文素材那里
     * @param $newsIdOrModel
     * @return News
     * @throws \Exception
     */
    public static function pushNews($newsIdOrModel)
    {
        if (!WxConfig::checkConfig(true)) return false;
        $wxNews = new WxNews($newsIdOrModel);
        $list = [];
        if ($wxNews->checkExist()) {
            $list = $wxNews->getModel()->items()->orderBy('id', 'asc')->get();
        }
        if (count($list)) {
            $mediaId = $wxNews->getModel()->media_id;
            $res = self::pushNewsWithItems($list->toArray(), $mediaId);
            if ($res && !$mediaId) {
                // 新素材，需要保存media_id
                $mediaId = $res['media_id'];
                $wxNews->getModel()->media_id = $mediaId;
                $wxNews->save();
            }
            if (is_array($res['news_item'])) {
                foreach ($list as $index => $item) {
                    $item['url_wx'] = $res['news_item'][$index]['url']; // 微信URL地址
                    $item['image_wx'] = $res['news_item'][$index]['thumb_url']; // 微信图片地址
                    $item->save();
                }
            }
            return $res;
        } else {
            return false;
        }
    }

    /**
     * 将图文内容上传到微信服务器的图文素材那里
     * @param array $newsItemList
     * @param string $newsMediaId 空视为新资源
     * @return array|bool|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function pushNewsWithItems(array $newsItemList, $newsMediaId = '')
    {
        $site = Site::getCurrentSite();
        $wx = $site->getOfficialAccount();
        $wxConfig = new WxConfig();
        if (!$wxConfig->infoIsFull() || count($newsItemList) == 0) {
            return false;
        }
        $wxDomain = $wxConfig->getDomain();
        $articles = [];
        $index = 0;
        $materialObj = $wx->getMaterialObj();
        foreach ($newsItemList as $row) {
            $url = $row['url'];
            if (!preg_match('@^https?://@', $url)) $url = getHttpProtocol() . "://" . $wxDomain . $url;
            // 替换链接和图片地址
            $row['content'] = WxReplyContentHandle::htmlReplaceDomain($row['content'], $materialObj, getHttpProtocol() . "://" . $wxDomain);
            // 处理封面图素材
            $mediaId = $row['image_media_id'];
            if (!$mediaId && $row['image']) {
                $imageUrl = Site::getSiteComdataDir('', true) . $row['image'];
                if (file_exists($imageUrl)) {
                    $imageResult = $materialObj->uploadImage($imageUrl);
                    if (intval($imageResult['errcode']) != 0) {
                        return $imageResult;
                    } else {
                        $mediaId = $imageResult['media_id'];
                        // 上传成功，保存到数据库
                        WxNewsItemModel::query()
                            ->where('site_id', $site->getSiteId())
                            ->where('id', $row['id'])
                            ->update([
                                'image_media_id' => $mediaId
                            ]);
                    }
                }
            }
            // 构造数据结构
            $articleParam = [
                'title' => $row['title'],
                'author' => $row['author'],
                'digest' => $row['digest'],
                'show_cover' => count($articles) ? 1 : 0,
                'thumb_media_id' => $mediaId,
                'content' => $row['content'],
                'source_url' => $url
            ];
            if ($newsMediaId) {
                // 更新图文信息
                $result = $materialObj->updateArticle($newsMediaId, new Article($articleParam), $index);
                if (intval($result['errcode']) != 0) {
                    return $result;
                }
            } else {
//                $articleParam['need_open_comment'] = $row['comment_open'] ? 1 : 0;
//                $articleParam['only_fans_can_comment'] = $row['comment_only_fans'] ? 1 : 0;
                $articles[] = new Article($articleParam);
            }
            $index++;
        }
        if (!$newsMediaId) {
            // 新建图文信息
            $result = $materialObj->uploadArticle($articles);
            // 如果没有评论权限 去掉相关字段 重新推送一次
//            if ($result['errcode'] == 88000) {
//                foreach ($articles as &$art) {
//                    unset($art['need_open_comment']);
//                    unset($art['only_fans_can_comment']);
//                }
//                $result = $wx->getMaterialObj()->uploadArticle($articles);
//            }

            if (intval($result['errcode']) != 0) {
                return $result;
            }
        } else {
            $result['media_id'] = $newsMediaId;
        }
        // 获取图文详细的地址链接和图片链接
        $mediaId = $result['media_id'];
        $newsInfo = $materialObj->get($mediaId);
        if ($newsInfo) {
            $result['news_item'] = $newsInfo['news_item'];
        }
        return $result;
    }

    public static function ReplaceSrc($match, $urlprefix)
    {
        if (strpos($match[4], "http://") === false && strpos($match[4], "https://") === false && preg_match('@^(//)@', $match[4]) == false) {
            return "<{$match[1]}{$match[2]}={$match[3]}{$urlprefix}{$match[4]}.{$match[5]}";
        } else {
            return "<{$match[1]}{$match[2]}={$match[3]}{$match[4]}.{$match[5]}";
        }
    }

    public static function ReplaceBg($match, $urlprefix)
    {
        $url = $match[2];
        $url = str_replace("'", '', $url); //清除‘ “符号
        $url = str_replace('"', '', $url);
        $url = trim($url);
        if (strpos($url, "http://") === false && strpos($url, "https://") === false) {
            return "background{$match[1]}:url({$urlprefix}{$url})";
        } else {
            return "background{$match[1]}:url({$url})";
        }
    }
}