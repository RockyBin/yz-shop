<?php

namespace YZ\Core\Site;

use Ipower\Common\Util;
use YZ\Core\Model\VerifyFileModel;

/**
 * 此类用来匹配网站的静态验证文件，如百度，微信，360等的验证文件
 * Class VerifyFile
 * @package YZ\Core\Site
 */
class VerifyFile
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
     * 从数据库读出验证文件的列表
     * @param int $page 读第几页
     * @param int $pageSize 每页的条数
     * @param string $keyword 文件路径的搜索关键词
     * @return array
     */
    public function getList($page = 1, $pageSize = 9999, $keyword = '')
    {
        $offset = ($page - 1) * $pageSize;
        $query = VerifyFileModel::query()->where('site_id', $this->getSiteId());
        if ($keyword) {
            $keyword = addslashes($keyword);
            $query->where('path', 'like', '%' . $keyword . '%');
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
        $query = VerifyFileModel::query()->where('site_id', $this->getSiteId());
        if ($keyword) {
            $keyword = addslashes($keyword);
            $query->where('path', 'like', '%' . $keyword . '%');
        }
        $count = $query->count('id');
        return $count;
    }

    /**
     * 添加或修改记录
     * @param $id 添加时，id为空,修改时，id为相应记录的主键
     * @param $type 文件类型，字符串，没具体作用，只是标记一下方便管理
     * @param $urlPath 验证文件的url路径
     * @param $postFile 上传文件的表单域
     * @return mixed
     * @throws \Exception
     */
    public function edit($id, $type, $urlPath, $postFile)
    {
        if (!$id) $id = 0;
        $this->checkFile($postFile);
        // 处理 urlPath
        if ($urlPath) {
            $urlPath = trim($urlPath, '/');
        }
        if ($urlPath) {
            $urlPath = '/verify/' . $urlPath . '/';
        } else {
            $urlPath = '/verify/';
        }

        // 检查是否已经存在相同的文件
        $fullName = $urlPath . $postFile['name'];
        $fullName = str_replace('//', '/', $fullName);
        $checkFile = VerifyFileModel::query()
            ->where('site_id', '<>', $this->getSiteId())
            ->where('path', $fullName)
            ->count('id');
        if ($checkFile) {
            throw new \Exception('错误：已经存在相同的验证文件，如需覆盖请先删除旧文件');
        }
        // 删除旧文件
        if ($id) {
            $data = VerifyFileModel::query()->where('site_id', $this->getSiteId())->where('id', $id)->first();
            if ($data) {
                $file = Site::getSiteComdataDir($data->site_id, true) . $data->path;
                @unlink($file);
            }
        } else {
            $data = new VerifyFileModel();
            $data->site_id = $this->getSiteId();
        }
        $savePath = Site::getSiteComdataDir($this->getSiteId(), true) . $urlPath;
        Util::mkdirex($savePath);
        $saveFile = $savePath . '/' . $postFile['name'];
        @unlink($saveFile);
        if (move_uploaded_file($postFile['tmp_name'], $saveFile) !== false) {
            $data->type = $type;
            $data->path = $fullName;
            $data->save();
            return $data->id;
        } else {
            throw new \Exception('错误：上传文件失败');
        }
    }

    /**
     * 删除验证文件
     * @param $id 验证文件记录的ID
     */
    public function delete($id)
    {
        $query = VerifyFileModel::query()->where('site_id', $this->getSiteId())->where('id', $id);
        $row = $query->first();
        if ($row) {
            $file = Site::getSiteComdataDir($row->site_id, true) . $row->path;
            @unlink($file);
        }
        $query->delete();
    }

    /**
     * 根据类型删除文件
     * @param $type
     */
    public function deleteByType($type)
    {
        if (!$type) return;
        $list = VerifyFileModel::query()->where('site_id', $this->getSiteId())->where('type', $type)->get();
        if (count($list) > 0) {
            // 清理文件
            foreach ($list as $item) {
                $file = Site::getSiteComdataDir($item->site_id, true) . $item->path;
                @unlink($file);
            }
            // 清理数据
            VerifyFileModel::query()->where('site_id', $this->getSiteId())->where('type', $type)->delete();
        }
    }

    /**
     * 验证文件合法性
     * @param $postFile
     * @throws \Exception
     */
    public function checkFile($postFile)
    {
        if (!preg_match('/^[0-9a-z_\-]+\.(txt|html|htm)$/i', $postFile['name'])) {
            throw new \Exception('错误：文件名不能包含中文或特殊字符，并且文件类型必须是 txt 或 html 文件');
        }
        if (preg_match('/^(index|default|robots|sitemap)/i', $postFile['name'])) {
            throw new \Exception('错误：文件名不能包含index,default,robots,sitemap等单词');
        }
    }
}
