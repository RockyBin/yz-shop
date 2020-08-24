<?php

namespace App\Modules\ModuleShop\Libs\Model\AreaAgent;

use App\Modules\ModuleShop\Rules\AreaAgent\AreaAgentRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class AreaAgentLevelModel extends BaseModel
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('site_id', function(Builder $builder){
            $builder->where('site_id','=',Site::getCurrentSite()->getSiteId());
        });

    }

    public function areaAgent()
    {
        return $this->hasMany(AreaAgentModel::class,'area_agent_level');
    }

    public function areaApplyAgent()
    {
        return $this->hasMany(AreaAgentApplyModel::class,'apply_area_agent_level');
    }

    protected $table = 'tbl_area_agent_level';
    protected $guarded = [];
    protected $casts = [
        'commission' => 'array'
    ];
    public $timestamps = true;

    const STATUS_ACTIVE = 1;//启用
    const STATUS_FORBIDDEN = 0;//禁用
    const AREA_AGENT_ARRAY = [self::FIELD_DISTRICT=>'区代理', self::FIELD_CITY=>'市代理', self::FIELD_PROVINCE=>'省代理'];
    const FIELD_PROVINCE = self::TABLE_SETTINGS.'.province';
    const FIELD_CITY = self::TABLE_SETTINGS.'.city';
    const FIELD_DISTRICT = self::TABLE_SETTINGS.'.district';

    //table condition select field
    const TABLE_STATUS = 'status';
    const TABLE_SITE_ID = 'site_id';
    const TABLE_SETTINGS = 'commission';
    const TABLE_NAME = 'name';

    //request parameter
    const REQUEST_STATUS = 'status';//选择状态

    //verify condition restrict
    const WHERE_DECIMAL = 100.000;

    protected $hidden = [self::CREATED_AT, self::UPDATED_AT];

    public static $customMessages = [
        'name.required' => '名称不能为空',
        'name.max' => '名称 最多20个字',
        self::TABLE_SETTINGS.'.required' => ':attribute 不能为空',
        self::TABLE_SETTINGS.'.array' => ':attribute 必须是个数组',
        self::TABLE_SETTINGS.'.province.required' => ':attribute 省代理 不能为空',
        self::TABLE_SETTINGS.'.province.max' => ':attribute 省代理 最大100',
        self::TABLE_SETTINGS.'.province.numeric' => ':attribute 省代理 必须是数值',
        self::TABLE_SETTINGS.'.city.required' => ':attribute 市代理 不能为空',
        self::TABLE_SETTINGS.'.city.numeric' => ':attribute 市代理 必须是数值',
        self::TABLE_SETTINGS.'.city.max' => ':attribute 市代理 最大100',
        self::TABLE_SETTINGS.'.district.required' => ':attribute 区代理 不能为空',
        self::TABLE_SETTINGS.'.district.max' => ':attribute 区域代理 最大100',
        self::TABLE_SETTINGS.'.district.numeric' => ':attribute 区域代理 必须是数值',
        self::TABLE_SETTINGS.'.size' => ':attribute 最多三个元素'
    ];

    public function rules()
    {
        $some = ['required','numeric','max:100', new AreaAgentRule()];
        return [
            'name' => 'bail|required|max:20',
            self::TABLE_SETTINGS => 'array|size:3',
            self::TABLE_SETTINGS.'.province' => $some,
            self::TABLE_SETTINGS.'.city' => $some,
            self::TABLE_SETTINGS.'.district' => $some
        ];
    }

    public function scopeStatus($query)
    {
        return $query->where(self::TABLE_STATUS, request(self::REQUEST_STATUS,self::STATUS_ACTIVE));
    }

    public function scopeForbidden($query)
    {
        return $query->where(self::TABLE_STATUS, self::STATUS_FORBIDDEN);
    }

    public function scopeActive($query)
    {
        return $query->where(self::TABLE_STATUS, self::STATUS_ACTIVE);
    }

    public function dataFilter(array $data)
    {
         $validator = Validator::make($data, $this->rules(), self::$customMessages);

         if ($validator->fails())
         {
             throw new \Exception($validator->errors()->first());
         }

         $data[self::TABLE_SITE_ID] = Site::getCurrentSite()->getSiteId();

         return $this->fill($data);
    }

    public function pass()
    {
        return $this->areaAgent();
    }

    public function applying()
    {
        return $this->areaAgent();
    }
}
