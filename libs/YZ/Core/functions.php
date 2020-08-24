<?php


/**
 * 获取当前请求使用的协议
 * @return string
 */
function getHttpProtocol()
{
    $protocol = 'http';
    if (\YZ\Core\Common\ServerInfo::get("HTTPS") == 'on') $protocol = 'https';
    elseif (\YZ\Core\Common\ServerInfo::get('HTTP_X_SSL_PROXY') == 'true') $protocol = 'https';
    return $protocol;
}

/**
 * 获取当前访问网站的域名（过滤了端口号）
 *
 * @return string
 */
function getHttpHost()
{
    return explode(':', \YZ\Core\Common\ServerInfo::get('HTTP_HOST'))[0];
}

/***
 * 获取用户端的IP地址
 * @return string
 */
function getClientIP()
{
    $ip = \YZ\Core\Common\ServerInfo::get("HTTP_X_FORWARDED_FOR");
    if ($ip == '') $ip = \YZ\Core\Common\ServerInfo::get("REMOTE_ADDR");
    $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

/**
 * 获取当前的毫秒数
 * @param bool $isfull 返回完整的时间戳，还是只返回毫秒部分
 * @return float
 */
function getMillisecond($isfull = true)
{
    list($microsecond, $time) = explode(' ', microtime()); //' '中间是一个空格
    if (!$isfull) return (float)sprintf('%.0f', (floatval($microsecond)) * 1000);
    return (float)sprintf('%.0f', (floatval($microsecond) + floatval($time)) * 1000);
}

/**
 * 生成UUID
 * @param int $maxlen UUID的最大长度，如果为0，表示最大长度为原始的UUID长度
 * @return string
 * @throws Exception
 */
function genUuid($maxlen = 0)
{
    $uuid = str_replace('-', '', Uuid::generate());
    // 之前直接截取的写法会有重复的问题 这里再随机一次
    $uuidLen = strlen($uuid);
    $maxlen = $maxlen ?: $uuidLen;
    $subLen = $uuidLen - 1;
    $resUuid = "";
    while (strlen($resUuid) < $maxlen) {
        $resUuid .= $uuid[random_int(0, $subLen)];
    }
//    if ($maxlen) $uuid = substr($uuid, 0, $maxlen);
    return $resUuid;
}

/**
 * 获取当前终端类型（如微信公众号，H5网站，PC等）
 */
function getCurrentTerminal()
{
    $type = \YZ\Core\Constants::TerminalType_Unknown;
    if (\Ipower\Common\UserAgent::isWxOfficialAccount()) $type = \YZ\Core\Constants::TerminalType_WxOfficialAccount;
    elseif (\Ipower\Common\UserAgent::isWxWork()) $type = \YZ\Core\Constants::TerminalType_WxWork;
    elseif (\Ipower\Common\UserAgent::isWxApp()) $type = \YZ\Core\Constants::TerminalType_WxApp;
    elseif (\Ipower\Common\UserAgent::isMobile()) $type = \YZ\Core\Constants::TerminalType_Mobile;
    elseif (\Ipower\Common\UserAgent::isPC()) $type = \YZ\Core\Constants::TerminalType_PC;
    return $type;
}

/**
 * 生成用于 json API 的返回数组
 * @param int $code 结果代码，200代表成功，5xx=表示服务器处理错误，4xx=表示客户端参数错误
 * @param string $msg 操作结果的消息说明
 * @param array $data 操作结果的数据（可选）
 * @return array
 */
function makeApiResponse($code, $msg, $data = [])
{
    $ret = ['code' => $code, 'msg' => $msg, 'data' => $data];
    return $ret;
}

/**
 * 生成代表成功的用于 json API 的返回数组
 * @param string $msg
 * @param array $data
 * @return array
 */
function makeApiResponseSuccess($msg, $data = [])
{
    return makeApiResponse(200, $msg, $data);
}

/**
 * 生成代表逻辑错误的用于 json API 的返回数组
 * @param $msg
 * @param array $data
 * @return array
 */
function makeApiResponseFail($msg, $data = [])
{
    return makeApiResponse(400, $msg, $data);
}

/**
 * 生成代表成功的用于 json API 的返回数组
 * @param \Exception $exception 异常对象
 * @param array $data
 * @return array
 */
function makeApiResponseError(\Exception $exception, $data = [])
{
    $code = $exception->getCode() ?: 500;
    return makeApiResponse($code, $exception->getMessage(), $data);
}

/**
 * 生成用于业务层的返回结果数组
 * @param int $code 结果代码，200代表成功，5xx=表示服务器处理错误，4xx=表示客户端参数错误
 * @param string $msg 操作结果的消息说明
 * @param array $data 操作结果的数据（可选）
 * @return array
 */
function makeServiceResult($code, $msg = '', $data = [])
{
    $ret = ['code' => $code, 'msg' => $msg, 'data' => $data];
    return $ret;
}

/**
 * 生成用于业务层的返回成功结果数组
 * @param string $msg
 * @param array $data
 * @return array
 */
function makeServiceResultSuccess($msg = '', $data = [])
{
    return makeServiceResult(200, $msg, $data);
}

/**
 * 生成用于业务层的返回失败结果数组
 * @param $msg
 * @param array $data
 * @return array
 */
function makeServiceResultFail($msg, $data = [])
{
    return makeServiceResult(400, $msg, $data);
}

/**
 * 生成用于 json API 的返回数组并输出
 * @param $code 结果代码，200代表成功，5xx=表示服务器处理错误，4xx=表示客户端参数错误
 * @param $msg 操作结果的消息说明
 * @param $data 操作结果的数据（可选）
 */
function echoApiResponse($code, $msg, $data = [])
{
    $ret = makeApiResponse($code, $msg, $data);
    echo json_encode($ret);
}

function number_format2($data, int $decimals = 0)
{
    return str_replace(",", '', number_format($data, $decimals));
}

/**
 * 钱相关的 元转分
 * @param int $price
 * @return string
 */
function moneyYuan2Cent($price = null)
{
    $price = $price ?: 0;
    return intval(bcmul($price, 100));
}

/**
 * 钱相关的 分转元
 * @param int $price
 * @return string
 */
function moneyCent2Yuan(int $price = null)
{
    $price = $price ?: 0;
    return bcdiv($price, 100, 2);
}

/**
 * 钱相关的 乘法计算(分-->分)
 * @param int $money 单位分
 * @param int $discount $discount
 * @return int
 */
function moneyMul(int $money = null, $discount = 1)
{
    $money = $money ?: 0;
    return intval(bcmul($money, $discount));
}

/**
 * 生成订单号
 */
function generateOrderId()
{
    return date('YmdHis') . randInt(1000, 9999);
}

/**
 * 生成售后单号
 * @return string
 */
function generateAfterSaleId()
{
    return date('YmdHis') . randInt(1000, 9999);
}

/**
 * 生成随机数
 * @param int $min
 * @param int $max
 * @return int
 */
function randInt($min = 0, $max = 9999)
{
    return intval($min + mt_rand() / mt_getrandmax() * ($max - $min));
}

/**
 * 生成随机数
 * @param $length
 * @return string
 */
function randString($length)
{
    return strtolower(str_random($length));
}

/**
 * 判断变量是否为空字符串或 null
 * @param $string
 * @return bool
 */
function isNullOrEmpty($string)
{
    return $string === '' || $string === null;
}

/**
 * 扩展 php 的 explode 函数，去除字符串前和后的 $delimiter 再进行分割
 * @param string $delimiter 分隔字符
 * @param string $string 输入的字符串
 * @return array
 */
function myExplode(string $delimiter, string $string)
{
    return explode($delimiter, preg_replace('`(^\s*' . $delimiter . '\s*)|(\s*' . $delimiter . '\s*$)`', '', $string));
}

/**
 * 把对象打散为数组
 * @param $value
 * @param string $separator
 * @param null $exclude
 * @return array
 */
function myToArray($value, $separator = ',', $exclude = null)
{
    $list = [];
    if (is_array($value)) {
        $list = $value;
    } else if (is_numeric($value)) {
        $list = [$value];
    } else if (is_string($value) && !empty(trim($value))) {
        $list = explode($separator, trim($value));
    }
    // 过滤排除的数据
    if ($exclude) {
        $data = [];
        foreach ($list as $item) {
            if ($exclude != $item) {
                $data[] = $item;
            }
        }
        $list = $data;
    }
    return $list;
}

/**
 * 保存图片之前要做的处理 把不需要的图片删掉
 * @param array $originalImages 旧的图片地址数组
 * @param array $newImages 新的图片地址数组
 * @param string $rootPath 根目录 不传则使用默认站点目录
 * @return bool                 无需删除返回false 有删除返回true
 */
function beforeSaveImage($originalImages, $newImages, $rootPath = '')
{
    if (empty($originalImages)) {
        return false;
    }
    if (is_array($originalImages) && is_array($newImages)) {
        // 比对出来不需要的旧图片
        $delImages = array_diff($originalImages, $newImages);
        if (!empty($delImages)) {
            // 默认根目录
            $rootPath = $rootPath ?: \YZ\Core\Site\Site::getSiteComdataDir('', true);
            // 删掉不需要的图片
            foreach ($delImages as $image) {
                @unlink($rootPath . $image);
            }
            return true;
        }
    }
    return false;
}

/**
 * 判断是否在swoole环境下 
 *
 * @return boolean
 */
function isSwoole(){
    return (key_exists('SERVER',$_SERVER) && stripos($_SERVER['SERVER'],'swoole') !== false || stripos($_SERVER['SCRIPT_FILENAME'], 'bin/laravels') !== false);
}

/**
 * 是否在CLI模式下
 * @return bool
 */
function isInCli()
{
    $flag = strtolower(php_sapi_name()) == 'cli' && !isSwoole();
    return $flag;
}

function myexit($msg = '')
{
    if (isSwoole()) //swoole的环境
    {
		try{
			throw new Swoole\ExitException($msg);
		}catch(\Exception $ex){
		}
    }
    else //php-fpm的环境
    {
		exit($msg);
    }
}

/**
 * 把 target数组合并到 source数组
 * @param $source
 * @param $target
 */
function myArrayMerge(&$source, $target)
{
    if (is_array($source)) {
        foreach ($source as $key => &$val) {
            if (array_key_exists($key, $target)) {
                if (!is_array($target[$key]) && !is_array($val)) {
                    $val = $target[$key];
                } else if (is_array($val) && is_array($target[$key])) {
                    myArrayMerge($val, $target[$key]);
                }
            }
        }
    }
}

/**
 * 处理语言数据
 * @param $langData
 * @param $replaceData
 */
function pregReplaceForLang(&$langData, $replaceData)
{
    if (is_array($langData) && is_array($replaceData)) {
        foreach ($langData as $key => &$val) {
            if ($key == 'diy_word') continue;
            if (is_array($val)) {
                pregReplaceForLang($val, $replaceData);
            } else {
                // 正则替换
                preg_match_all("/{([^}]*)}/", $val, $matches);
                if ($matches) {
                    foreach ($matches[1] as $matchKey) {
                        $val = str_replace("{" . $matchKey . "}", $replaceData[$matchKey], $val);
                    }
                }
            }
        }
    }
}

/**
 * 获取当前时间戳，精确到毫秒
 *
 * @return float
 */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

/**
 * 获取当前时间输出，精确到毫秒
 *
 * @param [type] $format 时间格式化字符串，如 'Y年m月d日 H时i分s秒 x毫秒'
 * @param [float] $time 通过 microtime_float() 获得
 * @return void
 */
function microtime_format($format, $time = 0)
{
    if(!$time) $time = microtime_float();
    list($usec, $sec) = explode(".", $time);
    $date = date($format,$usec);
    return str_replace('x', $sec, $date);
}

/**
 * 获取当前站点的id
 * @return mixed
 */
function getCurrentSiteId()
{
    return \YZ\Core\Site\Site::getCurrentSite()->getSiteId();
}

/**
 * 获取当前站点的文件夹
 * @param bool $isFull
 * @return mixed|string
 */
function getSitePath($isFull = true)
{
    return \YZ\Core\Site\Site::getSiteComdataDir('', $isFull);
}

/**
 * 获取当前站点的防伪码数据库路径
 * @param bool $isInit  是否自动初始化
 * @return string
 */
function getSecurityDatabasePath($isInit = false)
{
    $path = getSitePath() . "/product-security";
    $database = $path . '/database.sqlite';
    if ($isInit && !file_exists($database)) {
        if (!is_dir($path)) {
            File::makeDirectory($path, 0755, true);
        }
        $databaseSys = config('database.connections.system_security_code_sqlite.database');
        File::copy($databaseSys, $database);
    }
    return $database;
}

if (!function_exists('getSameElement'))
{
    /**
     * 过滤所有相同元素
     * @param array $data
     * @return array
     */
    function getSameElement(array $data): array
    {

        $filter_key = [];

        $count = count($data);

        foreach ($data as $k => $v)
        {
            $counts = $count - 1;

            $cursor = false;

            for ($i = $k; $i <= $counts ; $i++)
            {
                if ($v == $data[$i + 1])
                {
                    if (in_array($i + 1, $filter_key) && $k != $cursor) break;

                    $cursor = $k;

                    $filter_key[] = $i+1;
                }
            }

            if (!is_bool($cursor))
            {
                $filter_key[] = $cursor;
            }
        }

        return $filter_key;
    }
}