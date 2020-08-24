<?php

namespace App\Modules\ModuleShop\Libs\UI;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Model\NavMobiModel;
use YZ\Core\Site\Site;

class NavMobi
{
    private $_model = null;

    /**
     * 构造函数
     * @param $idOrModel 数据库记录的模型或ID
     * @param int $deviceType 设备类型，1=手机，2=大屏，当没有指定 $idOrModel 才有效
     */
    public function __construct($idOrModel,$deviceType = 1){
        if($idOrModel){
            if(is_numeric($idOrModel)) $this->_model = NavMobiModel::where(['id' => $idOrModel,'site_id' => Site::getCurrentSite()->getSiteId()])->first();
            else $this->_model = $idOrModel;
        }else{
            $this->_model = new NavMobiModel(['device_type' => $deviceType]);
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 更新导航设置
     * @param array $info
     */
    public function update(array $info){
        if(array_key_exists('background',$info)) $this->_model->background = $info['background'];
        if(array_key_exists('normal_color',$info)) $this->_model->normal_color = $info['normal_color'];
        if(array_key_exists('active_color',$info)) $this->_model->active_color = $info['active_color'];
        if(array_key_exists('device_type',$info)) $this->_model->device_type = $info['device_type'];
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
        $context = [];
        $context['id'] = $this->_model->id;
        $context['background'] = $this->_model->background;
        $context['normal_color'] = $this->_model->normal_color;
        $context['active_color'] = $this->_model->active_color;
        $context['device_type'] = $this->_model->device_type;
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
     * @return static
     */
    public static function getDefaultNav($deviceType = 1){
        //默认查找第一个导航记录
        $model = NavMobiModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId(),'device_type' => $deviceType])->orderBy('id','asc')->first();
        return new static($model,$deviceType);
    }
}