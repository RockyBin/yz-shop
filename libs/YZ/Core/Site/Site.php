<?php
//phpcodelock
namespace YZ\Core\Site;

use App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Ipower\Common\DomainUtil;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Exceptions\SiteNotFoundException;
use YZ\Core\Model\DomainModel;
use YZ\Core\Model\SiteModel;
use YZ\Core\Weixin\OfficialAccount;
use YZ\Core\License\SNUtil;

class Site
{
    private $_siteId = 0;
    private $_model = null;
    private $_config = null;
    private $_officailAccount = null;
    private $_sn = null;
    private $_app = null;

    const DomainType_All = 0;
    const DomainType_User = 1;
    const DomainType_Gift = 2;

    /**
     * 初始化，CLI模式下，初始化不报错误，因为在程序入口的时候就已经初始化了
     * 网站类，此类贯穿整个请求的生命周期，在系统中会有很多地方会调用到它
     * @param int $siteId
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function __construct($siteId = 0)
    {
        $nocheck = false;
        if (ServerInfo::get('REQUEST_URI')) {
            // http 模式下处理一些事情
            if (stripos(Request::getRequestUri(), '/sysmanage/') !== false) return; // 72ad后台不用检测网站
            if (stripos(Request::getRequestUri(), '/common/verifycode') !== false) $nocheck = true; // 验证码控制器不用检测网站
            if (stripos(Request::getRequestUri(), '/shop/crm/') !== false) $nocheck = true; // 前台CRM相关控制器不用检测网站
            if (!$siteId && Request::get('InitSiteID')) $siteId = intval(Request::get('InitSiteID'));
            if (!$siteId && Request::cookie('InitSiteID')) $siteId = intval(Request::cookie('InitSiteID'));
            if (!$siteId) {
                if(SNUtil::isPlatformVersion()) {
                    $domain = Request::getHost();
                    if (substr($domain, 0, 4) == "www.") $domain = substr($domain, 4);
                    $domain = DomainModel::where('domain', $domain)->limit(1)->first();
                    if ($domain) $siteId = $domain->site_id;
                }else{
                    $site = SiteModel::query()->limit(1)->first();
                    if($site) $siteId = $site->site_id;
                }
            }
        }
        //echo date('Y-m-d H:i:s').' '.Request::getHost()."  siteid = $siteId\n";
        // 初始化model
        if ($siteId) {
            $this->_model = SiteModel::where('site_id', $siteId)->first();
            if ($this->_model) {
                $this->_siteId = $siteId;
            }
        }
        if (ServerInfo::get('REQUEST_URI')) {
            // http 模式下处理一些事情
            if (!$this->_model && !$nocheck) {
                throw new SiteNotFoundException("site " . $siteId . " not found");
            }
            Cookie::queue('InitSiteID', $this->_siteId, 0, $path = '/', null, null, false, false);
            // 是否定制写进 cookie 让前端读取
            Cookie::queue('is_custom', $this->isCustom() ? 1 : 0, 0, $path = '/', null, null, false, false);
            if($this->_model->custom_dir) {
                Cookie::queue('CustomDir', $this->_model->custom_dir, 0, $path = '/', null, null, false, false);
            }
        }
    }

    /**
     * 返回站点记录的 model
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 获取网站ID
     * @return mixed
     */
    public function getSiteId()
    {
        if ($this->_model) return $this->_model->site_id;
        else if ($this->_siteId > 0) return $this->_siteId;
        return 0;
    }

    /**
     * 是否定制
     * @return bool
     */
    public function isCustom()
    {
        if ($this->_model) return $this->_model->is_custom ? true : false;
        return false;
    }

    /**
     * 初始化网站的设置信息对象
     * @return Config
     */
    private function initConfig()
    {
        return new Config($this->_siteId);
    }

    /**
     * @return null|Config 获取此站点的设置信息对象
     */
    public function getConfig()
    {
        if ($this->_config == null) {
            $this->_config = $this->initConfig();
        }
        return $this->_config;
    }

    /**
     * 获取微信公众号对象实例
     * @return null|OfficialAccount
     */
    public function getOfficialAccount()
    {
        if ($this->_officailAccount == null) {
            $this->_officailAccount = new OfficialAccount($this->_siteId);
        }
        return $this->_officailAccount;
    }

    /**
     * 获取当前网站的序列号对象
     *
     * @return null|SNInstance
     */
    public function getSn(){
        if ($this->_sn == null) {
            $this->_sn = SNUtil::getSNInstance($this->_model['sn']);
        }
        return $this->_sn; 
    }

