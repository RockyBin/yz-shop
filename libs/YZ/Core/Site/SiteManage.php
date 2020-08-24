<?php
//phpcodelock
namespace YZ\Core\Site;

use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\MemberLevelModel;
use App\Modules\ModuleShop\Libs\Model\NavMobiModel;
use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use App\Modules\ModuleShop\Libs\Model\ShopConfigModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Model\SiteModel;
use YZ\Core\Model\DomainModel;
use App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;

/**
 * 网站管理类，处理网站新建、复制、删除...等
 * Class SiteManage
 * @package YZ\Core\Site
 */
class SiteManage
{
    public static $productSkuNum = ['sku_name_num' => 3, 'sku_value_num' => 10]; //商品sku数量默认值

    /**
     * 添加网站
     * @param $name 网站名称
     * @param $module 应用模块名称，用来表示此网站购买的是哪个应用，目前只能是 ModuleShop
     * @param $domain 网站域名
     * @param $expiry_at 网站过期时间
     * @param $status 网站状态
     * @param $version 网站应用版本号，与 应用模块名称 有关
     * @param $fidprod 主站的产品号
     * @param $username 管理员用户名
     * @param $password 管理员密码
     * @param $addfunc 附加功能列表
     */
    public static function addSite($name, $module, $domain, $expiry_at, $status, $version, $fidprod, $username, $password, $addfunc = '', $params = [])
    {
        // 检测域名是否被占用
        $domains = preg_split('/[,，;；\s]+/', $domain);
        foreach ($domains as $dom) {
            if (substr($dom, 0, 4) == "www.") {
                $dom = substr($dom, 4);
            }
            $count = DomainModel::where('domain', '=', $dom)->count('*');
            if ($count) {
                throw new \Exception("域名" . $dom . "已被占用");
            }
        }
        //  生成序列号
        $snclass = "\\App\\Modules\\" . $module . "\\Libs\\License\\SN";
        $sn = (new $snclass())->generate($domain, intval($version), $expiry_at, $addfunc);
        // 生成tbl_site记录
        $site = new SiteModel();
        $site->module = $module;
        $site->name = $name;
        $site->domains = $domain;
        $site->created_at = date('Y-m-d H:i:s');
        $site->expiry_at = $expiry_at;
        $site->status = $status;
        $site->sn = $sn;
        $site->fidprod = $fidprod;
        $site->save();
        $siteId = $site->site_id;
        // 添加管理员记录
        $admin = new SiteAdmin();
        $admin->setInfo([
            'username' => trim($username),
            'password' => Hash::make($password),
            'name' => "系统管理员",
            'status' => Constants::SiteAdminStatus_Active,
            'role_type' => Constants::SiteRoleType_Admin,
            'site_id' => $siteId
        ]);
        $admin->save();
        $admin->addPerm(Constants::SiteRole_SiteAdmin);
        // 创建网站之后的操作
        if (!isset($params['staff_num']) || $params['staff_num'] == null) {
            switch (true) {
                case $version == LibsConstants::License_STANDARD:
                    $params['staff_num'] = 3;
                    break;
                case $version == LibsConstants::License_DISTRIBUTION:
                    $params['staff_num'] = 10;
                    break;
                case $version == LibsConstants::License_AGENT_DISTRIBUTION:
                    $params['staff_num'] = 20;
                    break;
                case $version == LibsConstants::License_GROUP:
                    $params['staff_num'] = 20;
                    break;
                case $version == LibsConstants::License_MICRO_CLOUDSTOCK:
                    $params['staff_num'] = 20;
                    break;
            }
        }
        self::createdSiteAfter($siteId, $params);
        return $siteId;
    }

    /**
     * 创建网站成功后的操作
     * @param $siteId
     */
    public static function createdSiteAfter($siteId, $params = [])
    {
        self::initSiteSharePaper($siteId);
        self::initSiteMobileNav($siteId);
        self::initSiteMemberLevel($siteId);
        self::initSiteDistributionLevel($siteId);
        self::initSiteShopConfig($siteId, $params);
        // 创建网站文件目录
        static::createSiteDir($siteId);
    }

