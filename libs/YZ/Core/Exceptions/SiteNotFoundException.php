<?php
namespace YZ\Core\Exceptions;

class SiteNotFoundException extends \Exception
{
    function __construct($msg = '')
    {
        parent::__construct($msg);
    }

    // Handler的render函数
    public function render($request)
    {
        return $this->handle($request);
    }
    
    // 新添加的handle函数
    public function handle($request){
        if($request->ajax()){
            return response(makeApiResponse(404,"找不到指定的网站，请确认域名已经绑定；".$this->getMessage()), 200);
        }else{
            return response("找不到指定的网站，请确认域名已经绑定；".$this->getMessage(), 404);
        }
    }
}