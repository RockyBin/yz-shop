<?php

namespace App\Modules\ModuleShop\Libs\SharePaper\Mobi;

use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\Constants;

use App\Modules\ModuleShop\Libs\Link\LinkHelper;
use function GuzzleHttp\Promise\queue;
use Ipower\Common\Util;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\SiteAdminModel;
use YZ\Core\Model\WxAppModel;
use YZ\Core\Model\WxReplyModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;
use YZ\Core\Weixin\WxApp;
use YZ\Core\Weixin\WxAutoReply;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use Illuminate\Support\Collection;
use YZ\Core\Weixin\WxConfig;

class Paper
{
    private $_model = null;
    private $siteId = 0;

    public function __construct($idOrModel = null)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) $this->_model = SharePaperModel::where(['id' => $idOrModel])->first();
            else $this->_model = $idOrModel;
        } else {
            $this->_model = new SharePaperModel();
            $this->_model->id = 0;
            $this->_model->type = Constants::SharePaperType_Home;
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        }
        self::setSiteId(Site::getCurrentSite()->getSiteId());
    }

    /**
     * 这个方法主要用于新建网站的时候，Site这个类还没有初始化，但又要使用到site_id，所以需要传值创建
     */
    public function setSiteId($siteId = 0)
    {
        $this->siteId = $siteId;
    }

    /**
     * 根据不同的type，生成不同的Model
     * @param $type 0 会员中心 1 分销中心 2 代理中心
     */
    public static function getTypePaper($type)
    {
        $query = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        switch (true) {
            case $type == Constants::SharePaperType_MemberCenter:
                $query->where('show_member_center', 1);
                break;
            case $type == Constants::SharePaperType_DistributorCenter:
                $query->where('show_distributor_center', 1);
                break;
            case $type == Constants::SharePaperType_AgentCenter:
                $query->where('show_agent_center', 1);
                break;
            case $type == Constants::SharePaperType_AreaAgentCenter:
                $query->where('show_area_agent_center', 1);
                break;
            case $type == Constants::SharePaperType_DealerCenter:
                $query->where('show_dealer_center', 1);
                break;
            case $type == Constants::SharePaperType_StaffCenter:
                $query->where('show_staff_center', 1);
                break;
            default :
                return self::getDefaultPaper();
        }
        $model = $query->first();
        return new static($model);
    }


    public function update(array $info = [])
    {
        if (array_key_exists('name', $info)) $this->_model->name = $info['name'];
        if (array_key_exists('background', $info)) $this->_model->background = $info['background'];
        if (array_key_exists('type', $info)) $this->_model->type = $info['type'];
        if (array_key_exists('modules', $info)) $this->_model->modules = is_string($info['modules']) ? $info['modules'] : json_encode($info['modules']);
        if (array_key_exists('desc', $info)) $this->_model->desc = $info['desc'];
        if (array_key_exists('template', $info) && $info['template']) $this->_model->template = $info['template'];
        if (array_key_exists('site_id', $info)) $this->_model->site_id = $info['site_id'];
        if (array_key_exists('show_member_center', $info)) $this->_model->show_member_center = $info['show_member_center'];
        if (array_key_exists('show_distributor_center', $info)) $this->_model->show_distributor_center = $info['show_distributor_center'];
        if (array_key_exists('show_agent_center', $info)) $this->_model->show_agent_center = $info['show_agent_center'];
        if (array_key_exists('show_area_agent_center', $info)) $this->_model->show_area_agent_center = $info['show_area_agent_center'];
        if (array_key_exists('show_dealer_center', $info)) $this->_model->show_dealer_center = $info['show_dealer_center'];
        if (array_key_exists('show_staff_center', $info)) $this->_model->show_staff_center = $info['show_staff_center'];
        $this->_model->updated_at = date('Y-m-d H:i:s');
        $this->_model->save();
        //设置关键词需要用到id
        $this->setKeyword($info['keyword'], $info['name']);
        // 生成图片的时候需要实例化一下site_id
        $this->setSiteId($info['site_id']);
        //因为生成图片的时候需要用到id,所以只能在save之后再进行预览图的一些生成
        $this->setPreviewImage();

        return $this->_model->id;
    }

    /**
     * 处理关键词
     * @param $keyword 关键词
     * @param $name 关键词名字
     */
    private function setKeyword($keyword, $name)
    {
        if ($keyword) {
            if ($this->_model->keyword_id) {
                $wxAutoReply = new WxAutoReply($this->_model->keyword_id);
            } else {
                $wxAutoReply = new WxAutoReply();
                $wxAutoReply->setCreatedAt(date('Y-m-d H:i:s'));
            }
            $wxAutoReply->setKeyword($keyword);
            $wxAutoReply->setName($name);
            $wxAutoReply->setType(CoreConstants::Weixin_AutoReply_Keyword);
            $callback = '\App\Modules\ModuleShop\Libs\SharePaper\Mobi\WeixinMessageHelper@sendWeixinPaperImage';
            $param = [['paper_id' => $this->_model->id]];
            $wxAutoReply->setReplyCallback($callback, CoreConstants::Weixin_Callback_Poster, $param);
            $wxAutoReply->setUpdatedAt(date('Y-m-d H:i:s'));
            $wxAutoReply->save();
            $keyword_id = $wxAutoReply->getModel()->id;

            $this->_model->keyword_id = $keyword_id;
        } else {
            //如果关键词为空，但原本又有关键词，说明需要删除关键词,还要把keyword_id置为0
            if ($this->_model->keyword_id) {
                $wxAutoReply = new WxAutoReply($this->_model->keyword_id);
                $wxAutoReply->delete();
                $this->_model->keyword_id = 0;
            }
        }
        $this->_model->save();
    }

    /**
     * 处理预览图相关事宜
     */
    public function setPreviewImage()
    {
        //如果有图片先把图片删除 ,但不删除模板图片(模板图片被删除了，需要改BUG)
        if ($this->_model->preview_image && strpos($this->_model->preview_image, 'sysdata') === false) {
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
     * 保存海报显示的位置
     * @param  $data array [{type:0,id:1},{type:1,id:2}] //0：会员中心 1：分销中心 2：团队分红
     */
    public static function savePaperShow(array $data)
    {
        //['id'=>$item->id,'显示的位置'=>1]
        foreach ($data as $item) {
            if ($item['id'] != 0) {
                if ($item['type'] == Constants::SharePaperType_MemberCenter) {
                    //因为只展现一张图片，所以需要先把其他的全部置为0先
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_member_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_member_center = 1;
                } elseif ($item['type'] == Constants::SharePaperType_DistributorCenter) {
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_distributor_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_distributor_center = 1;
                } elseif ($item['type'] == Constants::SharePaperType_AgentCenter) {
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_agent_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_agent_center = 1;
                } elseif ($item['type'] == Constants::SharePaperType_DealerCenter) {
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_dealer_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_dealer_center = 1;
                } elseif ($item['type'] == Constants::SharePaperType_StaffCenter) {
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_staff_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_staff_center = 1;
                } elseif ($item['type'] == Constants::SharePaperType_AreaAgentCenter) {
                    SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->update(['show_area_agent_center' => 0]);
                    $paper = SharePaperModel::query()
                        ->where('site_id', Site::getCurrentSite()->getSiteId())
                        ->where('id', $item['id'])
                        ->first();
                    $paper->show_area_agent_center = 1;
                }
                $paper->save();
            }
        }
    }

    /**
     * 拿取分销中心设置以及团队分红设置
     */
    public static function getConfig()
    {
        $site = Site::getCurrentSite();
        $distributionSetting = $site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_DISTRIBUTION) ? true : false;
        $agentSetting = $site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_AGENT) ? true : false;
        $areaAgentSetting = $site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT) ? true : false;
        return ['memberSetting' => true, 'staffSetting' => true, 'distributionSetting' => $distributionSetting, 'agentSetting' => $agentSetting, 'areaAgentSetting' => $areaAgentSetting, 'dealerSetting' => true];
    }

    /**
     * 拿取海报显示的位置的信息
     */
    public static function getShowPosition()
    {
        //会员中心
        $memberCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_member_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        //分销中心
        $distributorCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_distributor_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        //代理中心
        $agentCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_agent_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        //区域代理中心
        $areaAgentCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_area_agent_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        //经销商中心
        $dealerCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_dealer_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        //员工端
        $staffCenter = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('show_staff_center', 1)
            ->select(['id', 'name', 'preview_image'])
            ->first();
        return ['memberSetting' => $memberCenter, 'distributionSetting' => $distributorCenter, 'agentSetting' => $agentCenter, 'areaAgentSetting' => $areaAgentCenter, 'dealerSetting' => $dealerCenter, 'staffSetting' => $staffCenter];
    }

    public static function getConfigInfo()
    {
        $info = self::getShowPosition();
        $config = self::getConfig();
        return ['info' => $info, 'config' => $config];
    }

    /**
     * 删除海报
     * @param $id array
     */
    public static function delete($id)
    {
        //拿取关键词的ID
        $query = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $id);
        $keyword_data = $query->selectRaw('group_concat(keyword_id) as keyword_id')->first();
        //同步删除关键词
        if ($keyword_data->keyword_id) {
            WxReplyModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->whereIn('id', explode(',', $keyword_data->keyword_id))
                ->delete();
        }
        SharePaperModel::query()
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

        $query = SharePaperModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());
        if ($search['name']) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }
        $total = $query->count();
        $last_page = ceil($total / $pageSize);
        if ($page > 0) {
            $query->forPage($page, $pageSize);
        }
        $list = $query->select(['id', 'name', 'desc', 'preview_image', 'updated_at'])->orderBy('id', 'desc')->get();

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
     * @param int $fromCache 是否从缓存加载，一般，前台从缓存加载，后台不从缓存加载，通过 publish() 将页面数据生成缓存，以达到发布后才显示的目
     * @param $type 生成的位置，确定是不是在员工端生成
     * @param $terminal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * @return 输出HTML
     * @throws \Exception
     */
    public function render($memberId, $type = 0, $terminal = 0)
    {
        $data = $this->getModel()->toArray();
        // 获取显示会员或者员工的信息
        $data['memberInfo'] = $this->getPersonInfo($memberId, $type);
        if (!preg_match('@^(https?:)|(/comdata|sysdata/)@', $data['background'])) $data['background'] ? $data['background'] = '/shop/front/' . $data['background'] : '';
        $data['modules'] = json_decode($data['modules'], true);
        $wxAppModel = WxAppModel::query()->where('site_id', getCurrentSiteId())->first();
        $isWxApp = $wxAppModel && $wxAppModel->appid && $terminal == \YZ\Core\Constants::TerminalType_WxApp;
        //先处理下图片模块的src
        foreach ($data['modules'] as &$item) {
            if ($item['module_type'] == 'ModuleImage') {
                if (strpos($item['src'], 'images/share-paper') !== false) {
                    $item['src'] = '/shop/front/' . $item['src'];
                }
            }
            if ($item['module_type'] == 'ModuleQrcode') {

                if (strpos($item['logo'], 'images/share-paper') !== false) {
                    $item['logo'] = '/shop/front/' . $item['logo'];
                } elseif ($item['logo']) {
                    $item['logo'] = $item['logo'];
                }
                if (file_exists($item['logo'])) {
                    // $qrcode->merge($item['logo'],.3,true);
                }
                if ($isWxApp) {
                    $qrcode = $this->createQrcodeWithWxApp($memberId, $type, $item['linkinfo']);
                } elseif ($item['qrtype'] == '1' && $memberId) { //公众号二维码
                    $qrcode = $this->createQrcodeWithOfficialAccount($memberId);
                } else { //h5
                    $qrcode = $this->createQrcodeWithH5($memberId, $type, $item['linkinfo']);
                }
                $qrcode = "data:image/png;base64," . base64_encode($qrcode);
                $item['qrdata'] = $qrcode;
            }
        }
        unset($item);
        $data['width'] = $_REQUEST['width'] ? $_REQUEST['width'] : 375;
        $data['fontscale'] = $data['width'] / 375; //因为设计界面是按375宽度的，所以文字要有一个放大比例，否则生成的图片中的文本就会模糊
        return view('moduleshop::SharePaper/PaperMobi', $data);
    }

    private function createQrcodeWithOfficialAccount($memberId)
    {
        $site = Site::getCurrentSite();
        $wechat = $site->getOfficialAccount();
        $savepath = Site::getSiteComdataDir('', true) . '/member-invite-qrcode';
        Util::mkdirex($savepath);
        $file = $savepath . '/member-' . $memberId . '-' . $wechat->getConfig()->getModel()->appid . '.jpg';
        if (file_exists($file)) {
            $qrcode = file_get_contents($file);
        } else {
            $res = $wechat->qrcode("invite", $memberId);
            $qrcode = file_get_contents($res['qrurl']);
            file_put_contents($file, $qrcode); //保存一下用户的公众号二维码，避免每次重新生成可能会导致微信的永久码不够用的问题
        }
        return $qrcode;
    }

    private function createQrcodeWithH5($memberId, $type, $linkInfo = null)
    {
        //生成二维码
        $qrcodeObj = QrCode::format('png')
            ->size(700)
            ->encoding('UTF-8')
            ->errorCorrection('Q')
            ->margin(1);
        $wxConfig = (new WxConfig())->getModel();
        if ($type == Constants::SharePaperType_StaffCenter) {
            $serverUrl = $wxConfig->domain ? $wxConfig->domain : ServerInfo::get('HTTP_HOST');
            // 因为小程序的SITEBASEURL必须是https，解决某些网站是没有做SSL证书的，打不开公众号商城
            $protocol = 'http';
        } else {
            $serverUrl = ServerInfo::get('HTTP_HOST');
            $protocol = getHttpProtocol();
        }
        $qrurl = $protocol . '://' . $serverUrl . '/shop/front/';
        if ($linkInfo['link_type']) $qrurl .= LinkHelper::getUrl($linkInfo['link_type'], $linkInfo['link_data']);
        else $qrurl .= '#/';
        if ($memberId) $qrurl .= (strpos($qrurl, '?') === false ? '?' : '&') . ($type == Constants::SharePaperType_StaffCenter ? 'fromadmin' : 'invite') . '=' . $memberId;
        $qrcode = $qrcodeObj->generate($qrurl);
        return $qrcode;
    }

    private function createQrcodeWithWxApp($memberId, $type, $linkInfo = null)
    {
        $wxConfig = WxAppModel::query()->where('site_id', getCurrentSiteId())->first();
        $savepath = Site::getSiteComdataDir('', true) . '/member-invite-qrcode';
        Util::mkdirex($savepath);
        //生成链接地址
        $qrurl = '/shop/front/';
        if ($linkInfo['link_type']) $qrurl .= LinkHelper::getUrl($linkInfo['link_type'], $linkInfo['link_data']);
        else $qrurl .= '#/';
        if ($memberId) $qrurl .= (strpos($qrurl, '?') === false ? '?' : '&') . ($type == Constants::SharePaperType_StaffCenter ? 'fromadmin' : 'invite') . '=' . $memberId;
        //检测是之前是否有生成过了
        $filename = 'member-' . $memberId . '-' . $wxConfig->appid . '-' . md5($qrurl) . '.jpg';
        $file = $savepath . '/' . $filename;
        if (file_exists($file)) {
            $qrcode = file_get_contents($file);
        } else {
            $wxApp = new WxApp();
            $qrurl = "pages/index?url=" . urlencode(str_replace('/#/', '/vuehash/', $qrurl));
            $response = $wxApp->getQrcode($qrurl, ['auto_color' => true, 'width' => 700]);
            if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                $filename = $response->saveAs($savepath, $filename);
                $qrcode = file_get_contents($savepath . '/' . $filename);
            }
        }
        return $qrcode;
    }

    /**
     * 渲染海报并生成图片
     * @param $memberId
     * @param int $returnType 0=返回图片的url地址，1=返回本地图片路径（这个是为了方便微信等后续需要对图片进行一些处理的情况），2=返回IMG标签
     * @param $admin 后台使用去生成预览图片
     * @param $type 所需生成的位置
     * @param $terminal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * 否则就返回图片的 url 地址
     */
    public function renderImage($memberId, $returnType = 0, $admin = false, $type = 0, $terminal = 0)
    {
        $wkhtml = new \Ipower\Common\WkhtmlUtil();
        $width = 750;
        $protocol = $type == Constants::SharePaperType_StaffCenter ? 'https' : getHttpProtocol();
        $url = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/sharepaper/mobi/paper/render?width=' . $width . '&member_id=' . $memberId . '&type=' . $type;
        $url .= '&id=' . $this->_model->id . '&InitSiteID=' . $this->siteId . '&terminal=' . $terminal;
        if ($admin) {
            //生成的预览图放在该文件夹下面
            $dir = public_path() . '/tmpdata/' . $this->siteId;
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $file = '/tmpdata/' . $this->siteId . '/template' . time() . '.jpg';
        } else {
            $file = '/tmpdata/member_qrcode_' . $memberId . '_' . $terminal . '_' . $type . '.jpg';
        }
        $bool = $wkhtml->generateImg($url, public_path() . $file, ['width' => $width]);
        if ($bool) {
            if ($returnType === 0) return $protocol . '://' . ServerInfo::get('HTTP_HOST') . $file;
            elseif ($returnType === 1) return $admin ? $file : public_path() . $file;//当后台生成预览图片
            else return "<img src='" . $protocol . '://' . ServerInfo::get('HTTP_HOST') . $file . "?rand=" . mt_rand() . "'>";
        }
        return null;
    }


    /**
     * 获取会员或员工的信息
     * @param $memberId
     * @param $type 生成的位置，确定是不是在员工端生成，如果是在员工端生成则要生成员工的信息
     * @return 输出HTML
     * @throws \Exception
     */
    private function getPersonInfo($memberId, $type)
    {
        if ($type == Constants::SharePaperType_StaffCenter) {
            $mModel = SiteAdminModel::find($memberId);
            //  $member = new SiteAdmin(intval($memberId));
            // $mModel = $member->getModel();
        } else {
            $member = new Member($memberId);
            $mModel = $member->getModel();
        }
        if ($mModel) {
            $data = $mModel->toArray();
            if (!$mModel->headurl) {
                $data['headurl'] = '/shop/front/images/share-paper/head.png'; //默认头像
            } elseif (strpos($mModel->headurl, 'images/share-paper') !== false) {
                $data['headurl'] = '/shop/front/' . $data['headurl'];
            } elseif (!preg_match('@^https?://@i', $mModel->headurl) !== false) {
                $data['headurl'] = Site::getSiteComdataDir() . $data['headurl'];
            }
            if ($type == Constants::SharePaperType_StaffCenter) $data['nickname'] = $data['name'];
        } else {
            $data['headurl'] = '/shop/front/images/share-paper/head.png'; //默认头像
        }
        if ($type == Constants::SharePaperType_StaffCenter) {
            $data['nickname'] = $mModel['name'];
        }
        return $data;
    }

    /**
     * 获取网站默认的导航
     */
    public static function getDefaultPaper()
    {
        //默认查找第一个页面记录
        $model = SharePaperModel::query()->where(['site_id' => Site::getCurrentSite()->getSiteId(), 'type' => Constants::SharePaperType_Home])->first();
        if (!$model) {
            $model = new SharePaperModel();
            $model->type = Constants::PageMobiType_Home;
            $model->site_id = Site::getCurrentSite()->getSiteId();
        }
        return new static($model);
    }

    /**
     * 根据keyword_id 获取keyword
     * @param  keyword_id
     */
    public static function getKeyword($keyword_id)
    {
        $keyword_data = WxReplyModel::query()
            ->where(['site_id' => Site::getCurrentSite()->getSiteId(), 'id' => $keyword_id])
            ->select(['data'])
            ->first();
        $keyword = json_decode($keyword_data->data, true);
        //因为现在海报的关键词只有一个
        return $keyword['keyword']['0']['value'];
    }

    /**
     * 当会员输入关键词或点击菜单时，返回会员的推广二维码
     * @param $wxmessage 收到的来自微信的消息
     */
    public static function sendWeixinPaperImage($wxmessage)
    {
        $openid = $wxmessage['FromUserName'];
        $auth = MemberAuthModel::where('type', \YZ\Core\Constants::MemberAuthType_WxOficialAccount)
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('openid', $openid)->first();
        $site = Site::getCurrentSite();
        $wx = $site->getOfficialAccount();
        $bindUrl = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/#/member/member-center';
        if (!$auth) {
            $wx->sendMessage($openid, '您还没有绑定公众号，不能获取海报，点击<a href="' . $bindUrl . '">这里绑定</a>');
            return;
        }
        $wx->sendMessage($openid, '您还没有绑定公众号，不能获取海报，点击<a href="' . $bindUrl . '">这里绑定</a>');
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

}
