<?php

namespace YZ\Core\Weixin;

use EasyWeChat\Factory;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Logger\Log;
use YZ\Core\Model\WxTemplateModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxTemplateTpl;

/**
 * 微信公众号消息模板类
 * Class WxTemplateMessage
 * @package YZ\Core\Weixin
 */
class WxTemplateMessage
{
    private $_wxConfig = null;
    private $_app = null;
    private $_model = null;
    private $_type = 0;
    private $_siteId = 0;

    /**
     * 初始化
     * WxTemplateMessage constructor.
     * @param $type
     * @param int $siteId
     */
    public function __construct($type, $siteId = 0)
    {
        $this->_wxConfig = new WxConfig($siteId);
        $this->_siteId = $this->_wxConfig->getModel()->site_id;
        if ($this->_wxConfig->infoIsFull()) {
            $options = [
                'app_id' => $this->_wxConfig->getModel()->appid,
                'secret' => $this->_wxConfig->getModel()->appsecret,
            ];
            $this->_app = Factory::officialAccount($options);
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
            return WxTemplateModel::query()
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
        $notice = WxTemplateTpl::TemplateConfig[$type];
        $status = 0;
        $shortId = '';
        if ($notice) {
            $status = $notice['status'] ? 1 : 0;
            $shortId = $notice['short_id'];
        }

        $model = new WxTemplateModel();
        $model->fill([
            'site_id' => $this->_siteId,
            'type' => $type,
            'status' => $status,
            'short_id' => $shortId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $model->save();
        $this->_model = $this->findByType($type);
    }

    /**
     * 获取绑定的域名
     * @return null|string
     */
    public function getDomain()
    {
        if ($this->_wxConfig) {
            return $this->_wxConfig->getDomain();
        }
        return null;
    }

    /**
     * 获取模型
     * @return null
     */
    public function getModel()
    {
        if ($this->_wxConfig) {
            return $this->_model;
        }
        return null;
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
     * 新建一个模板消息
     * @param bool $reset
     * @return bool|mixed|null
     */
    public function addTemplate($reset = false)
    {
        if ($this->checkExist() && $this->_app) {
            $templateId = $this->_model->template_id;
            $shortId = $this->_model->short_id;
            if (!$shortId) {
                $res = WxTemplateTpl::TemplateConfig[$this->_model->type];
                $this->_model->short_id = $res['short_id'];
                $this->_model->save();
                $shortId = $this->_model->short_id;
            }
            if ($templateId && $reset) {
                // 删除原来的
                $res = $this->_app->template_message->deletePrivateTemplate($templateId);
                if (intval($res['errcode']) == 0) {
                    $templateId = null;
                } else {
                    Log::writeLog('wx_template', '[' . $this->_siteId . ']Delete Error:' . $res['errmsg']);
                }
            } else if (empty($templateId)) {
                // 查看其他相同的short_id有没有已经建好模板的
                $sameModel = WxTemplateModel::query()
                    ->where('site_id', $this->_siteId)
                    ->where('short_id', $shortId)
                    ->whereNotNull('template_id')
                    ->where('template_id', '!=', '')
                    ->first();
                if ($sameModel) {
                    $templateId = $sameModel->template_id;
                    // 更新数据库
                    WxTemplateModel::query()
                        ->where('site_id', $this->_siteId)
                        ->where('short_id', $shortId)
                        ->where(function (Builder $subQuery) {
                            $subQuery->whereNull('template_id')->orWhere('template_id', '=', '');
                        })
                        ->update([
                            'template_id' => $templateId,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            }
            // 新获取一个
            if (empty($templateId) && $shortId) {
                $res = $this->_app->template_message->addTemplate($shortId);

                if (intval($res['errcode']) == 0) {
                    $templateId = $res['template_id'];
                    WxTemplateModel::query()
                        ->where('site_id', $this->_siteId)
                        ->where('short_id', $shortId)
                        ->update([
                            'template_id' => $templateId,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    return $templateId;
                } else {
                    Log::writeLog('wx_template', '[' . $this->_siteId . ']Add Error:' . $res['errmsg']);
                }
            }

            return $templateId;
        } else {
            return false;
        }
    }

    /**
     * 必须将所属行业设置为下列两个才能正确使用特定模板
     * 主行业代码、副行业代码
     * IT科技 互联网/电子商务=1
     * IT科技 T软件与服务=2
     */
    public function setIndustry()
    {
        if ($this->_app) {
            $res = $this->_app->template_message->setIndustry(1, 2);
            if ($res['errcode'] > 0) {
                Log::writeLog('wx_template', '[' . $this->_siteId . ']Industry Error:' . $res['errmsg']);
            }
        }
    }

    /**
     * 想微信发送模板消息
     * @param $openId
     * @param array $data
     * @param string $url
     * @return bool
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     */
    public function send($openId, $data = [], $url = '')
    {
        // 从数据库读取用户的消息配置
        if ($this->isActive() && $openId) {
            if (!$this->_model->template_id) {
                $this->_model->template_id = $this->addTemplate();
            }
            if ($this->_model->template_id) {
                $customFirst = trim($this->_model->first_data);
                $customRemark = trim($this->_model->remark_data);
                $shortId = trim($this->_model->short_id);
                $templateData = WxTemplateTpl::getTemplateData();
                $template = $templateData[$shortId];

                if ($template && $this->_app) {
                    $content = $template['content'];
                    if (!$customFirst && $data['wx_content_first']) {
                        $customFirst = $data['wx_content_first'];
                    }
                    if ($customFirst) {
                        if (array_key_exists('first', $content)) $content['first'] = $customFirst;
                        if (array_key_exists('frist', $content)) $content['frist'] = $customFirst; // TM00701 竟然是 frist，醉了
                    }
                    if (!$customRemark && $data['wx_content_remark']) $customRemark = $data['wx_content_remark'];
                    if ($customRemark && array_key_exists('remark', $content)) $content['remark'] = $customRemark;
                    foreach ($content as $contentKey => $contentVal) {
                        foreach ($data as $key => $val) {
                            $contentVal = str_replace("{" . $key . "}", $val, $contentVal);
                        }
                        $content[$contentKey] = $contentVal;
                    }
                    $res = $this->_app->template_message->send([
                        'touser' => $openId,
                        'template_id' => $this->_model->template_id,
                        'url' => $url,
                        'data' => $content
                    ]);
                    if (intval($res['errcode']) == 0) {
                        Log::writeLog('wx_template', 'Send To ' . $openId . ' with type[' . $this->_model->type . ']'. '-url:' . $url);
                        return true;
                    } else {
                        Log::writeLog('wx_template', '[' . $this->_siteId . ']Send Error:' . $res['errmsg'] . '-url:' . $url.'-openid:'.$openId);
                    }
                }
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
        $query = WxTemplateModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if (is_numeric($param['status'])) {
            $query->where('status', intval($param['status']));
        }

        return $query->get();
    }
}