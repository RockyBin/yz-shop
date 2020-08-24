<?php

namespace YZ\Core\Point;

use YZ\Core\Model\PointModel;
use YZ\Core\Site\Site;

/**
 * 积分业务类
 * Class Point
 * @package YZ\Core\Point
 */
class Point
{
    use \YZ\Core\Events\Eventable;
    private $_model = null;
    private $_siteId = 0;

    /**
     * 增加添加积分时的回调事件处理程序
     * @param $callback 回调事件处理程序，可以是类名或闭包
     */
    public function addOnAddEvent($callback)
    {
        $this->registerEvent('onAdd', $callback);
    }

    /**
     * 返回积分记录的 model
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 尽可能地返回SiteID
     * @return int|mixed
     */
    public function getSiteId()
    {
        if ($this->checkExist()) {
            return $this->_model->site_id;
        } else if ($this->_siteId) {
            return $this->_siteId;
        } else {
            return Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 添加积分记录
     * @param array $info
     * @return mixed
     */
    public function add(array $info)
    {
        $model = new PointModel();
        $model->fill($info);
        if (!$model->site_id) {
            $model->site_id = $this->getSiteId();
        }
        $model->save();
        $this->init($model);
        $this->fireEvent('onAdd');
        return $model->id;
    }

    /**
     * 修改积分记录
     * @param array $info
     */
    public function edit(array $info)
    {
        if ($this->checkExist()) {
            $this->_model->fill($info);
            $this->_model->save();
        }
    }

    /**
     * 查找指定ID的积分记录
     * @param $pointId
     */
    public function find($pointId)
    {
        $query = PointModel::where('id', $pointId);
        if ($this->_siteId) {
            $query = $query->where('site_id', $this->_siteId);
        }
        $this->init($query->first());
    }

    /**
     * 根据 FinanceModel 初始化积分对象
     * @param $modelObj
     */
    private function init($modelObj)
    {
        $this->_model = $modelObj;
        if ($modelObj && $modelObj->site_id) {
            $this->_siteId = $modelObj->site_id;
        }
    }
}
