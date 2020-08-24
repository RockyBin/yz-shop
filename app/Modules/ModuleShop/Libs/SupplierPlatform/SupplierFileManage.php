<?php
/**
 * 供应商平台文件管理
 * User: liyaohui
 * Date: 2020/7/22
 * Time: 10:13
 */

namespace App\Modules\ModuleShop\Libs\SupplierPlatform;


use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierFileModel;
use Illuminate\Http\UploadedFile;
use Ipower\Common\Util;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

class SupplierFileManage
{
    private $siteId = 0;
    private $supplierId = 0;

    /**
     * SupplierFileManage constructor.
     * @param int $siteId
     * @throws \Exception
     */
    public function __construct($siteId = 0)
    {
        if(!$siteId) $siteId = Site::getCurrentSite()->getSiteId();
        $this->siteId = $siteId;
        $this->supplierId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        if (!$this->supplierId) {
            throw new \Exception('请先登录');
        }
    }

    /**
     * 添加文件
     * @param UploadedFile $file 文件对象
     * @throws \Exception
     */
    public function upload(UploadedFile $file, $folderId){
        $savePath = Site::getSiteComdataDir($this->siteId,true).'/resource/'.date('Y-m-d');
        Util::mkdirex($savePath);
        $extension = strtolower($file->getClientOriginalExtension());
        $saveName = 'supplier-' . date('YmdHis') . randInt(1000,9999);
        $fullPath = $savePath . "/" . $saveName . "." . $extension;
        $upload = new FileUpload($file, $savePath. "/", $saveName);
        if(in_array($extension,["png", "jpg", "gif", 'jpeg'])){
            $upload->reduceImageSize(1500, '', $quality = 85);
        }else{
            $upload->save();
        }
        $fileModel = new SupplierFileModel();
        $fileModel->site_id = $this->siteId;
        $fileModel->folder_id = $folderId ? $folderId : 0;
        $fileModel->name = substr($file->getClientOriginalName(), 0, (strlen($extension) + 1) * -1);
        $fileModel->path = str_ireplace(Site::getSiteComdataDir($this->siteId,true), '', $fullPath);
        $fileModel->type = $extension;
        $fileModel->size = $file->getSize();
        $fileModel->supplier_id = $this->supplierId;
        $fileModel->save();
    }

    /**
     * 文件改名
     * @param $id 文件ID
     * @param $name 新文件名称
     * @throws \Exception
     */
    public function rename($id,$name){
        $check = SupplierFileModel::query()
            ->where([
                'site_id' => $this->siteId,
                'name' => $name,
                'supplier_id' => $this->supplierId
            ])
            ->where('id','<>',$id)->count('id');
        if($check){
            throw new \Exception('同名文件已经存在');
        }
        $folder = SupplierFileModel::query()
            ->where([
                'site_id' => $this->siteId,
                'id' => $id,
                'supplier_id' => $this->supplierId
            ])
            ->first();
        $folder->name = $name;
        $folder->save();
    }

    /**
     * 删除文件
     * @param $id 文件ID
     */
    public function delete($id){
        $file = SupplierFileModel::query()
            ->where([
                'site_id' => $this->siteId,
                'id' => $id,
                'supplier_id' => $this->supplierId
            ])
            ->first();
        if($file){
            @unlink(Site::getSiteComdataDir($this->siteId,true) . $file->path);
            $file->delete();
        }
    }

    /**
     * 获取文件列表
     * @param array $params 查询参数
     * @param array $sortRule 排序规则
     * @return mixed
     */
    public function getList($params = [], $sortRule = ['id' => 'desc']){
        $query = SupplierFileModel::query()
            ->where('site_id', $this->siteId)
            ->where('supplier_id', $this->supplierId);
        if(!isNullOrEmpty($params['folder_id'])) $query->where('folder_id',$params['folder_id']);
        if($params['keyword']) $query->where('name', 'like', '%' . $params['keyword'] . '%');
        if($params['type']){
            if(!is_array($params['type'])) $params['type'] = [$params['type']];
            $query->whereIn('type', $params['type']);
        }
        if($params['return_total_record']){
            return $query->count('id');
        }
        if($params['page_size']) $query->forPage($params['page'] ?: 1, $params['page_size']);
        foreach ($sortRule as $key => $direction){
            if($key) $query->orderBy($key,$direction);
        }
        return $query->get();
    }
}