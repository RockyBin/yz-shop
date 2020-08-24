<?
return [
    'app\Http\Controllers\SysManage\Site\SiteController@addSite' => 'SYSADMIN,SITEADMIN',
	'app\Http\Controllers\SysManage\Site\SiteController@editSite' => 'SYSADMIN,SITEADMIN',
    'app\Http\Controllers\SysManage\Site\SiteController@deleteSite' => 'SYSADMIN',
    'app\Http\Controllers\SysManage\Site\SiteController@clearSite' => 'SYSADMIN',
    'app\Http\Controllers\SysManage\Admin\AdminController@addUser' => 'SYSADMIN',
    'app\Http\Controllers\SysManage\Admin\AdminController@deleteUser' => 'SYSADMIN',
    'app\Http\Controllers\SysManage\Admin\AdminController@editUser' => 'SYSADMIN',
    'app\Modules\ModuleShop\Http\Controllers\SysManage\Template\TemplateController@add' => 'TPLADMIN',
    'app\Modules\ModuleShop\Http\Controllers\SysManage\Template\TemplateController@edit' => 'TPLADMIN',
    'app\Modules\ModuleShop\Http\Controllers\SysManage\Template\TemplateController@delete' => 'TPLADMIN',
];
?>