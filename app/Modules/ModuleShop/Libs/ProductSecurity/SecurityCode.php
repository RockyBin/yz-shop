<?php
/**
 * 商品防伪码业务逻辑类
 * User: liyaohui
 * Date: 2019/10/30
 * Time: 15:47
 */

namespace App\Modules\ModuleShop\Libs\ProductSecurity;


use App\Modules\ModuleShop\Libs\Model\ProductModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use YZ\Core\Common\Export;
use YZ\Core\Logger\Log;
use YZ\Core\Model\ProductSecurityCodeBatchModel;
use YZ\Core\Model\ProductSecurityCodeModel;

class SecurityCode
{
    public $siteId = 0;

    public function __construct()
    {
        $this->siteId = getCurrentSiteId();
    }

    /**
     * 生成防伪码
     * @param int $count 防伪码数量
     * @param ProductSecurityCodeBatchModel $batch 批次
     * @param int $try 重试次数 为0时不再重试
     * @return bool
     * @throws \Exception
     */
    public function createCode($count, ProductSecurityCodeBatchModel $batch, $try = 3)
    {
        try {
            $maxNum = 100;  // 每条sql插入的条数
            $forNum = ceil($count / $maxNum);
            $total = $count;
            $codeModel = new ProductSecurityCodeModel();
            // 切换连接到sqlite
            $db = DB::connection($codeModel->getConnectionName());
            // 关闭磁盘检测
            $db->statement("PRAGMA synchronous = OFF;");
            // 使用事物提交
            $db->beginTransaction();
            for ($i = 0; $i < $forNum; $i++) {
                $insertData = [];
                for ($insertCount = 0; $count > 0 && $insertCount < $maxNum; $insertCount++, $count--) {
                    $insertData[] = [
                        'site_id' => $this->siteId,
                        'batch_id' => $batch->id,
                        'batch_code' => $batch->batch_code,
                        'code' => strtoupper(substr(genUuid(), 0, 10)),
                        'product_id' => $batch->product_id
                    ];
                }
                $db->table($codeModel->getTable())->insert($insertData);
            }
            $db->commit();
            return true;
        } catch (\Exception $e) {
            // 重试次数为1时即停止 并写入log
            if ($try - 1 < 1) {
                Log::writeLog("security_code_error",
                    "batch_id : " . $batch->id . " batch_code : " . $batch->batch_code . " create code fail");
                throw $e;
            }
            $db->rollBack();
            usleep(100);
            $this->createCode($total, $batch, $try - 1);
        }
    }

