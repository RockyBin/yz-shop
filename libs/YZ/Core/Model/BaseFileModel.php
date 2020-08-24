<?php

namespace YZ\Core\Model;

/**
 * Class BaseFileModel 带文件上传的模型，可自定义如何处理上传的文件
 * @package App
 */
class BaseFileModel extends BaseModel implements StaplerableInterface
{
    use EloquentTrait;
}
