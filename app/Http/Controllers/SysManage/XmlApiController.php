<?php

namespace App\Http\Controllers\SysManage;

use Illuminate\Routing\Controller as BaseController;
use YZ\Core\Model\SmsConfigModel;
use YZ\Core\Site\SiteManage;
use YZ\Core\Site\SiteAdmin;
use Illuminate\Support\Facades\Hash;

/**
 * 用于和主站通信的xml数据接口
 * Class XmlApi
 * @package App\Http\Controllers\SysManage
 */
class XmlApiController extends BaseController
{
    function XmlResponse($code, $msg, $data = array())
    {
        //与我们主站那边的XML通信规范返回
        /*
        <request>
            <command>
                <action>StartVm , MigrateVm ,GetVmInfo<!-- 执行操作的方法名 --></action>
            </command>
            <!-- die -> StartVm -> GetVmInfo(get hostid) --> c_lasthostid -->
            <!-- alive -> MigrateVm(newhostid) -> (oldhost newhostid) -->
            <param>
                <!-- 要传递的参数，具体因执行的方法不同而不同 -->
                <Name>flowtest1</Name>

                <!-- MigrateVm 参数 -->
                <Name>flowtest1</Name>
                <NewHostId>111</NewHostId>
            </param>

            <seccheck>
                <sid>test</userid>
                <stamp>DateTime.Ticks</stamp>
                <chksum>md5($stamp.$apipass)</chksum>
            </seccheck>
        </request>

        <response>
            <result>
                <code>200<code> <!-- if success return 200 , otherwise return 500 -->
                <msg>command completed successfully<msg>
            </result>

            <resdata>
                <logg>ftp created successfully</logg>
                <hostid>11</hostid>
            </resdata>
        </response>
        */
        $ret = array();
        $ret['response'] = array();
        $ret['response']['result'] = array();
        $ret['response']['result']['code'] = $code;
        $ret['response']['result']['msg'] = $msg;
        $ret['response']['resdata'] = $data;
        $xml = \Ipower\Common\Util::arrToXml($ret);
        $xml = mb_convert_encoding($xml, 'gbk', 'utf-8');
        \YZ\Core\Logger\Log::writeLog('manager', 'response:' . $xml);
        return response($xml)->header('Content-Type', 'text/xml;charset=gb2312');
    }

    public function index()
    {
        try {
            $xml = file_get_contents('php://input');
            if (strlen($xml) > 0) {
                $xml = base64_decode($xml);
                $xml = mb_convert_encoding($xml, 'utf-8', 'gbk');
                \YZ\Core\Logger\Log::writeLog('manager', 'receive:' . $xml);
                $reqdata = \Ipower\Common\Util::xmlToArray($xml);

                //开始安全验证
                $username = $reqdata['seccheck']['sid'];
                $stamp = $reqdata['seccheck']['stamp'];
                $chksum = $reqdata['seccheck']['chksum'];
                $apiuser = config('app.API_USER');
                $apipass = config('app.API_PASSWORD');

                //验证用户名
                if ($username != $apiuser) {
                    return self::XmlResponse(400, "Sec UserName is Illegal");
                }

                //md5验证
                if ($chksum != md5($stamp . $apipass)) {
                    return self::XmlResponse(400, "Sec chksum is illegal");
                }

                $command = $reqdata['command']['action'];

                if (method_exists($this, $command)) {
                    return $this->$command($reqdata);
                } else {
                    return self::XmlResponse(400, "unknow command $command");
                }
            } else {
                return self::XmlResponse(400, 'nodata');
            }
        } catch (\Exception $ex) {
            return self::XmlResponse(500, $ex->getMessage());
        }
    }

