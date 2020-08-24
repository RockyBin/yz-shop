<?php
namespace App\Modules\ModuleShop\Libs\Dealer\DealerInvite;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerInvitePosterModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use Ipower\Common\Util;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Model\WxAppModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxApp;

class InvitePoster
{
    private $_model = null;

    public function __construct($idOrModel = null)
    {
        if ($idOrModel) {
            if (is_numeric($idOrModel)) $this->_model = DealerInvitePosterModel::where(['id' => $idOrModel])->first();
            else $this->_model = $idOrModel;
        } else {
            $this->_model = new DealerInvitePosterModel();
            $this->_model->id = 0;
            $this->_model->site_id = Site::getCurrentSite()->getSiteId();
        }
    }

    public function update(array $info = [])
    {
        if (array_key_exists('name', $info)) $this->_model->name = $info['name'];
        if (array_key_exists('background', $info)) $this->_model->background = $info['background'];
        if (array_key_exists('modules', $info)) $this->_model->modules = is_string($info['modules']) ? $info['modules'] : json_encode($info['modules']);
        if (array_key_exists('site_id', $info)) $this->_model->site_id = $info['site_id'];
        $this->_model->updated_at = date('Y-m-d H:i:s');
        $this->_model->save();
        return $this->_model->id;
    }

    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 删除邀请海报
     * @param $id array
     */
    public static function delete($id)
    {
        DealerInvitePosterModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('id', $id)
            ->delete();
    }

    /**
     * 渲染邀请海报
     * @param $memberId
     * @param $inviteLevel
     * @param $termainal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * @return 输出HTML
     * @throws \Exception
     */
    public function render($memberId,$inviteLevel,$terminal = 0)
    {
        $member = new Member($memberId);
        $mModel = $member->getModel();
        $data = $this->getModel()->toArray();
        $wxAppModel = WxAppModel::query()->where('site_id',getCurrentSiteId())->first();
        $isWxApp = $wxAppModel && $wxAppModel->appid && $terminal == \YZ\Core\Constants::TerminalType_WxApp;
        $qrurl = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/#/cloudstock/cloud-center';
        if ($isWxApp){
            $qrurl = '/shop/front/#/cloudstock/cloud-center'; //小程序码的页面地址最大长度是128，因为完整URL可能过长，这里只使用路径部分
        }
        $levelModel = DealerLevelModel::find($inviteLevel);
        if ($mModel) {
            $data['memberInfo'] = $mModel->toArray();
            $qrurl .= '?invite=' . $data['memberInfo']['id'].'&inviteLevel='.$inviteLevel;
            if (!$mModel->headurl) {
                $data['memberInfo']['headurl'] = '/shop/front/images/default_head.png'; //默认头像
            } elseif (strpos($mModel->headurl, 'images/') !== false) {
                $data['memberInfo']['headurl'] = '/shop/front/' . $data['memberInfo']['headurl'];
            } elseif (!preg_match('@^https?://@i', $mModel->headurl)) {
                $data['memberInfo']['headurl'] = Site::getSiteComdataDir() . $data['memberInfo']['headurl'];
            }

        } else {
            $data['memberInfo']['headurl'] = '/shop/front/images/default_head.png'; //默认头像
            //$qrurl = "此为实例二维码，没有实际效果";
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
                if($isWxApp) {
                    $qrcode = $this->createQrcodeWithWxApp($memberId,$qrurl);
                } elseif ($item['qrtype'] == '1' && $memberId) { //公众号二维码
                    $site = Site::getCurrentSite();
                    $wechat = $site->getOfficialAccount();
                    $savepath = Site::getSiteComdataDir('',true).'/dealer-invite-qrcode';
                    Util::mkdirex($savepath);
                    $file = $savepath.'/member-'.$memberId.'-level-'.$inviteLevel.'-'.$wechat->getConfig()->getModel()->appid.'.jpg';
                    if(file_exists($file)){
                        $qrcode = file_get_contents($file);
                    } else {
                        $res = $wechat->qrcode(["invite" => $memberId,'action' => 'dealerInvite','inviteLevel' => $inviteLevel]);
                        $qrcode = file_get_contents($res['qrurl']);
                        file_put_contents($file, $qrcode); //保存一下用户的公众号二维码，避免每次重新生成可能会导致微信的永久码不够用的问题
                    }
                } else {
                    $qrcode = $qrcode->generate($qrurl);
                }
                $qrcode = "data:image/png;base64," . base64_encode($qrcode);
                $item['qrdata'] = $qrcode;
            }
            if ($item['module_type'] == 'ModuleLevel') {
                $item['text'] = $levelModel->name;
            }
        }
        unset($item);
        $data['width'] = $_REQUEST['width'] ? $_REQUEST['width'] : 375;
        $data['fontscale'] = $data['width'] / 375; //因为设计界面是按375宽度的，所以文字要有一个放大比例，否则生成的图片中的文本就会模糊
        return view('moduleshop::InviteDealer/InviteDealer', $data);
    }

    private function createQrcodeWithWxApp($memberId,$qrurl){
        $wxConfig = WxAppModel::query()->where('site_id',getCurrentSiteId())->first();
        $savepath = Site::getSiteComdataDir('', true) . '/dealer-invite-qrcode';
        Util::mkdirex($savepath);
        //检测是之前是否有生成过了
        $qrurl .= '&fromwxapp=1';
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
     * @param $inviteLevel
     * @param int $returnType 0=返回图片的url地址，1=返回本地图片路径（这个是为了方便微信等后续需要对图片进行一些处理的情况），2=返回IMG标签
     * @param $terminal 终端类型 参考 \YZ\Core\Constants::TerminalType_XXX
     * @param $admin 后台使用去生成预览图片
     * 否则就返回图片的 url 地址
     */
    public function renderImage($memberId, $inviteLevel, $returnType = 0, $terminal = 0)
    {
        $wkhtml = new \Ipower\Common\WkhtmlUtil();
        $width = 750;
        $url = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/dealerinvite/poster/render?width=' . $width . '&member_id=' . $memberId.'&inviteLevel='.$inviteLevel;
        $url .= '&id=' . $this->_model->id.'&terminal='. $terminal;
        $dir = '/tmpdata/dealerinvite/'.Site::getCurrentSite()->getSiteId();
        if (!is_dir(public_path().$dir)) Util::mkdirex(public_path().$dir);
        $file = $dir.'/poster_'.$memberId.'_'.$inviteLevel.'_'.$terminal.'.jpg';
        $bool = $wkhtml->generateImg($url, public_path() . $file, ['width' => $width]);
        if ($bool) {
            if ($returnType === 0) return getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . $file;
            elseif ($returnType === 1) return str_replace('\\','/',public_path()) . $file;
            else return "<img src='" . getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . $file . "?rand=" . mt_rand() . "'>";
        }
        return null;
    }

    /**
     * 获取网站默认的授权证书
     */
    public static function getDefaultPoster()
    {
        //默认查找第一条记录
        $model = DealerInvitePosterModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->first();
        if (!$model) {
            $tpl = static::loadDefaultTemplate();
            $model = new DealerInvitePosterModel();
            $model->site_id = Site::getCurrentSite()->getSiteId();
            $model->background = $tpl['background'];
            $model->modules = json_encode($tpl['modules']);
        }
        return new static($model);
    }

    /**
     * 获取模板数据
     * @return json $templateData 返回模板数据
     */
    public static function loadDefaultTemplate()
    {
        $dir = __DIR__ . '/Template';
        $content = file_get_contents($dir . '/default.json');
        $data = json_decode($content, true);
        return $data;
    }
}