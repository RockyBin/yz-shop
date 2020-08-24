<?php
namespace App\Http\Controllers\SysManage\Ssl;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Site\Site;

class SslConfigController extends BaseController{
	//此文件用来下载加载SSL证明书和相关配置，以便CDN服务器请求此网址来生成SSL站点的配置
	public function getList(){
		set_time_limit(300);
		//先加载我们自己的证书
		$items = [];
		$dir = public_path().'/sysdata/sslcerts';
		$hdir = opendir($dir);
		while($subdir = readdir($hdir)){
			if($subdir == '.' || $subdir == '..') continue;
			if(!is_dir($dir.'/'.$subdir)) continue;
			$certfile = '';
			$keyfile = '';
			$dir2 = $dir.'/'.$subdir;
			$hdir2 = opendir($dir2);
			while($file = readdir($hdir2)){
				if(preg_match('/\.crt$/i',$file)){
					$certfile = $dir2.'/'.$file;
				}else if(preg_match('/\.key$/i',$file)){
					$keyfile = $dir2.'/'.$file;
				}
			}
			closedir($hdir2);
			if($certfile && $keyfile){
				$items[] = array(
					'certfile' => str_replace(public_path(),'',$certfile),
					'certfile_md5' => md5_file($certfile),
					'keyfile' => str_replace(public_path(),'',$keyfile),
					'keyfile_md5' => md5_file($keyfile),
					'domain' => str_replace("_wildcard.","*.",$subdir)
				);
			}
		}
		closedir($hdir);
		//加载用户的证书
		$sslList = \DB::select("select * from tbl_sslcert");
		foreach($sslList as $val){
            $val = (array)$val;
            $val['cert_file'] = Site::getSiteComdataDir($val['site_id']).$val['cert_file'];
            $val['key_file'] = Site::getSiteComdataDir($val['site_id']).$val['key_file'];
			if(!file_exists(public_path().$val['cert_file']) || !file_exists(public_path().$val['key_file'])) continue;
			if(!$val['cert_file_md5']){
                $val['cert_file_md5'] = md5_file(public_path().$val['cert_file']);
                $val['key_file_md5'] = md5_file(public_path().$val['key_file']);
                \DB::table('tbl_sslcert')->where('id',$val['id'])->update(['cert_file_md5' => $val['cert_file_md5'],'key_file_md5' => $val['key_file_md5']]);
            }
			$items[] = array(
				'certfile' => $val['cert_file'],
				//'certfile_md5' => md5_file(public_path().$val['cert_file']),
                'certfile_md5' => $val['cert_file_md5'],
				'keyfile' => $val['key_file'],
				//'keyfile_md5' => md5_file(public_path().$val['key_file']),
                'keyfile_md5' => $val['key_file_md5'],
				'domain' => str_replace(","," ",$val['domains'])
			);
		}

		return array('success' => true,'items' => $items,'nginx_tpl' => $this->getNginxConfigTpl());
	}

	public function getFile(){
		$file = Request::get('file');
		$md5 = Request::get('md5');
		$file = public_path()."/".$file;
		if(preg_match('/\.(crt|cer|cert|pem|key)$/',$file)){
			$checkmd5 = md5_file($file);
			if($checkmd5 != $md5){
				return array('success' => false,'msg' => 'md5 error');
			}
			if(!file_exists($file)){
				return array('success' => false,'msg' => 'file not exists');
			}
			return array('success' => true,'data' => base64_encode(file_get_contents($file)));
		}else{
			return array('success' => false,'msg' => 'file ext error');
		}
	}

	private function getNginxConfigTpl(){
		$nginxTpl = <<<EOF
		server {
			listen 443 ssl;
			listen [::]:443 ssl;
			server_name DOMAINNAME;
			ssl_certificate 'SSLCERT';
			ssl_certificate_key 'SSLKEY';
			ssl_protocols TLSv1.1 TLSv1 TLSv1.2;
			ssl_verify_client off;
			ssl_prefer_server_ciphers on;
			ssl_session_cache shared:SSL:10m;
			ssl_session_timeout 10m;
	
			location / {
				proxy_pass http://127.0.0.1/;
				proxy_set_header Host \$host;
				proxy_set_header X-Real-IP  \$remote_addr;
				proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
				proxy_set_header X-SSL-Proxy true;
				proxy_set_header X-YZShop true;
				proxy_set_header Connection "keep-alive";
				proxy_set_header X-FORWARDED_PROTO \$scheme;
				add_header P3P 'CP="IDC DSP COR NID CUR OUR NOR" policyref="/p3p.xml"';
			 }
		}
EOF;
		if(Request::get('hasIPv6') != '1') $nginxTpl = str_replace("listen [::]:443","#listen [::]:443",$nginxTpl);
		return $nginxTpl;
	}

	public function getExecCode(){
		$code = base64_encode(file_get_contents(dirname(__FILE__).'/GenSslConfig.php'));
		return array('success' => true,'code' => $code);
	}
}