    public function CreateSite($reqdata)
    {
        try {
            $AdminUser = $reqdata['param']['AdminUser'];
            $AdminPass = $reqdata['param']['AdminPass'];
            $SiteName = $reqdata['param']['SiteName'];
            $DomainName = $reqdata['param']['DomainName'];
            $ExTime = $reqdata['param']['ExTime'];
            $fIDProd = $reqdata['param']['fIDProd'];
            $Status = $reqdata['param']['Status'];
            $Version = $reqdata['param']['Version']; //参考 app\Modules\ModuleShop\Libs\Constants::License_XXX
			$AddFunc = $reqdata['param']['AddFunc'];
            $SiteID = SiteManage::addSite($SiteName,"ModuleShop",$DomainName,$ExTime,$Status,$Version,$fIDProd,$AdminUser,$AdminPass,$AddFunc);
            //如果有传SMS的相关信息，就生成相关记录
            $SmsID = $reqdata['param']['SmsID'];
            $SmsPwd = $reqdata['param']['SmsPwd'];
            $smsModel = new SmsConfigModel();
            $smsModel->site_id = $SiteID;
            $smsModel->type = 1;
            $smsModel->appid = $SmsID;
            $smsModel->appkey = $SmsPwd;
            $smsModel->sign = "在线商城";
            $smsModel->save();
            $data = array();
            $data['SiteID'] = $SiteID;
            return self::XmlResponse(200, "Command Successfully", $data);
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "Create Site Failed:" . $ex->getMessage());
        }
    }

    public function BindDomain($reqdata)
    {
        try {
            $SiteID = $reqdata['param']['SiteID'];
            $DomainName = $reqdata['param']['DomainName'];
            SiteManage::editSite($SiteID,['domains' => $DomainName]);
            return self::XmlResponse(200, "Command Successfully");
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "域名绑定失败:" . $ex->getMessage());
        }
    }

    public function DeleteSite($reqdata)
    {
        try {
            $SiteID = $reqdata['param']['SiteID'];
            SiteManage::deleteSite($SiteID);
            return self::XmlResponse(200, "Command Successfully");
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "删除用户失败:" . $ex->getMessage());
        }
    }

    public function CopySite($reqdata)
    {
        try {
            set_time_limit(1200);
            $SrcID = $reqdata['param']['SrcID'];
            $DestID = $reqdata['param']['DestID'];
            return self::XmlResponse(401, "暂未实现");
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "复制失败:" . $ex->getMessage());
        }
    }

    public function ChangePass($reqdata)
    {
        try {
            $SiteID = $reqdata['param']['SiteID'];
            $AdminUser = $reqdata['param']['AdminUser'];
            $AdminPass = $reqdata['param']['AdminPass'];
            $admin = SiteAdmin::getByUserName($AdminUser,$SiteID);
            if ($admin) {
                $adminClass = new SiteAdmin($admin);
                $adminClass->save([
                    'password' => Hash::make($AdminPass)
                ]);
                return self::XmlResponse(200, "Command Successfully");
            } else {
                return self::XmlResponse(500, "修改管理密码失败:找不到指定的管理员");
            }
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "修改管理密码失败:" . $ex->getMessage());
        }
    }

    public function Modify($reqdata)
    {
        try {
            $SiteID = $reqdata['param']['SiteID'];
            $Status = $reqdata['param']['Status'];
            $DomainName = $reqdata['param']['DomainName'];
            $ExTime = $reqdata['param']['ExTime'];
            $fIDProd = $reqdata['param']['fIDProd'];
            $Version = $reqdata['param']['Version'];
            $AddFunc = $reqdata['param']['AddFunc'];
            $info = [];
            $addInfo = [];
            if (!isNullOrEmpty($Status)) $info['status'] = $Status;
            if (!isNullOrEmpty($DomainName)) $info['domains'] = $DomainName;
            if (!isNullOrEmpty($ExTime)) $info['expiry_at'] = $ExTime;
            if (!isNullOrEmpty($fIDProd)) $info['fidprod'] = $fIDProd;
            if (!isNullOrEmpty($Version)) $info['version'] = $Version;
            if (!isNullOrEmpty($AddFunc)) $addInfo['addFunc'] = $AddFunc;
            SiteManage::editSite($SiteID, $info, $addInfo);
            return self::XmlResponse(200, "Command Successfully");
        } catch (\Exception $ex) {
            return self::XmlResponse(500, "修改站点失败:" . $ex->getMessage());
        }
    }
}