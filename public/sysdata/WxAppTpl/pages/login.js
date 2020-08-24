const app = getApp()
var CONFIG = require("../config.js");
Page({
  data: {
    backurl: "",
    logo: "",
    name: ""
  },
  onLoad: function (options) {
    if (options['backurl']) {
      this.setData({
        backurl: decodeURIComponent(options['backurl'])
      });
    }

    var shopConfig = app.globalData.configInfo.shop_config;
    var name = shopConfig ? shopConfig.name : CONFIG['APPTITLE'];
    var logo = "images/icon-store.gif";
    //var logo = "https://llshop2.72dns.com/comdata/0000/1/shopsite/1582604224.JPG";
    if(shopConfig && shopConfig.logo){
      logo = shopConfig.logo;
      logo = logo.indexOf("https://") == -1 && logo.indexOf("http://") == -1 ? CONFIG['SITEBASEURL'] + logo : logo;
    }
    this.setData({
      logo: logo,
      name: name
    });
  },

  goBack: function () {
    wx.navigateBack({
      complete: (res) => { },
    })
  },

  doMemberLogin(encmobile, success) {
    var that = this;
    app.sendRequest({
      url: '/shop/member/login/dobind',
      method: 'POST',
      hideLoading: true,
      data: {
        encmobile: encmobile,
        session_key: app.getSessionKey()
      },
      success: function (res) {
        if (res.code == 200) {
          success(res.data);
        } else {
          wx.showToast({
            title: res.msg,
            icon: "none"
          })
        }
      },
      fail: function (res) {
        that.showModal({
          title: '提示',
          content: '登录失败' + (res ? '：' + res.msg : '')
        });
      }
    });
  },

  onGetPhoneNumber: function (event) {
    var that = this;
    var success = function (data) {
      //that.doMemberLogin(data['encPurePhoneNumber'], function () {
        var loginRedirect = CONFIG['SITEBASEURL'] +"/shop/front/#/member/member-center";
        if(that.data.backurl){
          loginRedirect = that.data.backurl.indexOf("https://") == -1 ? CONFIG['SITEBASEURL'] + that.data.backurl : that.data.backurl;
          loginRedirect = encodeURIComponent(loginRedirect);
        }
        loginRedirect = loginRedirect.replace("http://","https://");
        var loginurl = CONFIG['SITEBASEURL'] + "/shop/member/login/dobind?encmobile="+ encodeURIComponent(data['encPurePhoneNumber']) +"&jump=1&session_key="+ encodeURIComponent(app.getSessionKey()) +"&loginRedirect="+ loginRedirect;
        wx.reLaunch({
          url: 'index?url=' + encodeURIComponent(loginurl)
        })
      //});
    }

    var fail = function () {
      app.login();
      app.showModal({
        content: "获取手机失败，请点击授权按钮重试"
      });
    }

    if (event.detail.encryptedData) {
      app.getMobile(event.detail.iv, event.detail.encryptedData, success, fail);
    }
  }
})
