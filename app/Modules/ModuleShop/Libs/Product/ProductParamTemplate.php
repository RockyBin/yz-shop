<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Product;

use App\Modules\ModuleShop\Libs\Model\ProductParamTemplateModel;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Site\Site;

class ProductParamTemplate
{
    private $_model = null;

    /**
     * 初始化
     * ProductParamTemplate constructor.
     * @param int $idOrModel
     */
    public function __construct($idOrModel = 0)
    {
        if (is_numeric($idOrModel)) {
            $this->findById($idOrModel);
        } else {
            $this->init($idOrModel);
        }
    }

    /**
     * 列表数据
     * @param array $param
     * @return array
     */
    public static function getList(array $param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        if ($page <= 0) $page = 1;
        if ($pageSize <= 0) $pageSize = 20;
        $showAll = $param['show_all'] ? true : false;

        $query = ProductParamTemplateModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        // 关键词查询
        if (trim($param['keyword'])) {
            $query->where('name', 'like', '%' . trim($param['keyword']) . '%');
        }
        // 总数据量
        $total = $query->count();
        // 获取数据
        $last_page = ceil($total / $pageSize);
        if ($showAll) {
            $last_page = 1;
            $page = 1;
        } else {
            $query->forPage($page, $pageSize);
        }
        // 排序
        if ($param['order_by'] && Schema::hasColumn('tbl_product_param_template', $param['order_by'])) {
            if ($param['order_by_asc']) {
                $query->orderBy($param['order_by']);
            } else {
                $query->orderByDesc($param['order_by']);
            }
        } else {
            $query->orderByDesc('id');
        }
        $list = $query->get();
        // 返回值
        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 添加数据
     * @param array $param
     * @param bool $reload
     * @return bool|mixed
     */
    public function add(array $param, $reload = false)
    {
        if ($param) {
            $param['site_id'] = Site::getCurrentSite()->getSiteId();
            $param['created_at'] = date('Y-m-d H:i:s');
            $param['updated_at'] = date('Y-m-d H:i:s');
            $model = new ProductParamTemplateModel();
            $model->fill($param);
            $model->save($param);
            if ($reload) {
                $this->findById($model->id);
            }
            return $model->id;
        } else {
            return false;
        }
    }

    /**
     * 修改数据
     * @param array $param
     * @param bool $reload
     * @return bool
     */
    public function edit(array $param, $reload = false)
    {
        if ($this->checkExist()) {
            unset($param['site_id']);
            $param['updated_at'] = date('Y-m-d H:i:s');
            $this->_model->fill($param);
            $this->_model->save();
            if ($reload) {
                $this->findById($this->_model->id);
            }
            return true;
        }
        return false;
    }

    /**
     * 删除数据
     */
    public function delete()
    {
        if ($this->checkExist()) {
            $this->_model->delete();
        }
    }

    /**
     * 数据是否存在
     * @return bool
     */
    public function checkExist()
    {
        return $this->_model && $this->_model->id ? true : false;
    }

    /**
     * 返回模型数据
     * @return bool|null
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 初始化
     * @param $model
     */
    private function init($model)
    {
        if ($model) {
            $this->_model = $model;
        }
    }

    /**
     * 根据id查找
     * @param $id
     */
    private function findById($id)
    {
        if ($id) {
            $model = ProductParamTemplateModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $id)
                ->first();
            $this->init($model);
        }
    }
}