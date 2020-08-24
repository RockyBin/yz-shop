<?
//此文件用来远程加载云指SSL证书的配置，然后生成CDN服务器的nginx配置文件
function downFile($filePath,$remoteFile,$md5){
    $filejson = file_get_contents("http://slb.meidianbang.net/core/sysmanage/ssl/getfile?file=".$remoteFile."&md5=".$md5);
    $filedata = json_decode($filejson,true);
    if($filedata['success']){
        $content = base64_decode($filedata['data']);
        file_put_contents($filePath,$content);
    }
}

function check($certFile,$keyFile){
    $certFile = file_get_contents($certFile);
    $keyFile = file_get_contents($keyFile);
    $keyPassphrase = "";
    $keyCheckData = array(0 => $keyFile,1 => $keyPassphrase);
    $result = openssl_x509_check_private_key($certFile,$keyCheckData);
    return $result;
}

$configFile = "/etc/nginx/nginx_yzshop_ssl.conf";
if (file_exists($configFile)) {
    $confMd5 = md5(file_get_contents($configFile));
}
$sslcertDir = "/etc/nginx/sslcerts";
if(!file_exists($sslcertDir)) mkdir($sslcertDir);
exec("ifconfig",$ifconfig);
$ifconfig = implode("",$ifconfig);
$hasIPv6 = strpos($ifconfig,"inet6 addr") !== false;
$json = file_get_contents("http://slb.meidianbang.net/core/sysmanage/ssl/getlist?hasIPv6=".$hasIPv6);
$data = json_decode($json,true);
if(!$data['success']) die("get config data error");
$nginxTpl = $data['nginx_tpl'];
$configFileContent = '';
$downCertNum = 0;
foreach($data['items'] as $item){
    echo "processing ".$item['domain']." \n";
    $domaindir = preg_replace('/[\s,]+/','_',$item['domain']);
    $domaindir = str_replace("*.",'_wildcard.',$domaindir);
    if(!file_exists($sslcertDir.'/'.$domaindir)) mkdir($sslcertDir.'/'.$domaindir);
    //加载证书文件
    $certFileInfo = pathinfo($item['certfile']);
    $certFileName = $certFileInfo['basename'];
    $certFilePath = $sslcertDir.'/'.$domaindir.'/'.$certFileName;
    $needDownCert = 1;
    if(file_exists($certFilePath)){
        $certFileMd5Check = md5_file($certFilePath);
        if($certFileMd5Check == $item['certfile_md5']) $needDownCert = 0;
    }
    if($needDownCert){
        downFile($certFilePath,$item['certfile'],$item['certfile_md5']);
		$downCertNum++;
    }

    //加载key文件
    $keyFileInfo = pathinfo($item['keyfile']);
    $keyFileName = $keyFileInfo['basename'];
    $keyFilePath = $sslcertDir.'/'.$domaindir.'/'.$keyFileName;
    $needDownKey = 1;
    if(file_exists($keyFilePath)){
        $keyFileMd5Check = md5_file($keyFilePath);
        if($keyFileMd5Check == $item['keyfile_md5']) $needDownKey = 0;
    }
    if($needDownKey){
        downFile($keyFilePath,$item['keyfile'],$item['keyfile_md5']);
    }

    if(file_exists($certFilePath) && file_exists($keyFilePath)){
        if (check($certFilePath,$keyFilePath)) {
            $configContent = $nginxTpl;
            $configContent = str_replace('DOMAINNAME', $item['domain'], $configContent);
            $configContent = str_replace('SSLCERT', $certFilePath, $configContent);
            $configContent = str_replace('SSLKEY', $keyFilePath, $configContent);
            $configFileContent .= $configContent."\n";
            echo $item['domain']." cert ok \n";
        }else{
            echo $item['domain']." cert check error \n";
        }
    }else{
        echo $item['domain']." cert or key file not exists \n";
    }
    if($needDownCert || $needDownKey) usleep(100000);
    else usleep(50000);
}
if($confMd5 == md5($configFileContent) && !$downCertNum){
    echo "配置文件相同，不用重新加载\n";
    exit;
}
file_put_contents($configFile,$configFileContent);

/* 加入重定向以得到标准错误输出 stderr。 */
$handle = popen('nginx -t 2>&1', 'r');
while (!feof($handle)) $testnginx .= fread($handle, 2096);
echo $testnginx;
pclose($handle);

if(strpos($testnginx,"test is successful") !== false){
    echo system("service nginx reload");
}else{
    echo "nginx configure test fail:" .$testnginx."\n";
}
echo "all done \n";
?>