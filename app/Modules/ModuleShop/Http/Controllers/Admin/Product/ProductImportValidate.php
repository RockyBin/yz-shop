<?php


namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;


use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Rules\ProductImportValidate\ProductValidateCateRule;
use App\Modules\ModuleShop\Rules\ProductImportValidate\ProductValidateImgRule;
use App\Modules\ModuleShop\Rules\ProductImportValidate\ProductValidateSkusRule;
use Exception;
use Illuminate\Support\Arr;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

/**
 * @property ProductValidateSkusRule productValidateSkusRule
 * @property ProductValidateImgRule productValidateImgRule
 * @property ProductValidateCateRule productValidateCateRule
 */
class ProductImportValidate
{
    //sku_value_num 规格值数量
    protected $header = [];
    protected $productName = [];
    protected $productCode = [];
    protected $alterMsg = '异常提示';
    public $site_id;
    public $site_admin;
    public $tmpImg;
    public $productCategory;
    public $arrImg = [];

    public function valid(array $titles)
    {
        //验证xlsx文件格式

        $this->header = $titles;

        foreach ($this->verifyField() as $value)
        {
            throw_if(!in_array($value, $titles), Exception::class, '上传失败，上传文件内容格式错误!请重新上传文件');
        }

        $this->product_sku_num = $product_sku_num = ShopConfig::getProductSkuNum();//array type: sku_name_num sku_value_num

        //规格判断 单规格 或 多规格

        switch ($standard = request()->get('standard'))
        {
            case 1:
                //单规格 不应出现规格名称
                $standard = 1;
                break;

            case 2:
                //多规格
                $standard = 2;
                break;

            default:
                //异常

                throw new Exception('异常的规格选项 standard 选项');
        }

        //对比标题 格式 规格 => standard_single standard_many standard_select

        //对比商品图片格式判断 如果商品图片格式过多或者过少 都算格式有误

        foreach ($this->verifyNameMany() as $value)
        {
            $pmn = count(array_filter($titles, function ($var)use($value)
            {
                return strpos($var, $value) !== false;
            }));
            $string_alter = '';
            $booleans = true;
            switch ($value)
            {
                case '商品图':
                    $booleans = $pmn == 6;

                    $string_alter = '商品图片格式错误';
                    break;
                //多规格验证
                case "规格名称" && $standard==2:

                    if ($pmn < 1)
                    {
                        $booleans = false;

                        $string_alter = '规格名称缺少';
                    }else{
                        $booleans = $pmn <= $product_sku_num['sku_name_num'];

                        $string_alter = '规格项和系统数量不对应';
                    }

                    break;

            }

            throw_if(!$booleans, Exception::class, $string_alter,500);

        }

        //拼装产品数量 done

        //验证每个产品格式

        //拆分 成功 与 失败 数据

        //成功的数据 拼装入库
    }

    public function callVerifyRule(): array
    {
        $keys = array_values($this->header);

        $specificationName = [];

        $i = 1;

        $spec_val = '规格值';

        $spec_name = '规格名称';

        foreach ($keys as $key=>$v)
        {
            $spec_val_i = $spec_val . $i;

            if ($v == $spec_val_i)
            {
                $specificationName[$v] = $spec_name.$i; //规格值=>规格名称
                $i += 1;
            }
        }

        return $specificationName;
    }

