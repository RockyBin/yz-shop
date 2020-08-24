<?php
/**
 * 区域代理公共方法
 * User: liyaohui
 * Date: 2020/5/29
 * Time: 17:18
 */

namespace App\Modules\ModuleShop\Libs\AreaAgent;


use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Model\MemberAddressModel;

class AreaAgentHelper
{
    /**
     * 根据地址查询对应的区域代理列表
     * @param MemberAddressModel|array $address
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAreaAgentListByAddress($address)
    {
        $agentList = AreaAgentModel::query()
            ->whereRaw('((area_type=? and prov=?) or (area_type=? and city=?) or (area_type=? and district=?))',[
                AreaAgentConstants::AreaAgentLevel_Province,
                $address['prov'],
                AreaAgentConstants::AreaAgentLevel_City,
                $address['city'],
                AreaAgentConstants::AreaAgentLevel_District,
                $address['area']
            ])
            ->where('status', AreaAgentConstants::AreaAgentStatus_Active)
            ->where('site_id', getCurrentSiteId())
            ->get();
        return $agentList;
    }

    /**
     * 获取区域代理的代理区域id
     * @param $agent
     * @return int
     */
    public static function getAreaAgentAreaId($agent)
    {
        switch ($agent['area_type']) {
            case AreaAgentConstants::AreaAgentLevel_Province:
                return $agent['prov'];
            case AreaAgentConstants::AreaAgentLevel_City:
                return $agent['city'];
            case AreaAgentConstants::AreaAgentLevel_District:
                return $agent['district'];
            default:
                return 0;

        }
    }

    /**
     * 根据区域代理类型和所代理的区域ID，返回完整的代理层级路径，区域从大到小排列，如 ["广东省"]、["广东省","广州市"]、 ["广东省","广州市","天河区"]
     * @param int $areaType
     * @param array $areaIds，请传入完整的路径ID，如 [省ID，市ID，区ID]
     * @return array
     */
    public static function getAreaTypePath($areaType, $areaIds)
    {
        $list = DistrictModel::query()->whereIn('id', $areaIds)->orderBy('level')->get();
        $ret = [];
        foreach ($list as $item) {
            $ret[] = $item->name;
        }
        return $ret;
    }

    /**
     * 检测区域是否已经有代理
     * @param array $areaInfo   区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @param int $applyId      申请记录id 审核时检测 要传入当前审核的id 不然会检测不通过
     * @return bool
     * @throws \Exception
     */
    public static function checkAreaAgentExist($areaInfo = [], $applyId = 0)
    {
        if (!$areaInfo || !is_array($areaInfo)) {
            throw new \Exception('数据错误');
        }
        // 要检测的区域信息 解析分组
        if (is_array($areaInfo))
        $areaInfo = collect($areaInfo);
        $provList = $areaInfo->where('area_type', AreaAgentConstants::AreaAgentLevel_Province)->pluck('prov')->toArray();
        $cityList = $areaInfo->where('area_type', AreaAgentConstants::AreaAgentLevel_City)->pluck('city')->toArray();
        $districtList = $areaInfo->where('area_type', AreaAgentConstants::AreaAgentLevel_District)->pluck('district')->toArray();
        // 查找对应的数据是否已存在
        $agent = AreaAgentModel::query()
            ->where('site_id', getCurrentSiteId())
            ->whereIn('status', [AreaAgentConstants::AreaAgentStatus_Active, AreaAgentConstants::AreaAgentStatus_Cancel]);
        $whereRaw = [];
        $bindings = [];
        if ($provList) {
            $whereRaw[] = '(area_type=? and prov in (?))';
            $bindings[] = AreaAgentConstants::AreaAgentLevel_Province;
            $bindings[] = implode(',', $provList);
        }
        if ($cityList) {
            $whereRaw[] = '(area_type=? and city in (?))';
            $bindings[] = AreaAgentConstants::AreaAgentLevel_City;
            $bindings[] = implode(',', $cityList);
        }
        if ($districtList) {
            $whereRaw[] = '(area_type=? and district in (?))';
            $bindings[] = AreaAgentConstants::AreaAgentLevel_District;
            $bindings[] = implode(',', $districtList);
        }
        if ($whereRaw) {
            $whereRaw = '(' . implode(' or ', $whereRaw) . ')';
            $agent->whereRaw($whereRaw, $bindings);
        } else {
            throw new \Exception('数据错误');
        }
        if ($agent->first()) {
            return true;
        }
        // 检测是否有申请中的
        // 替换一下对应的字段
        $whereRaw = str_replace(
            ['area_type', 'prov', 'city', 'district'],
            ['apply_area_type', 'apply_prov', 'apply_city', 'apply_district'],
            $whereRaw);
        $apply = AreaAgentApplyModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('status', AreaAgentConstants::AreaAgentStatus_WaitReview);
        if ($applyId) {
            $apply->where('id', '!=', $applyId);
        }
        $apply = $apply->whereRaw($whereRaw, $bindings)
            ->first();
        return !!$apply;
    }