    /**
     * 新建站点时 默认的海报数据
     * @param $siteId
     * @return SharePaperModel
     */
    public static function initSiteSharePaper($siteId)
    {
        $sharePaper = new Paper();
        $info['name'] = '分享海报';
        $info['background'] = '/sysdata/paper/template7.png';
        $info['type'] = \App\Modules\ModuleShop\Libs\Constants::SharePaperType_Home;
        $info['modules'] = '[{"id":"module_90653","module_type":"ModuleHead","position":"absolute","top":"11.0097%","left":"42.3292%","width":"14.9292%","height":"9.9412%","zIndex":1},{"id":"module_301254","module_type":"ModuleNickName","position":"absolute","top":"24.1508%","left":"41.5292%","width":"17.0667%","height":"4.2629%","zIndex":1,"color":"black","fontSize":16,"bold":1,"textAlign":"left"},{"id":"module_49046","module_type":"ModuleText","position":"absolute","top":"29.6570%","left":"24.7292%","width":"52.0000%","height":"3.3748%","zIndex":1,"color":"rgba(153,153,153,1)","fontSize":13,"bold":0,"text":"\u4f60\u597d\uff01\u6211\u662f\u67d0\u67d0\uff0c\u5f88\u9ad8\u5174\u8ba4\u8bc6\u4f60\uff01","textAlign":"left"},{"id":"module_352366","module_type":"ModuleQrcode","position":"absolute","top":"42.0904%","left":"33.2667%","width":"33.5917%","height":"22.3746%","zIndex":1,"color":"black","logo":null,"qrtype":0},{"id":"module_841775","module_type":"ModuleText","position":"absolute","top":"71.2256%","left":"28.7292%","width":"44.2667%","height":"3.9049%","zIndex":1,"color":"rgba(153,153,153,1)","fontSize":12,"bold":0,"text":"\u957f\u6309\u8bc6\u522b\u4e8c\u7ef4\u7801\u5373\u53ef\u7acb\u523b\u5173\u6ce8","textAlign":"left"}]';
        $info['template'] = 'template_7';
        $info['site_id'] = $siteId;
        //保存图片显示位置
        $info['show_member_center'] = 1;
        $info['show_distributor_center'] = 1;
        $info['show_agent_center'] = 1;
        $info['show_area_agent_center'] = 1;
        $info['show_dealer_center'] = 1;
        $paperId = $sharePaper->update($info);

        $staffSharePaper = new Paper();
        $staffSharePaperInfo['name'] = '员工分享海报';
        $staffSharePaperInfo['background'] = '/sysdata/paper/template10.jpg';
        $staffSharePaperInfo['type'] = \App\Modules\ModuleShop\Libs\Constants::SharePaperType_Home;
        $staffSharePaperInfo['modules'] = '[{"id":"module_926971","module_type":"ModuleHead","position":"absolute","top":"4.6126%","left":"7.4000%","width":"37.0667%","height":"24.6892%","zIndex":1},{"id":"module_383553","module_type":"ModuleText","position":"absolute","top":"30.3675%","left":"6.5917%","width":"18.3917%","height":"4.0825%","zIndex":1,"color":"rgba(51,51,51,1)","fontSize":15,"bold":1,"text":"邀请您","textAlign":"left"},{"id":"module_224442","module_type":"ModuleText","position":"absolute","top":"34.2779%","left":"6.6000%","width":"26.1292%","height":"3.7245%","zIndex":1,"color":"rgba(153,153,153,1)","fontSize":12,"bold":0,"text":"共赚取现金","textAlign":"left"},{"id":"module_675374","module_type":"ModuleImage","position":"absolute","top":"47.5994%","left":"15.4667%","width":"78.1251%","height":"13.1411%","zIndex":1,"src":"/sysdata/paper/subimg/template10-1.png","borderRadius":"0"},{"id":"module_260893","module_type":"ModuleImage","position":"absolute","top":"46.3505%","left":"6.6000%","width":"6.6667%","height":"26.8151%","zIndex":1,"src":"/sysdata/paper/subimg/template10-2.png","borderRadius":"0"},{"id":"module_727614","module_type":"ModuleText","position":"absolute","top":"30.3675%","left":"19.6667%","width":"18.4000%","height":"4.0853%","zIndex":1,"color":"rgba(255,131,63,1)","fontSize":15,"bold":1,"text":"注册会员","textAlign":"left"},{"id":"module_765415","module_type":"ModuleText","position":"absolute","top":"50.0833%","left":"22.6000%","width":"63.2000%","height":"3.5524%","zIndex":1,"color":"rgba(51,51,51,1)","fontSize":14,"bold":0,"text":"点击【微信授权】授权手机号快速","textAlign":"left"},{"id":"module_678982","module_type":"ModuleText","position":"absolute","top":"54.1741%","left":"22.3292%","width":"18.6625%","height":"3.7245%","zIndex":1,"color":"black","fontSize":14,"bold":0,"text":"注册会员","textAlign":"left"},{"id":"module_251945","module_type":"ModuleImage","position":"absolute","top":"61.4565%","left":"15.1292%","width":"79.1917%","height":"13.1439%","zIndex":1,"src":"/sysdata/paper/subimg/template10-1.png","borderRadius":"0"},{"id":"module_474728","module_type":"ModuleText","position":"absolute","top":"63.9432%","left":"23.1292%","width":"60.5251%","height":"3.7245%","zIndex":1,"color":"rgba(51,51,51,1)","fontSize":14,"bold":0,"text":"截图或保存图片后打开微信扫描二","textAlign":"left"},{"id":"module_803563","module_type":"ModuleText","position":"absolute","top":"67.8508%","left":"22.8667%","width":"27.4667%","height":"3.7245%","zIndex":1,"color":"rgba(51,51,51,1)","fontSize":14,"bold":0,"text":"维码进入商城","textAlign":"left"},{"id":"module_971420","module_type":"ModuleImage","position":"absolute","top":"5.8615%","left":"70.0667%","width":"11.4667%","height":"7.6377%","zIndex":1,"src":"/sysdata/paper/subimg/template10-3.png","borderRadius":"0"},{"id":"module_668878","module_type":"ModuleText","position":"absolute","top":"15.0977%","left":"61.0000%","width":"33.5917%","height":"3.5524%","zIndex":1,"color":"rgba(255,255,255,1)","fontSize":12,"bold":0,"text":"壹米电器商城专卖店","textAlign":"left"},{"id":"module_944776","module_type":"ModuleNickName","position":"absolute","top":"18.2921%","left":"65.6000%","width":"19.7292%","height":"3.7245%","zIndex":1,"color":"rgba(175,163,168,1)","fontSize":12,"bold":0,"textAlign":"center"},{"id":"module_312506","module_type":"ModuleImage","position":"absolute","top":"41.2023%","left":"5.5292%","width":"40.2667%","height":"5.3286%","zIndex":1,"src":"/sysdata/paper/subimg/template10-4.png","borderRadius":"0"},{"id":"module_157451","module_type":"ModuleText","position":"absolute","top":"65.1865%","left":"8.7292%","width":"8.8000%","height":"5.5007%","zIndex":1,"color":"rgba(255,255,255,1)","fontSize":14,"bold":1,"text":"2","textAlign":"left"},{"id":"module_376437","module_type":"ModuleText","position":"absolute","top":"51.6874%","left":"8.7292%","width":"8.8000%","height":"4.2629%","zIndex":1,"color":"rgba(255,255,255,1)","fontSize":14,"bold":1,"text":"1","textAlign":"left"},{"id":"module_515188","module_type":"ModuleText","position":"absolute","top":"42.0904%","left":"16.4667%","width":"19.2000%","height":"3.1972%","zIndex":1,"color":"rgba(255,255,255,1)","fontSize":10,"bold":0,"text":"完成以下步骤","textAlign":"left"},{"id":"module_83991","module_type":"ModuleQrcode","position":"absolute","top":"77.2647%","left":"39.1292%","width":"25.6000%","height":"17.0460%","zIndex":1,"color":"black","logo":null,"qrtype":0,"linkinfo":{"link_type":"home","link_data":null,"link_url":"#\/","link_desc":"链接到 店铺首页"}}]';
        $staffSharePaperInfo['template'] = 'template_10';
        $staffSharePaperInfo['site_id'] = $siteId;
        //保存图片显示位置
        $staffSharePaperInfo['show_staff_center'] = 1;
        $staffSharePaper->update($staffSharePaperInfo);

        return $paperId;
    }

