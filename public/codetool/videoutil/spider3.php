<?php
//这个文件最好是放在windows服务器上，并使用iis作为服务器，设置进程池使用SYSTEM帐户
//这是调用python的爬虫工具进行页面代码抓取
if($_REQUEST['cmd'] == 'getcontent'){
	exec("D:\\Python\\Python38\\python.exe ".__DIR__."/spider.py \"".$_REQUEST['url']."\"",$res);
	$res = implode("",$res);
	echo $res;
}