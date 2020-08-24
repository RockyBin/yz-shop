<?php

namespace App\Modules\ModuleShop\Rules\ProductImportValidate;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;

class ProductValidateImgRule implements Rule
{
    public $confirmedProductName;

    public $tmpImg;

    /**
     * Create a new rule instance.
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->tmpImg = $collection;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->attribute = $attribute.' 不存在';

        if (strpos($value,'-') !== false)
        {
            list($productName, $_) = explode('-',trim($value));

            if ($productName != $this->confirmedProductName){

                $this->attribute = $attribute . ' 图片名称不对应';
                return false;
            }

            return $this
                ->tmpImg
                ->where('name','=',$value)
                ->isNotEmpty();

        }else{
            $this->attribute = $attribute.' 有误！';

            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->attribute;
    }
}
