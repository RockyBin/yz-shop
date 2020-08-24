<?php

namespace YZ\Core\Model;

/**
 * 公众号的模板消息表
 * Class TemplateMsgModel
 * @package YZ\Core\Model
 */
class WxTemplateModel extends BaseModel
{
    protected $table = 'tbl_wx_template';
    protected $fillable = [
        'site_id',
        'type',
        'short_id',
        'template_id',
        'status',
        'first_data',
        'remark_data',
        'created_at',
        'updated_at'
    ];
}