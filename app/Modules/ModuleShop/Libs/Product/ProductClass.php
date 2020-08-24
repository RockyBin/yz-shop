<?php
/**
 * 产品分类业务类
 */

namespace App\Modules\ModuleShop\Libs\Product;


use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

class ProductClass
{
    private $_site = null;
    private $_class = null;

    /**
     * ProductClass constructor.
     * @param null $class 可以是分类模型ProductClassModel的实例 也可以是分类id 为空则会初始化分类模型对象
     * @param null $site
     */
    public function __construct($class = null, $site = null)
    {
        $this->_site = $site;
        if (!$this->_site) {
            $this->_site = Site::getCurrentSite();
        }
        if ($class) {
            if ($class instanceof ProductClassModel) {
                $this->_class = $class;
            } else {
                $this->_class = ProductClassModel::query()->findOrFail($class);
            }
        } else {
            $this->_class = new ProductClassModel();
        }
    }

    /**
     * 批量保存分类
     * @param array $classInfo 要保存的产品数据  有上下级关系
     * @return array
     * @throws \Exception
     */
    public static function saveAllClass($classInfo)
    {
        DB::beginTransaction();
        try {
            // 子级用批量保存
            $insertChildClass = [];
            $updateChildClass = [];
            foreach ($classInfo as $parentClass) {
                $class = self::saveClass($parentClass);
                foreach ($parentClass['childClass'] as $childClass) {
                    $childClass['parent_id'] = $class->id;
                    $childClass['site_id'] = Site::getCurrentSite()->getSiteId();
                    if (empty($childClass['id'])) {
                        $insertChildClass[] = $childClass;
                    } else {
                        $updateChildClass[] = $childClass;
                    }
                }
            }
            if (!empty($insertChildClass)) {
                ProductClassModel::query()->insert($insertChildClass);
            }
            if (!empty($updateChildClass)) {
                (new ProductClassModel())->updateBatch($updateChildClass);
            }
            DB::commit();
            return makeServiceResult(200, $classInfo);
        } catch (\Exception $e) {
            DB::rollBack();
            return makeServiceResult(500, $e->getMessage());
        }

    }

    /**
     * @param $classInfo
     * @return ProductClass|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public static function saveClass($classInfo)
    {
        if (empty($classInfo['id'])) {
            $class = new ProductClassModel();
        } else {
            $class = ProductClassModel::query()->findOrFail($classInfo['id']);
        }
        $class->class_name = $classInfo['class_name'];
        $class->image = $classInfo['image'];
        $class->status = $classInfo['status'] ? 1 : 0;
        $class->parent_id = $classInfo['parent_id'] ?: 0;
        $class->order = $classInfo['order'];
        $class->site_id = Site::getCurrentSite()->getSiteId();
        $class->save();
        return $class;
    }

    /**
     * 获取所有分类列表
     * 筛选条件 $param
     * @return mixed
     */
    public static function getClassList(array $param = [])
    {
        $expression = ProductClassModel::query()
            ->where(['site_id' => Site::getCurrentSite()->getSiteId()])
            ->orderBy('order');
//        $expression->withCount('productList as product_count');
        if ($param['parent_id'] != '') {
            $expression = $expression->where('parent_id', $param['parent_id']);
        }
        if ($param['status'] != '') {
            $expression = $expression->where('status', $param['status']);
        }
        $total = $expression->count();
        if ($param['page'] && $param['page_size']) {
            $page = $param['page'];
            $page_size = $param['page_size'];
            $expression = $expression->forPage($param['page'], $param['page_size']);
        }
        $last_page = $page_size == 0 ? 0 : ceil($total/$page_size);
        $list = $expression->get();
        return [
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'list' => $list,
            'last_page'=>$last_page
        ];
    }