    /**
     * 多规格数据拼装
     * @param array $rows
     * @param array $mergeCell
     * @return array
     * @throws Exception
     */
    public function validProductNum(array $rows,array $mergeCell): array
    {

        $data = [];

        $x = 0;

        $recordNeedle = 1;

        $count = count($rows);

        if (empty($mergeCell))
        {
            for ($i=1; $i<$count; $i++)
            {
                if (empty(array_values($rows[$i])[0])) continue;

                $product[] = $rows[$i];

                $data[] = $product;

                $product = [];
            }

            return $data;
        }

        foreach ($mergeCell as $vv)
        {

            if (strpos($vv, "B") !== false)
            {
                $x += 1;
                try{
                    list($start, $end) = array_map(function($a1){
                        preg_match('/\d+/',$a1, $argv);
                        return array_shift($argv);
                    },explode(':', $vv));
                }catch (Exception $exception)
                {
                    Log::writeLog('preg_match_error', $exception->getTraceAsString());
                    throw $exception;
                }


                $product = [];

                if (!isset($rows[$start-1]) || empty($rows[$start-1]))
                {
                    continue;
                }

                for ($i = $start-1; $i < $end; $i++)
                {
                    $product[] = $rows[$i];
                }

                $differenceSet = $start - 1 - $recordNeedle;

                for ($insertNum=0; $insertNum < $differenceSet; $insertNum++)
                {
                    $data[] = [$rows[$recordNeedle + $insertNum]];
                }

                $recordNeedle = $end;

                $data[] = $product;
            }

        }

        if ($x < 1)
        {
            for ($i=1; $i<$count; $i++)
            {
                if (empty(array_values($rows[$i])[0])) continue;

                $product[] = $rows[$i];

                $data[] = $product;

                $product = [];
            }
        }

        for ($recursive = 0; $recursive < (isset($end) ? $count - $end : 0); $recursive++)
        {
            $data[] = [$rows[$end+$recursive]];
        }

        if (empty($data))
        {
            throw new Exception('格式不对应');
        }

/*        for ($origin = $recursive = isset($end) ? $count - $end : 0; $recursive > 0; $recursive--)
        {
            $data[] = [$rows[$end + $origin - ($recursive - 1) - 1]];
        }*/

        return $data;
    }

    protected $isRepeats = [];

    public function manySpecCode(string $code, array &$repeats)
    {
        if (in_array($code, $repeats))
        {
            $res = true;

            $this->isRepeats[] = $code;

        }else{
            $repeats[] = $code;

            $res = false;
        }

        return $res;
    }