    /**
     * 获取当前站点对象
     * @return Site
     */
    public static function getCurrentSite(): Site
    {
        return app('YZSite');
    }

    /**
     * cli模式下必须先初始化siteID
     * @param $siteId
     */
    public static function initSiteForCli($siteId)
    {
        if (isInCli() && $siteId && !isSwoole()) {
            $site = app('YZSite');
            if (!$site || ($site && $site->getSiteId() != $siteId)) {
                App::bind('YZSite', function () use ($siteId) {
                    return new Site($siteId);
                });
            }
        }
    }

    /**
     * 获取站点的数据目录路径，为加快目录查找的性能，目录分两级，以站点ID的前两位为第一级，站点ID为第二级
     * @param string $siteId
     * @param bool $isfull 是否返回完整路径
     * @return mixed|string
     */
    public static function getSiteComdataDir($siteId = '', $isfull = false)
    {
        if (!$siteId) $siteId = self::getCurrentSite()->getSiteId();
        $path = public_path() . '/comdata/';
        if ($siteId < 1000) $path .= '0000';
        else $path .= substr($siteId, 0, 2);
        $path .= '/' . $siteId;
        if (!$isfull) $path = str_replace(public_path(), '', $path);
        $path = str_replace('\\', '/', $path);
        return $path;
    }

    /**
     * 获取站点的备份据目录路径，为加快目录查找的性能，目录分两级，以站点ID的前两位为第一级，站点ID为第二级
     * @param string $siteId
     * @param bool $isfull 是否返回完整路径
     * @return mixed|string
     */
    public static function getSiteBackupDir($siteId = '', $isfull = false)
    {
        if (!$siteId) $siteId = self::getCurrentSite()->getSiteId();
        $path = base_path() . '/backup/';
        if ($siteId < 1000) $path .= '0000';
        else $path .= substr($siteId, 0, 2);
        $path .= '/' . $siteId;
        if (!$isfull) $path = str_replace(base_path(), '', $path);
        $path = str_replace('\\', '/', $path);
        return $path;
    }

    /**
     * 域名是否赠送域名
     * @param string $domain
     * @return bool
     */
    public static function isGiftDomain($domain = '')
    {
        if (!$domain) $domain = explode(':', ServerInfo::get('HTTP_HOST'))[0];
        return DomainUtil::isSystemGiftDomain($domain);
    }

    /**
     * 获取所有绑定的域名
     * @param bool $dbFormat 是否数据库数据
     * @return array
     */
    public function getAllDomain($dbFormat = false)
    {
        return self::getDomains(Self::DomainType_All, $dbFormat);
    }

    /**
     * 获取绑定的域名中系统非赠送的域名
     * @param bool $dbFormat 是否数据库数据
     * @return array
     */
    public function getUserDomain($dbFormat = false)
    {
        return self::getDomains(Self::DomainType_User, $dbFormat);
    }

    /**
     * 获取绑定的域名中系统赠送的域名
     * @param bool $dbFormat 是否数据库数据
     * @return array
     */
    public function getGiftDomain($dbFormat = false)
    {
        return self::getDomains(Self::DomainType_Gift, $dbFormat);
    }

    /**
     * 获取绑定的域名
     * @param int $type 0=所有，1=非赠送域名，2=赠送域名
     * @param bool $dbFormat 是否数据库数据
     * @return array
     */
    private function getDomains($type = 0, $dbFormat = false)
    {
        $result = [];
        $siteId = self::getCurrentSite()->getSiteId();
        if (!$siteId) return [];
        $domainList = DomainModel::query()->where('site_id', $siteId)->get();
        foreach ($domainList as $domainItem) {
            $domain = strtolower(trim($domainItem->domain));
            if ($type == Self::DomainType_All) {
                $result[] = $dbFormat ? $domainItem : $domain;
            } else if ($type == Self::DomainType_User && !DomainUtil::isSystemGiftDomain($domain)) {
                $result[] = $dbFormat ? $domainItem : $domain;
            } else if ($type == Self::DomainType_Gift && DomainUtil::isSystemGiftDomain($domain)) {
                $result[] = $dbFormat ? $domainItem : $domain;
            }
          	//自动添加 www.
            if(!DomainUtil::isSystemGiftDomain($domain)){
                $root = DomainUtil::getRootDomain($domain);
                if ($root == $domain){
                    $dom = new DomainModel();
                    $dom->domain = "www.".$domain;
                    $dom->site_id = $this->getSiteId();
                    $result[] = $dom;
                }
            }
        }
        return $result;
    }
}