    /**
     * 获取防伪码列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getCodeList($params = [], $page = 1, $pageSize = 20)
    {
        $list = [];
        $total = 0;
        $lastPage = 1;
        $defaultProductInfo = ['product_name' => null, 'images' => null];
        $keyword = $params['keyword'] ? trim($params['keyword']) : '';
        // 只搜索关键字
        if ($keyword) {
            // 查找防伪码 不是第一页的不查
            if ($page == 1) {
                $keywordQueryCode = ProductSecurityCodeModel::query()
                    ->where('site_id', $this->siteId)
                    ->where('code', $keyword)
                    ->first();
                // 如果有匹配 则去查找对应的商品信息
                if ($keywordQueryCode) {
                    $productInfo = $keywordQueryCode->product_id
                        ? ProductModel::query()
                        ->where('site_id', $this->siteId)
                        ->where('id', $keywordQueryCode->product_id)
                        ->select(['name as product_name', 'small_images as images'])
                        ->first()
                        : [];
                    // 如果没有关联的商品 则给出默认值
                    $productInfo = $productInfo ? $productInfo->toArray() : $defaultProductInfo;
                    $list[] = array_merge($keywordQueryCode->toArray(), $productInfo);
                    $total = 1;
                }
            }
            // 查找防伪码批次
            $batchQueryList = [];
            $batchQuery = ProductSecurityCodeModel::query()
                ->where('site_id', $this->siteId)
                ->where('batch_code', $keyword);
            $batchQueryTotal = $batchQuery->count();
            if ($batchQueryTotal) {
                $total = $batchQueryTotal;
                $lastPage = ceil($total / $pageSize);
                $batchQueryList = $batchQuery->forPage($page, $pageSize)->get();
                $batchQueryList = $this->getCodeProduct($batchQueryList, $defaultProductInfo);
                $list = array_merge($list, $batchQueryList);
            }
            // 如果使用防伪码批次查询到数据 则不再使用商品名称去查找
            if (!$batchQueryList) {
                // 查找商品 根据批次去查询
                $keywordQueryProduct = ProductSecurityCodeBatchModel::query()
                    ->from('tbl_product_security_code_batch as b')
                    ->join('tbl_product as p', 'p.id', 'b.product_id')
                    ->where('b.site_id', $this->siteId)
                    ->where('p.name', 'like', "%{$keyword}%")
                    ->select(['b.id as batch_id', 'p.name as product_name', 'p.small_images as images'])
                    ->forPage($page, $pageSize)
                    ->get();
                // 如果有商品匹配到 则去查找对应的防伪码
                if ($keywordQueryProduct->count()) {
                    $batchIds = $keywordQueryProduct->pluck('batch_id')->unique()->toArray();
                    // 格式化一下商品列表 用于查找
                    $productList = [];
                    foreach ($keywordQueryProduct as $pro) {
                        $productList[$pro['batch_id']] = [
                            'product_name' => $pro['product_name'],
                            'images' => $pro['images'],
                        ];
                    }
                    $codeQuery = ProductSecurityCodeModel::query()
                        ->where('site_id', $this->siteId)
                        ->whereIn('batch_id', $batchIds);
                    $total = $codeQuery->count();
                    $lastPage = ceil($total / $pageSize);
                    $codeList = $codeQuery->forPage($page, $pageSize)
                        ->orderByDesc('id')
                        ->get()->toArray();
                    // 循环匹配商品信息
                    foreach ($codeList as $code) {
                        if ($productList[$code['batch_id']]) {
                            $code = array_merge($code, $productList[$code['batch_id']]);
                        } else {
                            $code = array_merge($code, $defaultProductInfo);
                        }
                        $list[] = $code;
                    }
                }
            }
        } else {
            $query = ProductSecurityCodeModel::query()->where('site_id', $this->siteId);
            $total = $query->count();
            $lastPage = ceil($total / $pageSize);
            $list = $query->forPage($page, $pageSize)->orderByDesc('id')->get();
            $list = $this->getCodeProduct($list, $defaultProductInfo);
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 获取防伪码关联的商品信息
     * @param Collection $list 防伪码列表
     * @param array $defaultProductInfo 默认的商品信息
     * @return array|Collection
     */
    private function getCodeProduct(Collection $list, $defaultProductInfo)
    {
        if ($list->count()) {
            // 查找商品相关信息
            $productIds = $list->pluck('batch_id')->unique()->toArray();
            $productListObj = ProductSecurityCodeBatchModel::query()
                ->from('tbl_product_security_code_batch as b')
                ->leftJoin('tbl_product as p', 'p.id', 'b.product_id')
                ->where('b.site_id', $this->siteId)
                ->whereIn('b.id', $productIds)
                ->select(['b.id', 'p.name as product_name', 'p.small_images as images'])
                ->get();
            $productList = [];
            foreach ($productListObj as $pro) {
                $productList[$pro['id']] = [
                    'product_name' => $pro['product_name'],
                    'images' => $pro['images'],
                ];
            }
            // 插入商品数据
            $list = $list->toArray();
            foreach ($list as &$item) {
                if ($productList[$item['batch_id']]) {
                    $item = array_merge($item, $productList[$item['batch_id']]);
                } else {
                    $item = array_merge($item, $defaultProductInfo);
                }
            }
        }
        return $list;
    }

