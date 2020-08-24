<?php

namespace App\Modules\ModuleShop\Rules\ProductImportValidate;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;

class ProductValidateSkusRule implements Rule
{
    public $productSkus;

    /**
     * Create a new rule instance.
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->productSkus = $collection;
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
        return $this
            ->productSkus
            ->where('serial_number','=',$value)
            ->isEmpty();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return '商品编号 已存在';
    }
}