    //验证多规格商品导入 执行每一行验证
    public function validEachLine(array $arr): array
    {
        $err_num = 0;

        $this->verifyNum($arr);

        $rules = Arr::only($this->rule(), [
            "商品编码（必填）",
            "销售价（必填）",
            "成本价（必填）",
            "库存（必填）"
        ]);

        $specificationName = $this->callVerifyRule();

        $fail_msg = [];

        $product_name = [];

        $repeat_val = [];//商品编号

        foreach ($arr as $k => &$item)
        {

            $img = [];

            $empty_field = [];

            $spec_val_verify = [];//验证 规格值

            $bools = false;//标识 是否要做一些处理

            foreach ($item as $p_k => &$value)
            {

                $rule = $rules;

                if (!empty($value["商品名称（必填）"]))
                {

                    $this->productValidateImgRule->confirmedProductName = $value['商品名称（必填）'];

                    $img = array_values($this->getImg($value));

                    $product_name[$k] = $value["商品名称（必填）"];

                    $rule = $this->rule();

                    $filter = array_filter($specificationName, function($spec_name, $spec_v)use($value,&$rules, &$empty_field){

                        if (isset($rules[$spec_v]))
                        {
                            unset($rules[$spec_v]);
                        }

                        $bool = true;

                        //判断是否有规格名称，如果没有，就认为是一个为空的规格
                        if ($value[$spec_name] == '')
                        {
                            $empty_field[$spec_v] = $spec_name;//规格值 => 规格名称

                            $bool = false;
                        }

                        return $bool;
                    }, ARRAY_FILTER_USE_BOTH);

                    foreach ($filter as $spec_val=>$spec_name)
                    {
                        $rule[$spec_name] = 'required';

                        $rule[$spec_val] = 'required';

                        $rules[$spec_val] = 'required';

                        $spec_val_verify[$spec_val][] = $value[$spec_val];//获取规格值

                    }

                }else{
                    //添加具体的规格值
                    foreach($spec_val_verify as $key_spec_val => &$spec_val_arr)
                    {
                        if (!in_array($value[$key_spec_val], $spec_val_arr))
                        {
                            $spec_val_arr[] = $value[$key_spec_val];
                        }
                    }
                }

                //验证第一次商品可能为空的情况下 不管填写了哪一个 只认定填写的商品名称往下验证的数据
                if ($p_k < 1 && empty($value["商品名称（必填）"])) $rule = $this->rule();

                $validator = \Validator::make($value, $rule, $this->messages);

                if ($validator->fails() || $bool = $this->manySpecCode($value['商品编码（必填）'], $repeat_val))
                {

                    $bools = true;

                    $err_num += 1;

                    if (!empty($img)) array_push($this->arrImg, ...$img);

                    $value[$this->alterMsg] = isset($bool) && $bool ? $value['商品编码（必填）'].' 01商品编码重复' : implode(',', $validator->errors()->all());

                    unset($bool);

                    $fail_msg[] = $item;

                    unset($arr[$k]);

                    break;
                }

            }

            if (!$bools)
            {
                foreach($spec_val_verify as $key_spec_val => $spec_val_arr)
                {
                    if (count($spec_val_arr) > $this->product_sku_num['sku_value_num'])
                    {
                        $err_num += 1;

                        $item[0][$this->alterMsg] = $key_spec_val . ' 规格值过多';

                        $fail_msg[] = $item;

                        unset($arr[$k]);
                    }
                }
            }

            if (!empty($empty_field))
            {
                array_walk($item, function($val, $key)use($empty_field){
                    foreach ($empty_field as $k => $v)
                    {
                        unset($val[$k]);
                        unset($val[$v]);
                    }
                });
            }
        }

        //将所有的重复商品编号取出
        foreach($this->isRepeats as $p_code)
        {
            foreach ($arr as $pk => $item)
            {
                $productCodeKey = array_search($p_code, array_column($item,'商品编码（必填）'));

                if (!$productCodeKey) continue;

                $arrTest[] = $productCodeKey;

                $item[$productCodeKey][$this->alterMsg] = $p_code . ' 商品编号重复';

                $fail_msg[] = $item;

                unset($arr[$pk]);

                break;
            }
        }

        $theSame = getSameElement($product_name);//返回键名 数组

        if (!empty($theSame))
        {

            foreach ($theSame as $product_key)
            {
                if (!isset($arr[$product_key]))
                {
                    continue;
                }

                $err_num += 1;

                $arr[$product_key][0][$this->alterMsg] = '不能有相同名称的商品';

                array_push($fail_msg, $arr[$product_key]);

                unset($arr[$product_key]);
            }
        }

        return [
            'fail_arr' => $fail_msg,
            'success_arr' => $arr,
            'err_num' => $err_num
        ];
    }

    protected function verifyErrSpec($validator): string
    {
        if (strpos($validator->errors()->keys()[0], '规格') !== false)
        {
            return $validator->errors()->keys()[0] . ':规格信息不完整';
        }

        return $validator->errors()->first();
    }

    /**
     * 验证单规格
     * @param array $arr
     * @return array
     * @throws Exception
     */
    public function validSingle(array $arr): array
    {
        array_shift($arr);

        $err_num = 0;

        $this->verifyNum($arr);

        $fail_msg = [];

        $repeats = false;

        foreach ($arr as $k => &$v)
        {
            $this->productValidateImgRule->confirmedProductName = $v["商品名称（必填）"];

            $validate = \Validator::make($v,$this->rule(), $this->messages);

            if ($validate->fails() || $repeats = $this->productNameCode($v, $k))
            {
                $err_num += 1;

                $repeats == false ? $v[$this->alterMsg] = implode(',', $validate->errors()->all()).' 格式或信息有误': false;

                $repeats = false;

                $fail_msg[] = $v;

                $img = array_values($this->getImg($v));

                empty($img) || array_push($this->arrImg, ...$img);

                unset($arr[$k]);
            }

        }

        $this->errProductNameCodeEach($arr, $fail_msg);

        return [
            'fail_arr' => $fail_msg,
            'success_arr' => $arr,
            'err_num' => $err_num
        ];
    }

