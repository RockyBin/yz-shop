import { sendRequest, uploadRequest } from './components/utils/require.js';
import globalData from './components/utils/globalData.js';
import { appGetExtConfig } from './components/utils/getConfig.js';
import { hideToast, showModal, showToast } from './components/utils/utils.js';

App({
  onLaunch: function (options) {
    this.loadConfigInfo();
  },
  onShow: function () {
    this.login();
  },
  onHide() {

  },

  //在页面 onLoad 中调用，获取页面的地址，包括参数，并自动设置页面变量 pageurl
  getPageUrl: function (page, pageOptions) {
    var url = getCurrentPages()[getCurrentPages().length - 1].__route__;
    var query = [];
    for (var key in pageOptions) {
      query.push(key + "=" + pageOptions[key]);
    }
    if (query.length > 0) {
      var str = query.join("&");
      url += "?" + str;
    }
    page.setData({
      pageurl: url
    });
    return url;
  },

  /**
   * 调用获取用户信息的API
   * @param {*} cb 用户授权获取后的回调函数
   * @param {*} failcb 用户不允许授权时的回调函数
   */
  getUserInfo: function (cb, failcb) {
    var that = this;
    if (Object.keys(that.globalData.userInfo).length) {
      typeof cb == "function" && cb(that.globalData.userInfo)
    } else {
      var doGetUserInfo = function () {
        //调用登录接口
        var getUserInfoParams = {
          withCredentials: false,
          success: function (res) {
            that.globalData.userInfo = res.userInfo
            if (res['iv']) that.globalData.userInfo['iv'] = res.iv;
            if (res['encryptedData']) that.globalData.userInfo['encryptedData'] = res.encryptedData;
            typeof cb == "function" && cb(that.globalData.userInfo)
          },
          fail: function (res) {
            that.showModal({
              title: '提示',
              content: '获取用户信息失败：' + res.errMsg + ",小程序功能无法正常使用"
            });
            typeof failcb == "function" && failcb(res);
          },
          complete: function (res) {

          }
        };
        wx.getUserInfo(getUserInfoParams);
      }
      doGetUserInfo();
    }
  },

  /**
   * 调用小程序login接口获取 openid 和 session_key
   * @param {*} success 登录成功时的回调函数
   * @param {*} fail 登录失败时的回调函数
   */
  login: function (success, fail) {
    var that = this;
    wx.login({
      success: function (res) {
        if (res.code) {
          //发起网络请求
          that.sendRequest({
            url: '/core/member/login/wxapp/session/get',
            method: 'POST',
            hideLoading: true,
            data: {
              code: res.code
            },
            success: function (res) {
              if (res.code == 200) {
                that.initDataAfterGetSession(res.data);
                if (success) success();
              } else {
                wx.showToast({
                  title: res.msg,
                  icon: "none"
                })
                if (fail) fail();
              }
            },
            fail: function (res) {
              that.showModal({
                title: '提示',
                content: '登录失败' + (res ? '：' + res.msg : '')
              });
              if (fail) fail();
            }
          });
        } else {
          console.log('获取用户登录状态失败：' + res.errMsg)
        }
      }
    });
  },

  /**
   * 调用小程序的 session 接口后，处理返回的数据，主要是获取 session_key 的 openid
   */
  initDataAfterGetSession(data) {
    if (data['openid']) this.globalData.userInfo['openid'] = data['openid'];
    if (data['session_key']) this.setSessionKey(data['session_key']);
    if (data['expires_in']) {
      var timestamp = (new Date()).valueOf();
      this.globalData.session_expiry = timestamp + data['expires_in'];
    }
  },

  /**
   * 检测 session_key 是否已过期，如果过期，就重新获取
   */
  checkSession() {
    var sk = this.getSessionKey();
    var timestamp = (new Date()).valueOf();
    var timeout = timestamp > this.globalData.session_expiry;
    if (!sk) {
      this.login();
    };
  },

  /**
   * 获取用户绑定的手机号
   * @param {*} iv 调用 getphonenumber 接口返回的 iv 值
   * @param {*} encryptedData 调用 encryptedData 接口返回的 iv 值
   * @param {*} success 获取成功时的回调
   * @param {*} fail 获取失败时的回调
   */
  getMobile: function(iv, encryptedData, success, fail) {
    //发起网络请求
    var that = this;
    that.sendRequest({
      url: '/core/member/login/wxapp/mobile/get',
      method: 'POST',
      hideLoading: true,
      data: {
        sessionKey: that.getSessionKey(),
        iv: iv,
        encryptedData: encryptedData
      },
      success: function (res) {
        if (res.code == 200) {
          if (success) success(res.data);
        } else {
          wx.showToast({
            title: res.msg,
            icon: "none"
          })
          if (fail) fail(res.data);
        }
      },
      fail: function (res) {
        that.showModal({
          title: '提示',
          content: '获取失败' + (res ? '：' + res.msg : '')
        });
        if (fail) fail();
      }
    });
  },

  /**
   * 显示 toast 提示
   * @param Object param 
   */
  showToast,
  hideToast,
  showModal,
  /**
   * 执行有上传文件的请求
   * @param object param 
   */
  uploadRequest,
  /**
   * 执行普通的 POST GET 请求，如果有上传文件的，请使用 uploadRequest() 方法
   * @param{object param 
   */
  sendRequest,
  getSessionKey: function () {
    return this.globalData.session_key;
  },
  setSessionKey: function (session_key) {
    this.globalData.session_key = session_key;
    wx.setStorage({
      key: 'session_key',
      data: session_key
    })
  },

  //初始化一些配置信息
  initConfigInfo: function (info, fn) {
    var that = this;
    if (info['siteComdataPath']) this.globalData.siteComdataPath = info['siteComdataPath'];
    if (info['site_id']) this.globalData.siteId = info['site_id'];
    if (info['LicensePerm']) this.globalData.LicensePerm = info['LicensePerm'];
    that.globalData.configInfo = info;
    fn && fn();
  },

  /**
   * 加载一些基础配置信息
   * @param {Object} fn 回调函数对象
   */
  loadConfigInfo: function (fn) {
    var that = this;
    if (that['configInfoLoaded']) return;
    if (that['loadingConfigInfo']) return;
    that['loadingConfigInfo'] = true;
    that.sendRequest({
      url: '/shop/front/site/info',
      method: 'GET',
      hideLoading: true,
      success: function (res) {
        that['loadingConfigInfo'] = false;
        if (res.code != 200) {
          console.log('loadConfigInfo fail：' + res.msg);
        } else {
          that.initConfigInfo(res.data, fn);
          that['configInfoLoaded'] = true;
        }
      },
      fail: function (res) {
        that['loadingConfigInfo'] = false;
        console.log('loadConfigInfo fail');
      }
    })
  },

  /**
   * 将接口返回的图片等用户上传的资源地址替换为绝对地址
   * @param {string} url 
   */
  getAbsoluteUrl: function (url) {
    if (url instanceof Array) {
      for (var k in url) {
        var tmp = url[k].replace(/http(s)?:\/\/[0-9a-z\-\.]+/gi, '');
        tmp = this.globalData.siteBaseUrl + tmp;
        url[k] = tmp;
      }
    } else if (typeof url == 'string') {
      url = url.replace(/http(s)?:\/\/[0-9a-z\-\.]+/gi, '');
      url = this.globalData.siteBaseUrl + url;
    }
    return url;
  },

  /**
 * 检查版本权限
 */
  hasLicensePerm: function (perm) {
    var LicensePerm = this.globalData.LicensePerm;
    var arr = perm.toString().split(",");
    for (var i = 0; i < LicensePerm.length; i++) {
      for (var j = 0; j < arr.length; j++) {
        if (
          arr[j].toLowerCase().trim() ===
          LicensePerm[i].toLowerCase().trim()
        ) {
          return true;
        }
      }
    }
    return false;
  },
  /**
   * 通用运行时全局数据对象,在系统中很多地方需要用到
   */
  globalData
})