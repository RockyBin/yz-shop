<?php

namespace App\Modules\ModuleShop\Libs\SmallShop;


use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockApplySetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\SmallShopModel;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;
use YZ\Core\Model\MemberAuth;
use YZ\Core\Member\Member;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 小店
 * @author Administrator
 */
class SmallShop
{
    use DispatchesJobs;
    private $siteId = 0;
    private $model = null;

    function __construct($memberIdOrModel)
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        if (is_numeric($memberIdOrModel)) {
            $this->model = SmallShopModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('member_id', $memberIdOrModel)
                ->first();
        } else {
            $this->model = $memberIdOrModel;
        }

        if (!$this->model) {
            throw new \Exception('无此小店，先申请小店');
        }
    }


    public function edit($params)
    {
        if ($params['file_banner']) {
//            if (is_file($params['file_banner'])) {
//                // 上传banner
//                $bannerSaveDir = Site::getSiteComdataDir('', true) . '/small_shop/banner/';
//                $imageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
//                $upload = new FileUpload($params['file_banner'], $bannerSaveDir, $imageName);
//                $upload->save();
//                $params['banner'] = '/small_shop/banner/' . $upload->getFullFileName();
//            }
            $params['banner'] = $params['file_banner'];
        } else {
            $params['banner'] = Null;
        }

        if ($params['file_logo']) {
            if (is_file($params['file_logo'])) {
                // 上传Logo
                $LogoSaveDir = Site::getSiteComdataDir('', true) . '/small_shop/logo/';
                $logoName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $logoUpload = new FileUpload($params['file_logo'], $LogoSaveDir, $logoName);
                $logoUpload->save();
                $params['logo'] = '/small_shop/logo/' . $logoUpload->getFullFileName();
            }
        } else {
            $params['logo'] = Null;
        }

        if ($params['file_video']) {
            if (is_file($params['file_video'])) {
                // 上传视频
                $videoSaveDir = Site::getSiteComdataDir('', true) . '/small_shop/video/';
                $videoName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $videoUpload = new FileUpload($params['file_video'], $videoSaveDir, $videoName);
                $videoUpload->setAllowFileType(['mp4', 'ogv', 'mov', '3gp']);
                $videoUpload->save();
                $params['video'] = '/small_shop/video/' . $videoUpload->getFullFileName();
            }
        } else {
            $params['video'] = Null;
        }


        if ($params['file_video_cover']) {
            if (is_file($params['file_video_cover'])) {
                $videoCoverSaveDir = Site::getSiteComdataDir('', true) . '/small_shop/video_cover/';
                $videoCoverName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $videoCoverUpload = new FileUpload($params['file_video_cover'], $videoCoverSaveDir, $videoCoverName);
                $videoCoverUpload->save();
                $params['video_cover'] = '/small_shop/video_cover/' . $videoCoverUpload->getFullFileName();
            }
        } else {
            $params['video_cover'] = Null;
        }

        $this->model->fill($params);
        $this->model->save();
    }


    public static function add($params)
    {
        //版本号
        $sn = Site::getCurrentSite()->getSn();
        $count = SmallShop::getSmallShopCount();
        // 暂时测试用
        if ($sn->getCurLicense() == Constants::License_DISTRIBUTION && $count >= 500) {
            throw new \Exception('系统已经超出小店数量了哦~，如有疑问，请联系客服~');
        }
        $info = SmallShopModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $params['member_id'])
            ->where('status', 1)
            ->first();
        if ($info) {
            throw new \Exception('你已经拥有自己的小店，不需要再次申请');
        }
        $member = (new Member($params['member_id']))->getModel();
        if ($params['type'] == 1 && $member->is_distributor == 0) {
            throw new \Exception('你不是分销身份，不能申请小店');
        }
        if ($params['type'] == 2 && $member->agent_level <= 0) {

            throw new \Exception('你不是代理身份，不能申请小店');
        }
        // 创建一个空的
        $model = new SmallShopModel();
        $model->member_id = $params['member_id'];
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->save();
    }

    /**
     *
     **/
    public static function getInfo($params)
    {
        $data = SmallShopModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('member_id', $params['member_id'])
            ->where('status', 1)
            ->first();
        if (!$data) return null;
        $member = (new Member($params['member_id']))->getModel();
        if (!$params['nocheck'] && !intval($member['is_distributor']) && !intval($member['agent_level'])) {
            return null;
        }
        // 编辑的时候不需要二维码,昵称等
        if ($data && !$params['edit']) {
            $url = url('/') . '/shop/front/#/smallshop/smallshop-home?member_id=' . $params['member_id'] . '&invite=' . $params['member_id'];
            if (!$params['noqrcode']) {
                $qrcode = QrCode::format('png')
                    ->size(700)
                    ->encoding('UTF-8')
                    ->errorCorrection('M')
                    ->margin(0)
                    ->generate($url);
                $data['QrCode'] = "data:image/png;base64," . base64_encode($qrcode);
            }
            if (!$data['name']) $data['name'] = $member->nickname . '的小店';
            if (!$data['logo']) $data['logo'] = $member->headurl;
            $data['nickname'] = $member->nickname;
            $data['headurl'] = $member->headurl;
        }
        //返回商城的自选总开关是否有开启
        $data['small_shop_optional_product_status'] = Site::getCurrentSite()->getConfig()->getModel()->small_shop_optional_product_status;
        if ($data['banner']) $data['banner'] = explode(',', $data['banner']);
        return $data;
    }

    // 获取店铺总数量
    public static function getSmallShopCount()
    {
        $count = SmallShopModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->count();
        return $count;
    }

    // 寻找最近的上级店铺信息
    public static function getRecentlySmallShopInfo($member_id)
    {
        $info = SmallShop::getInfo(['member_id' => $member_id, 'noqrcode' => 1]);
        if (!$info) {
            //和产品商量暂时不限制层数
            //$maxLevel = AgentBaseSetting::getCurrentSiteSetting()->level + DistributionSetting::getCurrentSiteSetting()->level;
            $info = MemberParentsModel::query()
                ->selectRaw('ss.*,m.nickname,m.headurl')
                ->leftJoin('tbl_small_shop as ss', 'ss.member_id', 'tbl_member_parents.parent_id')
                ->leftJoin('tbl_member as m', 'm.id', 'tbl_member_parents.parent_id')
                ->where('tbl_member_parents.member_id', '=', $member_id)
                ->where('ss.status', '=', 1)
                //->where('tbl_member_parents.level', '<=', $maxLevel)
                ->where(
                    function ($query) {
                        $query->where('m.is_distributor', '>', 0)->orWhere('m.agent_level', '>', 0);
                    }
                )
                ->whereNotNull('ss.id')
                ->orderBy('tbl_member_parents.level', 'asc')
                ->first();
        }
        return $info;
    }

    // 暂时只供给banner图使用
    public static function upload($params)
    {
        if ($params['file_banner']) {
            if (is_file($params['file_banner'])) {
                // 上传banner
                $bannerSaveDir = Site::getSiteComdataDir('', true) . '/small_shop/banner/';
                $imageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $upload = new FileUpload($params['file_banner'], $bannerSaveDir, $imageName);
                $upload->save();
                return $banner = '/small_shop/banner/' . $upload->getFullFileName();
            }
        }
        return false;
    }
}