    public function __construct()
    {
        $this->site_id = Site::getCurrentSite()->getSiteId();

        $this->site_admin = session('SiteAdmin');

        $this->tmpImg = \DB::table('tbl_tmp_img')
            ->where('site_id','=',$this->site_id)
            ->where('uid','=',$this->site_admin['id'])
            ->get();

        $this->productCategory = \DB::table('tbl_product_class')
            ->where('site_id','=',$this->site_id)
            ->get();

        $this->productSkus = \DB::table('tbl_product_skus')
            ->where('site_id','=',$this->site_id)
            ->get(['serial_number']);

        $this->productValidateSkusRule = new ProductValidateSkusRule($this->productSkus);

        $this->productValidateImgRule = new ProductValidateImgRule($this->tmpImg);

        $this->productValidateCateRule = new ProductValidateCateRule($this->productCategory, $this->site_id);
    }

    protected function rule()
    {
        $priceFunc = function($attribute, $value, $fail){
            if (($len = strpos($value,'.')) !== false)
            {
                if ($len > 9999999)
                {
                    $fail($attribute . ' 数值过大');
                }

                $float = explode('.', $value)[1];

                if (strlen($float) > 2)
                {
                    $fail($attribute . ' 小数点超出2位数');
                }
            }elseif ($value > 9999999)
            {
                $fail($attribute . '数值过大');
            }
        };
        $photo = ['nullable', $this->productValidateImgRule];
        return [
            "商品编码（必填）"=> ['required','max:20', $this->productValidateSkusRule],
            "商品名称（必填）"=>'required|max:50',
            "市场价"=> ['nullable', 'numeric', $priceFunc],
            "销售价（必填）"=> ['required', 'numeric', $priceFunc],
            "成本价（必填）"=> ['required', 'numeric', $priceFunc],
            "库存（必填）"=>'required|numeric|max:9999999',
            "库存预警"=>'nullable|integer',
            "商品分类"=> ['nullable', $this->productValidateCateRule],
            "商品图片名称主图-1（必填）"=> ['required', $this->productValidateImgRule],
            "商品图片-2" => $photo,
            "商品图片-3" => $photo,
            "商品图片-4" => $photo,
            "商品图片-5" => $photo,
            "商品图片-6" => $photo,
        ];
    }

    protected $messages = [
        "商品编码（必填）.required" => "商品编码必填",
        "商品编码（必填）.max" => "商品编码最大20位",
//        '商品编码（必填）.unique' => '商品编号已存在',
        "商品名称（必填）.required" => "商品名称必填",
        "商品名称（必填）.max" => "商品名称最大50个字",
        "市场价.required" => '市场价必填',
        "市场价.numeric" => '市场价是数值',
        "商品图片名称主图-1（必填）.required" => '商品图片名称主图,必填',
        "商品图片名称主图-1（必填）.string" => '商品图片名称主图要是格式不对',
//        '商品图片名称主图-1（必填）.exists' => '商品主图不存在',
        "库存（必填）.required" => '库存必填',
        "库存（必填）.numeric" => '库存尽量是数值',
        "商品分类.required" => "商品分类必填",
//        "商品分类.exists" => '商品的分类不存在',
        "销售价（必填）.required" => '销售价必填',
        "销售价（必填）.numeric" => '销售价尽量是数值',
        "成本价（必填）.required" => '成本价必填',
        "成本价（必填）.numeric" => '成本价尽量是数值',
        "商品图片-2.string" => '商品图片-2 必须字符',
        "商品图片-3.string" => '商品图片-3 必须字符',
        "商品图片-4.string" => '商品图片-4 必须字符',
        "商品图片-5.string" => '商品图片-5 必须字符',
        "商品图片-6.string" => '商品图片-6 必须字符',
//        '商品图片-2.exists' => '商品图片-2 不存在',
//        '商品图片-3.exists' => '商品图片-3 不存在',
//        '商品图片-4.exists' => '商品图片-4 不存在',
//        '商品图片-5.exists' => '商品图片-5 不存在',
//        '商品图片-6.exists' => '商品图片-6 不存在',
        '库存（必填）.max'   => '库存设置过大 不能超过9999999',
        '销售价（必填）.min' => '销售价 必须大于0',

    ];

