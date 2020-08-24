<?php
/**
 * Created by PhpStorm.
 * User: 1
 * Date: 2019/3/11
 * Time: 15:13
 */

namespace App\Modules\ModuleShop\Libs\UI;


use App\Modules\ModuleShop\Libs\Model\StoreConfigModel;
use App\Modules\ModuleShop\Libs\Model\StyleColorModel;
use YZ\Core\Site\Site;

class StyleColorMobi
{
    /**
     * 编辑 新增配色
     * @param $info
     * @param int $id
     * @return bool
     */
    public function editColorInfo($info, $id = 0)
    {
        if ($id) {
            $color = StyleColorModel::find($id);
        } else {
            $color = new StyleColorModel();
        }
        $originData = $color->getColorInfo();
        $data = [
            'name' => $originData['name'],
            'gradient_color1' => $originData['gradient_color1'], // 渐变色1
            'gradient_color2' => $originData['gradient_color2'], // 渐变色2
            'main_color' => $originData['main_color'], // 主色
            'secondary_color' => $originData['secondary_color'], // 辅助色
            'background_color' => $originData['background_color'],// 背景色
            // images数据格式为 [{'page_name' => 'xx页', 'image_url' => '/fd/dd.jpg'}]
            'images' => $info['images']
        ];
        $color->css_file_name = $info['css_file_name'];
        $color->color_info = json_encode($data, JSON_UNESCAPED_UNICODE);
//        dd($color->toArray());
        return $color->save();
    }

    /**
     * 获取所有的配色
     * @return array
     */
    public function getColorList()
    {
        $list = StyleColorModel::query()->orderBy('sort')->get();

        foreach ($list as &$color) {
            $color['color_info'] = $color->getColorInfo();
        }
        return $list;
    }

    /**
     * 获取配色数据 没有id 则获取默认第一条的配色
     * @param int $id
     * @return mixed
     */
    public function getColorInfo($id = 0)
    {
        if ($id) {
            $info = StyleColorModel::find($id);
        } else {
            $info = StyleColorModel::query()->orderBy('sort')->first();
        }
        $data = [
            'id' => $info->id,
            'css_file_name' => $info->css_file_name
        ];
        return array_merge($data, $info->getColorInfo());
    }

    /**
     * 获取当前站点的配色信息
     * @return mixed
     */
    public function getSiteColor()
    {
        $colorId = StoreConfigModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('store_id', 0)
            ->value('style_color_id');
        // 没有选择配色的时候 取默认值
        $colorId = $colorId ?: 0;
        $colorInfo = $this->getColorInfo($colorId);
        return ['style_color_id' => $colorInfo['id'], 'color_info' => $colorInfo];
    }

    /**
     * 修改当前站点配色
     * @param $colorId
     * @return int
     */
    public function editSiteColor($colorId)
    {
        return StoreConfigModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('store_id', 0)
            ->update(['style_color_id' => $colorId]);
    }
}