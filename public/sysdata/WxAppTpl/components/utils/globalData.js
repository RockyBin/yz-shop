import { appGetExtConfig } from './getConfig.js';

var CONFIG = appGetExtConfig();
let globalDataCopy = {
  userInfo: {},
  configInfo: {},
  siteBaseUrl: CONFIG.SITEBASEURL,
  appId: CONFIG.APPID,
  appTitle: CONFIG.APPTITLE,
  siteId: 0,
  session_key: '',
  session_expiry: 0, //session_key的过期时间（时间戳）
  cookies: {},
  LicensePerm: []
}

let globalData = JSON.parse(JSON.stringify(globalDataCopy))

export const initGlobalData = () => {
  // 情况对象中的数据
  for (let key in globalData) {
    delete globalData[key]
  }
  // 重新初始化
  Object.assign(globalData, JSON.parse(JSON.stringify(globalDataCopy)))
}

export default globalData;