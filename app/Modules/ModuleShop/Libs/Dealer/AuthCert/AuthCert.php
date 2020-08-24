<?php
namespace App\Modules\ModuleShop\Libs\Dealer\AuthCert;

use App\Modules\ModuleShop\Libs\Constants as AppConstants;
use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerAuthCertItemModel;
use App\Modules\ModuleShop\Libs\Model\DealerAuthCertModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Model\WxAppModel;
use YZ\Core\Site\Site;
use Illuminate\Support\Collection;
use Ipower\Common\Util;
use YZ\Core\Constants;
use YZ\Core\Weixin\WxApp;

class AuthCert
{
    private $_model = null;

    public function __construct($idOrModel = null)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) $this->_model = DealerAuthCertModel::where(['id' => $idOrModel])->first();
            else $this->_model = $idOrModel;
        } else {
            $this->_model = new DealerAuthCertModel();
            $this->_model->id = 0;
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        }
    }

    public function update(array $info = [])
    {
        if (array_key_exists('name', $info)) $this->_model->name = $info['name'];
        if (array_key_exists('background', $info)) $this->_model->background = $info['background'];
        if (array_key_exists('modules', $info)) $this->_model->modules = is_string($info['modules']) ? $info['modules'] : json_encode($info['modules']);
        if (array_key_exists('template', $info) && $info['template']) $this->_model->template = $info['template'];
        if (array_key_exists('site_id', $info)) $this->_model->site_id = $info['site_id'];
        $this->_model->updated_at = date('Y-m-d H:i:s');
        $this->_model->save();
        //因为生成图片的时候需要用到id,所以只能在save之后再进行预览图的一些生成
        $this->setPreviewImage();
        return $this->_model->id;
    }

    /**
     * 处理预览图相关事宜
     */
    public function setPreviewImage()
    {
        //如果有图片先把图片删除
        if ($this->_model->preview_image) {
            @unlink(public_path() . '/' . $this->_model->preview_image);
        }
        $img = $this->renderImage(0, 1, true);
        if ($img) $this->_model->preview_image = $img;
        $this->_model->save();

    }

    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 删除授权证书
     * @param $id array
     */
    public static function delete($id)
    {
        //拿取关键词的ID
        $data = DealerAuthCertModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $id)->first();
        //如果有图片先把图片删除
        if ($data->preview_image) {
            @unlink(public_path() . '/' . $data->preview_image);
        } 
        DealerAuthCertModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $id)
            ->delete();
    }

    /**
     * 获取海报列表
     * @param $serach 搜索条件
     * @param int $page
     * @param int $pageSize
     */
    public static function getList($search = null, $page = 1, $pageSize = 20)
    {
        // 数据过滤
        $page = intval($page);
        $pageSize = intval($pageSize);
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = DealerAuthCertModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if ($search['name']) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }
        $total = $query->count();
        $query->orderByRaw('used desc ,id desc');
        $last_page = ceil($total / $pageSize);
        if ($page > 0) {
            $query->forPage($page, $pageSize);
        }
        $list = $query->select(['id', 'name', 'levels', 'preview_image', 'updated_at','used'])->orderBy('used','desc')->orderBy('id', 'desc')->get();
        unset($item);
        $result = [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }

    /**
     * 渲染页面
     * @param $memberId
     * @param $termainal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * @return 输出HTML
     * @throws \Exception
     */
    public function render($memberId,$terminal = 0)
    {
        $member = new Member($memberId);
        $mModel = $member->getModel();
        $aModel = DealerModel::where('member_id',$mModel->id)->first();
        $data = $this->getModel()->toArray();
        $level = DealerLevelModel::find($mModel->dealer_level);
        $wxAppModel = WxAppModel::query()->where('site_id',getCurrentSiteId())->first();
        $isWxApp = $wxAppModel && $wxAppModel->appid && $terminal == \YZ\Core\Constants::TerminalType_WxApp;
        if ($mModel) {
            $data['memberInfo'] = $mModel->toArray();
            if (!$mModel->headurl) {
                $data['memberInfo']['headurl'] = '/shop/front/images/default_head.png'; //默认头像
            } elseif (strpos($mModel->headurl, 'images/') !== false) {
                $data['memberInfo']['headurl'] = '/shop/front/' . $data['memberInfo']['headurl'];
            } elseif (!preg_match('@^https?://@i', $mModel->headurl) !== false) {
                $data['memberInfo']['headurl'] = Site::getSiteComdataDir() . $data['memberInfo']['headurl'];
            }

        } else {
            $data['memberInfo']['headurl'] = '/shop/front/images/default_head.png'; //默认头像
        }
        if (!preg_match('@^(https?:)|(/comdata|sysdata/)@', $data['background'])) $data['background'] ? $data['background'] = '/shop/front/' . $data['background'] : '';
        $data['modules'] = json_decode($data['modules'], true);
        //先处理下图片模块的src
        foreach ($data['modules'] as &$item) {
            if ($item['module_type'] == 'ModuleImage') {
                if (strpos($item['src'], 'images/authcert') !== false) {
                    $item['src'] = '/shop/front/' . $item['src'];
                }
            }
            if ($item['module_type'] == 'ModuleQrcode') {
                $item = static::formatQrcodeModule($item);
                //生成二维码网址
                $qrurl = '/shop/front/';
                if ($item['linkinfo']['link_type']) $qrurl .= LinkHelper::getUrl($item['linkinfo']['link_type'], $item['linkinfo']['link_data']);
                else $qrurl .= '#/';
                if ($data['memberInfo']['id']) $qrurl .= (strpos($qrurl, '?') === false ? '?' : '&') . 'invite=' . $data['memberInfo']['id'];
                //生成二维码
                $qrcode = QrCode::format('png')
                    ->size(700)
                    ->encoding('UTF-8')
                    ->errorCorrection('Q')
                    ->margin(1);
                if (strpos($item['logo'], 'images/authcert') !== false) {
                    $item['logo'] = '/shop/front/' . $item['logo'];
                } elseif ($item['logo']) {
                    $item['logo'] = $item['logo'];
                }
                if (file_exists($item['logo'])) {
                    // $qrcode->merge($item['logo'],.3,true);
                }
                if ($isWxApp){
                    $qrcode = $this->createQrcodeWithWxApp($memberId,$qrurl);
                } elseif ($item['qrtype'] == '1' && $memberId) { //公众号二维码
                    $site = Site::getCurrentSite();
                    $wechat = $site->getOfficialAccount();
                    $savepath = Site::getSiteComdataDir('',true).'/agent-cert-qrcode';
                    Util::mkdirex($savepath);
                    $file = $savepath.'/member-'.$memberId.'-'.$wechat->getConfig()->getModel()->appid.'.jpg';
                    if(file_exists($file)){
                        $qrcode = file_get_contents($file);
                    } else {
                        $res = $wechat->qrcode("invite", $memberId);
                        $qrcode = file_get_contents($res['qrurl']);
                        file_put_contents($file, $qrcode); //保存一下用户的公众号二维码，避免每次重新生成可能会导致微信的永久码不够用的问题
                    }
                } else {
                    $qrurl = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . $qrurl;
                    $qrcode = $qrcode->generate($qrurl);
                }
                $qrcode = "data:image/png;base64," . base64_encode($qrcode);
                $item['qrdata'] = $qrcode;
            }
            if ($item['module_type'] == 'ModuleLevel') {
                $item['text'] = $level->name;
            }
            if ($item['module_type'] == 'ModuleAuthDate') {
                $item['text'] = $aModel ? date('Y年m月d日',strtotime($aModel->passed_at)) : "";
            }
            if ($item['module_type'] == 'ModuleAuthCode') {
                if(strlen($mModel->id) > 6) $codeId = substr($mModel->id, -6);
                else $codeId = str_pad($mModel->id,6,0,STR_PAD_LEFT);
                $item['text'] = $aModel ? "授权编码：".$item['prefix'].date('Ymd',strtotime($aModel->passed_at)).$codeId : "授权编码：".date('Ymd')."000001";
            }
            if ($item['module_type'] == 'ModuleAuthTerm') {
                if ($aModel) {
                    if (!$item['term']) $item['term'] = 12;
                    $enddate = strtotime($aModel->passed_at." +".$item['term']." month");
                    if ($item['autorenew'] && $enddate < time()) {
                        while ($enddate < time()) {
                            $enddate = strtotime("+".$item['term']." month", $enddate);
                        }
                    }
                    $item['text'] = "授权期限：".date('Y年m月d日', strtotime($aModel->passed_at)).'-'.date('Y年m月d日', $enddate);
                }else{
                    $item['text'] = "授权期限：".date('Y年m月d日').'-'.date('Y年m月d日', strtotime('+1 year'));
                }
            }
        }
        unset($item);
        $data['width'] = $_REQUEST['width'] ? $_REQUEST['width'] : 375;
        $data['fontscale'] = $data['width'] / 375; //因为设计界面是按375宽度的，所以文字要有一个放大比例，否则生成的图片中的文本就会模糊
        return view('moduleshop::AuthCert/AuthCert', $data);
    }

    private function createQrcodeWithWxApp($memberId,$qrurl){
        $wxConfig = WxAppModel::query()->where('site_id',getCurrentSiteId())->first();
        $savepath = Site::getSiteComdataDir('', true) . '/dealer-auth-qrcode';
        Util::mkdirex($savepath);
        //检测是之前是否有生成过了
        $filename = 'member-' . $memberId . '-' . $wxConfig->appid .'-'.md5($qrurl) . '.jpg';
        $file = $savepath . '/' . $filename;
        if (file_exists($file)) {
            $qrcode = file_get_contents($file);
        }else {
            $wxApp = new WxApp();
            $qrurl = "pages/index?url=" . urlencode(str_replace('/#/', '/vuehash/', $qrurl));
            $response = $wxApp->getQrcode($qrurl, ['auto_color' => true, 'width' => 700]);
            if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                $filename = $response->saveAs($savepath, $filename);
                $qrcode = file_get_contents($savepath.'/'.$filename);
            }
        }
        return $qrcode;
    }

    /**
     * 渲染海报并生成图片
     * @param $memberId
     * @param int $returnType 0=返回图片的url地址，1=返回本地图片路径（这个是为了方便微信等后续需要对图片进行一些处理的情况），2=返回IMG标签
     * @param $admin 后台使用去生成预览图片
     * @param $termainal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * 否则就返回图片的 url 地址
     */
    public function renderImage($memberId, $returnType = 0, $admin = false, $terminal = 0)
    {
        $wkhtml = new \Ipower\Common\WkhtmlUtil();
        $width = 750;
        $url = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/authcert/render?width=' . $width . '&member_id=' . $memberId;
        $url .= '&id=' . $this->_model->id.'&terminal='. $terminal;
        $dir = Site::getSiteComdataDir().'/dealer-authcert';
        if (!is_dir(public_path().$dir)) mkdir(public_path().$dir, 0777, true);
        if ($admin) {
            //生成的预览图带demo标志
            $file = $dir.'/authcert_demo_' . time() . '.jpg';
        } else {
            $file = $dir.'/authcert_' . $memberId.($terminal ? '_'.$terminal:'') . '.jpg';
        }
        $bool = $wkhtml->generateImg($url, public_path() . $file, ['width' => $width]);
        if ($bool) {
            if ($returnType === 0) return getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . $file;
            elseif ($returnType === 1) return $admin ? $file : str_replace('\\','/',public_path()) . $file;
            else return "<img src='" . getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . $file . "?rand=" . mt_rand() . "'>";
        }
        return null;
    }

    /**
     * 获取网站默认的授权证书
     */
    public static function getDefaultPaper()
    {
        //默认查找第一条记录
        $model = DealerAuthCertModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->first();
        if (!$model) {
            $model = new DealerAuthCertModel();
            $model->site_id = Site::getCurrentSite()->getSiteId();
        }
        return new static($model);
    }

    /**
     * 获取模板数据
     * @return json $templateData 返回模板数据
     */
    public static function templateDate()
    {
        $dir = __DIR__ . '/Template';
        $data = [];
        $handle = opendir($dir);
        if ($handle) {
            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $content = file_get_contents($dir . '/' . $file);
                    $data[] = json_decode($content, true);
                }
            }
            closedir($handle);
        }
        //文件按照要求重新排序
        $data = self::sortTemplateDate($data);
        return $data;
    }

    /**
     * 模板文件排序
     * @param  $data 模板文件数组
     */
    private static function sortTemplateDate($data)
    {
        $collection = new Collection($data);
        $sort = $collection->sortBy('sort');
        $data = $sort->values()->all();
        return $data;
    }

    /**
     * 获取海报显示的位置的信息
     */
    public static function getApplySettingInfo()
    {
        $query = DealerAuthCertModel::query()->where('site_id', Site::getCurrentSite()->getSiteId());
        $list = $query->select(['id', 'name', 'levels', 'preview_image'])->get();
        $data = [];
        foreach($list as $item){
            $levels = json_decode($item->levels,true);
            if(!is_array($levels)) continue;
            foreach($levels as $level) {
                $data[$level] = $item;
            }
        }
        return $data;
    }

    /**
     * 保存海报显示的位置的信息
     *
     * @param array $info 设置信息，格式如 ['level_1' => 证书ID,'level_2' => 证书ID, ...]
     * @return void
     */
    public static function saveApplySettingInfo($info = array())
    {
        $idsLevel = [];
        foreach($info as $level => $id){
            $level = preg_replace('/[^\d]+/','',$level);
            $idsLevel[$id][] = $level;
        }
        DealerAuthCertModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId()])->update(['levels' => json_encode([]),'used' => 0]);
        foreach($idsLevel as $id => $levels){
            DealerAuthCertModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId(),'id' => $id])->update(['levels' => json_encode($levels),'used' => 1]);
        }
    }

    /**
     * 生成代理的授权证书
     *
     * @param int $memberId
     * @return void
     */
    public static function createMemberCert($memberId, $terminal = 0){
        //检测后台是否有配置好证书
        $member = new Member($memberId);
        $dealerLevel = $member->getModel()->dealer_level;
        $config = static::getApplySettingInfo();

        if (!$config[$dealerLevel]) {
            throw new \Exception("商家还设置授权证书，如有疑问，请联系客服~");
        }
        $certId = $config[$dealerLevel]->id;
        $cert = DealerAuthCertModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id',$certId)->first();
        if(!$cert){
            throw new \Exception("证书配置信息缺失，如有疑问，请联系客服~");
        }
        //读取当前会员是否已经生成过证书
        $certItem = DealerAuthCertItemModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('member_id',$memberId)->first();
        //生成证书ID
        $modules = new Collection(json_decode($cert->modules,true));
        $moduleAuthCode = $modules->where('module_type','ModuleAuthCode')->values()[0];
        $aModel = DealerModel::where('member_id',$memberId)->first();
        if(strlen($memberId) > 6) $codeId = substr($memberId, -6);
        else $codeId = str_pad($memberId,6,0,STR_PAD_LEFT);
        $id = $moduleAuthCode['prefix'].date('Ymd',strtotime($aModel->passed_at)).$codeId;
        if(!$certItem){
            $certItem = new DealerAuthCertItemModel();
            $certItem->site_id = Site::getCurrentSite()->getSiteId();
            $certItem->member_id = $memberId;
        }
        $authCert = new static($certId);
        $image = $authCert->renderImage($memberId, 1);
        $image = str_replace(Site::getSiteComdataDir('',true),'',$image);
        $certItem->image = $image;

        $wxAppModel = WxAppModel::query()->where('site_id',getCurrentSiteId())->first();
        if($wxAppModel) {
            $imageWxApp = $authCert->renderImage($memberId, 1, false, \YZ\Core\Constants::TerminalType_WxApp);
            $imageWxApp = str_replace(Site::getSiteComdataDir('', true), '', $imageWxApp);
            $certItem->image_wxapp = $imageWxApp;
        }

        $certItem->id = $id;
        $certItem->updated_at = date('Y-m-d H:i:s');
        $certItem->save();
        return $certItem;
    }

    /**
     * 根据手机号或证书编号查询会员的授权证书
     *
     * @param string $keyword
     * @return array
     */
    public static function searchMemberCert($keyword){
        $query = DealerAuthCertItemModel::query()->where('tbl_dealer_authcert_item.site_id', Site::getCurrentSite()->getSiteId());
        $query->select('tbl_dealer_authcert_item.*','tbl_dealer.status as agent_status');
        $query->leftJoin('tbl_member','tbl_member.id','tbl_dealer_authcert_item.member_id');
        $query->leftJoin('tbl_dealer','tbl_dealer.member_id','tbl_dealer_authcert_item.member_id');
        $query = $query->where(function ($query) use ($keyword) {
            $query->where('tbl_dealer_authcert_item.id',$keyword)->orWhere('tbl_member.mobile',$keyword);
        });
        $certItem = $query->first();
        return $certItem;
    }

    /**
     * 对二维码模块数据进行 格式化处理 以及 进行默认值处理
     * @param $module
     */
    public static function formatQrcodeModule($module) {
        $module['showlink'] = 1; //强制设置为显示链接选择器
        if(!is_array($module['linkinfo'])) $module['linkinfo'] = [];
        if(!$module['linkinfo']['link_type']){
            $module['linkinfo']['link_type'] = 'authcert_query';
            $module['linkinfo']['link_data'] = 0;
            $module['linkinfo']['link_url'] = '/dealer/dealer-authcert-query';
            $module['linkinfo']['link_desc'] = '链接到 授权查询';
        }
        return $module;
    }
}