import * as utils from './utils.js';
import { appGetExtConfig } from './getConfig.js';
import globalData from './globalData.js';
import { initGlobalData } from './globalData.js';
let http = {};
let currentRequire = new Date(); // 标识是否是最后一次请求，用于判断登录失效只跳转一次
var ErrorData = require('./ErrorData.js');
var CONFIG = appGetExtConfig();

/**
   * 保存请求的cookie
   * @param {response} response 网络请求结果对象 
   */
const saveCookie = function (response) {
  var cookies = response.cookies;
  var arr = {};
  var hasCookie = false;
  for (var k in cookies) {
    var item = cookies[k];
    if (item.indexOf(';') > -1) item = item.substr(0, item.indexOf(';'));
    var name = item.substr(0, item.indexOf("="));
    var value = item.substr(item.indexOf("=") + 1);
    arr[name] = value;
    hasCookie = true;
  }
  if (hasCookie) globalData.cookies = arr;
}
/**
  * 通用Ajax请求, 主要是供 sendRequest() 调用
  * @param Object 请求参数 
 */
const commonAjax = function (ops) {
  var isError = false; // 判断请求是否有错误
  var statusCodeNum = 0; // 状态码
  var headerData = {};  // 请求成功后的响应头
  var errMsgText = ''; // 错误信息
  let currentTime = currentRequire = new Date();
  if (!ops.header) ops.header = { 'content-type': 'application/json' };
  ops.header['cookie'] = utils.getCookie();
  wx.request({
    url: ops.requestUrl,
    data: ops.data,
    method: ops.method || 'GET',
    header: ops.header || {
      'content-type': 'application/json'
    },
    success: function (res) {
      statusCodeNum = res.statusCode;
      if (statusCodeNum == 404) {
        isError = true;
        errMsgText = JSON.stringify(res.data);
        typeof ops.fail == 'function' && ops.fail(res);
        return;
      }
      headerData = res.header;
      if (res.data && res.data.code != 200 && res.data.code != 401 && res.data.code != 403) {
        isError = true;
        errMsgText = JSON.stringify(res.data);
      }
      saveCookie(res);
      //这里应该对401状态码进行判断,自动跳转登录页
      if (res.data.code == 401) {
        if (currentTime === currentRequire) {
          initGlobalData();
          wx.reLaunch({
            url: '/pages/login/index',
          });
        }
      }
      typeof ops.success == 'function' && ops.success(res);
    },
    fail: function (res) {
      isError = true;
      errMsgText = res.errMsg;
      typeof ops.fail == 'function' && ops.fail(res);
    },
    complete: function (res) {
      typeof ops.complete == 'function' && ops.complete(res.data);
      if (isError) {
        ErrorData.reportError({
          appId: CONFIG['APPID'],
          appTitle: CONFIG['APPTITLE'],
          appSecret: CONFIG['APPSECRET'] || '',
          programType: 1,
          siteBaseUrl: CONFIG['SITEBASEURL'], // 基础 url 在 config 设置的
          errMsgData: errMsgText, // 错误信息捕获
          InitSiteID: globalData.siteId || 0,
          requestData: {
            url: ops.requestUrl || '',
            data: JSON.stringify(ops.data),
            method: ops.method || 'GET',
            header: JSON.stringify((headerData || {})),
            statusCode: statusCodeNum
          }
        })
      }
    }
  });
};
/**
  * 处理POST提交的参数，以便符合PHP的规范，特别是关于数组的
  * @param Object obj 
*/
const modifyPostParam = function (obj) {
  var query = '',
    name, value, fullSubName, subName, subValue, innerObj, i;

  for (name in obj) {
    value = obj[name];

    if (value instanceof Array) {
      for (i = 0; i < value.length; ++i) {
        subValue = value[i];
        fullSubName = name + '[' + i + ']';
        innerObj = {};
        innerObj[fullSubName] = subValue;
        query += modifyPostParam(innerObj) + '&';
      }
    } else if (value instanceof Object) {
      for (subName in value) {
        subValue = value[subName];
        fullSubName = name + '[' + subName + ']';
        innerObj = {};
        innerObj[fullSubName] = subValue;
        query += modifyPostParam(innerObj) + '&';
      }
    } else if (value !== undefined && value !== null)
      query += encodeURIComponent(name) + '=' + encodeURIComponent(value) + '&';
  }

  return query.length ? query.substr(0, query.length - 1) : query;
}
/**
   * 执行普通的 POST GET 请求，如果有上传文件的，请使用 uploadRequest() 方法
   * @param{object param 
   */
