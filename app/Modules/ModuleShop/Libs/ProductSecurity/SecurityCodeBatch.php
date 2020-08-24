<?php
/**
 * 防伪码批次业务逻辑
 * User: liyaohui
 * Date: 2019/10/30
 * Time: 15:24
 */

namespace App\Modules\ModuleShop\Libs\ProductSecurity;


use Carbon\Carbon;
use YZ\Core\Model\ProductSecurityCodeBatchModel;
use YZ\Core\Model\ProductSecurityCodeModel;

class SecurityCodeBatch
{
    public $siteId = 0;

    public function __construct()
    {
        $this->siteId = getCurrentSiteId();
    }

    /**
     * 添加防伪批次
     * @param $params
     * @throws \Exception
     */
    public function add($params)
    {
        $batch_count = intval($params['batch_count']);
        if ($batch_count <= 0 || $batch_count > 100000) {
            throw new \Exception("请输入正确的防伪码数量");
        }
        $batch = new ProductSecurityCodeBatchModel();
        $batch->batch_count = $batch_count;
        $batch->product_id = $params['product_id'] ?: 0;
        $batch->site_id = $this->siteId;
        $batch->created_at = Carbon::now();
        $batch->batch_code = date('YmdHis') . random_int(1000, 9999);
        $batch->save();
        // 生成防伪码
//        $time = microtime(true);
        $codeStatus = (new SecurityCode())->createCode($params['batch_count'], $batch);
//        dd(microtime(true) - $time);
        if ($codeStatus) {
            // 修改生成状态
            $batch->code_create_status = 1;
            $batch->save();
        }
    }

    public function getBatchList($params = [], $page = 1, $pageSize = 20)
    {
        $query = ProductSecurityCodeBatchModel::query()
            ->from('tbl_product_security_code_batch as b')
            ->leftJoin('tbl_product as p', 'p.id', 'b.product_id')
            ->where('b.site_id', $this->siteId);
        if (isset($params['keyword']) && $keyword = trim($params['keyword'])) {
            $keyword = trim($params['keyword']);
            $query->where(function ($q) use ($keyword) {
                $q->where('p.name', 'like', '%' . $keyword . '%');
                $q->orWhere('b.batch_code', $keyword);
            });
        }
        if (isset($params['created_at_start']) && trim($params['created_at_start']) !== '') {
            $query->where('b.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end']) && trim($params['created_at_end']) !== '') {
            $query->where('b.created_at', '<=', $params['created_at_end']);
        }
        $total = $query->count();
        $lasePage = ceil($total / $pageSize);
        $list = $query->forPage($page, $pageSize)
            ->select(['b.*', 'p.name as product_name', 'p.small_images as images'])
            ->orderBy('b.id', 'desc')
            ->get();
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lasePage,
            'list' => $list
        ];
    }

    /**
     * 删除批次
     * @param $batchId
     * @return bool
     */
    public function deleteBatch($batchId)
    {
        $batchDel = ProductSecurityCodeBatchModel::query()
            ->where('site_id', $this->siteId)
            ->where('id', $batchId)
            ->delete();
        // 同步删除防伪码
        $codeDel = ProductSecurityCodeModel::query()
            ->where('site_id', $this->siteId)
            ->where('batch_id', $batchId)
            ->delete();
        return $batchDel && $codeDel;
    }
}