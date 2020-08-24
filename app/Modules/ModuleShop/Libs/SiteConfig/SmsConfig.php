<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig;

use YZ\Core\Site\Site;
use YZ\Core\Model\SmsConfigModel;

/**
 * 短信设置类
 * Class OrderConfig
 * @package App\Modules\ModuleShop\Libs\SmsConfig
 */
class SmsConfig
{
    private $_siteId = 0;

    public function __construct($siteId = 0)
    {
        if(!$siteId){
            $this->_siteId = getCurrentSiteId();
        }else{
            $this->_siteId = $siteId;
        }
    }

    /**
     * 添加设置
     * @param array $info，设置信息，对应 SmsConfigModel 的字段信息
     */
    public function add()
    {
        $model = new SmsConfigModel();
        $model->site_id = $this->_siteId;
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 SmsConfigModel 的字段信息
     */
    public function edit(array $info)
    {
        $model = new SmsConfigModel();
        $model->fill($info);
        $model::query()->where(['site_id' => $this->_siteId])->update($info);
    }

    /**
     * 查找指定网站的短信设置
     */
    public function findInfo()
    {
        $data = SmsConfigModel::query()->where(['site_id' => $this->_siteId])->first();
        return $data;
    }

    /**
     * 获取信息
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getInfo()
    {
        $data = $this->findInfo();
        if (!$data) {
            $this->add();
        }
        return $this->findInfo();
    }

}