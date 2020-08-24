<?php
//这个文件最好是放在windows服务器上，并使用iis作为服务器，设置进程池使用SYSTEM帐户
//另外 spider.php 这个是使用 swoole 作为服务器并用在 linux 上的，但linux的phantomjs很慢
if($_REQUEST['cmd'] == 'getcontent'){
	exec("phantomjs --load-images=false --ignore-ssl-errors=false ".__DIR__."/content.js ".$_REQUEST['url'],$res);
	$res = implode("",$res);
	echo $res;
}