    /**
     * 新建站点时 默认的底部导航数据 初始化数据写在了模型里面
     * @param $siteId
     * @return NavMobiModel
     */
    public static function initSiteMobileNav($siteId)
    {
        $nav = new NavMobiModel();
        $nav->site_id = $siteId;
        $nav->save();
        return $nav;
    }

    /**
     * 新建站点 生成默认会员等级
     * @param $siteId
     * @return mixed
     */
    public static function initSiteMemberLevel($siteId)
    {
        $level = new MemberLevelModel();
        $level->name = "默认等级";
        $level->weight = 0;
        $level->status = 1;
        $level->for_newmember = 1;
        $level->condition = json_encode([]);
        $level->site_id = $siteId;
        return $level->save();
    }

    /**
     * 新建站点 生成默认分销等级
     * @param $siteId
     * @return mixed
     */
    public static function initSiteDistributionLevel($siteId)
    {
        $level = new DistributionLevelModel();
        $level->name = "默认等级";
        $level->weight = 0;
        $level->status = 1;
        $level->new_open = 1;
        $level->condition = json_encode([]);
        $level->commission = json_encode(['1' => 0, '2' => 0, '3' => 0]);
        $level->site_id = $siteId;
        return $level->save();
    }

    public static function initSiteShopConfig($siteId, $params = [])
    {
        if ($params['product_sku_num'] && is_array($params['product_sku_num'])) {
            $skuNum = [
                'sku_name_num' => intval($params['product_sku_num']['sku_name_num']),
                'sku_value_num' => intval($params['product_sku_num']['sku_value_num']),
            ];
            $skuNum = json_encode($skuNum);
        } else {
            $skuNum = json_encode(self::$productSkuNum);
        }
        $shopConfig = ShopConfigModel::find($siteId);
        if (!$shopConfig) {
            $shopConfig = new ShopConfigModel();
            $shopConfig->site_id = $siteId;
        }
        $shopConfig->product_sku_num = $skuNum;
        $shopConfig->staff_num = $params['staff_num'];
        $shopConfig->save();
    }

