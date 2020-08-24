<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>会员绑定</title>
        <script src='https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js'></script>
        <script>
	        $(document).ready(function(){
		        var getCodeTimer = null;
		        var getCodeSeconds = 60;
		        $('#btn-getcode').click(function(){
			        var url = "/core/common/verifycode/smscode?mobile=" + $('#mobile').val();
			        $('#btn-getcode').prop('disabled',true);
			        $.getJSON(url,function(data){
				        if(data.success){
					        $('#div-smscode-res').html('get sms code ok');
					        getCodeTimer = setInterval(function(){
								getCodeSeconds -= 1;
								$('#btn-getcode').val(getCodeSeconds + "秒后重新获取");
								if(getCodeSeconds <= 0){
									clearInterval(getCodeTimer);
					        		getCodeSeconds = 60;
					        		$('#btn-getcode').prop('disabled',false);
					        		$('#btn-getcode').val("获取验证码");
								}
						    }, 1);
				        }else{
					        $('#div-smscode-res').html('get sms code fail: ' + data.msg);
					        $('#btn-getcode').prop('disabled',false);
					        if(getCodeTimer) clearInterval(getCodeTimer);
					        getCodeSeconds = 0;
				        }
			        });
		        });

		        $('#bindform').submit(function(){
			        var url = '/core/member/login/dobind';
			        $.getJSON(url,{'mobile':$('#mobile').val(),'code':$('#code').val(),'type':$('#type').val()},function(data){
				        if(data.success){
					       	$('#div-bind-result').html(data.msg);
					       	if(data.redirect) window.location.href = data.redirect;
				        }else{
					        $('#div-bind-result').html(data.msg);
				        }
			        });
			        return false;
		        });
	        });
        </script>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <form id='bindform' action="/core/member/login/dobind" method="post">
                <div>
                	手机号：<input type="text" name="mobile" id="mobile">
                </div>
                <div>
                    验证码：<input type="text" name="code" id="code"><input type='button' id='btn-getcode' value='获取验证码'>
                </div>
                <div id="div-smscode-res">
                    
                </div>
                <div>
                    <input type="submit" value="绑定">
                </div>
                <div id='div-bind-result'>
                    
                </div>
                <div>
                    <input type="text" name="type" id="type" value="{{$type}}">
            </form>
        </div>
    </body>
</html>
