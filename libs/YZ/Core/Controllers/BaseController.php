<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use YZ\Core\Entities\BaseEntity;

class BaseController extends Controller
{
    public function callAction($method, $parameters)
    {
        $this->loadRequestValues($parameters); // 加载前端Request的值
        return parent::callAction($method, $parameters);
    }

    private function loadRequestValues(array $parameters)
    {
        if(count($parameters) === 1) return;

        /**
         * @var Request $request
         */
        $request = null;
        foreach ($parameters as $parameter) {
            if($parameter instanceof Request) {
                $request = $parameter;
                break;
            }
        }

        if(is_null($request)) return;

        foreach ($parameters as $parameter)
        {
            if($parameter instanceof BaseEntity)
            {
                /**
                 * @var BaseEntity $parameter
                 */
                $parameter->setValues($request);
            }
        }
    }
}