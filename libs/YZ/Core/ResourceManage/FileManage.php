<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/22
 * Time: 13:46
 */

namespace YZ\Core\ResourceManage;

use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Model\FileModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use YZ\Core\Site\Site;

/**
 * 资源管理器：文件夹管理
 * Class FileController
 * @package YZ\Core\ResourceManage
 */
class FileManage
{
    private $_siteId = 0;

    public function __construct($siteId = 0)
    {
        if(!$siteId) $siteId = Site::getCurrentSite()->getSiteId();
        $this->_siteId = $siteId;
    }

    /**
     * 添加文件
     * @param UploadedFile $file 文件对象
     * @throws \Exception
     */
    public function upload(UploadedFile $file,$folderId){
        $savePath = Site::getSiteComdataDir($this->_siteId,true).'/resource/'.date('Y-m-d');
        \Ipower\Common\Util::mkdirex($savePath);
        $extension = strtolower($file->getClientOriginalExtension());
        $saveName = date('YmdHis').randInt(1000,9999);
        $fullPath = $savePath."/".$saveName.".".$extension;
        $upload = new FileUpload($file,$savePath."/",$saveName);
        if(in_array($extension,["png", "jpg", "gif", 'jpeg'])){
            $upload->reduceImageSize(1500, '', $quality = 85);
        }else{
            $upload->save();
        }
        $fileModel = new FileModel();
        $fileModel->site_id = $this->_siteId;
        $fileModel->folder_id = $folderId ? $folderId : 0;
        $fileModel->name = substr($file->getClientOriginalName(),0,(strlen($extension) + 1) * -1);
        $fileModel->path = str_ireplace(Site::getSiteComdataDir($this->_siteId,true),'',$fullPath);
        $fileModel->type = $extension;
        $fileModel->size = $file->getSize();
        $fileModel->save();
    }

    /**
     * 文件夹改名
     * @param $id 文件ID
     * @param $name 新文件名称
     * @throws \Exception
     */
    public function rename($id,$name){
        $check = FileModel::where(['site_id' => $this->_siteId,'name' => $name])->where('id','<>',$id)->count('id');
        if($check){
            throw new \Exception('同名文件已经存在');
        }
        $folder = FileModel::where(['site_id' => $this->_siteId,'id' => $id])->first();
        $folder->name = $name;
        $folder->save();
    }

    /**
     * 删除文件
     * @param $id 文件ID
     */
    public function delete($id){
        //删除此目录下的文件
        $file = FileModel::where(['site_id' => $this->_siteId,'id' => $id])->first();
        if($file){
            @unlink(Site::getSiteComdataDir($this->_siteId,true).$file->path);
            $file->delete();
        }
    }

    /**
     * 获取文件列表
     * @param $params 查询参数
     * @param array $sortRule 排序规则
     * @return mixed
     */
    public function getList($params = [],$sortRule = ['id' => 'desc']){
        $query = FileModel::query()->where('site_id',$this->_siteId);
        if(!isNullOrEmpty($params['folder_id'])) $query->where('folder_id',$params['folder_id']);
        if($params['keyword']) $query->where('name','like', '%'.$params['keyword'].'%');
        if($params['type']){
            if(!is_array($params['type'])) $params['type'] = [$params['type']];
            $query->whereIn('type',$params['type']);
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