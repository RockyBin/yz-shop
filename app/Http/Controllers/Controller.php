<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use YZ\Core\Model\Member;

class Controller extends BaseController
{
	public function index(){
	    $url = getHttpProtocol().'://'.getHttpHost().'/shop/front/';
		return redirect($url);
	}
}
