<?php
/**
 * 处理微信回复内容.
 * User: liyaohui
 * Date: 2019/4/18
 * Time: 11:04
 */

namespace YZ\Core\Weixin;


use App\Modules\ModuleShop\Libs\Link\LinkHelper;

class WxReplyContentHandle
{
    /**
     * 处理文本内容 只保留<a href="...">xxxx</a>
     * @param $content
     * @return mixed|string
     */
    public static function parseContent($content)
    {
        if (!$content) return $content;
        // 读取当前微信绑定的域名
        $wxConfig = new WxConfig();
        $domain = $wxConfig->getDomain();
        // 处理数据
        $content = str_ireplace("<br>", "\n", $content);
        $content = str_ireplace("<br/>", "\n", $content);
        $content = strip_tags($content, "<a>");
        $content = LinkHelper::replaceHtmlLink($content, $domain);
        // 把超链接的其他属性去掉只保留href
        preg_match_all("/<a[^>]+>/", $content, $links, PREG_SET_ORDER);
        foreach ($links as $link) {
            $oldLink = $link[0];
            $link = $link[0];
            preg_match("/href=('|\")?([^'\"]+)/", $link, $href);
            if ($href[2]) {
                $link = '<a href="' . $href[2] . '">';
            }
            if ($oldLink != $link) $content = str_replace($oldLink, $link, $content);
        }
        return $content;
    }

    /**
     * 替换图文内容中的a链接和图片地址
     * @param $html             图文内容
     * @param $materialObj      图文实例 为了推送图片给微信
     * @param string $domain    域名
     * @return mixed
     * @throws \Exception
     */
    public static function htmlReplaceDomain($html, $materialObj, $domain = '')
    {
        if (!$html) return $html;
        if (!$domain) {
            $wxConfig = new WxConfig();
            $domain = getHttpProtocol() . '://' . $wxConfig->getDomain();
        }
        $imageBasePath = public_path();
        $srcPreg = '@<img.*?src=((\\\")|(\')|(\"))?(?!((https?:)?//))(?<src>[^>\\\"\'\"]+)@i';
        $hrefPreg = '@<a.*?href=((\\\")|(\')|(\"))?(?!((https?:)?//))(?<href>[^> \\\"\'\"]+)@i';
        $urlPreg = '@background[-image]?.*?url\(((\\\")|(\')|(\"))?(?<url>(?!((https?:)?//))[^\\\"\'\)\"]+)@i';
        // 替换链接路径
        preg_match_all($hrefPreg, $html, $hrefRes);
        foreach ($hrefRes['href'] as $href) {
            // 是否需要额外添加斜杠
            $tempDomain = substr($href, 0, 1) == '/' ? $domain : $domain . '/';
            $html = str_replace($href, $tempDomain . $href, $html);
        }
        // 替换图片路径
        preg_match_all($srcPreg, $html, $srcRes);
        foreach ($srcRes['src'] as $src) {
            $newSrc = self::getArticleImageUrl($src, $materialObj, $imageBasePath);
            $html = str_replace($src, $newSrc, $html);
        }
        // 替换背景图片路径
        preg_match_all($urlPreg, $html, $urlRes);
        foreach ($urlRes['url'] as $url) {
            $newUrl = $newSrc = self::getArticleImageUrl($url, $materialObj, $imageBasePath);
            $html = str_replace($url, $newUrl, $html);
        }
        return $html;
    }

    /**
     * 推送图片给微信
     * @param $src          源图片地址
     * @param $materialObj  图文实例 为了推送图片
     * @param $basePath
     * @return mixed
     * @throws \Exception
     */
    public static function getArticleImageUrl($src, $materialObj, $basePath)
    {
        $imgPath = $basePath . $src;
        $newSrc =$src;
        if (file_exists($imgPath)) {
            $newSrc = $materialObj->uploadArticleImage($imgPath);
            if ($newSrc['errcode'] != 0) {
                throw new \Exception($newSrc['errmsg']);
            }
            $newSrc = $newSrc['url'];
        }
        return $newSrc;
    }
}