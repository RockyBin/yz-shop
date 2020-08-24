<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Member;

use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Model\BaseModel;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

/**
 * 粉丝 Controller
 * Class FansController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class FansController extends BaseAdminController
{
    /**
     * 展示列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $page = $request->page > 1 ? $request->page : 1;
            $pageSize = $request->page_size > 0 ? $request->page_size : 20;
            $where = " where fans.site_id=:siteid";
            $params = ['siteid' => getCurrentSiteId()];
            if($request->keyword){
				if(trim($request->keyword) == "总店"){
					$where .= " and fans.invite = :invite ";
					$params['invite'] = '0';
				}else{
                    $keyword=  preg_replace('/[\xf0-\xf7].{3}/', "", $request->keyword);
					if($request->search_type == '1'){
						$where .= " and (inviter.nickname like :keyword or inviter.mobile like :mobile or inviter.name like :name) ";
						$params['keyword'] = '%' . trim($keyword) . '%';
						$params['mobile'] = '%' . trim($keyword) . '%';
						$params['name'] = '%' . trim($keyword) . '%';
					}elseif($request->search_type == '2'){
                        $where .= " and (admin.mobile like :mobile or admin.name like :name) ";
                        $params['mobile'] = '%' . trim($keyword) . '%';
                        $params['name'] = '%' . trim($keyword) . '%';
                    }else {
						$where .= " and (fans.nickname like :keyword or member.mobile like :mobile or member.name like :name or member2.mobile like :mobile2 or member2.name like :name2) ";
						$params['keyword'] = '%' . trim($keyword) . '%';
						$params['mobile'] = '%' . trim($keyword) . '%';
						$params['name'] = '%' . trim($keyword) . '%';
                        $params['mobile2'] = '%' . trim($keyword) . '%';
                        $params['name2'] = '%' . trim($keyword) . '%';
					}
				}
            }
            if($request->starttime){
                $where .= " and (member.created_at >= :starttime or member2.created_at >= :starttime2) ";
                $params['starttime'] = $request->starttime;
                $params['starttime2'] = $request->starttime;
            }
            if($request->endtime){
                $where .= " and (member.created_at <= :endtime or member2.created_at <= :endtime2) ";
                $params['endtime'] = $request->endtime;
                $params['endtime2'] = $request->endtime;
            }
            if(intval($request->status) === 0){
                $where .= " and if(member.invite1 = fans.invite or member2.invite1 = fans.invite,0,1) ";
            }
            if(intval($request->status) === 1){
                $where .= " and (member.invite1 = fans.invite or member2.invite1 = fans.invite) ";
            }
            // 统计记录数
            $from = " from tbl_wx_user as fans left join tbl_member_auth as auth on (auth.openid = fans.openid and auth.site_id = fans.site_id) ";
            $from .= " left join tbl_member as member on member.id = auth.member_id ";
            $from .= " left join tbl_member as member2 on member2.id = fans.member_id ";
            $from .= " left join tbl_member as inviter on inviter.id = fans.invite ";
            $from .= " left join tbl_site_admin as admin on (member.admin_id = admin.id OR member2.admin_id = admin.id) ";
            $sql = $sql = "select count(fans.id) as total ".$from.$where;
            $result = BaseModel::runSql($sql,$params);
            $total = $result[0]->total;
            $lastPage = ceil($total/$pageSize);
            // 列表
            $sql = "select fans.id,fans.member_id as mid,fans.platform,fans.openid,fans.nickname,fans.headimgurl,fans.subscribe,fans.subscribe_time,fans.invite,auth.id as auth_id,";
            $sql .= " member.id as member_id,member.mobile,member.created_at as register_at, member.invite1 as member_invite, member.has_bind_invite as member_has_bind_invite, ";
            $sql .= " member2.id as member_id2,member2.mobile as mobile2,member2.created_at as register_at2, member2.invite1 as member_invite2, member2.has_bind_invite as member_has_bind_invite2, ";
            $sql .= " inviter.id as invite_id,inviter.nickname as invite_nickname,inviter.name as invite_name,inviter.headurl as invite_headurl,inviter.mobile as invite_mobile ,";
            $sql .= " admin.name as admin_name,admin.mobile as admin_mobile";
            $sql .= $from;
            $sql .= $where." order by fans.id desc limit :offset,:pagesize";
            $offset = ($page - 1) * $pageSize;
            $list = BaseModel::runSql($sql,array_merge(['pagesize' => $pageSize,'offset' => $offset],$params));
            foreach ($list as &$val){
                $val->subscribe_time = date('Y-m-d H:i:s',$val->subscribe_time);
                //粉丝表的 member_id 字段有效时，以 fans.member_id join 出来的数据为准，
                if(is_numeric($val->mid)){
                     foreach ($val as $col => $val2){
                        if(substr($col,-1) == '2'){
                             $tcol = substr($col,0,-1);
                             $val->$tcol = $val2;
                        }
                    }
                }
                $val->mobile = Member::memberMobileReplace($val->mobile);
                $val->has_bind = ($val->invite && $val->member_invite == $val->invite && $val->member_id) || $val->member_has_bind_invite ? 1 : 0;
                if($val->platform == Constants::Fans_PlatformType_H5) $val->openid = '--'; //h5注册的不显示openid
            }
            unset($val);
            $data = [
                'list' => $list,
                'total' => $total,
                'last_page' => $lastPage,
                'current' => $page,
                'page_size' => $pageSize,
            ];
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
