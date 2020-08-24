<?php

namespace App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;

use App\Modules\ModuleShop\Libs\Agent\Condition\abstractCondition;
use YZ\Core\Site\Site;
use YZ\Core\Model\PayConfigModel;
use YZ\Core\FileUpload\FileUpload as FileUpload;

/**
 * 支付配置抽象类
 * @package App\Modules\ModuleShop\Libs\SiteConfig\PayConfig\abstractPayconfig
 */
class abstractPayconfig
{

    protected $_model = null;
    protected $selectColumn = null;//需要查询的字段
    protected $saveColumn = null;//需要保存的字段
    protected $uploadColumn = null; //需要上传文件的字段

    protected function __construct(array $selectColumn = null, array $saveColumn = null, array $uploadColumn = null)
    {
        $this->initModel();
        if ($selectColumn) $this->selectColumn = $selectColumn;
        if ($saveColumn) $this->saveColumn = $saveColumn;
        if ($uploadColumn) $this->uploadColumn = $uploadColumn;
    }

    final public function initModel()
    {
        $model = $this->getModel();
        if (!$model) {
            $model = new PayConfigModel();
            $model->site_id = Site::getCurrentSite()->getSiteId();
            // 默认是关闭状态
            $model->type = json_encode([
                'wxpay' => 0,
                'alipay' => 0,
                'wxpay_offline'=>0,
                'alipay_offline'=>0,
                'bankpay'=>0
            ]);
            $model->save();
            $model = $this->getModel();
        }
        $this->_model = $model;
    }

    /**
     * 保存设置
     * @param array 用户传输的参数
     * 根据$this->saveColumn保存设置
     * @throws \Exception
     */
    final public function save(array $info)
    {
        foreach ($this->saveColumn as $item) {
            if (array_key_exists($item,$info)) {
                $this->_model->$item = $info[$item];
            }
        }
        $this->_model->save();
    }

    /**
     * 查找指定网站的支付设置
     */
    final public function getModel()
    {
        $model = PayConfigModel::query()
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()]);
        if ($this->selectColumn) {
            $model->select($this->selectColumn);
        }
        $model = $model->first();
        return $model;
    }

    /**
     * 上传所需文件
     * @param $info 用户端的上传参数
     */
    final public function upload(array $info)
    {
        if ($this->uploadColumn) {
            foreach ($this->uploadColumn as &$item) {
                if ($info[$item] && $info[$item] instanceof \Illuminate\Http\UploadedFile && !strpos($info[$item], 'paysite')) {
                    $filename = $item . time();
                    $filepath = Site::getSiteComdataDir('', true) . '/paysite';
                    $upload = new FileUpload($info[$item], $filepath, $filename);
                    $upload->setAllowFileType(['pem',"png", "jpg", "gif", 'jpeg']);
                    $upload->save();
                    $info[$item] = '/paysite/' . $upload->getFullFileName();
                }
            }
        }
        return $info;
    }

}