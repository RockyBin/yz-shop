<?php

namespace App\Modules\ModuleShop\Rules\ProductImportValidate;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;
use YZ\Core\Site\SiteAdmin;

class ProductValidateCateRule implements Rule
{
    protected $productClass;

    /**
     * Create a new rule instance.
     *
     * @param Collection $collection
     * @param int $site_id
     */
    public function __construct(Collection $collection, $site_id = 0)
    {
        $this->productClass = $collection;
        $this->site_id = $site_id;
    }

    protected $format = null;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $classId = [];

        $bigCate = [];

        $res = str_replace('：',':', str_replace('；',';',$value));

        $format = $this->formatMatch($res);

        if ($format)
        {
            $this->format='分类格式错误';

            return false;
        }

        $res = trim($res,';');

        $build = explode(';', $res);

        foreach ($build as $k => $v)
        {
            $this->format = null;

            if (strpos($v,':') !== false)
            {
                if (substr_count($v,':') > 1){$this->format = '目前只支持二级分类';break;}

                list($bigClass, $childrenClass) = explode(':', $v);

                $parentData = $this->productClass->where('class_name','=',$bigClass)->first();

                if (is_null($parentData))
                {
                    $this->format = $bigClass.' 分类不存在';
                    continue;
                }

                $childRes = $this->
                productClass->
                where('parent_id','=',$parentData->id)->
                where('class_name','=',$childrenClass)->first();

                if (is_null($childRes))
                {
                    $this->format = $childrenClass.' 子分类不存在';
                    continue;
                }

                $classId[$parentData->id][] = $childRes->id;

            }else{
                $productResultFetch = $this->productClass->where('class_name','=',$v)->first();

                if (is_null($productResultFetch))
                {
                    $this->format = $v.' 分类不存在';
                    continue;
                }

                array_push($bigCate, ...$this->productCategory($v));

            }
        }

        if (!empty($classId) || !empty($bigCate))
        {
            $classId = array_unique(array_merge($this->productSplit($classId), $bigCate));

            \Cache::put(base64_encode($value. $this->site_id), $classId,2);

            return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->format;
    }

    /**
     * @param string $string
     * @return false|int
     */
    public function formatMatch(string $string)
    {
        return preg_match_all('/[^\x{4e00}-\x{9fa5}-:;\w]+/u',$string,$result);
    }

    protected function productCategory(string $name): array
    {
        $data = [];

        $db_data = $this->productClass
            ->where('class_name','=', $name)
            ->first();

        if ($db_data && $db_data->parent_id == 0)
        {
            $res = $this->productClass
                ->where('parent_id','=', $db_data->id);

            $data = $res->pluck('id');
        }

        if($data instanceof Collection && !$data->isEmpty())
        {
            $data->push($db_data->id);

            $data = $data->toArray();
        }else{
            $data = [$db_data->id];
        }

        return $data;
    }

    /**
     * @param array $classId
     * @return array
     */
    public function productSplit(array $classId): array
    {
            $class = [];

            foreach($classId as $parent_id => $childrenArr)
            {
                $arrUnique = array_unique($childrenArr);

                $allSubclass = $this->productClass->where('parent_id','=', $parent_id)->count();

                if ($allSubclass > count($arrUnique))
                {
                    //筛选
                    array_push($class, ...$arrUnique);
                }else{
                    //全选
                    $arrUnique[] = $parent_id;

                    array_push($class, ...$arrUnique);
                }
            }

            return $class;
    }
}
