<?php

/**
 * 经销商奖金主表 包括业绩奖 销售奖 推荐奖
 * User: liyaohui
 * Date: 2019/12/27
 * Time: 10:39
 */
namespace App\Modules\ModuleShop\Libs\Model;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\QueryParameters\DealerOrderRewardQueryParameter;
use Illuminate\Database\Eloquent\Builder;
use ReflectionException;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityCollection;
use YZ\Core\Entities\Utils\EntityExecutionActions;
use YZ\Core\Entities\Utils\EntityExecutionOptions;
use YZ\Core\Entities\Utils\EntityExecutionPresets;
use YZ\Core\Entities\Utils\PaginationEntity;
use YZ\Core\Entities\Utils\RelatedDataPresetEvent;
use YZ\Core\Model\BaseModel;

class DealerRewardModel extends BaseModel
{
    protected $table = 'tbl_dealer_reward';
    public $timestamps = true;
    protected $fillable = [
        'site_id',
        'member_id',
        'type',
        'status',
        'reward_money',
        'pay_member_id',
        'reason',
        'about',
        'created_at',
        'updated_at',
        'verify_at',
        'exchange_at'
    ];

    /**
     * 业绩奖
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function performanceReward()
    {
        return $this->hasOne(DealerPerformanceRewardModel::class, 'reward_id');
    }

    /**
     * 推荐奖
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function recommendReward()
    {
        return $this->hasOne(DealerRecommendRewardModel::class, 'reward_id');
    }

    /**
     * 销售奖
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function saleReward()
    {
        return $this->hasOne(DealerSaleRewardModel::class, 'reward_id');
    }

    /**
     * @param int $id
     * @param EntityExecutionOptions|null $entityExecutionOptions
     * @param EntityExecutionPresets|null $entityExecutionPresets
     * @param EntityExecutionActions|null $entityExecutionActions
     * @return DealerRewardEntity|null
     * @throws ReflectionException
     */
    public function getSingleById(int $id, EntityExecutionOptions $entityExecutionOptions = null, EntityExecutionPresets $entityExecutionPresets = null,
                                  EntityExecutionActions $entityExecutionActions = null)
    {
        $model = $this->newQuery()->find($id);
        return is_null($model) ? null : new DealerRewardEntity($model, $entityExecutionOptions, $entityExecutionPresets, $entityExecutionActions);
    }

    /**
     * @param PaginationEntity $paginationEntity
     * @param DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter
     * @return EntityCollection
     * @throws \Exception
     */
    public function getOrderRewardPaginationByAdmin(PaginationEntity $paginationEntity, DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter)
    {
        $dealerOrderRewardQueryParameter->type = Constants::DealerRewardType_Order;
        $queryParameters = $dealerOrderRewardQueryParameter->toArrayWithNotNullValues();
        $query = $this->newQuery();
        $selectColumns = ['tbl_dealer_reward.*'];

        // 分解查询参数，写入对应的Sql到Builder中。
        foreach ($queryParameters as $key => $value) {
            switch ($key) {
                case DealerOrderRewardQueryParameter::KEYWORD :
                    {
                        $keywordType = $dealerOrderRewardQueryParameter->keyword_type;
                        $keyword = $dealerOrderRewardQueryParameter->keyword;

                        switch ($keywordType) {
                            case 1:
                                $query->leftJoin("tbl_member as temp_$key", "temp_$key.id", '=', 'tbl_dealer_reward.member_id');
                                $query->where(function (Builder $subQuery) use ($keyword, $key) {
                                    $subQuery->where("temp_$key.nickname", 'like', "%$keyword%");
                                    $subQuery->orWhere("temp_$key.mobile", 'like', "%$keyword%");
                                });
                                break;
                            case 2:
                                $query->leftJoin("tbl_member as temp_$key", "temp_$key.id", '=', 'tbl_dealer_reward.pay_member_id');
                                $query->where(function (Builder $subQuery) use ($keyword, $key) {
                                    $subQuery->where("temp_$key.nickname", 'like', "%$keyword%");
                                    $subQuery->orWhere("temp_$key.mobile", 'like', "%$keyword%");
                                });
                                break;
                        }
                    }
                    break;
                case DealerOrderRewardQueryParameter::PAYER:
                    {
                        switch ($value) {
                            case 0:
                                $query->where('tbl_dealer_reward.pay_member_id', '=', 0);
                                break;
                            case 1:
                                $query->where('tbl_dealer_reward.pay_member_id', '>', 0);
                                break;
                        }
                    }
                    break;
                case DealerOrderRewardQueryParameter::STATUS:
                    if ($value !== -9) {
                        $query->where("tbl_dealer_reward.$key", '=', $value);
                    }
                    break;
                case DealerOrderRewardQueryParameter::TYPE:
                case DealerOrderRewardQueryParameter::SITE_ID:
                    $query->where("tbl_dealer_reward.$key", '=', $value);
                    break;
                case DealerOrderRewardQueryParameter::IDS:
                    $query->whereIn('tbl_dealer_reward.id', (array)$value);
                    $paginationEntity->show_all = true;
                    break;
            }
        }

        $query->select($selectColumns);
        // 创建EntityCollection实例。
        $dealerRewardPagination = EntityCollection::createInstance(DealerRewardEntity::class);
        // 设置Entity关联数据层级计数。
        $dealerRewardPagination->setRelatedCount(-1);
        // 设置Entity关联数据预设事件，并传参DealerReward的类型到回调方法中。
        $dealerRewardPagination->setRelatedDataPresetEvent(new RelatedDataPresetEvent(DealerRewardEntity::class,
            'handlePresetRelatedData', $dealerOrderRewardQueryParameter->type));
        // 使用EntityCollection::createDataPagination()生成分页数据的Model Collection，并加载到EntityCollection中。
        $dealerRewardPagination->loadData(EntityCollection::createDataPagination($query, $paginationEntity));

        return $dealerRewardPagination;
    }
}