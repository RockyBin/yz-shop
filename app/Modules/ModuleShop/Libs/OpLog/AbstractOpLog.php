<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\OpLog;

use App\Modules\ModuleShop\Libs\Model\OpLogModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use Illuminate\Support\Facades\Session;

abstract class AbstractOpLog
{
    protected $_model = null;

    protected static function getSiteId(){
        return Site::getCurrentSite()->getSiteId();
    }
    /**
     * 获取操作员的IP
     * return IP
     */
    protected static function getIpAdress()
    {
        return getClientIP();
    }

    /**
     * 获取后台操作员的信息
     * return
     */
    protected static function getOpAdmin()
    {
        return SiteAdmin::getLoginedAdminId() ? SiteAdmin::getLoginedAdminId() : 0;
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
    protected static function saveAct(int $type, $target, $beforeData, $afterData){
        $OpLogModel = new OpLogModel();
        $OpLogModel->site_id = self::getSiteId();
        $OpLogModel->type = $type;
        $OpLogModel->target = $target;
        $OpLogModel->before_data = $beforeData;
        $OpLogModel->after_data = $afterData;
        $OpLogModel->op_admin = self::getOpAdmin();
        $OpLogModel->op_member = self::getOpMember();
        $OpLogModel->ip_address = self::getIpAdress();
        $OpLogModel->save();
    }

    /**
     * 保存操作
     * @param $type 操作类型
     * @param $beforeData 修改前的数据源
     * @param $afterData 修改后的数据源
     * return
     */
    abstract protected static function save(int $type, $target, $beforeData, $afterData);
}