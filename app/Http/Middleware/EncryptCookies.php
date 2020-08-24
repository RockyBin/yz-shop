<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        'InitSiteID',
        'invite',
        'invite_nick_name',
        'member_id',
		'auth_name',
		'auth_headurl',
        'is_custom',
		'CustomDir',
    ];
}
