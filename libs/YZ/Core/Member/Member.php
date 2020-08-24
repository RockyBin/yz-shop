<?php

namespace YZ\Core\Member;

use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\OpLog\OpLog;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberParentsModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\MemberWithdrawAccountModel;
use App\Modules\ModuleShop\Jobs\ResetMemberParentsJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Site\SiteAdminAllocation;
use YZ\Core\Task\TaskHelper;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Common\DataCache;
use YZ\Core\Weixin\WxApp;
use YZ\Core\Weixin\WxConfig;
use YZ\Core\Weixin\WxWork;

/**
 * 会员类
 * Class Member
 * @package YZ\Core\Member
 */
class Member
{
    use \YZ\Core\Events\Eventable;
    use DispatchesJobs;
    private $_model = null;
    private $_siteId = 0;
    private $_useCache = true;

    /**
     * 初始化
     * Member constructor.
     * @param int $idOrModel 会员id或会员实例
     * @param int $param_siteId 网站id，当不需要会员实例化或者查找一个当前站点下的会员时使用
     */
    public function __construct($idOrModel = 0, $param_siteId = 0, $useCache = true)
    {
        if (!$useCache) $this->setCache(false);
        // 初始化 site_id，如果会员实例化成功，则会被会员的 site_id 覆盖
        if ($param_siteId) {
            $this->_siteId = intval($param_siteId);
        } else {
            $this->_siteId = getCurrentSiteId();
        }
        // 实例化会员
        if ($idOrModel) {
            if (is_numeric($idOrModel)) {
                $this->find($idOrModel, $param_siteId);
            } else $this->init($idOrModel);
        }
    }

    /**
     * 增加添加会员时的回调事件处理程序
     * @param $callback ，回调事件处理程序，可以是类名或闭包
     */
    public function addOnAddEvent($callback)
    {
        if ($callback) {
            $this->registerEvent('onAdd', $callback);
        }
    }

    /**
     * 添加登录时的回调事件处理程序
     * @param $callback
     */
    public function addOnLoginEvent($callback)
    {
        if ($callback) {
            $this->registerEvent('onLogin', $callback);
        }
    }

    /**
     * 会员设置上级时的事件
     * @param $callback ，回调事件处理程序，可以是类名或闭包
     */
    public function addOnSetParentEvent($callback)
    {
        if ($callback) {
            $this->registerEvent('onSetParent', $callback);
        }
    }

    /**
     * 是否有注册事件
     * @return bool
     */
    public function hasOnAddEvent()
    {
        return $this->hasEvent('onAdd');
    }

    /**
     * 是否有登录事件
     * @return bool
     */
    public function hasOnLoginEvent()
    {
        return $this->hasEvent('onLogin');
    }

    /**
     * 是否有设置上级的事件
     * @return bool
     */
    public function hasOnSetParentEvent()
    {
        return $this->hasEvent('onSetParent');
    }

    /**
     * 是否有某个事件
     * @param $eventName 事件名称
     * @return bool
     */
    private function hasEvent($eventName)
    {
        $dispatcher = $this->getEventDispatcher();
        if ($dispatcher) {
            return $dispatcher->hasListeners($eventName);
        }
        return false;
    }

    /**
     * 是否使用缓存
     * @param boolean $flag
     */
    public function setCache(bool $flag)
    {
        $this->_useCache = $flag;
    }

    /**
     * 返回会员记录的 model
     */
    public function getModel()
    {
        return $this->_model;
    }

    public function getModelId()
    {
        if ($this->checkExist()) {
            return $this->_model->id;
        } else {
            return 0;
        }
    }

