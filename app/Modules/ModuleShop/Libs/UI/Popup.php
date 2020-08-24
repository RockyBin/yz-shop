<?php

namespace App\Modules\ModuleShop\Libs\UI;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Model\PopupModel;
use YZ\Core\Site\Site;

class Popup
{
    private $_model = null;

    /**
     * 构造函数
     * @param $idOrModel 数据库记录的模型或ID
     * @param int $deviceType 设备类型，1=手机，2=大屏，当没有指定 $idOrModel 才有效
     */
    public function __construct($idOrModel,$deviceType = 1,$pageType = 1){
        if($idOrModel){
            if(is_numeric($idOrModel)) $this->_model = PopupModel::where(['id' => $idOrModel,'site_id' => Site::getCurrentSite()->getSiteId()])->first();
            else $this->_model = $idOrModel;
        }else{
            $this->_model = new PopupModel(['device_type' => $deviceType,'page_type' => $pageType]);
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
            $this->_model->layout = 1;
            $this->_model->interval = 3;
            $this->_model->size_type = 1;
            $this->_model->margin = 20;
            $this->_model->show_type = 1;
            $this->_model->show_interval = 5;
            $this->_model->autoclose = 0;
            $this->_model->autoclose_second = 60;
        }
    }

    /**
     * 更新弹窗设置
     * @param array $info
     */
    public function update(array $info){
        if(array_key_exists('device_type',$info)) $this->_model->device_type = $info['device_type'];
        if(array_key_exists('page_type',$info)) $this->_model->page_type = $info['page_type'];
        if(array_key_exists('layout',$info)) $this->_model->layout = $info['layout'];
        if(array_key_exists('interval',$info)) $this->_model->interval = $info['interval'];
        if(array_key_exists('size_type',$info)) $this->_model->size_type = $info['size_type'];
        if(array_key_exists('margin',$info)) $this->_model->margin = $info['margin'];
        if(array_key_exists('show_type',$info)) $this->_model->show_type = $info['show_type'];
        if(array_key_exists('show_interval',$info)) $this->_model->show_interval = $info['show_interval'];
        if(array_key_exists('autoclose',$info)) $this->_model->autoclose = $info['autoclose'];
        if(array_key_exists('autoclose_second',$info)) $this->_model->autoclose_second = $info['autoclose_second'];
        if(array_key_exists('items',$info)) $this->_model->items = json_encode($info['items']);
        $this->_model->save();
        return $this->_model->id;
    }

    public function getModel(){
        return $this->_model;
    }

    /**
     * 渲染
     */
    public function render(){
        $context = $this->_model->toArray();
        $context['items'] = json_decode($this->_model->items,true);
        if(!$context['items']) $context['items'] = [];
        foreach ($context['items'] as $key => $item){
            //数据库记录以链接类型和链接data为做准，url在渲染时重新生成
            $context['items'][$key]['link_url'] = LinkHelper::getUrl($item['link_type'],$item['link_data']);
        }
        return $context;
    }

    /**
     * 获取网站默认的导航
     * @param int $deviceType 设备类型，1=手机，2=大屏
     * @param int $pageType 页面类型，1=首页,目前只有首页
     * @return static
     */
    public static function getDefaultPopup($deviceType = 1,$pageType = 1){
        //默认查找第一个弹窗记录
        $model = PopupModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId(),'device_type' => $deviceType,'page_type' => $pageType])->orderBy('id','asc')->first();
        return new static($model,$deviceType,$pageType);
    }
}