<?php
/**
 * 产品分类
 * User: 李耀辉
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;


use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Model\ProductClassModel;
use App\Modules\ModuleShop\Libs\Product\ProductClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Site\Site;

class ProductClassController extends BaseSiteAdminController
{
    public function getClassList()
    {
        $list = ProductClass::getClassList();
        return makeApiResponseSuccess('ok', $list['list']);
    }

    /**
     * 添加分类
     * @param Request $request
     * @return array
     */
    public function saveClass(Request $request)
    {
        try{
            $classData = $request->all();
            $save = ProductClass::saveAllClass($classData['classList']);
            if ($save['code'] == 200) {
                return makeApiResponseSuccess('ok');
            } else {
                return $save;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }

    }

    /**
     * 上传产品图片
     * @param Request $request 必须有class_image 图片文件  classId 可选 传入classId 则直接保存到分类
     * @return array
     */
    public function uploadClassImage(Request $request)
    {
        try {
            $imagePath = ProductClass::UploadClassImage($request->file('class_image'));
            if ($request->input('class_id')) {
                $class = new ProductClass($request->input('class_id'));
                return $class->editClassImage($imagePath);
            } else {
                return makeApiResponseSuccess('ok', ['image' => $imagePath, 'imageUrl' => ProductClass::getImageUrl($imagePath)]);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除分类数据
     * @param ProductClassModel $class
     * @return array
     */
    public function deleteClass(ProductClassModel $class)
    {
        try {
            $class = new ProductClass($class);
            if ($class->deleteClass()) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '删除失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取该分类下有多少产品 包括子级分类
     * @param ProductClassModel $class
     * @return array
     */
    public function getProductsCount(ProductClassModel $class)
    {
        $count = (new ProductClass($class))->getBelongsClassProductCount();
        return makeApiResponseSuccess('ok', ['count' => $count]);
    }

    /**
     * 改变分类状态 显示 隐藏
     * @param Request $request
     * @param ProductClassModel $class
     * @return array
     */
    public function editProductClassStatus(Request $request, ProductClassModel $class) {
        try {
            $status = $request->input('status', 0);
            $class = new ProductClass($class);
            $save = $class->editClassStatus($status);
            if ($save !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '修改失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}