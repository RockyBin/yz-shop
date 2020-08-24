<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Product;

use App\Modules\ModuleShop\Exports\ProductExport;
use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Model\TmpImg;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class ImportFeaturesController extends BaseSiteAdminController
{

    protected $site_id;

    protected $uid;

    protected $valid;

    public function beforeAction($action = '')
    {
        $this->valid = new ProductImportValidate();

        $this->uid = $this->valid->site_admin['id'];

        $this->site_id = $this->valid->site_id;
    }

    /**
     * 根据用户标识一下用户的上传的所有图片
     * 产品图片上传
     * upload format png,jpg
     */
    public function imgUpload(Request $request)
    {
        //对应用户uid

        $uid = $this->uid;

        $validate = \Validator::make($request->all(), ['myimage' => 'required|image:png,jpeg','name'=>'required|min:3|max:200'], [
            'myimage.required' => '图片文件必填',
            'myimage.image' => '图片文件必须是jpeg或png',
            'name.required' => '名称必填',
            'name.min' => '至少三个字',
            'name.max' => '最大100个字'
        ]);
        //对应图片的名称

        $imgName = $request->get('name');

        try {

            if ($validate->fails())
            {
                throw new \Exception($validate->errors()->first());
            }

            $imgName = strpos($imgName,'.') !== false ? explode('.',$imgName)[0] : $imgName;

            $imagePath = \App\Modules\ModuleShop\Libs\Product\Product::uploadProductImage($request->file('myimage'));

            $tmpImg = TmpImg::query();

            $tmpImg->where('uid','=',$uid)
                ->where('name','=',$imgName)
                ->where('site_id','=',$this->site_id);

            if ($tmpImg->exists())
            {
                $img = $tmpImg->first()->fill([
                    'img_path'=> $imagePath,
                    'name' => $imgName
                ])->save();
            }else{
                $img = $tmpImg->create([
                    'uid' => $uid,
                    'img_path'=> $imagePath,
                    'name' => $imgName,
                    'site_id' => $this->site_id
                ]);
            }
        } catch (\Exception $th) {
            return makeApiResponse(500, $th->getMessage());
        }

        return makeApiResponse(200,'ok', $img);
    }

    /**
     * 批量导入
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importProduct(Request $request)
    {
        try {
            //1虚拟 0实物 standard = 1 单规格 2多规格
            $virtual = [1 =>1, 2=>0];

            $result = ['success' => ['num' => 0]];

            $product_status = $request->get('status',0);

            $path = $request->file('myfile');

            $standard = $request->get('standard',1);

            $op = $standard == 2 ? ['mergeCells' => []] : [];

            $rows = $this->importExcel($path,0,$op);

            $this->valid->valid($this->headings);

            if(isset($op['mergeCells'])) //多规格 验证
            {
                $data = $this->valid->validProductNum($rows, $op['mergeCells']);

                $arr = $this->valid->validEachLine($data);//多维数组 验证所有的数据成功与一些失败的产品 准备拼装入库
            }else{
                $arr = $this->valid->validSingle($rows);
            }

            if (!empty($arr['fail_arr']))
            {
                $xlsx_path = 'ShangPinYC-'.date('YmdHis').'.xlsx';

                Excel::store(new ProductExport($arr['fail_arr'], $this->headings, $standard), $xlsx_path,'local_error_xlsx');

                $url = $request->getSchemeAndHttpHost() . '/tmpdata/product/errorxlsx/'.$xlsx_path;

                $this->delTmpImg($this->valid->getArrImg());

                $result['fail'] = [
                    'url' => $url,
                    'count' => $arr['err_num']
                ];
            }

            //多规格 skus and skuData都得加数据 但 单规格 留空数组
            $field = [
                'name',//商品名称
                'status' =>0,//状态 1上架 0下架
                'class_ids' => [1147,], //分类id
                'big_images' => [],//大图 主图下标最小
                'serial_number' => 'fer998',//商品编号
                'freight_id' => 0,
                'supply_price' => '', //成本价
                'price' => '', //销售价
                'market_price' => '',//市场价
                'warning_inventory', //库存预警
                'inventory' => 0, //库存
                'small_images' => [], //小图
                'weight' => '', //产品重量
                'type' => '', // 0实物商品 1虚拟商品
                'skus' => [
                    [
                        'price'=>13,
                        'serial_number'=>'fbiweb3234',
                        'sku_code' => 'new_1587984828908',
                        'supply_price' => '成本价',
                        'inventory'=>14,//库存,
                        'market_price'=>22,//市场价
                        'id'=>0
                    ]
                ],
                'skuData' => [
                    ['has_image'=>false,'id'=>'new_20201223','name'=>'颜色', 'values' => [
                        [
                            'id'=>"new_1587984828907",
                            'image' => '',
                            'value' => '红'
                        ],
                        [
                            'id'=>"new_1587984828908",
                            'image' => '',
                            'value' => '黄'
                        ],
                        [
                            'id'=>"new_1587984828909",
                            'image' => '',
                            'value' => '绿'
                        ]
                    ]
                ],


            ]];

            $dataField = [];

            foreach ($arr['success_arr'] as $productArr)
            {

                $skus = [];

                $skuData = [];

                $field  = [];

                $img = [];

                //插入数据库
                foreach ($productArr as $k => $v)
                {
                   //处理多规格数据
                    $sku_code = '';
                    if (is_array($v))
                    {
                        if (!is_null($v['商品名称（必填）']))
                        {
                            $field = [
                                'name' => $v['商品名称（必填）'],//商品名称
                                'status' => $product_status,//状态 1上架 0下架
                                'class_ids' => empty($v['商品分类']) ? [] : $this->productCategory($v['商品分类']), //分类id
                                'serial_number' => '',//商品编号
                                'freight_id' => 0,
                                'supply_price' => 0, //成本价
                                'price' => 0, //销售价
                                'market_price' => 0,//市场价
                                'warning_inventory'=> $v['库存预警'] ?? 0, //库存预警
                                'inventory' => 0, //库存
                                'small_images' => [], //小图
                                'weight' => 0, //产品重量
                                'type' => $virtual[$request->get('virtual',0)],
                                'big_images' => []
                            ];
                        }
                        foreach($v as $name => $value)
                        {

                            if (strpos($name, '商品图片') !== false && !is_null($value))
                            {
                                $img[] = $value;
                            }

                            if (strpos($name, '规格名称') !== false && !is_null($value))
                            {

                                while (true)
                                {
                                    next($v);

                                    $spec_v = key($v);

                                    if (strpos($spec_v,'规格值') !== false)
                                    {
                                        break;
                                    }
                                }

                                $skuData[$spec_v] = [
                                    'has_image' => false,
                                    'id' => uniqid('new_'),
                                    'name' => $value,//规格名称 => 值
                                    'values' => []
                                ];

                            }elseif(
                                strpos($name, '规格值') !== false
                                && !is_null($value)
                                && !array_key_exists($value,$skuData[$name]['values'])
                            )
                            {
                                $values_id = uniqid('new_');

                                $skuData[$name]['values'][$value]= [
                                    'id' => $values_id,
                                    'image' => '',
                                    'value' => $value
                                ];

                                $sku_code = $this->implodeStr($sku_code, $values_id);
                            }else
                            {
                                $values_id = $skuData[$name]['values'][$value]['id'];

                                $sku_code = $this->implodeStr($sku_code, $values_id);
                            }

                        }

                        $skus[] = [
                            'price'=>$v['销售价（必填）'],
                            'serial_number'=> $v['商品编码（必填）'],
                            'sku_code' => trim($sku_code, ','),
                            'supply_price' => $v['成本价（必填）'],
                            'inventory'=>$v['库存（必填）'],//库存,
                            'market_price'=>$v['市场价'],//市场价
                            'id'=>0
                        ];
                    }else{

                        $field = [
                            'name' => $productArr['商品名称（必填）'],//商品名称
                            'status' => $product_status,//状态 1上架 0下架
                            'class_ids' => empty($productArr['商品分类']) ? [] : $this->productCategory($productArr['商品分类']), //分类id
                            'serial_number' => $productArr['商品编码（必填）'],//商品编号
                            'freight_id' => 0,
                            'supply_price' => $productArr['成本价（必填）'], //成本价
                            'price' => $productArr['销售价（必填）'], //销售价
                            'market_price' => $productArr['市场价'],//市场价
                            'warning_inventory' => $productArr['库存预警'], //库存预警
                            'inventory' => $productArr['库存（必填）'], //库存
                            'weight' => 0, //产品重量
                            'type' => $virtual[$request->get('virtual',0)],
                            'big_images' => [],
                            'small_images' => [],
                            'member_rule' => 0,
                        ];

                        $img = $this->valid->getImg($productArr);

                        break;
                    }

                }

                $field['skus'] = $skus;

                $field['skuData'] = array_values($skuData);

                if (!empty($img))
                {
                    $img_arr = $this->img($img);

                    $field['big_images'] = $img_arr['big_images'];

                    $field['small_images'] = $img_arr['small_images'];
                }

                //先拼接好所有的数据结构 准备插入数据库
                $dataField[] = $field;

                $result['success']['num'] += 1;
            }

            //入库
            $save= [];

            foreach ($dataField as $vvv)
            {
                $product = new \App\Modules\ModuleShop\Libs\Product\Product();
                $sku  = $vvv['skus'];
                $skuData = $vvv['skuData'];
                unset($vvv['skus']);
                unset($vvv['skuData']);
                $save[] = $product->store($vvv, $sku, $skuData);
            }

            $res = makeApiResponseSuccess('ok', $result);
        }catch (\Exception $exception)
        {
            Log::writeLog('xlsx_error',$exception->getTraceAsString());
            $res = makeApiResponse(500, $exception->getTraceAsString());
        }

        return $res;
    }

    public function img(array $arr)
    {
        $big = [];

        $small = [];

        $argc = [
            'big_images' => [], //大图地址
            'small_images' => [],//小图地址
        ];

        $uid = $this->uid;;

        //预防图片会为空
        $tmp_img = TmpImg::query()
            ->where('uid','=',$uid)
            ->where('site_id','=', $this->site_id)
            ->whereIn('name', $arr)
            ->get();

        foreach ($tmp_img as $k => $value)
        {
            $img_path = $value['img_path'];

            $min = $img_path['smallImage'];

            $max = $img_path['bigImage'];

            if (array_search($value['name'], $arr) < 1)
            {

                $big[] = $max;

                $small[] = $min;
            }else{
                $big[$k+1] = $max;

                $small[$k+1] = $min;
            }

//            $value->delete();
        }

        $argc['big_images'] = $big;

        $argc['small_images'] = $small;

        return $argc;
    }

    /**
     * 使用PHPEXECL导入
     * @param string $filePath 文件路径地址
     * @param int $sheet 文件的sheet序号，从0开始
     * @param array $options
     * @return array 二维数组，每一个元素为一个数组，表示一列的数据。
     *               键为A,B,C的列号，值为该行所对应列的单元格内的值，第一个元素为表头
     * @throws \Exception
     */
    function importExcel(string $filePath = "", int $sheet = 0, &$options = [])
    {

        try {
            /* 转码 */
            $filePath = iconv("utf-8", "gb2312", $filePath);

            if (empty($filePath) or !file_exists($filePath)) {
                throw new Exception("文件不存在!");
            }

            /** @var Xlsx $objRead */
            $objRead = IOFactory::createReader("Xlsx");

            if (!$objRead->canRead($filePath)) {
                /** @var Xls $objRead */
                $objRead = IOFactory::createReader("Xls");

                if (!$objRead->canRead($filePath)) {
                    throw new Exception("只支持导入Excel文件！");
                }
            }

            /* 如果不需要获取特殊操作，则只读内容，可以大幅度提升读取Excel效率 */
            empty($options) && $objRead->setReadDataOnly(true);
            /* 建立excel对象 */
            $obj = $objRead->load($filePath);
            /* 获取指定的sheet表 */
            $currSheet = $obj->getSheet($sheet);

            if (isset($options["mergeCells"])) {
                /* 读取合并行列 */
                $options["mergeCells"] = $currSheet->getMergeCells();
            }

//            if (0 == $columnCnt) {
//                /* 取得最大的列号 */
//                $columnH = $currSheet->getHighestColumn();
//                /* 兼容原逻辑，循环时使用的是小于等于 */
//                $columnCnt = Coordinate::columnIndexFromString($columnH);
//            }


            /* 获取总行数 */
//            $rowCnt = $currSheet->getHighestRow();
            $data   = [];

            $arr = $currSheet->toArray();

            $this->headings = $head = $arr[0];

            foreach ($arr as $k => $v)
            {
                $count = count(array_filter($v, function($val){
                    return !is_null($val);
                }));

                if ($count < 3)
                {
                    continue;
                }

                $data[] = array_combine($head, $v);
            }

            return $data;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 删除临时产品图片
     * @param array $array
     * @return void
     */
    public function delTmpImg(array $array)
    {

        if(!empty($array))
        {
            $root = Site::getSiteComdataDir('', true);

            $query = TmpImg::query()
                ->where('site_id','=',$this->site_id)
                ->where('uid','=',$this->uid)
                ->whereIn('name',$array)
                ->get();
            foreach ($query as $k => $model)
            {
                    list($big,$small) = array_values($model['img_path']);
                    if (file_exists($root. $big))
                    {
                        unlink($root. $big);
                        unlink($root. $small);
                    }
                    $model->delete();
            }

        }
    }

    protected function implodeStr($sku_code, $values_id)
    {
        if (strpos($sku_code,',') !== false)
        {
            $sku_code = trim($sku_code,',') . ',' . $values_id;
        }else{
            $sku_code .= ','.$values_id;
        }

        return $sku_code;
    }

    /**
     * 修复分类添加，无法在前台展示问题。调整图片验证规则问题。修复产品编码重复问题
     * laravel没找到更加灵活多变的验证对比方式。错误数据展示时，无法原样返回
     * 组装单规格数据格式
     * @param array $array
     * @return array
     */
    protected function assembleArray(array $array): array
    {

    }

    /**
     * 组装商品分类
     * @param string $name
     * @return array
     */
    protected function productCategory(string $name): array
    {
        return \Cache::get(base64_encode($name . $this->site_id));
    }

    public function getUrlXlsx()
    {
        $url = request()->getSchemeAndHttpHost() . '/sysdata/product/import-template/';

        return makeApiResponse(200,'ok',[
            'many' => $url.'DuoguigeMB.xlsx',
            'single' => $url.'DanguigeMB.xlsx'
        ]);
    }

}