    /**
     * 修改网站
     * @param $siteid 网站ID
     * @param $info 网站信息，有以下值
     * $info['name'] 网站名称
     * $info['module'] 应用模块
     * $info['domains'] 网站域名
     * $info['expiry_at'] 过期时间
     * $info['status'] 状态
     * $info['version'] 应用模块版本
     * @param array $additionalInfo 其它信息
     * @throws \Exception
     */
    public static function editSite($siteid, array $info, $additionalInfo = array())
    {
        //检测域名是否被占用
        if ($info['domains']) {
            $domains = preg_split('/[,，;；\s]+/', $info['domains']);
            foreach ($domains as $dom) {
                if (substr($dom, 0, 4) == "www.") {
                    $dom = substr($dom, 4);
                }
                $count = DomainModel::where('domain', '=', $dom)->where('site_id', '<>', $siteid)->count('*');
                if ($count) {
                    throw new \Exception("域名" . $dom . "已被占用");
                }
            }
        }
        $site = SiteModel::find($siteid);
        if (!$site) throw new \Exception("site $siteid not exists");
        //生成序列号
        $module = $info['module'];
        if (!$module) {
            $module = $site->module;
        }
        $snclass = "\\App\\Modules\\" . $module . "\\Libs\\License\\SN";
        $sn = $snclass::createInstance($site->sn);
        $addFuncA = $additionalInfo['addFunc'];
        if (!$addFuncA) {
            $addFuncA = $sn->addFunctions;
        }
        $domain = $info['domains'];
        if (!$domain) {
            $domain = $site->domains;
        }
        $expiry_at = $info['expiry_at'];
        if (!$expiry_at) {
            $expiry_at = $site->expiry_at;
        }
        $version = $info['version'];
        if (!$version) {
            $version = $sn->version;
        }
        $sn = (new $snclass())->generate($domain, intval($version), $expiry_at, $addFuncA);
        $site->sn = $sn;
        //修改tbl_site记录
        if ($info['module']) {
            $site->module = $info['module'];
        }
        if ($info['name']) {
            $site->name = $info['name'];
        }
        if ($info['domains']) {
            $site->domains = $info['domains'];
        }
        if ($info['expiry_at']) {
            $site->expiry_at = $info['expiry_at'];
        }
        if ($info['fidprod']) {
            $site->fidprod = $info['fidprod'];
        }
        if (array_key_exists('status', $info)) {
            $site->status = $info['status'];
        }
        $shopConfig = ShopConfigModel::find($siteid);
        if ($info['product_sku_num']) {
            $skuNum = [
                'sku_name_num' => intval($info['product_sku_num']['sku_name_num']),
                'sku_value_num' => intval($info['product_sku_num']['sku_value_num']),
            ];

            $shopConfig->product_sku_num = json_encode($skuNum);
            $shopConfig->save();
        }
        if ($info['staff_num']) {
            $shopConfig->staff_num = $info['staff_num'];
            $shopConfig->save();
        }
        if (isset($info['remark'])) {
            $site->remark = $info['remark'];
        }
        $site->save();
    }

