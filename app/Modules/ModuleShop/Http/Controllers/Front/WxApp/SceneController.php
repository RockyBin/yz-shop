<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\WxApp;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Live\Chat;
use App\Modules\ModuleShop\Libs\Model\LiveChatModel;
use Illuminate\Http\Request;

class SceneController extends BaseFrontController
{
    /**
     * 返回相关的场景值URL
     * @param Request $request
     * @return array
     */
    public function toUrl(Request $request)
    {
        try {
            $scene = $request->get('scene');
            $scene = explode(':', $scene);
            switch ($scene[0]) {
                case 'pro':
                    $url = $this->getProductScene($scene);
                    break;
                case 'live':
                    $url = $this->getLiveScene($scene);
                    break;
                case 'smsh':
                    $url = $this->getSmallShopScene($scene);
                    break;
                case 'gro':
                    $url = $this->getGroupBuyingScene($scene);
                    break;
                case 'grop':
                    $url = $this->getGroupBuyingProductScene($scene);
                    break;
                default:
                    $url = '/shop/front/';
            }
            $url .= (stripos($url, '?') !== false ? '&' : '?') . 'fromwxapp=1';
            return makeApiResponseSuccess('成功', ['url' => $url]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 返回商品相关的场景值URL
     * @param string $scene
     * @return array
     */
    private function getProductScene(array $scene)
    {
        $url = '/shop/front/vuehash/product/product-detail?id=' . $scene[1];
        if ($scene[2]) $url .= '&invite=' . $scene[2];
        return $url;
    }

    /**
     * 返回商品相关的场景值URL
     * @param string $scene
     * @return array
     */
    private function getLiveScene(array $scene)
    {
        $url = '/shop/front/vuehash/live/live-detail?id=' . $scene[1];
        if ($scene[2]) $url .= '&invite=' . $scene[2];
        return $url;
    }

    /**
     * 返回小店相关的场景值URL
     * @param string $scene
     * @return array
     */
    private function getSmallShopScene(array $scene)
    {
        $url = '/shop/front/vuehash/smallshop/smallshop-home?member_id=' . $scene[1];
        if ($scene[2]) $url .= '&invite=' . $scene[2];
        return $url;
    }

    /**
     * 返回拼团分享详情相关的场景值URL
     * @param string $scene
     * @return array
     */
    private function getGroupBuyingScene(array $scene)
    {
        $url = '/shop/front/vuehash/groupbuying/group-share-purchase?group_buying_id=' . $scene[1];
        if ($scene[2]) $url .= '&invite=' . $scene[2];
        return $url;
    }


    /**
     * 返回拼团商品详情页相关的场景值URL
     * @param string $scene
     * @return array
     */
    private function getGroupBuyingProductScene(array $scene)
    {
        $url = '/shop/front/vuehash/groupbuying/product-detail?id=' . $scene[1];
        if ($scene[2]) $url .= '&invite=' . $scene[2];
        return $url;
    }
}