<?php
namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use YZ\Core\Model\BaseModel;
use YZ\Core\Site\Site;

class FansController extends BaseController
{
    /**
     * 列出我推荐的粉丝
     * @return array
     */
    public function getFansList(Request $request)
    {
        try {
            $page = $request->page > 1 ? $request->page : 1;
            $pageSize = $request->page_size > 0 ? $request->page_size : 20;
            $where = " where fans.site_id=:siteid and invite=:invite and case when fans.member_id REGEXP '^[0-9]+$' then fans.invite <> member2.invite1 else fans.invite <> member.invite1 end ";
            $params = ['siteid' => getCurrentSiteId(),'invite' => $this->memberId];
            // 统计记录数
            $from = " from tbl_wx_user as fans left join tbl_member_auth as auth on (auth.openid = fans.openid and auth.site_id = fans.site_id) ";
            $from .= " left join tbl_member as member on member.id = auth.member_id ";
            $from .= " left join tbl_member as member2 on member2.id = fans.member_id ";
            $sql = $sql = "select count(fans.id) as total ".$from.$where;
            $result = BaseModel::runSql($sql,$params);
            $total = $result[0]->total;
            $lastPage = ceil($total/$pageSize);
            // 列表
            $sql = "select fans.id,fans.member_id as mid,fans.openid,fans.nickname,fans.headimgurl,fans.subscribe,fans.subscribe_time,fans.invite,auth.id as auth_id,";
            $sql .= " member.id as member_id,member.created_at as register_at, member.invite1 as member_invite, ";
            $sql .= " member2.id as member_id2,member2.created_at as register_at2, member2.invite1 as member_invite2 ";
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
                $val->has_bind = $val->member_invite == $val->invite ? 1 : 0;
            }
            unset($val);

            $data = [
                'list' => $list,
                'total' => $total,
                'last_page' => $lastPage,
                'current' => $page,
                'page_size' => $pageSize,
            ];
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 列出我推荐的会员
     * @param Request $request
     * @return array
     */
    public function getMemberList(Request $request)
    {
        try {
            $page = $request->page > 1 ? $request->page : 1;
            $pageSize = $request->page_size > 0 ? $request->page_size : 20;
            $where = " where member.site_id=:siteid and invite1=:invite";
            // 统计记录数
            $sql = $sql = "select count(member.id) as total from tbl_member as member ".$where;
            $result = BaseModel::runSql($sql,['siteid' => getCurrentSiteId(),'invite' => $this->memberId]);
            $total = $result[0]->total;
            $lastPage = ceil($total/$pageSize);
            // 列表
            $sql = "select member.nickname,member.headurl,member.created_at ";
            $sql .= " from tbl_member as member ";
            $sql .= $where." order by member.id desc limit :offset,:pagesize";
            $offset = ($page - 1) * $pageSize;
            $list = BaseModel::runSql($sql,['siteid' => getCurrentSiteId(),'invite' => $this->memberId,'pagesize' => $pageSize,'offset' => $offset]);
            foreach ($list as &$val){
                if($val->headurl && !preg_match('@(http://|https://)@',$val->headurl)) $val->headurl = Site::getSiteComdataDir().$val->headurl;
            }
            unset($val);
            $data = [
                'list' => $list,
                'total' => $total,
                'last_page' => $lastPage,
                'current' => $page,
                'page_size' => $pageSize,
            ];
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}