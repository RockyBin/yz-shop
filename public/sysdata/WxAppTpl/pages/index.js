const app = getApp()
var CONFIG = require("../config.js");
Page({
  data: {
    wburl: null,
    shareurl: CONFIG['SITEBASEURL'],
    title: '',
    invite: 0,
    wbdata:{}
  },
  onShareAppMessage(e){
    var url = this.data.shareurl;
    if (url){
      if (this.data.invite && url.indexOf('invite=' + this.data.invite) == -1) {
        url += (url.indexOf('?') == -1 ? "?" : "&") + "invite=" + this.data.invite;
      }
      url = url.replace('&inviteNext=1','');
      url = url.replace("http://","https://"); //网页那边不知道哪里会从https跳到了http，这里先作下替换，保证分享出来的地址是https的
      url = url.replace(/(\/#\/)$/,"/"); //坑爹的apache，当网址是以 /vuehash/ 结尾时，还是会自动跳一下，因为https是放在CDN处的，apache还是因为当前链接是http的，结果导致跳出https了，这时先替换一下，保证URL不会以 /vuehash/ 结尾

      /**
       * 可能需要特殊处理的分享链接
       * 经销商授权邀请 /#/dealer/dealer-invite-show?inviteLevel=25&invite=69
       * 购物车 /#/product/shopping-cart
       */
      //url = url.replace('/dealer/dealer-invite-show','/cloudstock/cloud-center');

      console.log('share url = ',url,encodeURIComponent(url.replace("#","vuehash")));
      return{
        title: this.data.title ? this.data.title : CONFIG['APPTITLE'],
        path: 'pages/index?url=' + encodeURIComponent(url.replace("#","vuehash"))
      }
    }else{
      return {
        title: CONFIG['APPTITLE'],
        path: 'pages/index'
      }
    }
  },
  onLoad: function (options) {
    var that = this;
    if (options['url']){
      var url = decodeURIComponent(options['url']);
      url = url.indexOf("https://") == -1 ? CONFIG['SITEBASEURL'] + url : url;
      that.setData({
        'wburl': url
      });
    } else if (options['scene']){
      that.parseScene(decodeURIComponent(options['scene']));
    } else {
      that.setData({
        'wburl': CONFIG['SITEBASEURL']
      });
    }

    setInterval(function(){
      wx.setNavigationBarTitle({
        title: CONFIG['APPTITLE'] || (app.globalData.configInfo.shop_config ? app.globalData.configInfo.shop_config.name : '')
      })
    }, 50);
  },
  /**
   * 因为小程序的临时二维码的场景值最多只能是32位字符,所以生成二维码时,场景值都是简写的,
   * 这里要将场景值翻译为正常的URL
   * @param {*} scene 
   */
  parseScene: function(scene){
    var that = this;
    app.sendRequest({
      url: '/shop/front/wxapp/scene/tourl',
      method: 'GET',
      hideLoading: true,
      data: {
        scene: scene
      },
      success: function (res) {
        if (res.code == 200) {
          var url = res.data.url;
          url = url.indexOf("https://") == -1 ? CONFIG['SITEBASEURL'] + url : url;
          console.log(url);
          that.setData({
            'wburl': url
          });
        } else {
          app.showModal({
            content: '获取场景URL失败:' + JSON.stringify(res)
          });
          that.setData({
            'wburl': CONFIG['SITEBASEURL']
          });
        }
      },
      fail: function (res) {
        app.showModal({
          content: '获取场景URL失败:' + JSON.stringify(res)
        });
        that.setData({
          'wburl': CONFIG['SITEBASEURL']
        });
      }
    });
  },
  onWebViewLoad:function(e){
    this.getWebViewUrl();
  },
  getWebViewUrl: function () {
    var that = this;
    wx.createSelectorQuery().select('#webview').fields({
      properties: ['src'],
    }, function (res) {
      console.log(res.src);
      that.setData({
        'shareurl': decodeURI(res.src)
      });
    }).exec();
  },
  onMessage: function(e){
    console.log("浏览器传过来的数据",e);
    this.setData({ wbdata: e.detail.data});
    for(var i in e.detail.data){
      for(var k in e.detail.data[i]) {
        if( k == 'invite') {
          this.setData({ invite: e.detail.data[i][k]});
          console.log("invite id = ",e.detail.data[i][k]);
        }
        if( k == 'title') {
          this.setData({ title: e.detail.data[i][k]});
          console.log("title = ",e.detail.data[i][k]);
        }
        if( k == 'shareurl') {
          this.setData({ shareurl: e.detail.data[i][k]});
          console.log("shareurl = ",e.detail.data[i][k]);
        }
      }
    }
  }
})
