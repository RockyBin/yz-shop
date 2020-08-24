const app = getApp();
var CONFIG = require("../config.js");
Page({

  /**
   * 页面的初始数据
   */
  data: {
    payInfo: {},
    receiver: ""
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var that = this;
    that.setData({
      receiver: app.globalData.configInfo.shop_config ? app.globalData.configInfo.shop_config.name : CONFIG['APPTITLE']
    });
    if (options['data']) {
      var data = JSON.parse(decodeURIComponent(options['data']));
      console.log("data from html:", data);
      data = data.result;
      data.json = JSON.parse(data.json);
      this.setData({
        payInfo: data
      });
      console.log('payInfo:', this.data.payInfo);
      //this.goBack(); return;
    }
  },

  doPay: function(){
    var that = this;
    wx.requestPayment({
      appId: this.data.payInfo.json.appId,
      timeStamp: this.data.payInfo.json.timeStamp,
      nonceStr: this.data.payInfo.json.nonceStr,
      package: this.data.payInfo.json.package,
      signType: this.data.payInfo.json.signType,
      paySign: this.data.payInfo.json.paySign,
      success(res) {
        app.showToast({
          title: '支付成功'
        });
        setTimeout(function () { that.goBack(); }, 1000);
      },
      fail(res) {
        app.showModal({
          title: '提示',
          content: '支付失败' + (res ? '：' + res.errMsg : '')
        });
      }
    });
  },

  goBack: function () {
    var that = this;
    var redirect = CONFIG['SITEBASEURL'] + "/shop/front/#/member/member-center";
    if (that.data.payInfo.backurl) {
      var backurl = that.data.payInfo.backurl;
      if(backurl.indexOf('/shop/front/') == -1) backurl = '/shop/front/' + backurl;
      redirect = backurl.indexOf("https://") == -1 ? CONFIG['SITEBASEURL'] + backurl : backurl;
    }
    redirect = redirect.replace("http://", "https://");
    redirect = redirect + (redirect.indexOf("?") > -1 ? "&" : "?") + "backfromwxapp=1";
    console.log("pay redirect",redirect);
    wx.reLaunch({
      url: 'index?url=' + encodeURIComponent(redirect)
    })
  }
})