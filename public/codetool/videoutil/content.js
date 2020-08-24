//这是使用 phantomjs 的爬虫工具，命令行如  D:\phantomjs.exe content.js http://www.baidu.com
var page = require('webpage').create();
var system = require('system');
if(system.args.length == 1){
    phantom.exit();
}else{
	//不管如何20秒后退出 phantomjs ，避免进程过多
	setTimeout(function(){
		phantom.exit();
	}, 20000);
	page.viewportsize = {width:1440,height:2000};
	page.customHeaders = {
	   "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1"
	 };
    page.open(system.args[1], function (status) {
        if (status !== 'success') {
            console.log('FAIL to load the address '+system.args[1]);
			phantom.exit();
        } else {
            console.log("GOT REPLY FROM SERVER:");
			page.evaluate(function(){
				window.scrollTo(0,10000);//滚动到底部
			});
			//延时两秒再输出页面内容，以让那些WPA页面渲染完
			setTimeout(function(){
				console.log(page.content);
				//page.render("json2form.jpg");
				phantom.exit();
			}, 2000);
        }
    });
}