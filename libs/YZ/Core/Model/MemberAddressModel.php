<?php

namespace YZ\Core\Model;

use Illuminate\Support\Facades\DB;

/**
 * 会员的收货地址
 * Class MemberAddressModel
 * @package YZ\Core\Model
 */
class MemberAddressModel extends BaseModel
{
    protected $table = 'tbl_member_address';
    protected $fillable = [
        'country', 'prov', 'city', 'area', 'address', 'name', 'phone', 'site_id', 'member_id', 'is_default', 'status'
    ];
    public $timestamps = true;

    public function addressText()
    {
        $country = $this->country == 'CN' ? '中国' : '国外';
        $districtIds = [$this->prov, $this->city, $this->area];
        $district = DB::table('tbl_district')
            ->whereIn('id', $districtIds)
            ->pluck('name', 'id');
        return [
            'country' => $country,
            'prov' => $district[$this->prov],
            'city' => $district[$this->city],
            'area' => $district[$this->area],
            'address' => $this->address
        ];
    }
}