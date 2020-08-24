<?php
namespace YZ\Core\Model;

/**
 * 站点的主记录
 * Class SiteModel
 * @package YZ\Core\Model
 */
class SiteModel extends BaseModel
{
    protected $table = 'tbl_site';
    protected $primaryKey = 'site_id';
    //protected $fillable = [];
	//public static $rules = array();

    public function __construct()
    {
        parent::__construct();
    }

    public static function boot()
    {
        parent::boot();
        //static::setEventDispatcher(new \Illuminate\Events\Dispatcher());
        static::saved(function($model){
            static::onSaved($model);
        });
        static::deleted(function($model){
            static::onDeleted($model);
        });
    }

    public static function onSaved($model)
    {
        if($model->getOriginal('domains') != $model->domains) {
            DomainModel::where('site_id', $model->site_id)->delete();
            $domains = preg_split('/[\s,;]+/', $model->domains);
            foreach ($domains as $domain) {
                $row = new DomainModel();
                if(substr($domain,0,4) == "www.") $domain = substr($domain,4);
                $row->domain = $domain;
                $row->site_id = $model->site_id;
                $row->save();
            }
        }
    }

    public static function onDeleted($model)
    {
        DomainModel::where('site_id', $model->site_id)->delete();
    }
}