//获取程序的额外配置，兼容第三方平台及非第三方平台
export const appGetExtConfig = function () {
  var c = wx.getExtConfigSync();
  if (c['ext']) return c['ext']; //当使用第三方平台时，以 getExtConfigSync()的 ext 节点为准
  else return require("../../config.js");
}