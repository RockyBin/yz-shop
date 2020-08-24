<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

use YZ\Core\Entities\BaseEntity;

class PaginationEntity extends BaseEntity
{
    public $is_all = false;
    public $page = 0;
    public $page_size = 0;
    public $last_page = 0;
    public $total = 0;
    public $show_all = false;

    public function __construct($map = null)
    {
        parent::__construct($map, EntityExecutionOptions::createNotWorkingInstance());
    }
}