    /**
     * 检测区域是否有区域重复的
     * @param  array $areaInfo 区域信息 [['area_type' => 9, 'prov' => 1111, 'city' => 11, 'district' => 222]];
     * @return bool|array
     */
    public static function checkAreaIsRepeat($areaInfo)
    {
        $areasColl = collect($areaInfo);
        foreach ($areaInfo as $item) {
            // 如果是省代 则当前不能代理此省的其他区域
            if ($item['area_type'] == AreaAgentConstants::AreaAgentLevel_Province) {
                $exit = $areasColl->where('area_type', '!=', AreaAgentConstants::AreaAgentLevel_Province)
                    ->where('prov', $item['prov'])
                    ->isNotEmpty();
                if ($exit) {
                    return true;
                }
            }
            // 如果是市代 则不能代理该市的区代和省代
            elseif ($item['area_type'] == AreaAgentConstants::AreaAgentLevel_City) {
                $exit = $areasColl->where('area_type', '=', AreaAgentConstants::AreaAgentLevel_District)
                    ->where('city', $item['city'])
                    ->isNotEmpty();
                if ($exit) {
                    return true;
                }
                $exit = $areasColl->where('area_type', '=', AreaAgentConstants::AreaAgentLevel_Province)
                    ->where('prov', $item['prov'])
                    ->isNotEmpty();
                if ($exit) {
                    return true;
                }
            } else {
                // 区代 则不能有该区的 省代和市代
                $exit = $areasColl->where('area_type', '=', AreaAgentConstants::AreaAgentLevel_Province)
                    ->where('prov', $item['prov'])
                    ->isNotEmpty();
                if ($exit) {
                    return true;
                }
                $exit = $areasColl->where('area_type', '=', AreaAgentConstants::AreaAgentLevel_City)
                    ->where('city', $item['city'])
                    ->isNotEmpty();
                if ($exit) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取会员代理的区域列表
     * @param $memberIds
     * @param bool $getAreaText
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getMemberAreaAgentList($memberIds, $getAreaText = true)
    {
        $agents = AreaAgentModel::query()
            ->where('site_id', getCurrentSiteId());
        if (is_array($memberIds)) {
            $agents->whereIn('member_id', $memberIds);
        } else {
            $agents->where('member_id', $memberIds);
        }
        $list = $agents->select([
            'area_type',
            'status',
            'prov',
            'city',
            'district',
            'member_id',
            'id'
        ])
            ->orderByDesc('area_type')
            ->get();
        if ($getAreaText) {
            $list = self::getListAreaText($list);
        }
        return $list;
    }

    /**
     * 获取列表中的区域名称
     * @param $list
     * @return mixed
     */
    public static function getListAreaText($list)
    {
        // 取出所有 省市区id
        $areaIds = [];
        foreach ($list as $item) {
            $areaIds[] = $item->prov;
            $areaIds[] = $item->city;
            $areaIds[] = $item->district;
        }
        // 去重
        $areaIds = array_unique($areaIds);
        // 查找所有需要的地址id
        $districtList = DistrictModel::query()->whereIn('id', $areaIds)->get()->keyBy('id');
        // 地址名称匹配
        foreach ($list as &$value) {
            $value->prov_text = $value->prov ? $districtList[$value->prov]['name'] : '';
            $value->city_text = $value->city ? $districtList[$value->city]['name'] : '';
            $value->district_text = $value->district ? $districtList[$value->district]['name'] : '';
        }
        return $list;
    }

    /**
     * 获取会员代理的区域列表和相关统计
     * @param int $memberId     会员id
     * @param bool $getTotal    是否是只获取下级总数
     * @return \Illuminate\Database\Eloquent\Collection|int|static[]
     */
    public static function getMemberAreaAgentListAndCount($memberId, $getTotal = false)
    {
        $siteId = getCurrentSiteId();
        $areaList = self::getMemberAreaAgentList($memberId);
        if ($areaList->isNotEmpty()) {
            // 代理的省的id
            $provIds = $areaList->where('area_type', AreaAgentConstants::AreaAgentLevel_Province)->pluck('prov')->toArray();
            $cityIds = $areaList->where('area_type', AreaAgentConstants::AreaAgentLevel_City)->pluck('city')->toArray();
            $provCount = [];
            $cityCount = [];
            // 统计省代的数据
            if ($provIds) {
                $provCount = AreaAgentModel::query()
                    ->where('site_id', $siteId)
                    ->whereIn('prov', $provIds)
                    ->where('status', AreaAgentConstants::AreaAgentStatus_Active);
                if ($getTotal) {
                    $provCount = $provCount->whereIn('area_type', [AreaAgentConstants::AreaAgentLevel_City, AreaAgentConstants::AreaAgentLevel_District])->count();
                } else {
                    $provCount = $provCount->selectRaw('prov, sum(if(`area_type`=?,1,0)) as city_count,sum(if(`area_type`=?,1,0)) as district_count',
                        [AreaAgentConstants::AreaAgentLevel_City, AreaAgentConstants::AreaAgentLevel_District])
                        ->groupBy('prov')
                        ->get()->keyBy('prov');
                }
            }
            if ($cityIds) {
                $cityCount = AreaAgentModel::query()
                    ->where('site_id', $siteId)
                    ->whereIn('city', $cityIds)
                    ->where('status', AreaAgentConstants::AreaAgentStatus_Active)
                    ->where('area_type', AreaAgentConstants::AreaAgentLevel_District);
                if ($getTotal) {
                    $cityCount = $cityCount->count();
                } else {
                    $cityCount = $cityCount->selectRaw('city, count(1) as district_count')
                        ->groupBy('city')
                        ->get()->keyBy('city');
                }
            }
            if ($getTotal) {
                return ($provCount ?: 0) + ($cityCount ?: 0);
            }
            // 把统计数据合并到记录
            foreach ($areaList as $area) {
                if ($area['area_type'] == AreaAgentConstants::AreaAgentLevel_Province) {
                    $area['sub_count'] = $provCount[$area['prov']] ?: ['city_count' => 0, 'district_count' => 0];
                } elseif ($area['area_type'] == AreaAgentConstants::AreaAgentLevel_City) {
                    $area['sub_count'] = $cityCount[$area['city']] ?: ['district_count' => 0];
                } else {
                    $area['sub_count'] = [];
                }
            }
        }
        return $areaList;
    }
}

