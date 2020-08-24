<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 2019/3/11
 * Time: 15:59
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\UI\StyleColor;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\UI\StyleColorMobi;
use Illuminate\Http\Request;

class StyleColorController extends BaseAdminController
{
    /**
     * 获取配色信息
     * @return array
     */
    public function getSiteColor()
    {
        try {
            $styleColor = new StyleColorMobi();
            $colorList = $styleColor->getColorList();
            $siteColor = $styleColor->getSiteColor();
            $data['list'] = $colorList;
            $data = array_merge($data, $siteColor);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改站点配色
     * @param Request $request
     * @return array
     */
    public function editSiteColor(Request $request)
    {
        try {
            $colorId = $request->input('color_id', 0);
            $styleColor = new StyleColorMobi();
            $save = $styleColor->editSiteColor($colorId);
            if ($save !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '保存失败');
            }

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function addStyleColor(Request $request)
    {
        $color = $request->input('color');
        $name = $request->input('name');
        $gradient_color1 = $request->input('gradient_color1');
        $gradient_color2 = $request->input('gradient_color2');
        $main_color = $request->input('main_color');
        $secondary_color = $request->input('secondary_color');
        $background_color = $request->input('background_color');
        $id = $request->input('id', 0);
        $css_file_name = $request->input('css_file_name');
        $data = [
            'name' => $name,
            'css_file_name' => $css_file_name,
            'gradient_color1' => '#' . $gradient_color1, // 渐变色1
            'gradient_color2' => '#' . $gradient_color2, // 渐变色2
            'main_color' => '#' . $main_color, // 主色
            'secondary_color' => '#' . $secondary_color, // 辅助色
            'background_color' => '#' . $background_color,// 背景色
            // images数据格式为 [{'page_name' => 'xx页', 'image_url' => '/fd/dd.jpg'}]
            'images' => [
//                [
//                    'page_name' => '会员中心',
//                    'image_url' => '/sysdata/style-color/' . $color . '/member-center.png'
//                ],
//                [
//                    'page_name' => '商品详情',
//                    'image_url' => '/sysdata/style-color/' . $color . '/product-detail.png'
//                ],
                [
                    'page_name' => '购物车',
                    'image_url' => '/sysdata/style-color/' . $color . '/shopping-cart.png'
                ],
                [
                    'page_name' => '创建订单',
                    'image_url' => '/sysdata/style-color/' . $color . '/create-order.png'
                ],
                [
                    'page_name' => '商品详情多规格',
                    'image_url' => '/sysdata/style-color/' . $color . '/product-detail-select-sku.png'
                ],
//                [
//                    'page_name' => '我的佣金',
//                    'image_url' => '/sysdata/style-color/' . $color . '/commission.png'
//                ],
            ]
        ];
//        dd($data);
        $styleColor = new StyleColorMobi();
        $add = $styleColor->editColorInfo($data, $id);
        if ($add) {
            return makeApiResponseSuccess('ok');
        }
    }
}