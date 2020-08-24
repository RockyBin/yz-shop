<?php

namespace App\Modules\ModuleShop\Libs\Order;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\LogisticsModel;

class Logistics
{
    private $siteId = -1; // 站点ID
    private $logisticsModal = null; // 快递信息实例

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 列表查询
     * @param $param
     * @return array
     */
    public function getList($param)
    {
        $page = intval($param['page']);
        $pageSize = intval($param['page_size']);
        $showAll = $param['show_all'] ? true : false;
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 1;

        $query = LogisticsModel::query();
        if ($this->siteId) {
            $query->where('site_id', $this->siteId);
        }
        // ids
        if ($param['ids']) {
            $ids = [];
            if (is_array($param['ids'])) {
                $ids = $param['ids'];
            } else {
                $ids = explode(',', trim($param['ids']));
            }
            if (count($ids) > 0) {
                $query->whereIn('id', $ids);
            }
        }
        // 会员id
        if (intval($param['member_id']) > 0) {
            $query->where('member_id', intval($param['member_id']));
        }
        // 订单id
        if ($param['order_id']) {
            $query->where('order_id', $param['order_id']);
        }

        $total = $query->count();
        if ($showAll && $total) {
            $page = 1;
            $pageSize = $total;
        }
        $last_page = ceil($total / $pageSize); // 总页数
        // 分页
        $query->forPage($page, $pageSize);
        $list = $query->orderBy('id', 'desc')->get();

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 添加一个物流信息
     * @param $param
     * @return bool|mixed
     */
    public function add($param)
    {
        $param['site_id'] = $this->siteId;
        // 创建时间
        if (!$param['created_at']) {
            $param['created_at'] = date('Y-m-d H:i:s');
        }
        $model = new LogisticsModel();
        $model->fill($param);
        if ($model->save()) {
            $this->logisticsModal = $model;
            return $model->id;
        } else {
            return false;
        }
    }

    /**
     * 修改
     * @param $param
     * @return bool
     */
    public function edit($param)
    {
        if ($this->checkExist()) {
            if (!$param['updated_at']) {
                $param['updated_at'] = date('Y-m-d H:i:s');
            }
            $this->logisticsModal->fill($param);
            $this->logisticsModal->save();
        } else {
            return false;
        }
    }

    /**
     * 是否存在
     */
    public function checkExist()
    {
        if ($this->logisticsModal && $this->logisticsModal->id) return true;
        else return false;
    }

    /**
     * 是否为指定供应商的物流
     */
    public function checkSupplier($supplierMemberId)
    {
        if ($this->logisticsModal && $this->logisticsModal->id) {
            $orderModel = OrderModel::find($this->logisticsModal->order_id);
            if($orderModel->supplier_member_id == $supplierMemberId) return true;
        }
        return false;
    }

    /**
     * 获取第三方或官网的查询链接（移动端）
     * @return array
     */
    public function getSearchPage()
    {
        if ($this->checkExist()) {
            $companyCode = $this->logisticsModal->logistics_company;
            $logisticsNo = $this->logisticsModal->logistics_no;
            if ($companyCode == Constants::ExpressCompanyCode_ShunFeng) {
                // 顺丰，去官网查，没办法
                return [
                    'url' => 'https://www.sf-express.com/mobile/cn/sc/dynamic_function/waybill/waybill_query_by_billno.html?billno=' . $logisticsNo
                ];
            } else if ($companyCode == Constants::ExpressCompanyCode_HuiTong) {
                // 百世汇通，快递100查起来有问题，用爱查快递
                return [
                    'url' => ' https://m.ickd.cn/result.html#no=' . $logisticsNo . '&com=auto'
                ];
            }

            // 大部分快递100都可以
            return [
                'url' => 'https://m.kuaidi100.com/result.jsp?nu=' . $logisticsNo
            ];
        }

        // 默认走快递100
        return [
            'url' => 'http://m.kuaidi100.com/'
        ];
    }

    /**
     * 静态查找
     * @param $logisticsId
     * @param int $siteId
     * @return Logistics
     */
    public static function find($logisticsId, $siteId = 0)
    {
        $logistics = New Logistics($siteId);
        $logistics->findById($logisticsId);
        return $logistics;
    }

    /**
     * 获取实例
     * @return bool|null
     */
    public function getModel()
    {
        if ($this->checkExist()) {
            return $this->logisticsModal;
        } else {
            return false;
        }
    }

    /**
     * 根据id查找
     * @param $logisticsId
     */
    private function findById($logisticsId)
    {
        $query = LogisticsModel::query()->where('id', $logisticsId);
        $query->where('site_id', $this->siteId);
        $this->init($query->first());
    }

    /**
     * 数据初始化
     * @param $model
     */
    private function init($model)
    {
        $this->logisticsModal = $model;
    }

}