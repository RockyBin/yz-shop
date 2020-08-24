<?php

namespace YZ\Core\Finance;

use YZ\Core\Constants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use YZ\Core\Locker\Locker;

class Finance
{
    use \YZ\Core\Events\Eventable;
    private $_model = null;

    /**
     * 构造函数
     * Finance constructor.
     * @param int $idOrModel
     */
    public function __construct($idOrModel = 0)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $model = FinanceModel::find($idOrModel);
                $this->init($model);
            } else {
                $this->init($idOrModel);
            }
        }
    }

    /**
     * 增加添加财务时的回调事件处理程序
     * @param $callback 回调事件处理程序，可以是类名或闭包
     */
    public function addOnAddEvent($callback)
    {
        $this->registerEvent('onAdd', $callback);
    }

    /**
     * 返回财务记录的 model
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 添加财务记录
     * @param array $info 财务信息，对应 FinanceModel 的字段信息
     * @param bool $autoFireEvent 是否添加完后自动执行回调事件
     * @return bool|mixed 新的财务id
     */
    public function add(array $info, $autoFireEvent = true)
    {
        $model = new FinanceModel();
        $model->fill($info);
        if (!$model->site_id) $model->site_id = Site::getCurrentSite()->getSiteId();
        $this->parseMoney($model);
        $model->save();
        $this->init($model);
        if ($autoFireEvent) {
            $this->fireAddEvent();
        }
        return $model->id;
    }

    /**
     * 执行添加财务成功时的回调事件
     * @return mixed
     */
    public function fireAddEvent()
    {
        return $this->fireEvent('onAdd', true, $this->_model->getAttributes());
    }

    /**
     * 查找指定ID的财务记录
     * @param $financeId
     */
    public function find($financeId)
    {
        $this->init(FinanceModel::find($financeId));
    }

    /**
     * 根据 FinanceModel 初始化财务对象
     * @param $modelObj
     */
    private function init($modelObj)
    {
        $this->_model = $modelObj;
    }

    /**
     * 处理金额、手续费、实际金额
     */
    private function parseMoney($model)
    {
        if ($model) {
            // 默认手续费为 0，如果有传实际金额，则不执行计算
            $money = intval($model->money);
            $moneyFee = intval($model->money_fee);
            if ($money != 0 && !$model->money_real) {
                $moneyFee = $money >= 0 ? abs($moneyFee) : 0 - abs($moneyFee);
                $model->money_fee = $moneyFee;
                if (abs($money) > abs($moneyFee)) {
                    $model->money_real = $money - $moneyFee;
                } else {
                    $model->money_real = 0;
                }
            }
        }
    }
}