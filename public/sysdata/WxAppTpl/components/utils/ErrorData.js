function getSysInfoData() {
    return new Promise((reslove, reject) => {
        wx.getSystemInfo({
            success(res) {
                reslove(res)
            },
            fail() {
                reject()
            }
        })
    })
}

function reportError(ops){
    var info = {}
    getSysInfoData().then(res => {
        var curPage = getCurrentPages()
        var curPageRouter = '未知';
        if (curPage && curPage[curPage.length - 1]) {
            curPageRouter = curPage[curPage.length - 1].route
        }
        info = {...ops, ...{
            brand: res.brand, // 手机品牌
            model: res.model, // 手机型号
            language: res.language, // 微信设置的语言
            version: res.version, // 微信版本号
            system: res.system, // 操作系统版本
            platform: res.platform, // 客户端平台
            appTitle: res.appTitle, // 小程序名称
            SDKVersion: res.SDKVersion, // 客户端基础库版本
            page: curPageRouter  // 所在页面
        }}
        wx.request({
            url: info.siteBaseUrl + '/shop/crm/error/report?InitSiteID=' + (info.InitSiteID || 0),
            data: info,
            method: 'POST'
        });
    })
}
module.exports = {
    reportError
}