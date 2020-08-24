const app = getApp()
var CONFIG = require("../config.js");
Page({
  data: {
    userInfo: {}
  },
  getUserInfo: function(e){
    this.setData('userInfo',e.detail.userInfo);
  }
})
