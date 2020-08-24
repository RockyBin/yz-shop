<?php

namespace App\Modules\ModuleShop\Libs\UI;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\PageMobiModel;
use App\Modules\ModuleShop\Libs\Model\ModuleMobiModel;
use App\Modules\ModuleShop\Libs\Model\TemplateMobiModel;
use App\Modules\ModuleShop\Libs\TemplateMobi\TemplateMobi;
use App\Modules\ModuleShop\Libs\UI\Cache\MobiPageCache;
use App\Modules\ModuleShop\Libs\UI\Module\Mobi\ModuleFactory;
use App\Modules\ModuleShop\Libs\UI\Cache\MobiModuleCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\TemplateMobi\TemplateMobiHelper;

class PageMobi
{
    private $_model = null;

    /**
     * 构造函数
     * @param $idOrModel 数据库记录的模型或ID
     * @param int $deviceType 设备类型，1=手机，2=大屏，当没有指定 $idOrModel 才有效
     */
    public function __construct($idOrModel,$deviceType = 1){
        if($idOrModel){
            if(is_numeric($idOrModel)) $this->_model = PageMobiModel::where(['id' => $idOrModel,'site_id' => Site::getCurrentSite()->getSiteId()])->first();
            else $this->_model = $idOrModel;
        }else{
            $this->_model = new PageMobiModel(['device_type' => $deviceType]);
            $this->_model->id = 0;
            $this->_model->type = Constants::PageMobiType_Home;
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        }
    }

    public function update(array $info = []){
        if(array_key_exists('title',$info)) $this->_model->title = $info['title'];
        if(array_key_exists('description',$info)) $this->_model->description = $info['description'];
        if(array_key_exists('background',$info)) $this->_model->background = $info['background'];
        $this->_model->saved_at = Carbon::now();
        if(array_key_exists('type',$info)) $this->_model->type = $info['type'];
        if(array_key_exists('device_type',$info)) $this->_model->device_type = $info['device_type'];
        $this->_model->save();
        return $this->_model->id;
    }

    public function getModel(){
        return $this->_model;
    }

    /**
     * 渲染页面
     * @param int $fromCache 是否从缓存加载，一般，前台从缓存加载，后台不从缓存加载，通过 publish() 将页面数据生成缓存，以达到发布后才显示的目
     * @return array
     * @throws \Exception
     */
    public function render($fromCache = 0){
        if($fromCache){
            $modules = PageMobi::loadPageModules($this->getModel()->id, 1, 0, 1);
            $pageInfo = MobiPageCache::get($this->getModel()->site_id,$this->getModel()->id);
        }else {
            $modules = PageMobi::loadPageModules($this->getModel()->id, 1, 0, 0);
            $pageInfo = $this->getModel();
        }
        return ['pageInfo' => $pageInfo,'moduleInfo' => $modules ];
    }

    /**
     * 发布页面
     */
    public function publish(){
        //将页面数据缓存到用户目录下
        MobiPageCache::add($this->getModel()->site_id, $this->getModel()->id, $this->getModel());
        //生成模块缓存
        static::loadPageModules($this->getModel()->id,1, 1);
        // 更新发布时间
        $this->getModel()->publish_at = Carbon::now();
        $this->getModel()->save();
    }