    /**
     * 删除防伪码
     * @param $id
     * @return bool
     * @throws \Exception
     */
    public function deleteCode($id)
    {
        if (!$id) {
            throw new \Exception('请选择要删除的防伪码');
        }
        if (is_numeric($id)) {
            $id = [$id];
        }
        // 先查找出来要删除的防伪码
        $query = ProductSecurityCodeModel::query()
            ->where('site_id', $this->siteId)
            ->whereIn('id', $id);
        $codeList = $query->get();
        if (!$codeList) {
            throw new \Exception('找不到相关的防伪码');
        }
        // 统计一下每个批次删除了几个防伪码
        $batchCount = [];
        foreach ($codeList as $code) {
            if (isset($batchCount['batch-' . $code['batch_id']])) {
                $batchCount['batch-' . $code['batch_id']]['count']++;
            } else {
                $batchCount['batch-' . $code['batch_id']] = [
                    'id' => $code['batch_id'],
                    'count' => 1
                ];
            }
        }
        // 删除防伪码
        $delete = $query->delete();
        // 更新批次表的防伪码数量字段
        if ($delete) {
            foreach ($batchCount as $batch) {
                $batchModel = ProductSecurityCodeBatchModel::query()->find($batch['id']);
                // 防伪码数量为0的删除该批次
                if ($batchModel->batch_count <= $batch['count']) {
                    $batchModel->delete();
                } else {
                    $batchModel->decrement('batch_count', $batch['count']);
                }
            }
        }
        return true;
    }

    /**
     * 防伪码查询
     * @param $code
     * @return array|bool
     * @throws \Exception
     */
    public function queryCode($code)
    {
        if (!$code = trim($code)) {
            throw new \Exception('请输入要查询的防伪码');
        }
        $query = ProductSecurityCodeModel::query()
            ->where('site_id', $this->siteId)
            ->where('code', $code)
            ->first();
        if (!$query) {
            return false;
        }
        $returnData = [
            'code' => $query->code,
            'query_times' => $query->query_times,
            'last_query_at' => $query->last_query_at,
            'product_name' => ''
        ];
        // 如果有商品 查询商品信息
        if ($query->product_id) {
            $productName = ProductModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $query->product_id)
                ->value('name');
            $returnData['product_name'] = $productName ?: '';
        }
        // 增加查询次数
        $query->query_times = $query->query_times + 1;
        // 写入本次查询时间
        $query->last_query_at = Carbon::now();
        $query->save();
        return $returnData;
    }

    /**
     * 根据批次号 导出批次防伪码
     * @param $batchId
     * @return \Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportCodeList($batchCode)
    {
        $heardText = [
            '批次编号',
            '防伪码编号',
            '商品名称',
            '查询次数',
            '防伪码生成链接'
        ];
        $list = ProductSecurityCodeModel::query()
            ->where('site_id', $this->siteId)
            ->where('batch_code', $batchCode)
            ->get();
        $exportData = collect([]);
        if ($list->count()) {
            // 是否有关联商品
            $productId = $list[0]['product_id'];
            $productName = '';
            // 查询链接
            $queryUrl = url('/shop/front/#/securitycheck/security-check-result?code=');
            if ($productId) {
                $productName = ProductModel::query()->where('site_id', $this->siteId)
                    ->where('id', $productId)
                    ->value('name');
                $productName = $productName ?: '';
            }
            foreach ($list as $item) {
                $exportData->push([
                    "\t" . $item->batch_code . "\t",
                    "\t" . $item->code . "\t",
                    $productName,
                    $item->query_times,
                    $queryUrl . $item->code
                ]);
            }
            // 导出次数加1
            ProductSecurityCodeBatchModel::query()
                ->where('site_id', $this->siteId)
                ->where('batch_code', $batchCode)->increment('export_times');
        }
        // 导出文件名
        $fileName = 'FANGWEI-' . date('YmdHis') . '.csv';
        $export = new Export($exportData, $fileName, $heardText);
        return $export->export();
    }
}
