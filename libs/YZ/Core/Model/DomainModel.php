<?php
namespace YZ\Core\Model;

/**
 * 站点的域名记录表，它将 tbl_site.domains 字段里的域名劈开后保存此这个表里，一个域名一条记录，加快初始化网站的效率
 * 如果不用此表，那初始化网站时，就要用 like 查询 tbl_site.domains 这个字段，效率比较低
 * Class DomainModel
 * @package YZ\Core\Model
 */
class DomainModel extends BaseModel
{
    protected $table = 'tbl_domain';
}