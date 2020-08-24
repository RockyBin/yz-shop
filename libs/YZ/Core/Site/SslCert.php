<?php

namespace YZ\Core\Site;

use Illuminate\Support\Facades\Request;
use Ipower\Common\Util;
use YZ\Core\Model\SslCertModel;
use Illuminate\Http\UploadedFile;

/**
 * 此类用来处理网站的 ssl 证书
 * Class SslCert
 * @package YZ\Core\Site
 */
class SslCert
{
    private $_site = null;

    public function __construct($site = null)
    {
        if (!$site) $this->_site = Site::getCurrentSite();
        else $this->_site = $site;
    }

    /**
     * 获取 site_id
     * @return int
     */
    private function getSiteId()
    {
        if ($this->_site) return $this->_site->getSiteId();
        return 0;
    }

    /**
     * 从数据库读出证书的列表
     * @param int $page 读第几页
     * @param int $pageSize 每页的条数
     * @param string $keyword 证书域名的搜索关键词
     * @return array
     */
    public function getList($page = 1, $pageSize = 9999, $keyword = '')
    {
        $offset = ($page - 1) * $pageSize;
        $query = SslCertModel::query()->where('site_id', $this->getSiteId());
        if ($keyword) {
            $keyword = addslashes($keyword);
            $query->where('domains', 'like', '%' . $keyword . '%');
        }
        $files = $query->limit($pageSize)->offset($offset)->get()->toArray();
        return $files;
    }

    /**
     * 从数据库读出验证文件的数量
     * @param string $keyword 文件路径的搜索关键词
     * @return mixed
     */
    public function getCount($keyword = '')
    {
        $query = SslCertModel::query()->where('site_id', $this->getSiteId());
        if ($keyword) {
            $keyword = addslashes($keyword);
            $query->where('domains', 'like', '%' . $keyword . '%');
        }
        $count = $query->count('id');
        return $count;
    }

    /**
     * 添加或修改记录
     * @param $id 添加时，id为空,修改时，id为相应记录的主键
     * @param $certFile 证书文件的表单域
     * @param $keyFile 私钥文件的表单域
     * @return mixed
     * @throws \Exception
     */
    public function edit($id, UploadedFile $certFile, UploadedFile $keyFile)
    {
        $this->checkFile($certFile);
        $this->checkFile($keyFile);

        // 检查证书文件是否有问题（合法性，过期时间）
        $certContent = file_get_contents($certFile->path());
        //判断证书是否包含中间证书
        preg_match_all('/BEGIN\s+CERTIFICATE/i',$certContent,$matchs);
        $notMiddleCert = count($matchs[0]) < 2;
        if($notMiddleCert){
            throw new \Exception("系统检测到您的证书链不完整(没有包含中间证书)，请联系证书发行方获取中间证书并附加到您的证书后面");
        }
        $keyContent = file_get_contents($keyFile->path());
        $check = $this->checkSsl($certContent,$keyContent);
        if(!$check){
            throw new \Exception("证书验证失败，请确保您有上传正确的Key文件");
        }

        //解析证书
        $sslParse = openssl_x509_parse($certContent);
        if(!$sslParse) throw new \Exception("证书验证失败，无法解析证书内容");

        //是否过期
        $isExpiry = $sslParse['validTo_time_t'] < time();
        if($isExpiry){
            throw new \Exception("该证书已过期");
        }

        // 检查证书的域名是否有绑定
        $domains = $this->explodeDomain($sslParse['extensions']['subjectAltName']);
        if(!$domains || !$this->checkDomain($domains)){
            throw new \Exception("该证书域名未绑定");
        }

        // 检查是否已经有相同域名的证书，有的话提示删除旧的
        $where = ['site_id' => $this->getSiteId(),'domains' => implode(' ', $domains)];
        $existsQuery = SslCertModel::query()->where($where);
        if ($id) $existsQuery->where('id','<>', $id);
        if ($existsQuery->count('id')) {
            throw new \Exception("系统已经有相同域名的证书，请先删除旧的证书再重新上传");
        }

        // 删除旧文件
        if ($id) {
            $data = SslCertModel::query()->where('site_id', $this->getSiteId())->where('id', $id)->first();
            if ($data) {
                $file = Site::getSiteComdataDir($data->site_id, true) . $data->cert_file;
                @unlink($file);
                $file = Site::getSiteComdataDir($data->site_id, true) . $data->key_file;
                @unlink($file);
            }
        } else {
            $data = new SslCertModel();
            $data->site_id = $this->getSiteId();
        }
        $savePath =  '/sslcert';
        Util::mkdirex(Site::getSiteComdataDir($this->getSiteId(), true).$savePath);
        $saveCertFile = $savePath . '/'. str_replace('*.','_wildcard',$domains[0]). '.' . $certFile->getClientOriginalName();
        $fullCertFile = Site::getSiteComdataDir($this->getSiteId(), true).$saveCertFile;
        $saveKeyFile = $savePath . '/'. str_replace('*.','_wildcard',$domains[0]) . '.' . $keyFile->getClientOriginalName();
        $fullKeyFile = Site::getSiteComdataDir($this->getSiteId(), true).$saveKeyFile;
        if (move_uploaded_file($certFile->path(), $fullCertFile) !== false && move_uploaded_file($keyFile->path(), $fullKeyFile) !== false) {
            $data->created_at = date('Y-m-d H:i:s');
            $data->expiry_at = date('Y-m-d H:i:s',$sslParse['validTo_time_t']);
            $data->cert_file = $saveCertFile;
            $data->cert_file_md5 = md5_file($fullCertFile);
            $data->key_file = $saveKeyFile;
            $data->key_file_md5 = md5_file($fullKeyFile);
            $data->domains = implode(' ', $domains);
            $data->status = 0;
            $data->save();
            return $data->id;
        } else {
            throw new \Exception('错误：上传证书文件失败');
        }
    }

