<?php

namespace App\Modules\ModuleShop\Libs\Member;

use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\MemberConfigModel;

/**
 * 会员配置类
 * Class MemberConfig
 * @package App\Modules\ModuleShop\Libs\Member
 */
class MemberConfig
{
    private $siteID = 0; // 站点ID

    /**
     * 初始化
     * MemberConfig constructor.
     * @param int $siteID
     */
    public function __construct($siteID = 0)
    {
        if ($siteID) {
            $this->siteID = $siteID;
        } else {
            $this->siteID = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 获取单条数据
     * @return bool
     */
    public function getInfo()
    {
        $memberConfig = MemberConfigModel::where([
            ['site_id', $this->siteID]
        ])->first();
        return $memberConfig ? $memberConfig : false;
    }

    /**
     * 获取单条数据，如果数据库没有，则插入默认数据再获取
     * @return bool
     */
    public function getConfig()
    {
        $memberConfig = $this->getInfo();
        if (!$memberConfig) {
            $this->save([]);
            $memberConfig = $this->getInfo();
        }

        return $memberConfig ? $memberConfig : false;
    }

    /**
     * 保存（新建或修改）
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {
        $memberConfig = MemberConfigModel::firstOrNew([
            'site_id' => $this->siteID
        ]);
        // 如果新建，需要赋予site_id
        if (!$memberConfig->site_id) {
            $memberConfig->site_id = $this->siteID;
        }
        $memberConfig->fill($data);
        return $memberConfig->save();
    }
}