    /**
     * 删除分类 会一并删除下级 及所有分类关联产品表数据
     * @return bool
     * @throws \Exception
     */
    public function deleteClass()
    {
        // 开启事务
        DB::beginTransaction();
        try {
            $classId = $this->_class->id;
            // 需要同时删除下级
            $childClass = [];
            self::getChildClassIds([$classId], $childClass);
            // 删除分类和产品的关联
            $classIdArray = [$classId];
            if (!empty($childClass)) {
                $classIdArray = array_merge($classIdArray, $childClass);
            }
            // 移除关联
            DB::table('tbl_product_relation_class')
                ->where('site_id', $this->_site->getSiteId())
                ->whereIn('class_id', $classIdArray)
                ->delete();
            // 删除图片
            $classImages = ProductClassModel::query()->whereIn('id', $classIdArray)->pluck('image');
            $rootPath = Site::getSiteComdataDir($this->_site->getSiteId(), true);
            foreach ($classImages as $img) {
                if (is_file($rootPath . $img)) {
                    unlink($rootPath . $img);
                }
            }
            // 删除分类
            ProductClassModel::query()->whereIn('id', $classIdArray)->delete();
            DB::commit();
            return true;
        } catch (\Exception $exception) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * 获取分类的所有下级id
     * @param array $parentId
     * @param $childClass
     */
    public static function getChildClassIds($parentId = [], &$childClass)
    {
        if (!empty($parentId)) {
            $class = ProductClassModel::query()
                ->whereIn('parent_id', $parentId)
                ->pluck('id')->toArray();
            if (!empty($class)) {
                $childClass = array_merge($childClass, $class);
                // 如果还有下级  递归处理
                self::getChildClassIds($class, $childClass);
            }
        }
    }

    public static function getParentClass($classId, $classList)
    {

    }

    /**
     * 统计属于分类的所有产品
     * @return int
     */
    public function getBelongsClassProductCount()
    {
        $classId = [$this->_class->id];
        // 获取所有子分类的id
        $childClass = [];
        self::getChildClassIds($classId, $childClass);
        //拿取该分类的父ID
        $class_data=DB::table('tbl_product_class')->where(['site_id'=>$this->_site->getSiteId(),'id'=>$classId])->select('parent_id')->first();
        if (!empty($childClass)) {
            //如果父ID是0的时候，证明这个分类是顶级分类，顶级分类的产品不算
            if($class_data->parent_id==0){
                $classId =$childClass;
            }else{
                $classId = array_merge($classId, $childClass);
            }
        }
        // 统计出该分类和子分类的产品数量
        $count = DB::table('tbl_product_relation_class')
            ->where('site_id', $this->_site->getSiteId())
            ->whereIn('class_id', $classId)
            ->selectRaw('count(DISTINCT product_id) as product_count')
            ->first();
        return $count->product_count;
    }

    /**
     * 修改分类名称
     * @param $name
     * @return bool
     */
    public function editClassName($name)
    {
        $this->_class->class_name = $name;
        return $this->_class->save();
    }

    /**
     * 修改分类图片
     * @param $image
     * @return array
     */
    public function editClassImage($image)
    {
        try {
            // 要删除旧的图片
            if ($this->_class->image && $this->_class->image != $image) {
                unlink(Site::getSiteComdataDir($this->_site->getSiteId(), true) . $this->_class->image);
            }
            $this->_class->image = $image;
            if ($this->_class->save()) {
                return makeServiceResult(200, 'ok', ['image' => $image, 'imageUrl' => self::getImageUrl($image)]);
            } else {
                return makeServiceResult(500, '保存失败');
            }
        } catch (\Exception $e) {
            return makeServiceResult(500, $e->getMessage());
        }

    }

    /**
     * 获取图片的url路径
     * @param $imagePath
     * @return string
     */
    public static function getImageUrl($imagePath)
    {
        return Site::getSiteComdataDir() . $imagePath;
    }

    /**
     * 上传分类图片
     * @param UploadedFile $image
     * @return string               图片保存路径
     * @throws \Exception
     */
    public static function uploadClassImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        $savePath = '/product/class/';
        $saveName = 'class-image-' . time() . str_random(5);
        $img = new FileUpload($image, $rootPath . $savePath, $saveName);
        $img->reduceImageSize(200);// 限制宽度为200
        $imgName = $img->getFullFileName();
        return $savePath . $imgName;
    }

    /**
     * 修改分类状态
     * @param $status
     * @return bool|int
     * @throws \Exception
     */
    public function editClassStatus($status)
    {
//        DB::beginTransaction();
        try {
            // 查找下级
            $childClass = [];
            self::getChildClassIds([$this->_class->id], $childClass);
            $childClass = array_merge($childClass, [$this->_class->id]);
            $status = $status ? 1 : 0;
            $saveChild = ProductClassModel::query()
                ->whereIn('id', $childClass)
                ->update(['status' => $status]);
            // 移除关联
//            DB::table('tbl_product_relation_class')
//                ->where('site_id', $this->_site->getSiteId())
//                ->whereIn('class_id', $childClass)
//                ->delete();
//            DB::commit();
            return $saveChild;
        } catch (\Exception $e) {
//            DB::rollBack();
            return false;
        }
    }

}