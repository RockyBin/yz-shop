<?php
namespace YZ\Core\Weixin;

use Illuminate\Support\Facades\Cache;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use YZ\Core\Events\Event;
use YZ\Core\Model\WxUserModel;

/**
 * Class WxUser 微信粉丝业务类
 * @package YZ\Core\Weixin
 */
class WxUser
{
    private $_model = null;

    /**
     * 初始化菜单对象
     * WxMenu constructor.
     * @param $idOrModel 菜单的 数据库ID 或 数据库记录模型
     */
    public function __construct($idOrModel)
    {
        if(is_string($idOrModel)) $this->_model = WxUserModel::find($idOrModel);
        else $this->_model = $idOrModel;
        if(!$this->_model) {
			$this->_model = new WxUserModel();
			$this->_model->platform = Constants::Fans_PlatformType_WxOfficialAccount;
		}
    }

    /**
     * 返回数据库记录模型
     * @return null|WxUserModel
     */
    public function getModel(){
        return $this->_model;
    }

    /**
     * 设置粉丝信息
     * @param array $userInfo 粉丝的信息
     */
    public function setInfo(array $userInfo){
        foreach($userInfo as $key => $val){
            $this->_model->$key = $val;
        }
    }

    /**
     * 为此粉丝添加标签
     * @param $tagId 标签ID
     * @throws \Exception
     */
    public function addTag($tagId){
        static::addUsersTag($this->_model->openid,$tagId);
        if(strpos($this->_model->tags,','.$tagId.',') == false){
            $this->_model->tags .= ','.$tagId.',';
            $this->_model->save();
        }
    }

    /**
     * 删除此用户的某个标签
     * @param $tagId 标签ID
     * @throws \Exception
     */
    public function removeTag($tagId){
        static::removeUsersTag($this->_model->openid,$tagId);
        if(strpos($this->_model->tags,','.$tagId.',') !== false){
            $this->_model->tags = str_replace(','.$tagId.',','',$this->_model->tags);
            $this->_model->save();
        }
    }

    /**
     * 保存粉丝
     */
    public function save(){
        $this->_model->save();
    }
}