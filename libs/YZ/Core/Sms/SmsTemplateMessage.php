<?php

namespace YZ\Core\Sms;

use App\Modules\ModuleShop\Libs\SiteConfig\SmsConfig;
use YZ\Core\Logger\Log;
use YZ\Core\Model\SmsTemplateModel;
use YZ\Core\Site\Site;

/**
 * 短信通知消息模板
 * Class TemplateMessage
 * @package YZ\Core\Weixin
 */
class SmsTemplateMessage
{
    private $_siteId = 0;
    private $_model = null;
    private $_type = 0;

    public function __construct($type, $siteId = '')
    {
        if (!$siteId) {
            $this->_siteId = getCurrentSiteId();
        } else {
            $this->_siteId = $siteId;
        }

        $this->_type = intval($type);
        $this->init($this->findByType($this->_type));
    }

    /**
     * 根据类型获取数据
     * @param $type
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    private function findByType($type)
    {
        if (intval($type) > 0) {
            return SmsTemplateModel::query()
                ->where('site_id', $this->_siteId)
                ->where('type', intval($type))
                ->first();
        }
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        } else {
            $this->add($this->_type);
        }
    }

    /**
     * 新建一个数据
     * @param $type
     */
    private function add($type)
    {
        if ($type > 0) {
            $notice = SmsTemplateTpl::TemplateConfig[$type];
            $status = 0;
            if ($notice) {
                $status = $notice['status'] ? 1 : 0;
            }

            $model = new SmsTemplateModel();
            $model->fill([
                'site_id' => $this->_siteId,
                'type' => $type,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $model->save();
            $this->_model = $this->findByType($type);
        }
    }

    /**
     * 保存数据
     * @param array $param
     */
    public function save(array $param)
    {
        $param['updated_at'] = date('Y-m-d H:i:s');
        if ($this->checkExist()) {
            $this->_model->fill($param);
            $this->_model->save();
        }
    }

    /**
     * 是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->type) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && $this->_model->status) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 返回模型
     * @return bool
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->_model;
        } else {
            return null;
        }
    }

    /**
     * 发送短信
     * @param $mobile
     * @param array $data
     * @param string $url
     * @return bool
     * @throws \Exception
     */
    public function send($mobile, $data = [], $url = '')
    {
        $data['url'] = $url;
        // 从数据库读取用户的消息配置
        if ($this->isActive() && $mobile) {
            $smsConfig = new SmsConfig($this->_siteId);
            $configModel = $smsConfig->getInfo();
            if (!$configModel || !$configModel->appid || !$configModel->type || !$configModel->appkey) {
                return false;
            }
            $type = intval($this->_model->type);
            $smsType = intval($configModel->type);
            if ($type > 0 && $smsType > 0) {
                $templateData = SmsTemplateTpl::getTemplateData();
                $template = $templateData[$smsType][$type];
                if (!$template) return false;
                $content = trim($template['content']);
                if ($data['sms_content']) {
                    $content = trim($data['sms_content']);
                }
                if ($this->_model->content) {
                    // 自定义内容
                    $content = trim($this->_model->content);
                }
                foreach ($data as $key => $val) {
                    $content = str_replace("{" . $key . "}", $val, $content);
                }
                // 发送短信
                return SmsApi::sendSmsBySite($content, $mobile, $this->_siteId);
            }
        }

        return false;
    }

    /**
     * 获取列表
     * @param $param
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getList($param = [])
    {
        $query = SmsTemplateModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if (is_numeric($param['status'])) {
            $query->where('status', intval($param['status']));
        }

        return $query->get();
    }
}