    public static function getSiteInfo($siteid)
    {
        $site = SiteModel::find($siteid)->toArray();
        $sn = \YZ\Core\License\SNUtil::getSNInstance($site['sn']);
        $site['version'] = $sn->version;
        $site['addfunc'] = $sn->addFunctions;
        $site['status_text'] = \YZ\Core\Constants::getSiteStatusText(intval($site['status']));
        // 获取商品sku数量
        $shopConfig = ShopConfigModel::query()->where('site_id', $siteid)->first();
        $productSkuNum = $shopConfig->product_sku_num;
        if ($productSkuNum && $skuNum = json_decode($productSkuNum, true)) {
            $skuNum['sku_name_num'] = $skuNum['sku_name_num'] ?: 3;
            $skuNum['sku_value_num'] = $skuNum['sku_value_num'] ?: 10;
        } else {
            $skuNum = self::$productSkuNum;
        }
        $site['product_sku_num'] = $skuNum;
        $site['staff_num'] = $shopConfig->staff_num;
        return $site;
    }

    public static function deleteSite($siteid)
    {
        //清除数据库记录
        $pdo = DB::getPdo();
        $statement = $pdo->prepare("show tables");
        //$statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
        $tables = $statement->fetchAll();
        foreach ($tables as $table) {
            $table = $table[0];
            $sql = "delete from $table where site_id = " . $siteid;
            try {
                DB::delete($sql);
            } catch (\Exception $ex) {
            }
        }
        //删除文件夹
        static::deleteSiteDir($siteid);
    }

    /**
     * 删除网站文件目录
     * @param $siteid
     */
    private static function deleteSiteDir($siteid)
    {
        $dir = Site::getSiteComdataDir($siteid, true);
        if (file_exists($dir)) {
            \Ipower\Common\Util::deleteDir($dir);
        }
    }

    /**
     * 创建网站文件目录
     * @param $siteid
     */
    private static function createSiteDir($siteid)
    {
        $dir = Site::getSiteComdataDir($siteid, true);
        if (!file_exists($dir)) {
            \Ipower\Common\Util::mkdirex($dir);
        }
    }

    /**
     * 备份网站
     * @param $siteId 网站ID
     * @param string $about 备份说明
     * @param int $type 备份类型
     */
    public static function backupSite($siteId, $about = "", $type = \YZ\Core\Constants::SiteBackupType_DayAuto)
    {
        try {
            //建立备份基本目录
            self::createSiteBackupDir($siteId);
            $siteDir = Site::getSiteComdataDir($siteId, true);
            $baseDir = Site::getSiteBackupDir($siteId, true);
            $dbBakDir = $baseDir . DIRECTORY_SEPARATOR . date("Y-m-d");
            \Ipower\Common\Util::mkdirex($dbBakDir);
            $fileBakDir = $baseDir . DIRECTORY_SEPARATOR . 'allfiles';
            \Ipower\Common\Util::mkdirex($fileBakDir);
            if (!file_exists($dbBakDir)) {
                throw new \Expception("无法建立备份目录，请检查权限");
            }
            if (!file_exists($fileBakDir)) {
                throw new \Expception("无法建立备份目录2，请检查权限");
            }
            $mSite = SiteModel::find($siteId);
            if ($mSite) {
                //每个备份都复制一个目录，导致备份文件太多，效率也慢，并且用户的网站建好后，基本上不会删除文件，都是增加文件的，所以后面备份的时候合并目录就好
                if (file_exists($siteDir)) {
                    \Ipower\Common\Util::mergeFolder($siteDir, $fileBakDir, false, ".webp,wx_app");
                    $files = \Ipower\Common\Util::listdir($fileBakDir);
                    $listfile = fopen($dbBakDir . DIRECTORY_SEPARATOR . 'filelist_' . $siteId . '.txt', 'w+');
                    foreach ($files as $f) {
                        $f = str_replace($fileBakDir . DIRECTORY_SEPARATOR, "", $f);
                        fwrite($listfile, $f . "\r\n");
                    }
                    fclose($listfile);
                }
            }
            $skipTables = array();
            $skipTables[] = "tbl_count_visit_log";
            $skipTables[] = "tbl_lock";
            $skipTables[] = "tbl_district";
            $skipTables[] = "tbl_industry";
            $skipTables[] = "tbl_style_color";
            $skipTables[] = "tbl_template_mobi";
            $skipTables[] = "tbl_unique_log";
			$skipTables[] = "tbl_tmp_img";
            $skipTables[] = "tbl_anti_code"; //这个表数据量很大，先不备份

            $destdb = $dbBakDir . DIRECTORY_SEPARATOR . 'database_tmp_' . date('YmdHis');
            \YZ\Core\Site\ExportUtil::exportData("mysql:" . $destdb, 0, $siteId, $skipTables);

            $backlog = array();
            $backlog['site_id'] = $siteId;
            $backlog['created_at'] = date("Y-m-d H:i:s");
            $backlog['about'] = $about;
            $backlog['type'] = $type;
            $conn = DB::getPdo();
            $db = new \Ipower\Db\DbLib($conn);
            $db->insert('tbl_backuplog', $backlog);
            $id = $db->getLastInsertID();
            rename($destdb, $dbBakDir . DIRECTORY_SEPARATOR . "database_" . $id);
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("backuplog", "backupSite() -> backup site $siteId error: " . $ex->getMessage());
        }
    }

