<?php
namespace YZ\Core\Member;
use Illuminate\Support\Facades\Cookie;

/**
 * 微信公众号，支付宝帐号等授权方式拿到的用户信息封装，方便外层统一数据格式
 * Class AuthUserInfo
 * @package YZ\Core\Member
 */
class AuthUserInfo
{
    /**
     * @var string openid，对于微信来讲，就是openid,对于支付宝来讲，它代表是的支付宝的用户号
     */
    public $openid = '';
    /**
     * @var string 昵称
     */
    public $nickname = '';
    /**
     * @var string 名字
     */
    public $name = '';
    /**
     * @var string 头像地址
     */
    public $headurl = '';
    /**
     * @var array 扩展信息，可能不同的授权方式拿到的数据格式会不一样
     */
    public $extInfo = [];

    public function __construct($openid,$nickname = '',$name = '',$headurl = '',$extInfo = null)
    {
        $this->openid = $openid;
        if($nickname) {
            $this->nickname = $nickname;
            Cookie::queue('auth_name', $this->nickname, 0, $path = '/', null, null, false, false);
        }
        if($name) {
            $this->name = $name;
        }
        if($headurl) {
            $this->headurl = $headurl;
            Cookie::queue('auth_headurl', $this->headurl, 0, $path = '/', null, null, false, false);
        }
        if($extInfo) $this->extInfo = $extInfo;
    }
}