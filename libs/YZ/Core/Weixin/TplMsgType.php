<?php
namespace YZ\Core\Weixin;


class TplMsgType
{
    /**
     * @var string 事件类型ID，自行定义
     */
    public $event = '';
    /**
     * @var string 腾讯模板库的编号(到公众号平台查看)
     */
    public $shortId = '';
    /**
     * @var string 消息模板说明
     */
    public $about = '';
    /**
     * @var string 所属权限，主要是在后台添加消息模板时用
     */
    public $perm = '';

    public function __construct($event,$shortId,$about,$perm = '')
    {
        $this->event = $event;
        $this->shortId = $shortId;
        $this->about = $about;
        $this->perm = $perm;
    }

    /**
     * 用数组初始化消息类型实例
     * @param $arr
     * @return TplMsgType
     */
    public static function arr2Instance($arr){
        $type = new TplMsgType($arr[0],$arr[1],$arr[2],$arr[3]);
        return $type;
    }
}