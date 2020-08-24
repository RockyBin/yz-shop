<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'Controller@index');

Route::get('/test',"TestController@index");
Route::get('/ttt',"TestController@index");
Route::get('/testmember',"TestController@testMember");
Route::get('/testfin',"TestController@testfin");
Route::get('/testwechat',"TestController@testwechat");
Route::get('/wechatauth',"TestController@wechatauth");

//以下是正式的路由
//语言JSON
Route::any('/lang/json/get',"LangJsonController@index");

//会员授权登录部分
Route::any('/core/member/login',"Member\LoginController@index"); // 会员登录表单
Route::any('/core/member/login/mobile/signin', "Member\LoginController@mobileCodeLogin"); // 手机验证码登录或注册
Route::any('/core/member/login/password/signin', "Member\LoginController@passwordLogin"); // 密码登录
Route::any('/core/member/login/password/reset', "Member\LoginController@resetPassword"); // 修改密码

Route::any('/core/member/login/wxlogin',"Member\LoginController@wxLogin"); //微信授权登录
Route::any('/core/member/login/wxlogincallback',"Member\LoginController@wxLoginCallBack"); //微信授权回调地址

Route::any('/core/member/login/wxlogin/wxwork',"Member\LoginController@wxWorkLogin"); //企业微信授权登录
Route::any('/core/member/login/wxlogincallback/wxwork',"Member\LoginController@wxWorkLoginCallBack"); //企业微信授权回调地址

Route::any('/core/member/login/wxscanlogin',"Member\LoginController@wxScanLogin"); //获取微信扫码授权相关的二维码信息
Route::any('/core/member/login/wxscancheck',"Member\LoginController@wxCheckHasScan"); //检测微信是否已经扫码
Route::any('/core/member/login/wxscanlogincallback/{scanid}',"Member\LoginController@wxScanLoginCallback"); //微信扫码授权回调地址(手机微信用)

Route::any('/core/member/login/wxapp/session/get',"Member\LoginController@wxAppGetSession"); //获取微信小程序的openid 和 session_key
Route::any('/core/member/login/wxapp/mobile/get',"Member\LoginController@wxAppGetMobile"); //获取微信小程序用户绑定的手机

Route::any('/core/member/login/qqlogin',"Member\LoginController@qqLogin"); //QQ授权登录
Route::any('/core/member/login/qqcallback',"Member\LoginController@qqLoginCallBack"); //QQ授权回调地址

Route::any('/core/member/login/alipaylogin',"Member\LoginController@alipayLogin"); //支付宝授权登录
Route::any('/core/member/login/alipaycallback',"Member\LoginController@alipayLoginCallBack"); //支付宝授权回调地址

Route::any('/core/member/login/bind',"Member\LoginController@showBind"); //显示会员绑定公众号，支付宝等授权帐号的界面
Route::any('/core/member/login/dobind',"Member\LoginController@doBind"); //会员绑定公众号，支付宝等授权帐号

//支付相关
Route::any('/core/member/payment/getsandbox',"Member\PayController@getSandboxInfo"); //获取沙箱的相关信息
Route::any('/core/member/payment/choose',"Member\PayController@doChoose"); //选择支付方式
Route::any('/core/member/payment/dopay',"Member\PayController@doPay"); //执行支付

Route::any('/core/member/payment/weixinpaycheckscan/{orderid?}/{memberid?}/{callback?}/{apptype?}',"Member\PayController@doWeixinPayCheckScan"); //微信扫码支付是否扫码检测
//Route::any('/core/member/payment/weixinpayreturn/{orderid?}/{memberid?}/{callback?}/{apptype?}',"Member\PayController@doWeixinPayReturn"); //微信支付回调
Route::any('/core/member/payment/weixinpaynotify/{orderid?}/{memberid?}/{callback?}/{apptype?}',"Member\PayController@doWeixinPayNotify"); //微信支付通知

