<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/22
 * Time: 10:49
 */

namespace YZ\Core\ResourceManage;

use YZ\Core\Model\FileModel;
use YZ\Core\Model\FolderModel;
use YZ\Core\Site\Site;

/**
 * 资源管理器：文件夹管理
 * Class FolderManage
 * @package YZ\Core\ResourceManage
 */
class FolderManage
{
    private $_siteId = 0;
    private $_maxNum = 100;

    public function __construct($siteId = 0)
    {
        if(!$siteId) $siteId = Site::getCurrentSite()->getSiteId();
        $this->_siteId = $siteId;
    }

    /**
     * 添加文件夹
     * @param $name 文件夹名称
     * @throws \Exception
     */
    public function add($name){
        $check = FolderModel::where(['site_id' => $this->_siteId,'name' => $name])->count('id');
        if($check){
            throw new \Exception('同名文件夹已经存在');
        }
        // 是否超过最大数量
        if (FolderModel::query()->where('site_id', $this->_siteId)->count() >= $this->_maxNum) {
            throw new \Exception('最多只能添加' . $this->_maxNum . '个文件夹');
        }
        $folder = new FolderModel();
        $folder->site_id = $this->_siteId;
        $folder->name = $name;
        $folder->save();
    }

    /**
     * 文件夹改名
     * @param $id 文件夹ID
     * @param $name 新文件夹名称
     * @throws \Exception
     */
    public function rename($id,$name){
        $check = FolderModel::where(['site_id' => $this->_siteId,'name' => $name])->where('id','<>',$id)->count('id');
        if($check){
            throw new \Exception('同名文件夹已经存在');
        }
        FolderModel::where(['site_id' => $this->_siteId,'id' => $id])->update(['name' => $name]);
    }

    /**
     * 删除文件夹
     * @param $id 文件夹ID
     * @throws \Exception
     */
    public function delete($id)
    {
        // 只有空文件夹可以删除 暂时没有子文件夹
        if (FileModel::query()->where(['site_id' => $this->_siteId,'folder_id' => $id])->first()) {
            throw new \Exception('只能删除空文件夹');
        } else {
            FolderModel::query()->where(['site_id' => $this->_siteId,'id' => $id])->delete();
        }
//        //递归删除子文件夹
//        $subFolders = FolderModel::where(['site_id' => $this->_siteId,'parent_id' => $id])->get();
//        foreach ($subFolders as $sub){
//            $this->delete($sub->id);
//        }
//        //删除此目录下的文件
//        $files = FileModel::where(['site_id' => $this->_siteId,'folder_id' => $id])->get();
//        foreach($files as $file){
//            @unlink(Site::getSiteComdataDir($this->_siteId,true).$file->path);
//            $file->delete();
//        }
//        $subFolders = FolderModel::where(['site_id' => $this->_siteId,'id' => $id])->delete();
    }

    /**
     * 获取文件夹列表
     * @param $params 查询参数
     * @param array $sortRule 排序规则
     * @return mixed
     */
    public function getList($params,$sortRule = ['id' => 'desc']){
        $query = FolderModel::query()->where('site_id',$this->_siteId);
        if(!isNullOrEmpty($params['parent_id'])) $query->where('parent_id',$params['parent_id']);
        if($params['keyword']) $query->where('name','like', '%'.$params['keyword'].'%');if($params['return_total_record']){
            return $query->count('id');
        }
        if($params['return_total_record']){
            return $query->count('id');
        }
        if($params['page_size']) $query->forPage($params['page'] ? $params['page'] : 1, $params['page_size']);
        foreach ($sortRule as $key => $direction){
            if($key) $query->orderBy($key,$direction);
        }
        return $query->get();
    }
}