<?
namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Point\PointConfig;
use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Site\Site;

/**
此类为积分的抵扣配置类,具体的值应该是从总体设置中读取或从产品配置中读取
*/
class PointDeductionConfig{
    /**
     * @var bool 是否启用抵扣
     */
	public $enable = false;

    /**
     * @var int 抵扣类型，0=按百分比，1=固定值
     */
	public $type = 0;

    /**
     * @var int 可以抵扣的最大金额,0表示不限制，与 $type 配置使用，
     * 当 $type=0 时，表示最大可以抵扣交易金额的百分之多少，
     * 当 $type=1 时，表示最多可以抵扣多少钱
     */
	public $max = 0;

    /**
     * @var int 多少积分可抵扣 $moneyUnit 的钱
     */
    public $ratio = 0;

    /**
     * @var int 金额单位，使用 $ratio 积分可抵扣 $moneyUnit 的钱，单位为分
     */
    public $moneyUnit = 100;

	public static function getPointDeductionWithProduct($productId,$skuId = 0) : PointDeductionConfig{
        $pointConfig = new PointConfig(Site::getCurrentSite()->getSiteId());
        $pinfo = ProductModel::find($productId);
        $info = $pointConfig->getInfo();
        // 如果 产品的point_status > 0 则是自定义积分规则
        if ($pinfo->point_status > 0) {
            $pointRule = $pointConfig->getPointRule($pinfo->point_status);
            $info = array_merge($info, $pointRule);
        }
	    $config = new PointDeductionConfig();
        $config->type = $info['out_order_pay_type']; // 现在只有按比例

        $terminal = getCurrentTerminal();
        $terminalOk = $info['terminal_wx'] == 1 && $terminal == \YZ\Core\Constants::TerminalType_WxOfficialAccount;
        $terminalOk |= $info['terminal_wxapp'] == 1 && $terminal == \YZ\Core\Constants::TerminalType_WxApp;
        $terminalOk |= $info['terminal_mobile'] == 1 && $terminal == \YZ\Core\Constants::TerminalType_Mobile;
        $terminalOk |= $info['terminal_pc'] == 1 && $terminal == \YZ\Core\Constants::TerminalType_PC;
        if($info['status'] == 1 && $info['out_order_pay_status'] == 1 && $pinfo->point_status >= 0 && $terminalOk) $config->enable = true;
        $config->max = $info['out_order_pay_max_percent'];
        $config->ratio = $info['out_order_pay_point'];
        $config->moneyUnit = 100;
        return $config;
    }
}
?>