Route::any('/core/member/payment/alipayreturn/{orderid?}/{memberid?}/{callback?}',"Member\PayController@doAlipayReturn"); //支付宝回调
Route::any('/core/member/payment/alipaynotify/{orderid?}/{memberid?}/{callback?}',"Member\PayController@doAlipayNotify"); //支付宝通知

Route::any('/core/member/payment/tlpayreturn/{orderid?}/{memberid?}/{callback?}',"Member\PayController@doTLPayReturn"); //通联支付回调
Route::any('/core/member/payment/tlpaynotify/{orderid?}/{memberid?}/{callback?}',"Member\PayController@doTLPayNotify"); //通联支付通知

//验证码相关
Route::any('/core/common/verifycode',"Common\VerifyCodeController@index"); //图片验证码
Route::any('/core/common/verifycode/check',"Common\VerifyCodeController@check"); //检测图片验证码
Route::any('/core/common/verifycode/smscode',"Common\VerifyCodeController@getSmsCode"); //获取短信验证码
Route::any('/core/common/verifycode/smscode/check',"Common\VerifyCodeController@checkSmsCode"); //检测短信验证码

//视频地址提取工具
Route::any('/core/common/videoutil/src',"Common\VideoUtilController@getSrc"); //获取抖音、快手等的视频地址

//二维码工具
Route::any('/core/common/qrcode/wxapp',"Common\QrcodeController@wxAppQrcode"); //生成小程序码

//txt html 各种验证文件处理
Route::any('/{name}.txt',"Common\VerifyFileController@index");
Route::any('/{name}.html',"Common\VerifyFileController@index");
Route::any('/{name}.htm',"Common\VerifyFileController@index");
Route::any('/.well-known/pki-validation/{name}.txt',"Common\VerifyFileController@index");
Route::any('/.well-known/pki-validation/{name}.html',"Common\VerifyFileController@index");
Route::any('/.well-known/pki-validation/{name}.htm',"Common\VerifyFileController@index");

//公众号通信
Route::any('/core/wechat/index',"Wechat\WechatController@index");
Route::any('/core/wechat/qrcode',"Wechat\WechatController@qrcode"); //生成带参二维码

//企业微信相关
Route::any('/core/wxwork/open/callback/data/{suite_id?}',"WxWork\Open\DataCallbackController@index"); //企业微信应用数据回调URL
Route::any('/core/wxwork/open/callback/command/{suite_id?}',"WxWork\Open\CommandCallbackController@index"); //企业微信应用指令回调URL
Route::any('/core/wxwork/open/callback/systemevent/{corp_id?}',"WxWork\Open\SystemEventCallbackController@index"); //企业微信服务商系统事件接收URL
Route::any('/core/wxwork/open/install',"WxWork\Open\PreAuthController@install"); //授权安装应用
Route::any('/core/wxwork/open/install/redirect',"WxWork\Open\PreAuthController@redirect"); //授权安装应用后的回调入口
Route::any('/core/wxwork/serve',"WxWork\ServeController@index"); //自建应用的接收消息入口

//我们自己的管理后台
Route::any('/core/sysmanage/xmlapi',"SysManage\XmlApiController@index"); //与主站的通信接口
Route::any('/core/sysmanage/login',"SysManage\LoginController@login"); //管理登录
Route::any('/core/sysmanage/logout',"SysManage\LoginController@logout"); //管理员退出
Route::any('/core/sysmanage/getuserinfo',"SysManage\LoginController@getUserInfo"); //获取管理员信息
Route::any('/core/sysmanage/home',"SysManage\HomeController@index"); //72ad后台首页数据
Route::any('/core/sysmanage/site/getlist',"SysManage\Site\SiteController@getlist"); //获取网站列表
Route::any('/core/sysmanage/site/add',"SysManage\Site\SiteController@addSite"); //添加网站
Route::any('/core/sysmanage/site/delete',"SysManage\Site\SiteController@deleteSite"); //删除网站
Route::any('/core/sysmanage/site/clear',"SysManage\Site\SiteController@clearSite"); //清除网站数据
Route::any('/core/sysmanage/site/getinfo',"SysManage\Site\SiteController@getSiteInfo"); //获取网站信息
Route::any('/core/sysmanage/site/edit',"SysManage\Site\SiteController@editSite"); //修改网站信息
Route::any('/core/sysmanage/site/login',"SysManage\Site\SiteController@loginSite"); //登录指定站点

