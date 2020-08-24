<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\VerifyLog;


use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use Illuminate\Support\Facades\Session;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;

abstract class AbstractVerifyLog
{
    protected $_model = null;

    protected static function getSiteId()
    {
        return Site::getCurrentSite()->getSiteId();
    }


    /**
     * 获取前台操作会员的信息
     *
     * @return void
     */
    protected static function getOpMember()
    {
        return intval(Session::get('memberId'));
    }

    /**
     * 记录操作日志
     *
     * @param integer $type 日志类型
     * @param string $target 操作对象，如操作订单，那应该是订单ID，如操作会员，那应该是会员ID
     * @param $beforeData 变化前数据
     * @param $afterData 变化后数据
     * @return void
     */
    protected static function saveAct($params)
    {
        if ($params['id']) {
            $VerifyLogModel = VerifyLogModel::find($params['id']);
        } else {
            $VerifyLogModel = new VerifyLogModel();
        }
        $VerifyLogModel->fill($params)->save();
        return $VerifyLogModel->id;
    }

    /**
     * 保存操作
     * @param $type 操作类型
     * @param $model 具体model
     * return
     */
    abstract protected static function save(int $type, $model);
}