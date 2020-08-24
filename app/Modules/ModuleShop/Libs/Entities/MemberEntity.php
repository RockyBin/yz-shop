<?php
namespace App\Modules\ModuleShop\Libs\Entities;

use App\Modules\ModuleShop\Libs\Entities\Traits\MemberEntityTrait;
use YZ\Core\Entities\BaseEntity;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

class MemberEntity extends BaseEntity
{
	/**
	 * @var int $id
	 */
	public $id;

	/**
	 * 所属网站 
	 * @var int $site_id
	 */
	public $site_id;

	/**
	 * 姓名 
	 * @var string $name
	 */
	public $name;

	/**
	 * 昵称 
	 * @var string $nickname
	 */
	public $nickname;

	/**
	 * 头像地址 
	 * @var string $headurl
	 */
	public $headurl;

	/**
	 * 等级 
	 * @var int $level
	 */
	public $level;

	/**
	 * 手机 
	 * @var string $mobile
	 */
	public $mobile;

	/**
	 * 邮箱 
	 * @var string $email
	 */
	public $email;

	/**
	 * 所在省份的ID 
	 * @var int $prov
	 */
	public $prov;

	/**
	 * 所在城市的ID 
	 * @var int $city
	 */
	public $city;

	/**
	 * 所在区/县的ID 
	 * @var int $area
	 */
	public $area;

	/**
	 * 年零 
	 * @var int $age
	 */
	public $age;

	/**
	 * 生日时间 
	 * @var $birthday
	 */
	public $birthday;

	/**
	 * 性别：0-保密，1-男，2-女 
	 * @var int $sex
	 */
	public $sex;

	/**
	 * 密码 
	 * @var string $password
	 */
	public $password;

	/**
	 * 支付密码 
	 * @var string $pay_password
	 */
	public $pay_password;

	/**
	 * 终端类型 
	 * @var int $terminal_type
	 */
	public $terminal_type;

	/**
	 * 注册渠道(0=手工注册,1=微信,2=支付宝,3=QQ,4=微博) 
	 * @var int $regfrom
	 */
	public $regfrom;

	/**
	 * 注册时间 
	 * @var \DataTime $created_at
	 */
	public $created_at;

	/**
	 * 最后登录 
	 * @var \DataTime $lastlogin
	 */
	public $lastlogin;

	/**
	 * 是否分销商 
	 * @var int $is_distributor
	 */
	public $is_distributor;

	/**
	 * 代理等级，可取值为 0,1,2,3 ; 0表示非代理 
	 * @var int $agent_level
	 */
	public $agent_level;

	/**
	 * 代理团队上级领导 
	 * @var int $agent_parent_id
	 */
	public $agent_parent_id;

	/**
	 * 经销商等级 
	 * @var int $dealer_level
	 */
	public $dealer_level;

	/**
	 * 经销商隐藏等级 
	 * @var int $dealer_hide_level
	 */
	public $dealer_hide_level;

	/**
	 * 经销商上级领导 
	 * @var int $dealer_parent_id
	 */
	public $dealer_parent_id;

	/**
	 * 是否是区域代理 0=不是 1=生效 -1=禁用 
	 * @var int $is_area_agent
	 */
	public $is_area_agent;

	/**
	 * 最早成为区代时间 
	 * @var \DataTime $area_agent_at
	 */
	public $area_agent_at;

	/**
	 * 推荐人 
	 * @var int $invite1
	 */
	public $invite1;

	/**
	 * 二级推荐人 
	 * @var int $invite2
	 */
	public $invite2;

	/**
	 * 三级推荐人 
	 * @var int $invite3
	 */
	public $invite3;

	/**
	 * 四级推荐人 
	 * @var int $invite4
	 */
	public $invite4;

	/**
	 * 五级推荐人 
	 * @var int $invite5
	 */
	public $invite5;

	/**
	 * 六级推荐人 
	 * @var int $invite6
	 */
	public $invite6;

	/**
	 * 七级推荐人 
	 * @var int $invite7
	 */
	public $invite7;

	/**
	 * 八级推荐人 
	 * @var int $invite8
	 */
	public $invite8;

	/**
	 * 九级推荐人 
	 * @var int $invite9
	 */
	public $invite9;

	/**
	 * 十级推荐人 
	 * @var int $invite10
	 */
	public $invite10;

	/**
	 * 是否已经尝试绑定过推荐关系,此值在注册或首次购买时根据后台设置的绑定时间进行设置 
	 * @var int $has_bind_invite
	 */
	public $has_bind_invite;

	/**
	 * 消费次数(付款成功算起) 
	 * @var int $buy_times
	 */
	public $buy_times;

	/**
	 * 消费金额(付款成功算起，单位分) 
	 * @var int $buy_money
	 */
	public $buy_money;

	/**
	 * 成交次数(过维权期算起) 
	 * @var int $deal_times
	 */
	public $deal_times;

	/**
	 * 成交金额(过维权期算起，单位分) 
	 * @var int $deal_money
	 */
	public $deal_money;

	/**
	 * 状态：0-冻结，1-生效 
	 * @var int $status
	 */
	public $status;

	/**
	 * 所属员工 
	 * @var int $admin_id
	 */
	public $admin_id;

	/**
	 * 备注 
	 * @var string $about
	 */
	public $about;

	const ID = 'id';
	const SITE_ID = 'site_id';
	const NAME = 'name';
	const NICKNAME = 'nickname';
	const HEADURL = 'headurl';
	const LEVEL = 'level';
	const MOBILE = 'mobile';
	const EMAIL = 'email';
	const PROV = 'prov';
	const CITY = 'city';
	const AREA = 'area';
	const AGE = 'age';
	const BIRTHDAY = 'birthday';
	const SEX = 'sex';
	const PASSWORD = 'password';
	const PAY_PASSWORD = 'pay_password';
	const TERMINAL_TYPE = 'terminal_type';
	const REGFROM = 'regfrom';
	const CREATED_AT = 'created_at';
	const LASTLOGIN = 'lastlogin';
	const IS_DISTRIBUTOR = 'is_distributor';
	const AGENT_LEVEL = 'agent_level';
	const AGENT_PARENT_ID = 'agent_parent_id';
	const DEALER_LEVEL = 'dealer_level';
	const DEALER_HIDE_LEVEL = 'dealer_hide_level';
	const DEALER_PARENT_ID = 'dealer_parent_id';
	const IS_AREA_AGENT = 'is_area_agent';
	const AREA_AGENT_AT = 'area_agent_at';
	const INVITE1 = 'invite1';
	const INVITE2 = 'invite2';
	const INVITE3 = 'invite3';
	const INVITE4 = 'invite4';
	const INVITE5 = 'invite5';
	const INVITE6 = 'invite6';
	const INVITE7 = 'invite7';
	const INVITE8 = 'invite8';
	const INVITE9 = 'invite9';
	const INVITE10 = 'invite10';
	const HAS_BIND_INVITE = 'has_bind_invite';
	const BUY_TIMES = 'buy_times';
	const BUY_MONEY = 'buy_money';
	const DEAL_TIMES = 'deal_times';
	const DEAL_MONEY = 'deal_money';
	const STATUS = 'status';
	const ADMIN_ID = 'admin_id';
	const ABOUT = 'about';

	use MemberEntityTrait;
}
