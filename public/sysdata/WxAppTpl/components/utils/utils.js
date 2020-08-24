import globalData from './globalData.js';
/**
  * 显示 toask 提示
  */
export const showToast = function (param) {
  wx.showToast({
    title: param.title,
    icon: param.icon,
    duration: param.duration || 1500,
    success: function (res) {
      typeof param.success == 'function' && param.success(res);
    },
    fail: function (res) {
      typeof param.fail == 'function' && param.fail(res);
    },
    complete: function (res) {
      typeof param.complete == 'function' && param.complete(res);
    }
  })
}
/**
  * 隐藏 toask 提示
  */
export const hideToast = function () {
  wx.hideToast();
}
/**
   * 显示模态提示框
   * @param Object param 
   */
export const showModal = function (param) {
  wx.showModal({
    title: param.title || '提示',
    content: param.content,
    showCancel: param.showCancel || false,
    cancelText: param.cancelText || '取消',
    cancelColor: param.cancelColor || '#000000',
    confirmText: param.confirmText || '确定',
    confirmColor: param.confirmColor || '#3CC51F',
    success: function (res) {
      if (res.confirm) {
        typeof param.confirm == 'function' && param.confirm(res);
      } else {
        typeof param.cancel == 'function' && param.cancel(res);
      }
    },
    fail: function (res) {
      typeof param.fail == 'function' && param.fail(res);
    },
    complete: function (res) {
      typeof param.complete == 'function' && param.complete(res);
    }
  })
}

/**
   * 获取请求需要用到的 cookie
   */
export const getCookie = function () {
  var cookies = "";
  for (var k in globalData.cookies) {
    cookies += k + "=" + globalData.cookies[k] + ";"
  }
  return cookies;
}

export const getAppId = function () {
  return globalData.appId;
}