Route::any('/core/sysmanage/admin/getlist',"SysManage\Admin\AdminController@getlist"); //获取管理员列表
Route::any('/core/sysmanage/admin/add',"SysManage\Admin\AdminController@addUser"); //添加管理
Route::any('/core/sysmanage/admin/delete',"SysManage\Admin\AdminController@deleteUser"); //删除管理
Route::any('/core/sysmanage/admin/getinfo',"SysManage\Admin\AdminController@getUserInfo"); //获取管理站信息
Route::any('/core/sysmanage/admin/edit',"SysManage\Admin\AdminController@editUser"); //修改管理信息

//ssl证书相关
Route::any('/core/sysmanage/ssl/getcode',"SysManage\Ssl\SslConfigController@getExecCode"); //获取配置SSL的执行代码
Route::any('/core/sysmanage/ssl/getfile',"SysManage\Ssl\SslConfigController@getFile"); //下载SSL证书
Route::any('/core/sysmanage/ssl/getlist',"SysManage\Ssl\SslConfigController@getList"); //获取SSL证书列表

//通用的后台管理功能，如支付设置，微信相关设置等
//验证文件相关
Route::any('/core/siteadmin/settings/verifyfile/add',"SiteAdmin\Settings\VerifyFileController@add"); //添加验证文件
Route::any('/core/siteadmin/settings/verifyfile/edit',"SiteAdmin\Settings\VerifyFileController@edit"); //修改验证文件
Route::any('/core/siteadmin/settings/verifyfile/delete',"SiteAdmin\Settings\VerifyFileController@delete"); //修改验证文件
Route::any('/core/siteadmin/settings/verifyfile/list',"SiteAdmin\Settings\VerifyFileController@list"); //列出验证文件

//资源管理器相关
Route::any('/core/siteadmin/resourcemanage/folder/add',"SiteAdmin\ResourceManage\FolderController@add"); //添加文件夹
Route::any('/core/siteadmin/resourcemanage/folder/edit',"SiteAdmin\ResourceManage\FolderController@rename"); //修改文件夹
Route::any('/core/siteadmin/resourcemanage/folder/rename',"SiteAdmin\ResourceManage\FolderController@rename"); //文件夹改名
Route::any('/core/siteadmin/resourcemanage/folder/delete',"SiteAdmin\ResourceManage\FolderController@delete"); //删除文件夹
Route::any('/core/siteadmin/resourcemanage/folder/list',"SiteAdmin\ResourceManage\FolderController@getList"); //列出文件夹

Route::any('/core/siteadmin/resourcemanage/file/upload',"SiteAdmin\ResourceManage\FileController@upload"); //上传文件
Route::any('/core/siteadmin/resourcemanage/file/delete',"SiteAdmin\ResourceManage\FileController@delete"); //删除文件
Route::any('/core/siteadmin/resourcemanage/file/list',"SiteAdmin\ResourceManage\FileController@getList"); //列出文件

//站点SSL管理
Route::any('/core/siteadmin/setting/ssl/list',"SiteAdmin\Settings\SslCertController@getList"); //获取SSL证书列表
Route::any('/core/siteadmin/setting/ssl/edit',"SiteAdmin\Settings\SslCertController@edit"); //添加修改证书
Route::any('/core/siteadmin/setting/ssl/delete',"SiteAdmin\Settings\SslCertController@delete"); //删除证书
Route::any('/core/siteadmin/setting/ssl/check',"SiteAdmin\Settings\SslCertController@check"); //检测证书是否生效