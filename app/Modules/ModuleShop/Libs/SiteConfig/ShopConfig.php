<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use YZ\Core\Site\Site;
use  App\Modules\ModuleShop\Libs\Model\ShopConfigModel;
use YZ\Core\FileUpload\FileUpload as FileUpload;
use YZ\Core\Site\SiteManage;

/**
 * 商城设置类
 * Class ShopConfig
 * @package App\Modules\ModuleShop\Libs\ShopConfig
 */
class ShopConfig
{

    /**
     * 添加设置
     * @param array $info，设置信息，对应 ShopConfigModel 的字段信息
     */
    public function add()
    {
        $model = new ShopConfigModel();
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 ShopConfigModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new ShopConfigModel();
        $model->fill($info);

        if ($info['logo'] != '' && !strpos($info['logo'], 'comdata')) {
            $filename = time();
            $filepath = Site::getSiteComdataDir('', true) . '/shopsite';
            $upload = new FileUpload($info['logo'], $filepath, $filename);
            $upload->save();
            $info['logo'] = Site::getSiteComdataDir('', false) . '/shopsite/' . $upload->getFullFileName();
        }

        $model->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 查找指定网站的商城设置
     */
    public function findInfo()
    {
        $data = ShopConfigModel::where(['site_id' => Site::getCurrentSite()->getSiteId()])->first();
        return $data;
    }

    public function getIndustry()
    {
        $data = \DB::table('tbl_industry')->get();
        return $data;
    }

    public function getInfo()
    {
        $data['info'] = $this->findInfo();
        if (!$data['info']) {
            $this->add();
        }
        $data['info'] = $this->findInfo();
        $data['industry'] = $this->getIndustry();
        return $data;
    }

    /**
     * 获取商品sku数量
     * @return array|mixed
     */
    public static function getProductSkuNum()
    {
        $productSkuNum = ShopConfigModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->value('product_sku_num');
        if ($productSkuNum && $productSkuNum = json_decode($productSkuNum, true)) {
            return $productSkuNum;
        } else {
            return SiteManage::$productSkuNum;
        }
    }
}