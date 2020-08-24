<?
//此文件用来远程加载云指SSL证书的配置，然后生成CDN服务器的nginx配置文件
//远程加载代码
$filejson = file_get_contents("http://slb.meidianbang.net/core/sysmanage/ssl/getcode");
    $filedata = json_decode($filejson,true);
    if($filedata['success']){
        $content = base64_decode($filedata['code']);
        $filePath = dirname(__FILE__)."/GenShopSslConfigAction.php";
        file_put_contents($filePath,$content);
        //执行代码
        system("php $filePath");
    }
?>