    //分析域名
    private function explodeDomain($domain){
        if($domain){
            $domainList = explode(',',$domain);
            foreach ($domainList as &$value) {
                $value = trim($value);
                $value = str_replace('DNS:', '', $value);
            }
            return $domainList;
        }
        return false;
    }

    //是否绑定域名
    private function checkDomain($domain){
        $siteDomain = $this->_site->getModel()->domains;;
        $domainList = explode(',',$siteDomain);
        foreach($domainList as $sd){
            $wildcard = "*.".substr($sd,strpos($sd,".") + 1);
            if(in_array($sd, $domain) || in_array('www.' . $sd, $domain) || in_array($wildcard, $domain)){
                return true;
            }
        }
        return false;
    }

    //检测证书是否合法
    private function checkSsl($certFile,$keyFile){
        $keyPass = "";
        $keyCheckData = array(0 => $keyFile,1 => $keyPass);
        $result = openssl_x509_check_private_key($certFile,$keyCheckData);
        return $result;
    }

    /**
     * 删除验证文件
     * @param $id 验证文件记录的ID
     */
    public function delete($id)
    {
        $query = SslCertModel::query()->where('site_id', $this->getSiteId())->where('id', $id);
        $row = $query->first();
        if ($row) {
            $file = Site::getSiteComdataDir($row->site_id, true) . $row->cert_file;
            @unlink($file);
            $file = Site::getSiteComdataDir($row->site_id, true) . $row->key_file;
            @unlink($file);
        }
        $query->delete();
    }

    /**
     * 检测SSL是否已经生效并更新数据
     * @param $id 证书记录的ID
     * @return bool
     */
    public function checkSslIsActiveWithId($id) {
        $info = SslCertModel::find($id);
        if($info){
            return $this->checkSslIsActive($info->toArray());
        }
        return false;
    }

    /**
     * 检测SSL是否已经生效并更新数据
     * @param $info tbl_sslcert 的记录
     * @return bool
     */
    public function checkSslIsActive(array &$info) {
        $domain = explode(' ', $info['domains']);
        $siteDomain = $this->_site->getModel()->domains;
        $domainList = explode(',',$siteDomain);
        // 用绑定的域名去检测ssl是否生效
        foreach($domain as $sd){
            if(substr($sd,0,2) == "*.") $sd = str_replace("*.","www.",$sd);
			$isActive = static::checkDomainSsl($sd);
			if($isActive){
				SslCertModel::query()->where(['id' => $info['id']])->update(['status' => 1]);
				return true;
			}
        }
      	return false;
    }

    /**
     * 检查域名的SSL是否已经生效
     * @param string $thisHost
     * @return bool
     */
    public static function checkDomainSsl($thisHost = ''){
        if($thisHost == ''){
            $thisHost = Request::getHttpHost();
        }
        $ch = curl_init("https://" . $thisHost . "/shop/front/index.html");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_exec($ch);
        $response = curl_getinfo($ch);
        if($response['http_code'] != 0){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 验证文件合法性
     * @param $postFile
     * @throws \Exception
     */
    public function checkFile(UploadedFile $postFile)
    {
        if (!preg_match('/\.(crt|cer|cert|pem|key)$/i', $postFile->getClientOriginalName())) {
            throw new \Exception('错误：文件后缀必须是 cer、crt、cert、pem、key 之一');
        }
    }
}
