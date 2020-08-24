<?php
namespace YZ\Core\Model;

/**
 * ssl 证书记录表
 * Class SslCertModel
 * @package YZ\Core\Model
 */
class SslCertModel extends BaseModel
{
    protected $table = 'tbl_sslcert';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }
}