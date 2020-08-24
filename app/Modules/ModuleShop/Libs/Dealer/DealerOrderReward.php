<?php
namespace App\Modules\ModuleShop\Libs\Dealer;


use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use Exception;
use YZ\Core\Services\ServiceProxy;

class DealerOrderReward implements IDealerReward
{
    /**
     * @var int
     */
    private $dealerRewardId = 0;
    /**
     * @var DealerRewardModel
     */
    private $dealerRewardModel = null;
    /**
     * @var DealerOrderRewardService
     */
    private $dealerOrderRewardService = null;

    public function __construct($dealerRewardId)
    {
        $this->dealerRewardId = $dealerRewardId;
        $this->dealerRewardModel = DealerRewardModel::find($dealerRewardId);
        $this->dealerOrderRewardService = DealerOrderRewardService::createInstance();
    }

    /**
     * 兑换奖金
     * @return mixed|void
     * @throws Exception
     */
    public function exchange()
    {
        $this->dealerOrderRewardService->exchange($this->dealerRewardId);
    }

    /**
     * 审核通过
     * @return mixed|void
     * @throws Exception
     */
    public function pass()
    {
        $this->dealerOrderRewardService->pass($this->dealerRewardId);
    }

    /**
     * 审核不通过
     * @param string $reason
     * @return mixed|void
     * @throws Exception
     */
    public function reject($reason = '')
    {
        $this->dealerOrderRewardService->reject($this->dealerRewardId, null, $reason);
    }

    /**
     * 获取Model
     * @return DealerRewardModel
     */
    public function getModel()
    {
        return $this->dealerRewardModel;
    }
}