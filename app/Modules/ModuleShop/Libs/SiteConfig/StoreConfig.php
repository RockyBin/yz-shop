<?php
namespace App\Modules\ModuleShop\Libs\SiteConfig;

use Illuminate\Http\UploadedFile;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;
use  App\Modules\ModuleShop\Libs\Model\StoreConfigModel;
/**
 * 商户设置类
 * Class StoreConfig
 * @package App\Modules\ModuleShop\Libs\StoreConfig
 */
class StoreConfig
{

    /**
     * 添加设置
     * @param array $info，设置信息，对应 StoreConfigModel 的字段信息
     */
    public function add(){
        $model = new StoreConfigModel();
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     * 编辑设置
     * @param array $info，设置信息，对应 StoreConfigModel 的字段信息
     */
    public function edit(array $info){
        $model = new StoreConfigModel();
        $model->fill($info);
        $model->where(['site_id'=>Site::getCurrentSite()->getSiteId()])->update($info);
    }

    /**
     * 上传二维码图片
     * @param UploadedFile $image
     * @return string
     * @throws \Exception
     */
    public function uploadQrcodeImg(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/store-config/';
        // 保存名称
        $saveName = 'store-qrcode' . time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(800, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 查找指定网站的商户设置
     */
    public function findInfo(){
        $data=StoreConfigModel::where(['site_id'=>Site::getCurrentSite()->getSiteId()])->first();
        return $data;
    }

    public function getInfo(){
        $data=$this->findInfo();
        if(!$data){
            $this->add();
        }
        $data=$this->findInfo();
        if($data->product_class_setting){
            $data->product_class_setting=json_decode($data->product_class_setting);
        }
        return makeServiceResult(200,'ok',$data);
    }
}