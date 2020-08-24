<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>会员登录</title>
        <script src='https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js'></script>
		<script src='/js/qrcode/qrcode.js'></script>
		<script src='/js/qrcode/jquery.qrcode.js'></script>
        <script>
	        $(document).ready(function(){
		        $('#img_verify_code').click(function(){
			        var url = $(this).attr("src");
			        if(url.indexOf("?") > -1) url = url.substr(0,url.indexOf('?'));
					$(this).attr("src",url + '?rnd=' + Math.random());
		        });

				//start of 扫码登录相关
				var checkScanTimer = null;
				var checkScanTime = 0;
		        $('#btn-scanlogin').click(function(){
			        var url = '/core/member/login/wxscanlogin';
			        $.getJSON(url,function(data){
				        if(data.success){
					       	$('#div-wxqrcode').qrcode({text:data.url,'logo':null,'width':200,height:200,logowidth:30,logoheight:30});
					       	checkScan(data.scanid);
				        }else{
					        $('#div-wxqrcode').html(data.msg);
				        }
			        });
			        return false;
		        });

		        function checkScan(scanid){
			        checkScanTimer = setInterval(function(){
				       	$.getJSON('/core/member/login/wxscancheck?scanid='+scanid,function(data){
					       	checkScanTime++;
					        if(data.success){
						       	stopCheckScanTimer();
						       	window.location = '/core/member/login/wxlogincallback?scanid=' + scanid;
					        }
					        if(checkScanTime > 30){
						        stopCheckScanTimer();
					        }
				        });
				    }, 2000);
		        }

		        function stopCheckScanTimer(){
			        if(checkScanTimer) clearInterval(checkScanTimer);
			        checkScanTime = 0;
		        }
		        //end of 扫码登录
	        });
        </script>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <form action="/core/member/login/normal" method="post">
                <div>
                    用户名：<input type="text" name="username">
                </div>
                <div>
                    密码：<input type="password" name="password">
                </div>
                <div>
                    验证码：<input type="text" name="verify_code"><img id='img_verify_code' style='cursor:pointer' src="/core/common/verifycode">
                </div>
                <div>
                    <input type="submit" value="登录">
                </div>
                <div>
                    <a href="/core/member/login/wxlogin">微信登录</a>
                    <a href="#" id='btn-scanlogin'>微信扫码登录</a>
					<a href="/core/member/login/qqlogin">QQ登录</a>
                    <a href="/core/member/login/alipaylogin">支付宝登录</a>
                </div>
                <div id='div-wxqrcode'>
	            </div>
            </form>
        </div>
    </body>
</html>
