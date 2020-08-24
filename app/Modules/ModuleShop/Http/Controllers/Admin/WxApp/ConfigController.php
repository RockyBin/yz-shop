<?php
namespace App\Modules\ModuleShop\Http\Controllers\Admin\WxApp;

use Illuminate\Http\Request;
use YZ\Core\Common\WxAppUtil;
use YZ\Core\Constants;
use YZ\Core\Model\VerifyFileModel;
use YZ\Core\Model\WxAppModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SslCert;
use YZ\Core\Site\VerifyFile;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use YZ\Core\FileUpload\FileUpload;

class ConfigController extends BaseAdminController
{
    /**
     * 信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $siteId = Site::getCurrentSite()->getSiteId();
            $configData = WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->first();
            if ($configData) {
                // 微信验证文件
                $verifyFile = VerifyFileModel::query()->where('site_id', $siteId)->where('type', Constants::VerifyFileType_WxApp_Verify)->first();
                if ($verifyFile) {
                    $configData->verify = $verifyFile->path;
                } elseif($configData) {
                    $configData->verify = '';
                }
            }

            // 域名列表
            $domainList = Site::getCurrentSite()->getUserDomain(true);

            // SSL证书情况
            $sslDomainList = [];
            $sslList = (new SslCert())->getList();
            foreach ($sslList as $item){
                $arr = preg_split("/[\s,]+/i",$item['domains']);
                foreach ($arr as $dom){
                    $sslDomainList[$dom] = 1;
                }
            }
            //过滤没有证书的域名
            $newDomainList = [];
            foreach ($domainList as $k => $item){
                $tmp = explode('.',$item->domain);
                array_shift($tmp);
                $wildcard = "*.".implode('.',$tmp);
                if ($sslDomainList[$item->domain] || $sslDomainList[$wildcard]){
                    $newDomainList[] = $item;
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), [
                'config' => $configData,
                'domain_list' => $newDomainList,
            ]);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $model = WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->first();
          	$param = $request->input();
          	if(!$model){
              	$model = new WxAppModel();
            	$param['site_id'] = getCurrentSiteId();
                $param['created_at'] = date('Y-m-d H:i:s');
            }
            // 验证文件
            if ($request->hasFile('verify_file')) {
                $verifyFileData = $_FILES['verify_file'];
                // 保存文件
                $verifyFile = new VerifyFile();
                $verifyFile->checkFile($verifyFileData);
                $verifyFile->deleteByType(Constants::VerifyFileType_WxApp_Verify);
                $verifyFile->edit(null, Constants::VerifyFileType_WxApp_Verify, null, $verifyFileData);
            }
            // 二维码
            if ($request->hasFile('qrcode_file')) {
                $fileName = 'qrcode_' . getCurrentSiteId() . '_' . time();
                $filePath = Site::getSiteComdataDir('', true) . '/wxapp';
                $handle = new FileUpload($request->file('qrcode_file'), $filePath, $fileName);
                $handle->save();
                $param['qrcode'] = '/wxapp/' . $handle->getFullFileName();
            }
            $model->fill($param);
            $model->save();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'),['config' => $model]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除小程序配置
     * @param Request $request
     */
    public function delete(Request $request){
        try {
            WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->delete();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 生成并下载小程序包
     */
    public function getPackage(){
        try {
            $this->createAppFile();
            return makeApiResponseSuccess("ok",['file' => $this->getAppFile()]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /** 生成小程序压缩包
     * @param $id
     * @throws Exception
     * @throws \Exception
     */
    private function createAppFile()
    {
        $model = WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->first();
        if ($model) {
            $rootDir = public_path();
            $tplPath = $rootDir . '/sysdata/WxAppTpl';
            $prefix = "site" . getCurrentSiteId();
            $exportDir = $rootDir . "/tmpdata/export/" . $prefix;
            if (!file_exists($tplPath)) {
                throw new \Exception("小程序模板不存在");
            }

            //复制原始小程序模板
            \Ipower\Common\Util::copyFolder($tplPath, $exportDir, true, ".txt,project.config.json,.doc");

            $configStr = "var CONFIG = {\n";
            $configStr .= "SITEBASEURL: 'https://" . $model->domain . "'," ;
            $configStr .= "APPID: '" . $model->appid . "'," ;
            $configStr .= "APPTITLE: '" . $model->name . "'" ;
            $configStr .= "}; \n";
            $configStr .= "module.exports = CONFIG;";
            file_put_contents($exportDir . '/config.js', $configStr);

            //json 文件完成
            $appJson = json_decode(file_get_contents($tplPath.'/app.json'),true);
            if(!$appJson['window']) $appJson['window'] = ['backgroundTextStyle' => 'light'];
            if($model->head_bgcolor) $appJson['window']['navigationBarBackgroundColor'] = $model->head_bgcolor;
            if($model->head_fontcolor) $appJson['window']['navigationBarTextStyle'] = $model->head_fontcolor;
            if($model->name) $appJson['window']['navigationBarTitleText'] = $model->name;

            file_put_contents($exportDir . '/app.json', json_encode($appJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            //压缩打包
            $zipFile = Site::getSiteComdataDir(0,true).'/wxapp.zip';
            $zip = new \Ipower\Common\Zip();
            $zip->zipDir($exportDir, $zipFile);
            \Ipower\Common\Util::deletedir($exportDir);
        } else {
            throw new \Exception("App 不存在");
        }
    }

    /**
     * 获取在线上传所需的信息
     * @param Request $request
     */
    public function getUploadInfo(Request $request){
        try {
            $model = WxAppModel::query()->where(['site_id' => getCurrentSiteId()])->first();
            $this->createAppFile();
            $res = WxAppUtil::getWxAppUploadServer();
            if ($res['code'] != 200) {
                throw new \Exception('获取上传服务器出错：' . $res['msg']);
            }
            $serverApi = $res['server'];
            $serverInfo = parse_url($res['server']);
            $server = $serverInfo['scheme'].'://'.$serverInfo['host'];
            if($serverInfo['scheme'] == 'http' && $serverInfo['port'] != 80) $server .= ':'.$serverInfo['port'];
            if($serverInfo['scheme'] == 'https' && $serverInfo['port'] != 443) $server .= ':'.$serverInfo['port'];
            $file = getHttpProtocol()."://".getHttpHost().$this->getAppFile() . '?v=' . time();
            return makeApiResponseSuccess("ok",['name' => $model->name, 'appid' => $model->appid,'appsecret' => $model->appsecret, 'file' => $file, 'server' => $server, 'serverApi' => $serverApi]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取生成的小程序包的下载地址
     * @return string
     */
    private function getAppFile()
    {
        $path = Site::getSiteComdataDir().'/wxapp.zip';
        return $path;
    }
}