    /**
     * 建立用户的备份目录
     * @param $userid
     * @throws Exception
     */
    public static function createSiteBackupDir($siteId)
    {
        if (env('BACKUP_LINKAPI_CREATE')) {
            $url = env('BACKUP_LINKAPI_CREATE') . "&subdir=" . $siteId;
            $res = file_get_contents($url);
            if (strpos($res, "success") === false) {
                throw new Exception("can not create site backup dir, error: " . $res);
            }
        } else {
            $dir = Site::getSiteBackupDir($siteId, true);
            if (!file_exists($dir)) {
                \Ipower\Common\Util::mkdirex($dir);
            }
        }
    }

    /**
     * 删除用户的备份目录
     * @param $siteId 网站ID
     * @throws Exception
     */
    public static function deleteSiteBackupDir($siteId)
    {
        //不管如何，先调用API删除一次
        if (env('BACKUP_LINKAPI_DELETE')) {
            $url = env('BACKUP_LINKAPI_DELETE') . "&subdir=" . $siteId;
            $res = file_get_contents($url);
            if (strpos($res, "success") === false) {
                throw new Exception("can not delete site backup dir, error: " . $res);
            }
        }
        //因为此目录不一定是个目录链接，这里需要按普通目录的方式再删除一次
        $dir = Site::getSiteBackupDir($siteId, true);
        \Ipower\Common\Util::deletedir($dir);
    }

    /**
     * 自动备份网站
     * @param bool $isdebug 是否调试，如果是，只备份第一个
     */
    public static function autoBackSite($isDebug = false)
    {
        try {
            $root = realpath(dirname(__FILE__) . '/../../../');
            $ulist = SiteModel::whereRaw('(status = 1 or status = 3)')->select('site_id')->get();
            foreach ($ulist as $dr) {
                try {
                    $siteId = $dr->site_id;
                    $count = DB::table('tbl_backuplog')->where(['site_id' => $siteId, 'type' => \YZ\Core\Constants::SiteBackupType_DayAuto])->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-15 days')))->count('id');
                    if ($count == 0) {
                        $pidfile = base_path() . "/backup/day_auto_bak_" . $siteId . ".pid";
                        if (file_exists($pidfile)) {
                            if (filemtime($pidfile) > strtotime('-30 minutes')) {
                                continue;
                            } //有这个文件，并且文件是在30分钟内，认为此网站正在做备份
                        }
                        fopen($pidfile, 'w+');
                        echo date('Y-m-d H:i:s') . " start to backup site $siteId \r\n";
                        self::backupSite($siteId, "系统自动备份", \YZ\Core\Constants::SiteBackupType_DayAuto);
                        unlink($pidfile);
                        sleep(1);
                    } else {
                        echo $siteId . " skip\r\n";
                    }
                } catch (\Exception $ex) {
                    \YZ\Core\Logger\Log::writeLog("backuplog", "backup site " . $dr->site_id . " error: " . $ex->getMessage());
                }
                if ($isDebug) {
                    break;
                }
            }
        } catch (\Exception $ex) {
            \YZ\Core\Logger\Log::writeLog("backuplog", "backup site error: " . $ex->getMessage());
        }
    }
}