    public function verifyField(): array
    {
        return [
            "商品编码（必填）",
            "商品名称（必填）",
            "市场价",
            "销售价（必填）",
            "成本价（必填）",
            "库存（必填）",
            "库存预警",
            "商品分类",
            "商品图片名称主图-1（必填）",
            "商品图片-2",
            "商品图片-3",
            "商品图片-4",
            "商品图片-5",
            "商品图片-6",
        ];
    }

    public function verifyNameMany()
    {
        return [
            '商品图',
            '规格名称',
        ];
    }

    protected function verifyNum(array $arr)
    {
        if (count($arr) > 100)
        {
            throw new \Exception('产品数量超过100');
        }
    }

    public function getArrImg()
    {
        return $this->arrImg;
    }

    /**
     * 获取图片
     * @param array $values
     * @return array
     */
    public function getImg(array $values)
    {
        return array_filter(Arr::only($values, [
            "商品图片名称主图-1（必填）",
            "商品图片-2",
            "商品图片-3",
            "商品图片-4",
            "商品图片-5",
            "商品图片-6",
        ]), function($val){
            return !is_null($val);
        });
    }

    protected $repeatProductName = [];

    protected $repeatProductCode = [];

    //验证多规格 单规格 编码 商品名称重复
    public function productNameCode(array &$rows, int $k=0): bool
    {
        $repeatRes = false;

        if (in_array($rows['商品名称（必填）'], $this->productName))
        {
            $rows[$this->alterMsg] = '商品名称有重复';

            $this->repeatProductName[] = $rows['商品名称（必填）'];

            $repeatRes = true;
        }else{
            $this->productName[$k] = $rows["商品名称（必填）"];
        }

        if (in_array($rows['商品编码（必填）'], $this->productCode))
        {
            $rows[$this->alterMsg] = '商品编码有重复';

            $this->repeatProductCode[] = $rows['商品编码（必填）'];

            $repeatRes = true;
        }else{
            $this->productCode[$k] = $rows['商品编码（必填）'];
        }

        return $repeatRes;
    }

    protected function errProductNameCodeEach(array &$rows,array &$fail_msg): void
    {
        foreach (array_unique($this->repeatProductName) as $name)
        {
            $errCodeAlter = '';

            $p_k = array_search($name, $this->productName);

            if (is_bool($p_k)) continue;

            if (isset($this->repeatProductCode[$p_k]))
            {
                $errCodeAlter = '商品编号有重复' . $this->repeatProductCode[$p_k];

                unset($this->repeatProductCode[$p_k]);
            }

            $rows[$p_k][$this->alterMsg] = '商品名称重复 '. $errCodeAlter ;

            $fail_msg[] = $rows[$p_k];

            $img = array_values($this->getImg($rows[$p_k]));

            empty($img) || array_push($this->arrImg, ...$img);

            unset($rows[$p_k]);
        }

        foreach (array_unique($this->repeatProductCode) as $p_k => $code)
        {

            $p_k = array_search($code, $this->productCode);

            if (is_bool($p_k)) continue;

            $rows[$p_k][$this->alterMsg] = '商品编号重复 '.$code;

            $fail_msg[] = $rows[$p_k];

            $img = array_values($this->getImg($rows[$p_k]));

            empty($img) || array_push($this->arrImg, ...$img);

            unset($rows[$p_k]);
        }
    }
}