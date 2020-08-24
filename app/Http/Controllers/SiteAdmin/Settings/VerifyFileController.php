<?php

namespace App\Http\Controllers\SiteAdmin\Settings;

use Illuminate\Support\Facades\Request;
use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use YZ\Core\Site\VerifyFile;

class VerifyFileController extends BaseSiteAdminController
{
    /**
     * 获取验证列表
     * @return array
     */
    public function list()
    {
        $vfile = new VerifyFile();
        $list = $vfile->getList();
        return makeApiResponseSuccess('ok', ['list' => $list]);
    }

    /**
     * 添加验证文件
     * @return array
     */
    public function add()
    {
        try {
            $type = Request::get('type');
            $path = Request::get('path');
            $postfile = $_FILES['file'];
            $vfile = new VerifyFile();
            $vfile->edit('', $type, $path, $postfile);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改验证文件
     * @return array
     */
    public function edit()
    {
        try {
            $id = Request::get('id');
            $type = Request::get('type');
            $path = Request::get('path');
            $postfile = $_FILES['file'];
            $vfile = new VerifyFile();
            $vfile->edit($id, $type, $path, $postfile);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 删除验证文件
     * @return array
     */
    public function delete()
    {
        try {
            $id = Request::get('id');
            $vfile = new VerifyFile();
            $vfile->delete($id);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}