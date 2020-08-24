<?php
//phpcodelock
namespace YZ\Core\License;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Logger\Log;

/**
 * 网站序列号的抽像类，各个功能模块的序列号信息类必须继承此类
 * Class AbstractSN
 * @package YZ\Core\License
 */
abstract class AbstractSN
{
    public $domain = "";
    public $date = 0;
    public $version = -1;
    public $addFunctions = "";
    public $class = "";

    /**
     * 生成序列号
     * @param string $sDomain 域名
     * @param int $sVersion 版本号（由具体应用来定
     * @param string $sDate
     * @param string $addFunc
     * @return string
     */
    public function generate($domain, int $version, $date,$addFunc = "")
    {
        $arrDom = explode(',',$domain);
        for ($i = 0; $i < count($arrDom); $i++)
        {
            if(preg_match('/^(www\.)/i',$arrDom[$i])) $arrDom[$i] = substr($arrDom[$i],4);
        }
        $domain = implode(',',$arrDom);
        $oResult = $domain . "@" . $date . "@" . $version . "@" . $addFunc."@". get_class($this);
        $des = new \Ipower\Common\CryptDes();
        $oResult = $des->encrypt($oResult);
        return $oResult;
    }

    /**
     * 根据密文序列号字符串解析出序列号对象
     * @param $sn
     * @return AbstractSN
     */
    public static function createInstance(string $sn):AbstractSN
    {
        if (!$sn) return new UnknowSN();
        try
        {
            $des = new \Ipower\Common\CryptDes();
            $str = $des->decrypt($sn);
            return static::createInstanceWithPlainText($str);
        }
        catch(\Exception $ex)
        {
            return new UnknowSN();
        }
    }

    /**
     * 根据明文序列号字符串解析出序列号对象
     * @param string $sn
     * @return AbstractSN
     */
    public static function createInstanceWithPlainText(string $sn):AbstractSN
    {
        if (!$sn) return new UnknowSN();
        try
        {
            $arr = explode('@',$sn);
            $instance = new static();
            $instance->domain = str_replace("，",",",$arr[0]);
            $timepart = preg_split('/\s+/',$arr[1]);
            $instance->date = strtotime($timepart[0] . " 23:59:59");
            $instance->version = intval($arr[2]);
            if (count($arr) > 3) {
                $instance->addFunctions = $arr[3];
                $instance->addFunctions = preg_replace('@^,+|,$@','',$instance->addFunctions);
            }
            if(count($arr) > 4) $instance->class = $arr[4];
            return $instance;
        }
        catch(\Exception $ex)
        {
            return new UnknowSN();
        }
    }

    /**
     * 验证站点的序列号
     * @return bool
     */
    public function validate()
    {
        $snok = $this->date > time();
        $host = explode(':', ServerInfo::get('HTTP_HOST'))[0];
        $snok &= $this->checkDomain($host);
        return $snok;
    }

    /**
     * 检测站点的域名是否已授权
     * @param $domain
     * @return bool
     */
    public function checkDomain($domain)
    {
        // 平台版本不验证域名
        if (SNUtil::isPlatformVersion() && \YZ\Core\Site\Site::getCurrentSite() != null) return true;

        if (!$this->domain) return false;
        if ("72E.NET" == strtoupper($this->domain)) return true; //72E.NET 这是一个特殊的域名，序列号是这个域名表示此序列号不限制域名

        // 判断序列号是否包含当前域名
        $result = false;
        $arr = explode(",", $this->domain);
        for ($i = 0; $i < count($arr); $i++) {
            if (trim($arr[$i]) != "" && preg_match("/(" . trim($arr[$i]) . ")$/i", $domain)) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    /**
     * 获取序列号内的产品版本号
     * @return int
     */
    public function getCurLicense()
    {
        return intval($this->version);
    }

    /**
     * 获取序列号文本表示形式
     * @return string
     */
    public function getText()
    {
        $domain = explode(':', ServerInfo::get('HTTP_HOST'))[0];
        $result = date("Y-m-d H:i:s", $this->date) . " / " . $this->domain . " / " . $domain . " / " . $this->version;
        $arr = explode(",", $this->domain);
        for ($i = 0; $i < count(arr); $i++) {
            $aBool = trim($arr[$i]) != "" && preg_match("/(" . trim($arr[$i]) . ")$/i", $domain);
            $result .= "/ " . trim($arr[$i]) . ":" . $aBool;
        }
        return $result;
    }

    /**
     * 检测当前序列号是否有某个权限
     * @param $p 要检测的权限值
     * @return bool
     */
    public abstract function hasPermission($p) : bool;

    /**
     * 获取当前序列号的产品版本的文字表示形式
     * @return string
     */
    public abstract function getCurLicenseText();

    /**
     * 获取当前序列号有哪些权限
     * @param $returnName int 是否返回权限的名称而不是权限的值，一般用于前端项目的权限判断或友好提示这种，用数字值不好理解
     * @return array
     */
    public abstract function getPermission($returnName = 0) : array;
}