    /**
     * 获取网站默认的导航
     * @param int $type 页面类型
     * @param int $deviceType 设备类型，1=手机，2=大屏
     * @return static
     */
    public static function getDefaultPage($type = Constants::PageMobiType_Home,$deviceType = 1){
        //默认查找第一个页面记录
        if(!$deviceType) $deviceType = 1;
        $model = PageMobiModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId(),'type' => $type,'device_type' => $deviceType])->first();
        if(!$model){
            $model = new PageMobiModel(['device_type' => $deviceType]);
            $model->type = $type;
            $model->site_id = Site::getCurrentSite()->getSiteId();
        }
        return new static($model,$deviceType);
    }

    /**
     * 加载移动端页面的模块数据
     * @param $pageId 页面ID
     * @param string $publish 查询条件：是否发布
     * @param int $updateCache 是否更新cache，一般用在后台编辑页面时，当不点击发布时，不应该更新cache
     * @param int $justCache 是否只从缓存数据里读取模块设置信息，一般情况下，用户在后台编辑完页面，在不点发布的情况下，数据是不会刷新到缓存的
     * 当用户点击发布时，将数据库里的数据按规则刷新到缓存，在正式环境下，只读取缓存里的数据，以达到页面点发布才生效的目的
     * @return mixed|null 返回模块的渲染结果
     * @throws \Exception
     */
    public static function loadPageModules($pageId,$publish = '',$updateCache = 1,$justCache = 0){
        $site = Site::getCurrentSite();
        $siteId = $site->getSiteId();
        $sortCol = 'show_order';
        $query = ModuleMobiModel::query();
        $query->where(['site_id' => $siteId,'page_id' => $pageId]);
        if($publish !== ''){
            if(strpos($publish,",") !== false) $query->whereIn('publish',explode(',',$publish));
            else $query->where('publish',$publish);
        }else{
            $query->where('publish',1);
        }
        $query->orderBy($sortCol,'ASC');
        $sql = $query->toSql().';'.var_export($query->getBindings(),true);
        $cacheKey = md5(strtolower($sql));//将sql转为小写是为了避免传参的大小写问题而导致sql不一致从而让缓存key无效
        if($justCache){
            $modules = MobiModuleCache::get($siteId, $cacheKey);
        }else{
            $modules = $query->get()->toArray();
            foreach($modules as $i => $m){
                $modules[$i] = ModuleFactory::createInstance($m);
            }
            if($updateCache == 1 && count($modules)) MobiModuleCache::add($siteId, $cacheKey, $modules);
        }
        if (is_array($modules)) {
            foreach ($modules as $i => $m) {
                $modules[$i] = $m->render();
            }
        }
        return array('cache_key' => $cacheKey,'modules' => $modules);
    }

    /**
     * 获取页面列表
     * @param array $param
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public static function getPageList($param = [], $page = 1, $pageSize = 20)
    {
        $query = PageMobiModel::query()
            ->where('tbl_page_mobi.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_page_mobi.device_type', '<>', 2) //大屏页面暂时不列出
            ->where('type', '<>', Constants::PageMobiType_MemberCenter); // 会员中心的页面不列出
        if (isset($param['type'])){
            if(!is_array($param['type'])) $param['type'] = [$param['type']];
            $query->whereIn('type',$param['type']);
        }
        if (isset($param['keyword'])) {
            $query->where('title', 'like', '%' . $param['keyword'] . '%');
        }
        if (isset($param['start_date']) && isset($param['end_date'])) {
            if (strtotime($param['end_date']) < $param['start_date']) {
                throw new \Exception('结束时间必须大于开始时间', 400);
            } else {
                $query->where('saved_at', '>=', $param['start_date'])
                    ->where('saved_at', '<=', $param['end_date']);
            }
        }
        // 查找模板名称
        $query->leftJoin('tbl_template_mobi', 'tbl_page_mobi.template_id', 'tbl_template_mobi.id');
        $total = $query->count();
        $lastPage = ceil($total / $pageSize);
        // 第一页的时候 主页排序到第一位
        $query->orderByRaw("find_in_set(type, 1) DESC,id DESC");
        $list = $query
            ->select(['tbl_page_mobi.*', 'tbl_template_mobi.name'])
            ->forPage($page, $pageSize)
            ->get();
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $list
        ];
    }

    /**
     * 设置页面为首页
     * @param $pageId
     * @return bool
     * @throws \Exception
     */
    public static function setHomePage($pageId)
    {
        // 查找页面
        $page = PageMobiModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $pageId)
            ->first();
        if (!$page) {
            throw new \Exception('页面不存在', 400);
        }
        // 把之前的主页设为自定义页面
        if ($page) {
            PageMobiModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('type', Constants::PageMobiType_Home)
                ->where('device_type', $page->device_type)
                ->update(['type' => Constants::PageMobiType_Custom]);
        }
        $page->type = Constants::PageMobiType_Home;
        $save = $page->save();

        return $save;
    }

    /**
     * 新建页面
     * @param array $info 页面信息
     * @return PageMobiModel
     * @throws \Exception
     */
    public static function addPage($info = array())
    {
        $model = new PageMobiModel();
        $siteId = Site::getCurrentSite()->getSiteId();
        if($info['name']) $model->title = $info['name'];
        else $model->title = "页面标题";
        if(!$info['device_type']) $info['device_type'] = 1;
        // 查找一下有没有页面 没有的话直接设置为主页
        $hasPage = PageMobiModel::query()->where('site_id', $siteId)->where('device_type', $info['device_type'])->first();
        $model->type = $hasPage ? Constants::PageMobiType_Custom : Constants::PageMobiType_Home;
        $model->site_id = $siteId;
        $model->device_type = $info['device_type'];
        $model->save();
        //复制模板的过程等设计那边模板做出来之后再加
        $template = new TemplateMobi();
        $tplInfo = $template->get($info['template_id']);
        if ($tplInfo && $tplInfo->is_blank != 1) {
            TemplateMobiHelper::installTemplate($tplInfo->id,$model->site_id,$model->id);
        }
        return $model;
    }

    /**
     * 创建一个空白页面 主要是新建站时使用
     * @param int $deviceType 设备类型，1=手机，2=大屏
     * @return PageMobiModel
     */
    public static function addBlankPage($deviceType = 1)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $model = new PageMobiModel(['device_type' => $deviceType]);
        $model->title = "页面标题";
        // 查找一下有没有页面 没有的话直接设置为主页
        $hasPage = PageMobiModel::query()->where('site_id', $siteId)->where('device_type', $deviceType)->first();
        $model->type = $hasPage ? Constants::PageMobiType_Custom : Constants::PageMobiType_Home;
        $model->site_id = $siteId;
        $model->save();
        return $model;
    }

    /**
     * 删除页面
     * @param int|array $pageIds    页面id 可以为数组
     * @return bool
     * @throws \Exception
     */
    public static function deletePage($pageIds)
    {
        if ($pageIds) {
            if (is_numeric($pageIds)) {
                $pageIds = [$pageIds];
            }
            // 查询出来要删除的页面
            $query = PageMobiModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('type', '!=', Constants::PageMobiType_Home)
                ->whereIn('id', $pageIds)
                ->get();
            $del = true;
            if ($query->count() > 0) {
                // 要删除页面对应的模块
                try {
                    DB::beginTransaction();
                    foreach ($query as $page) {
                        $page->modules()->delete();
                        $page->delete();
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                $del = false;
            }
            return $del;
        }
        return false;
    }

    /**
     * 获取会员中心页面需要的配置信息
     * @return array
     */
    public static function getMemberCenterPageConfig()
    {
        $config = (new Config(getCurrentSiteId()))->getModel();
        $colorConfig = (new StyleColorMobi())->getSiteColor();
        return [
            'retail_status' => $config->retail_status,
            'color_config' => $colorConfig['color_info']['css_file_name']
        ];
    }
}