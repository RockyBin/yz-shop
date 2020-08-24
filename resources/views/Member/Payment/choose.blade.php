<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>在线支付</title>
        <script src='https://res.wx.qq.com/open/js/jweixin-1.4.0.js'></script>
        <script src='https://cdn.bootcss.com/jquery/3.3.1/jquery.min.js'></script>
		<script src='/js/qrcode/qrcode.js'></script>
		<script src='/js/qrcode/jquery.qrcode.js'></script>
        <script>
	        (function(window, $) {
			    $.fn.serializeJson = function() {
			        var serializeObj = {};
			        var array = this.serializeArray();
			        $(array).each(
			                function() {
			                    if (serializeObj[this.name]) {
			                        if ($.isArray(serializeObj[this.name])) {
			                            serializeObj[this.name].push(this.value);
			                        } else {
			                            serializeObj[this.name] = [
			                                    serializeObj[this.name], this.value ];
			                        }
			                    } else {
			                        serializeObj[this.name] = this.value;
			                    }
			                });
			        return serializeObj;
			    };
			})(window, jQuery);

	        $(document).ready(function(){

				//start of 微信支付相关
				var payJson = '';
				var checkScanTimer = null;
				var checkScanTime = 0;
				var payParams = {};
				var payData = {};
		        $('#btn-pay').click(function(){
			        //var url = '/shop/member/order/pay';
					var url = '/core/member/payment/dopay';
			        stopCheckScanTimer();
			        payParams = $("#payform").serializeJson();
			        $.getJSON(url,payParams,function(data){
				        //微信扫码
				        if(data.success){
					        payData = data;
					        if(payParams['pay_type'] == '2' && data.result.trade_type == 'NATIVE'){
						        if(data.result.result_code == 'SUCCESS'){
							        $('#div-wxqrcode').qrcode({text:data.result.code_url,'logo':null,'width':200,height:200,logowidth:30,logoheight:30});
									checkScan(data.orderid);
								}else{
									$('#div-wxqrcode').html('下单失败：' + data.result.err_code_des);
								}
					        }else if(payParams['pay_type'] == '2' && data.result.trade_type == 'JSAPI'){ //微信公众号JSSDK
					            console.log(data);
						        payJson = JSON.parse(data.result.json);
						        callpay();
					        }
					        else if(payParams['pay_type'] == '2' && data.result.trade_type == 'MWEB'){ //微信公众号JSSDK
						        $('#div-wxh5pay').show();
						        var url = "/core/member/payment/weixinpayreturn/" + data.orderid + "/" + data.memberid;
								if(data['callback']) url += "/" + window.btoa(data['callback']);
						        $('#wxh5pay-confirm').attr('href',url);
						        window.location.href = data.result.mweb_url;
					        }
					        else if(payParams['pay_type'] == '3'){ //支付宝
						        $('body').html(data.result);
					        }else if(payParams['pay_type'] == '11' && data.result.trade_type == 'FORM'){ //通联H5
						        $('body').html(data.result.data);
					        }else if(payParams['pay_type'] == '11' && data.result.trade_type == 'REDIRECT'){ //通联跳转到三方网址调起APP支付(如支付宝)
						        window.location.href = data.result.data;
					        }
			        	}else{
				        	alert('出错：' + data.msg);
			        	}
			        });
			        return false;
		        });

		        function jsApiCall()
				{
					WeixinJSBridge.invoke(
						'getBrandWCPayRequest',
						payJson,//josn串
						function (res)
						{
							if(res.err_msg == "get_brand_wcpay_request:ok" ){
								var url = "/core/member/payment/weixinpayreturn/" + payData['orderid'] + "/" + payData['memberid'];
								if(payData['callback']) url += "/" + window.btoa(payData['callback']);
								window.location.href = url;
							}else{
								var msg = ("code = " + res.err_code + " , desc = " + res.err_desc + " , msg = " + res.err_msg);
								if(res.err_msg == "get_brand_wcpay_request:cancel") alert("用户取消操作");
								if(res.err_msg == "get_brand_wcpay_request:fail") alert("支付失败 " + msg);
							}
						}
					);
				}

				function callpay()
				{
				   if (typeof WeixinJSBridge == "undefined")
				   {
					   if (document.addEventListener)
					   {
						   document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
					   }
					   else if (document.attachEvent)
					   {
						   document.attachEvent('WeixinJSBridgeReady', jsApiCall);
						   document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
					   }
				   }
				   else
				   {
					   jsApiCall();
				   }
				}

		        function checkScan(orderid){
			        checkScanTimer = setInterval(function(){
				       	$.getJSON('/core/member/payment/weixinpaycheckscan?orderid='+orderid,function(data){
					       	checkScanTime++;
					        if(data.success && data.flag == true){
						       	stopCheckScanTimer();
						       	//window.location = '/core/member/login/wxlogincallback?scanid=' + scanid;
						       	alert('支付成功');
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
		        //end of 微信支付相关
	        });
        </script>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <form id='payform' action="/shop/member/order/pay" method="post">
                <div>
                    支付说明：支付订单 {{$orderid}} 
                </div>
                <div>
                    支付金额：{{$amount}} 
                </div>
                <div>
                    支付方式：
                    <input type='radio' name='pay_type' value='2' checked='true'>微信支付<br>
	                <input type='radio' name='pay_type' value='3'>支付宝支付<br>
					<input type='radio' name='pay_type' value='11'>通联支付<br>
		            <input type='radio' name='pay_type' value='1'>余额支付<br>
                </div>
                <div>
                    <input type="submit" id='btn-pay' value="支付">
                </div>
                                
                <div id='div-wxqrcode'>
	            </div>
	            
	            <div id='div-wxh5pay' style='display:none'>
					<div>
					    <div>请确认微信支付是否已经完成</div>
						<a id='wxh5pay-confirm'>已经完成支付</a>
		            </div>
	            </div>

	            <input type='hidden' name='order_id' value='{{$orderid}}'/>
	            <input type='hidden' name='amount' value='{{$amount}}'/>
	            <input type='hidden' name='memberid' value='{{$memberid}}'/>
	            <input type='hidden' name='callback' value='{{$callback}}'/>
            </form>
        </div>
    </body>
</html>