http.sendRequest = function (param) {
  var data = param.data || {},
    header = param.header,
    requestUrl;
  data.wx_app_id = utils.getAppId();
  if (globalData.siteId) {
    data.InitSiteID = globalData.siteId;
  }
  requestUrl = globalData.siteBaseUrl + param.url;
  if (param.method) {
    if (param.method.toLowerCase() == 'post') {
      data = modifyPostParam(data);
      header = header || {
        'content-type': 'application/x-www-form-urlencoded;'
      }
    }
    param.method = param.method.toUpperCase();
  }
  if (!param.hideLoading) {
    utils.showToast({
      title: '加载中...',
      icon: 'loading'
    });
  }
  commonAjax({
    requestUrl: requestUrl,
    data: data,
    method: param.method,
    header: header,
    success: function (res) {
      if (res.statusCode && res.statusCode != 200) {
        if (!param.hideLoading) utils.hideToast();
        utils.showModal({
          content: res.errMsg
        });
        return;
      }
      if (!param.hideLoading) utils.hideToast();
      typeof param.success == 'function' && param.success(res.data);
    },
    fail: function (res) {
      if (!param.chatHiddenModal) {
        utils.showModal({
          content: '请求失败 ' + res.errMsg
        })
      }
      typeof param.fail == 'function' && param.fail(res.data);
    },
    complete: function (res) {
      typeof param.complete == 'function' && param.complete(res.data);
    }
  })
}

/**
   * 执行有上传文件的请求
   * @param object param 
   */
http.uploadRequest = function (param) {
  var formData = param.data['formData'] || {},
    header = param.header || {},
    requestUrl;
  if (formData.app_id) {
  } else {
    formData.app_id = utils.getAppId();
  }
  if (globalData.siteId) {
    formData.InitSiteID = globalData.siteId;
  }
  requestUrl = globalData.siteBaseUrl + param.url;
  if (param.method) {
    if (param.method.toLowerCase() == 'post') {
      formData = modifyPostParam(formData);
      header = header || {
        'content-type': 'application/x-www-form-urlencoded;'
      }
    }
    param.method = param.method.toUpperCase();
  }
  if (!param.hideLoading) {
    utils.showToast({
      title: '加载中...',
      icon: 'loading'
    });
  }
  header['cookie'] = utils.getCookie();
  wx.uploadFile({
    url: requestUrl,
    filePath: param.data.filePath,
    name: param.data.name,
    formData: formData,
    method: param.method || 'GET',
    header: header || {
      'content-type': 'application/json'
    },
    success: function (res) {
      if (res.statusCode && res.statusCode != 200) {
        utils.hideToast();
        utils.showModal({
          content: '' + res.errMsg
        });
        typeof param.fail == 'function' && param.fail(res.data);
        return;
      }
      utils.hideToast();
      saveCookie(res);
      //这里应该对403状态码进行判断,自动跳转登录页
      if (res.data.code == 403) {
        wx.reLaunch({
          url: '/pages/login',
        });
      }
      typeof param.success == 'function' && param.success(res.data);
    },
    fail: function (res) {
      param.fail && param.fail(res.data);
      utils.showModal({
        content: '请求失败 ' + res.errMsg
      })
    },
    complete: function (res) {
      typeof param.complete == 'function' && param.complete(res.data);
    }
  });
}

module.exports = http;