    /**
     * 尽可能地返回一个 siteId
     * @return int|mixed
     */
    public function getSiteId()
    {
        if ($this->checkExist()) {
            return $this->getModel()->site_id;
        } else if ($this->_siteId) {
            return $this->_siteId;
        } else {
            return Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 添加会员
     * @param array $info 会员信息，对应 MemberModel 的字段信息
     * @return bool|mixed 成功返回会员id，失败返回false
     */
    public function add(array $info)
    {
        $model = new MemberModel();
        if(!key_exists('has_bind_invite',$info) && Site::getCurrentSite()->getConfig()->getModel()->bind_invite_time == 0) {
            $info['has_bind_invite'] = 1; //设置此会员已经设置过上级的标志
        }
        $model->fill($info);
        if (!$model->site_id) {
            $model->site_id = $this->getSiteId();
        }
        $result = $model->save();
        if ($result) {
            $this->find($model->id,true);
            Fans::createFansForRegister($this->getModel());
            $this->fireEvent('onAdd');
            return $model->id;
        } else {
            return false;
        }
    }

    /**
     * 修改会员
     * @param array $info
     * @throws \Exception
     */
    public function edit(array $info)
    {
        // 不能是自身
        if (array_key_exists('parent_id', $info) && $info['parent_id'] != $this->getModelId()) {
            $this->setParent($info['parent_id']);
            unset($info['parent_id']);
        }
        foreach ($info as $key => $val) {
            $this->_model->$key = $val;
        }
        $this->_model->save();

    }

    /**
     * 更改会员的上级
     * @param $parentId 新的上级会员ID
     * @param $orderId 在订单支付成功后再绑定上下级关系时需要，用来在绑定关系后分佣
     * @throws \Exception
     */
    public function setParent($parentId, $orderId = 0)
    {
        $oldParentId = $this->_model->invite1;
        $siteId = $this->getSiteId();
        $max = Constants::MaxInviteLevel;
        $curParentId = intval($parentId);
        if ($curParentId > 0) {
            // 验证上级是否存在
            $curParentExist = MemberModel::query()
                ->where('id', $curParentId)->where('site_id', $siteId)
                ->exists();
            if (!$curParentExist) {
                $curParentId = 0;
                $parentId = 0;
            }
        }
        $invites['invite1'] = $curParentId;
        for ($i = 2; $i <= $max; $i++) {
            if ($curParentId > 0) {
                $curParent = MemberModel::query()
                    ->where('id', $curParentId)->where('site_id', $siteId)
                    ->first();
                if ($curParent) {
                    $curParentId = intval($curParent->invite1);
                    if ($curParentId == $parentId) {
                        throw new \Exception('不能将此会员的上级设置为此会员的某个下级');
                    }
                } else {
                    $curParentId = 0;
                }
            } else {
                $curParentId = 0;
            }
            $invites['invite' . $i] = $curParentId;
        }
        $invites['has_bind_invite'] = 1; //设置此会员已经设置过上级的标志
        $this->_model->fill($invites);
        $this->_model->save();

        //同步粉丝记录
        WxUserModel::query()->where(['site_id' => $this->_model->site_id,'member_id' => $this->_model->id])->where('invite','<>',$parentId)->update(['invite' => $parentId]);

        //更新上级是此会员的其它会员的推荐路线
        for ($i = 1; $i <= $max; $i++) {
            $temp = array_values($invites);
            $newInvites = [];
            for ($j = $i + 1; $j <= $max; $j++) {
                $newInvites['invite' . $j] = array_shift($temp);
            }
            if (count($newInvites)) MemberModel::where('invite' . $i, '=', $this->_model->id)->where('site_id', '=', $this->_model->site_id)->update($newInvites);
        }

        //更新会员的上家表，记录会员的完整的上家ID
        static::resetParent($this->_model->id);
        //记录用户操作 $oldParentId 更改前的上级ID $parentId 更改后的上级的ID
        OpLog::Log(LibsConstants::OpLogType_DistributorUpperChange, $this->_model->id, $oldParentId, $parentId);
        //刷新此会员的关系链,用队列处理
        $this->dispatch(new ResetMemberParentsJob($this->_model->id, TaskHelper::createChangeMemberParentTaskGroupId($this->_model->site_id),$orderId));
        if ($this->_model->dealer_level && $this->_model->dealer_parent_id) {
            $this->dispatch(new MessageNotice(Constants::MessageType_CloudStock_Member_Add, $this->_model));
        }
        //调用更改上家时的事件
        $this->fireEvent('onSetParent', $invites);
    }

    /**
     * 更改会员的推荐员工ID
     * @param $parentId 新的推荐员工ID
     * @throws \Exception
     */
    public function setFromAdmin($adminId)
    {
        //如果没有指定员工ID，尝试读取当前会员的上级会员的员工ID
        //注意，在新注册会员的时候，要先调用了 setParent() 方法再调用此方法
        if (!$adminId) {
            $parent = MemberModel::where('id', $this->_model->invite1)->first();
            if ($parent) {
                $adminId = $parent->admin_id;
            }
        }
        if ($adminId) {
            $siteAdminModel = SiteAdminModel::where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('status', Constants::SiteAdminStatus_Active)
                ->find($adminId);
            $adminId = $siteAdminModel ? $adminId : 0;
        }
        //按后台分配规则分配
        if (!$adminId) {
            $adminId = (new SiteAdminAllocation())->allocate();
        }
        if ($adminId) {
            $this->_model->admin_id = $adminId;
            $this->_model->save();
            WxUserModel::query()->where(['site_id' => $this->_model->site_id,'member_id' => $this->_model->id])->update(['admin_id' => $adminId]);
        }
    }

    /**
     * 获取当前会员的微信公众号openid
     * @return string
     */
    public function getOfficialAccountOpenId()
    {
        $wxConfig = (new WxConfig())->getModel();
        $wxAppId = $wxConfig->appid;
        $openid = '';
        $row = MemberAuthModel::where(['member_id' => $this->_model->id, 'type' => Constants::MemberAuthType_WxOficialAccount, 'open_app_id' => $wxAppId])->first();
        if ($row) {
            $openid = $row->openid;
        }
        return $openid;
    }

    public function getAlipayUserId()
    {
        $config = Site::getCurrentSite()->getConfig()->getPayConfig();
        $alipayAppId = $config->alipay_appid;
        $openid = '';
        $row = MemberAuthModel::where(['member_id' => $this->_model->id, 'type' => Constants::MemberAuthType_Alipay, 'open_app_id' => $alipayAppId])->first();
        if ($row) {
            $openid = $row->openid;
        }
        return $openid;
    }

    /**
     * 获取用户填写的支付宝账户
     * @param array $data
     */
    public function getAlipayAccount()
    {
        $data = MemberWithdrawAccountModel::where(['member_id' => $this->_model->id, 'site_id' => $this->getSiteId()])->select(['alipay_account', 'alipay_name'])->first();
        if ($data->alipay_account && $data->alipay_name) {
            $arr['alipay_account'] = $data->alipay_account;
            $arr['alipay_name'] = $data->alipay_name;
            return $arr;
        } else {
            return false;
        }
    }

    /**
     * 绑定微信公众号授权
     * @param $openid
     * @param array $userInfo
     */
    public function bindWxOficialAccount($openid, $userInfo = [])
    {
        $wxConfig = (new WxConfig())->getModel();
        $this->bindAuthAccount(Constants::MemberAuthType_WxOficialAccount, $openid, $wxConfig->appid, $userInfo);
    }

    /**
     * 绑定企业微信授权帐号
     * @param $openid
     * @param array $userInfo
     */
    public function bindWxWorkAccount($openid, $userInfo = [])
    {
        $wxWork = new WxWork();
        $config = $wxWork->getConfig();
        $this->bindAuthAccount(Constants::MemberAuthType_WxWork, $openid, $config['corp_id'], $userInfo);
    }

    /**
     * 绑定微信小程序授权帐号
     * @param $openid
     * @param array $userInfo
     */
    public function bindWxAppAccount($openid, $userInfo = [])
    {
        $wxApp = new WxApp();
        $config = $wxApp->getConfig();
        $this->bindAuthAccount(Constants::MemberAuthType_WxApp, $openid, $config['app_id'], $userInfo);
    }

    /**
     * 绑定支付宝授权帐号
     * @param $openid
     * @param array $userInfo
     */
    public function bindAlipayAccount($openid, $userInfo = [])
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig()->getPayConfig();
        $this->bindAuthAccount(Constants::MemberAuthType_Alipay, $openid, $config->alipay_appid, $userInfo);
    }

    /**
     * 绑定QQ授权帐号
     * @param $openid
     * @param array $userInfo
     */
    public function bindQqAccount($openid, $userInfo = [])
    {
        $site = Site::getCurrentSite();
        $config = $site->getConfig();
        $this->bindAuthAccount(Constants::MemberAuthType_QQ, $openid, $config->qq_appid, $userInfo);
    }

    /**
     * 为会员绑定公众号，支付宝等的授权帐号
     * @param $type
     * @param $openid
     * @param array $userInfo
     */
    private function bindAuthAccount($type, $openid, $openAppId, $userInfo = [])
    {
        $siteId = $this->getSiteId();
        $memberId = $this->getModelId();
        $exists = MemberAuthModel::where('member_id', $memberId)
            ->where('type', $type)
            ->where('site_id', $siteId)
            ->where('openid', $openid)
            ->count();
        if (!$exists) {
            // 清理 
            MemberAuthModel::query()
                ->where('member_id', $memberId)
                ->where('type', $type)
                ->where('site_id', $siteId)
                ->delete();
            // 保存
            $authModel = MemberAuthModel::query()->where(['site_id' => $siteId,'openid' => $openid,'type' => $type])->first();
            if(!$authModel) {
                $authModel = new MemberAuthModel();
                $authModel->site_id = $siteId;
                $authModel->openid = $openid;
                $authModel->type = $type;
            }
            $authModel->member_id = $memberId;
            $authModel->open_app_id = $openAppId;
            $authModel->nickname = $userInfo['nickname'];
            $authModel->headurl = $userInfo['headurl'];
            $authModel->extend_info = @json_encode($userInfo['extInfo']);
            $authModel->save();
        }
    }

    /**
     * 登录单个会员（这时只做设置 Session 和执行回调事件，查找相应会员的过程在 Auth 类里处理）
     * @throws \Exception
     */
    public function login()
    {
        if (!$this->_model) throw new \Exception('please call ' . static::class . '::find first');
        $this->_model->lastlogin = date('Y-m-d H:i:s');
        $this->_model->save();
        Session::put('memberId', $this->_model->id);
        Cookie::queue('member_id', intval($this->_model->id), 1440000, null, null, false, false);
        if (!Session::has('WxOficialAccountOpenId')) {
            /*$auth = $this->_model->authList()->where('type', '=', Constants::MemberAuthType_WxOficialAccount)->first();
            if ($auth && $auth->openid) {
                Session::put('WxOficialAccountOpenId', $auth->openid);
            }*/
            $openId = $this->getOfficialAccountOpenId();
            if ($openId) {
                Session::put('WxOficialAccountOpenId', $openId);
            }
        }
        Session::remove(Constants::SessionKey_DistributorApply_ProductID); // 去掉分销商申请的产品信息
        //因为有可能用户设置的是下单时才绑定关系，这里可能需要更新邀请人
        Fans::syncFansInviteForLogin($this->_model);
        $this->fireEvent('onLogin');
    }

    /**
     * 查找指定ID的会员，缓存的更新放在 app\Modules\ModuleShop\Libs\DbEvents.php 做自动处理
     * @param $memberId
     */
    public function find($memberId,$useWritePdo = false)
    {
        $cacheKey = static::class . '_member_' . $memberId;
        if ($this->_useCache) $model = DataCache::getData($cacheKey) ? clone DataCache::getData($cacheKey) : null;
        if (!$model) {
            $query = MemberModel::query()->where('id', $memberId);
            if($useWritePdo) $query->useWritePdo();
            if ($this->_siteId) {
                $query = $query->where('site_id', $this->_siteId);
            }
            $model = $query->first();
            DataCache::setData($cacheKey, $model ? clone $model : $model);
        }
        $this->init($model);
    }

    /**
     * 返回当前会员头像的完整URL
     *
     * @return string
     */
    public function getMemberHeadUrl($domain = '')
    {
        $mModel = $this->getModel();
        if (!$domain) $domain = explode(',', ServerInfo::get('HTTP_HOST'))[0];
        if (!$mModel->headurl) {
            $headurl = getHttpProtocol() . "://" . $domain . '/shop/front/images/default_head.png'; //没有头像时返回默认头像
        } elseif (strpos($mModel->headurl, 'images/') !== false) {
            $headurl = getHttpProtocol() . "://" . $domain . '/shop/front/' . $mModel->headurl; //头像是系统图片的情况
        } elseif (preg_match('@^https?://@i', $mModel->headurl)) {
            $headurl = $mModel->headurl; //头像是完整url情况，比如直接使用了微信头像
        } else {
            $headurl = getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . $mModel->headurl; //自己上传的头像的情况
        }
        return $headurl;
    }

    /**
     * 根据手机号查找会员
     * @param $mobile
     * @return bool
     */
    public function findByMobile($mobile)
    {
        $model = MemberModel::where('site_id', $this->getSiteId())
            ->where('mobile', $mobile)
            ->first();
        if ($model) {
            $this->init($model);
            return true;
        }
        return false;
    }

    /**
     * 模型是否存在
     * @return bool
     */
    public function checkExist()
    {
        if ($this->_model && $this->_model->id) return true;
        else return false;
    }

    /**
     * 会员是否生效
     * @return bool
     */
    public function isActive()
    {
        if ($this->checkExist() && intval($this->getModel()->status) == Constants::MemberStatus_Active) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取查找某会员的下级会员的SQL
     * @param $memberIdOrStr
     * @param $maxLevel 最大的推荐层级
     * @param int $startLevel 从第几层级开始获取，默认为第一级
     * @param string $table
     * @return string
     */
    public static function getSubUserSql($memberIdOrStr, $maxLevel, $startLevel = 1, $table = '')
    {
        $max = Constants::MaxInviteLevel;
        if (!$maxLevel || $maxLevel > $max) {
            $maxLevel = $max;
        }
        $wheres = [];
        for ($i = $startLevel; $i <= $maxLevel; $i++) {
            $wheres[] = ($table ? $table . "." : "") . "invite$i = " . $memberIdOrStr;
        }
        $str = implode(" OR ", $wheres);
        return $str;
    }

    /**
     * 查找分销相关的上级会员ID
     * @param $memberId 当前会员ID
     * @param $maxLevel 最大的推荐层级
     * @return array
     */
    public static function getRelationMemberId($memberId, $maxLevel)
    {
        $memberId = intval($memberId);
        $current = MemberModel::find($memberId);
        //根据推荐关系查找相应的会员ID
        for ($i = 1; $i <= $maxLevel; $i++) {
            $column = "invite$i";
            if ($current->$column) $memberIds[] = $current->$column;
        }
        return $memberIds;
    }

    /**
     * 根据 MemberModel 初始化会员对象
     * @param $modelObj
     */
    private function init($modelObj)
    {
        $this->_model = $modelObj;
        // 如果实例化，设置siteId
        if ($modelObj && $modelObj->site_id) {
            $this->_siteId = $modelObj->site_id;
        }
    }

    /**
     * 查找某会员的所有上家的ID，不区分上家会员类型
     * 这里只是返回上家的所有ID，由外层的应用按需求再进行过滤
     *
     * @param int $memberId
     * @return $array 上家的会员ID数组，下标为0表示第一层上家，下标为1表示第二层上家，如此类推...
     */
    public static function findParentIds($memberId)
    {
        $pid = $memberId;
        //系统认为最多不可能超过100层，所以只循环10次
        $parentIds = [];
        for ($i = 0; $i < 10; $i++) {
            $model = MemberModel::query()->where('id', $pid)->first();
            $needBreak = false;
            if ($model) {
                for ($j = 1; $j <= 10; $j++) {
                    $parentId = $model->{'invite' . $j};
                    if (!$parentId) {
                        $needBreak = true;
                        break;
                    }
                    if ($parentId) {
                        $pid = $parentId;
                        $parentIds[] = $parentId;
                    }
                }
            }
            if ($needBreak) break;
        }
        return $parentIds;
    }

    /**
     * 将原来和上家$oldParentId有关的会员的上家列表重刷一次，这个方法一般在更改了会员的父级之后使用
     *
     * @param [int] $oldParentId 旧的父会员的ID
     * @return void
     */
    public static function resetSubMemerParentsWithOldParent($oldParentId)
    {
        //先找出与 $oldParentId 有关的记录
        $sql = "select member_id,level from tbl_member_parents where parent_id = '" . $oldParentId . "'";
        $conn = DB::getPdo();
        $db = new \Ipower\Db\DbLib($conn);
        $mlist = $db->query($sql, [], false);
        while ($row = $mlist->fetch(\PDO::FETCH_ASSOC)) {
            static::resetParent($row['member_id']);
        }
    }

    /**
     * 重刷某个会员的上家表记录
     * @param int $memberId
     * @throws \Exception
     */
    public static function resetParent($memberId)
    {
        try {
            DB::beginTransaction();
            $model = MemberModel::find($memberId);
            //删除旧的上家记录
            MemberParentsModel::where('member_id', $model->id)->delete();
            //重刷会员的上家表，记录会员的完整的上家ID
            if ($model->invite1) {
                $parentIds = static::findParentIds($model->id);
                $plevel = 1;
                $rows = [];
                foreach ($parentIds as $pid) {
                    $rows[] = [
                        'member_id' => $model->id,
                        'parent_id' => $pid,
                        'site_id' => $model->site_id,
                        'level' => $plevel
                    ];
                    $plevel++;
                }
                DB::table((new MemberParentsModel())->getTable())->insert($rows);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 检测会员是否绑定了手机号
     * @param bool $throw   是否抛出错误
     * @return bool
     * @throws \Exception
     */
    public function checkBindMobile($throw = true)
    {
        if (!preg_match('/^\d{11}$/', $this->_model['mobile'])) {
            if ($throw) {
                throw new \Exception('会员未绑定手机号', 459);
            }
            return false;
        }
        return true;
    }
}
