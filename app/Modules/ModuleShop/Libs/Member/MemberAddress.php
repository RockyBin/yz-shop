<?php
/**
 * 会员地址业务类
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Member;

use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Model\MemberAddressModel;
use YZ\Core\Site\Site;

class MemberAddress
{
    protected $_memberId = 0;

    public function __construct($memberId)
    {
        $this->_memberId = $memberId;
    }

    /**
     * 获取可用地址列表
     * @return array
     */
    public function getAddressList()
    {
        $list = MemberAddressModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->where('status', Constants::CommonStatus_Active)
            ->orderByRaw("is_default DESC, updated_at DESC,last_use_at DESC")
            ->get();
        $addressList = [];
        foreach ($list as $address) {
            $text = $address->addressText();
            $address = $address->toArray();
            $address['addressText'] = $text;
            $addressList[] = $address;
        }
        return $addressList;
    }

    /**
     * 获取默认的地址
     * @return array|\Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getDefaultAddress()
    {
        $address = MemberAddressModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $this->_memberId)
            ->where('status', Constants::CommonStatus_Active)
            ->orderByRaw("is_default DESC,last_use_at DESC")
            ->first();
        if ($address) {
            $addressText = $address->addressText();
            $address = $address->toArray();
            $address['addressText'] = $addressText;
        }
        return $address;
    }

    /**
     * 新增修改地址
     * @param $address
     * @return \LaravelArdent\Ardent\Ardent|\LaravelArdent\Ardent\Collection
     */
    public function editAddress($address)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        if ($address['is_default']) {
            // 如果是设置成默认，先把其他设成非默认
            MemberAddressModel::query()
                ->where('site_id', $siteId)
                ->where('member_id', $this->_memberId)
                ->where('is_default', 1)
                ->update(['is_default' => 0]);
        }

        $address['member_id'] = $this->_memberId;
        $address['site_id'] = $siteId;
        if (!$address['country']) {
            $address['country'] = "CN";
        }
        if ($address['id']) {
            $addressModel = MemberAddressModel::query()->where(['id' => $address['id'],'member_id' => $this->_memberId])->first();
            $addressModel->fill($address)->save();
        } else {
            $addressModel = MemberAddressModel::create($address);
        }
        return $addressModel;
    }

    /**
     * 软删除地址
     * @param $id 地址id
     * @return bool
     */
    public function deleteAddress($id)
    {
        if (empty($id)) return false;

        $address = [
            'id' => $id,
            'status' => Constants::CommonStatus_Unactive
        ];
        $this->editAddress($address);

        return true;
    }
}