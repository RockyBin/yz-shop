<?php

namespace App\Http\Controllers\SiteAdmin\Settings;

use Illuminate\Support\Facades\Request;
use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use YZ\Core\Site\SslCert;

class SslCertController extends BaseSiteAdminController
{
    /**
     * 获取证书列表
     * @return array
     */
    public function getList()
    {
        $cert = new SslCert();
        $page = Request::get('page',1);
        $pageSize = Request::get('page_size',20);
        $keyword = Request::get('keyword','');
        $list = $cert->getList($page,$pageSize, $keyword);
        foreach ($list as $k => $v){
            if (strtotime($v['expiry_at']) < time()) $v['status'] = 2;
            if ($v['status'] == 2) $list[$k]['statusText'] = '已过期';
            elseif ($v['status'] == 1) $list[$k]['statusText'] = '已生效';
            else $list[$k]['statusText'] = '未生效';
        }
        $total = $cert->getCount($keyword);
        $pageCount = ceil($total/$pageSize);
        $result = [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $pageCount,
            'list' => $list
        ];
        return makeApiResponseSuccess('ok', $result);
    }

    /**
     * 添加/修改证书文件
     * @return array
     */
    public function edit()
    {
        try {
            $id = Request::get('id');
            $certFile = Request::file('cert_file');
            $keyFile = Request::file('key_file');
            $ssl = new SslCert();
            $ssl->edit($id, $certFile, $keyFile);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除证书文件
     * @return array
     */
    public function delete()
    {
        try {
            $id = Request::get('id');
            $ssl = new SslCert();
            $ssl->delete($id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 检测证书是否生效
     * @return array
     */
    public function check()
    {
        try {
            $id = Request::get('id');
            $ssl = new SslCert();
            $status = $ssl->checkSslIsActiveWithId($id);
            if ($status) return makeApiResponseSuccess('证书已生效',['status' => 1]);
            else return makeApiResponseSuccess('证书未生效，请稍候再试',['status' => 0]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}