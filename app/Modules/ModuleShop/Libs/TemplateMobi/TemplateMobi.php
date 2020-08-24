<?php
namespace App\Modules\ModuleShop\Libs\TemplateMobi;
use App\Modules\ModuleShop\Libs\Model\TemplateMobiModel;
use YZ\Core\FileUpload\FileUpload;
/**
 *
 * Class TemplateMobi
 * @package App\Modules\ModuleShop\Libs\TemplateMobi
 */
class TemplateMobi
{
    public function __construct()
    {
    }

    /**
     * 获取单个模板信息
     * @param $id 模板ID
     * @return \LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     */
    public function get($id){
        return TemplateMobiModel::find($id);
    }

    /**
     * 添加模板
     * @param array $info 模板信息
     * @throws \Exception
     */
    public function add($info = array()){
        $savePath = public_path().'/sysdata/template/';
        \Ipower\Common\Util::mkdirex($savePath);
        $file = $info['image'];
        if($file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $saveName = date('YmdHis');
            $upload = new FileUpload($file, $savePath . "/", $saveName);
            if (in_array($extension, ["png", "jpg", "gif", 'jpeg'])) {
                $upload->reduceImageSize(1500, '', $quality = 85);
            } else {
                $upload->save();
            }
            $info['image'] = $saveName . '.' . $extension;
        }
        if($info['is_blank']){
            $exists = TemplateMobiModel::where('is_blank',1)->where('device_type','=',$info['device_type'])->count('id');
            if($exists){
                throw new \Exception("已存在空白模板，不能重复添加");
            }
        }
        unset($info['id']);
        $model = new TemplateMobiModel();
        $model->fill($info);
        $model->save();
    }

    /**
     * 删除模板
     * @param $id 模板ID
     * @throws \Exception
     */
    public function delete($id){
        $model = TemplateMobiModel::find($id);
        $image = public_path().'/sysdata/template/'.$model->image;
        if(is_file($image)) unlink($image);
        $model->delete();
    }

    /**
     * 修改模板
     * @param $id 模板ID
     * @param array $info 模板信息
     * @throws \Exception
     */
    public function edit($id,$info = array()){
        if($info['is_blank']){
            $exists = TemplateMobiModel::where('is_blank',1)->where('device_type','=',$info['device_type'])->where('id','<>',$id)->count('id');
            if($exists){
                throw new \Exception("已存在空白模板");
            }
        }
        $model = TemplateMobiModel::find($id);
        $file = $info['image'];
        if($file) {
            $savePath = public_path().'/sysdata/template/';
            \Ipower\Common\Util::mkdirex($savePath);
            $extension = strtolower($file->getClientOriginalExtension());
            $saveName = date('YmdHis');
            $upload = new FileUpload($file, $savePath . "/", $saveName);
            if (in_array($extension, ["png", "jpg", "gif", 'jpeg'])) {
                $upload->reduceImageSize(1500, '', $quality = 85);
            } else {
                $upload->save();
            }
            $info['image'] = $saveName . '.' . $extension;
            $image = public_path().'/sysdata/template/'.$model->image;
            if(is_file($image)) unlink($image);
        }
        unset($info['id']);
        $info['updated_at'] = date('Y-m-d H:i:s');
        $model->fill($info);
        $model->save();
    }

    /**
     * 列出模板
     * @param array $params 查找参数
     * @param array $sortRule 排除规则
     * @return \Illuminate\Database\Eloquent\Collection|int|static[]
     */
    public function getList($params = [],$sortRule = ['id' => 'desc']){
        $query = TemplateMobiModel::query();
        if(!isNullOrEmpty($params['industry_id'])) $query->where('industry_id',$params['industry_id']);
        if(!isNullOrEmpty($params['status'])) $query->where('status',$params['status']);
        if(!isNullOrEmpty($params['site_id'])) $query->where('site_id',$params['site_id']);
        if(!isNullOrEmpty($params['page_id'])) $query->where('page_id',$params['page_id']);
        if(!isNullOrEmpty($params['device_type'])) $query->where('device_type',$params['device_type']);
        if($params['keyword']) $query->where('name','like', '%'.$params['keyword'].'%');
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