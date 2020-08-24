<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Point;

use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Point\PointConfig;

/**
 * 积分配置项 Controller
 * Class PointConfigController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Point
 */
class PointConfigController extends BaseAdminController
{
    private $pointConfig;

    /**
     * 初始化
     * MemberConfigController constructor.
     */
    public function __construct()
    {
        $this->pointConfig = new PointConfig(Site::getCurrentSite()->getSiteId());
    }

    /**
     * 返回配置，如果没有数据，返回默认值
     * @return array
     */
    public function getInfo()
    {
        try {
            $config = $this->pointConfig->getInfo();
            $config = $this->convertOutputData($config);
            return makeApiResponseSuccess('成功', $config);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存配置
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $param = $request->all();
            $param = $this->convertInputData($param);
            $this->pointConfig->save($param);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据输出转换
     * @param $param
     * @return mixed
     */
    private function convertOutputData($param)
    {
        // 消费每多少元赠送积分
        $in_consume_per_price = $param['in_consume_per_price'];
        if ($in_consume_per_price) {
            $in_consume_per_price = intval($in_consume_per_price) / 100;
            $param['in_consume_per_price'] = number_format($in_consume_per_price, 2);
        }
        return $param;
    }

    /**
     * 数据输入转换
     * @param $param
     * @return mixed
     */
    private function convertInputData($param)
    {
        // 消费每多少元赠送积分
        $in_consume_per_price = $param['in_consume_per_price'];
        if ($in_consume_per_price) {
            $param['in_consume_per_price'] = 100 * floatval($param['in_consume_per_price']);
        }
        return $param;
    }
}