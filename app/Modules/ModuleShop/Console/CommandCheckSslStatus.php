<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YZ\Core\Model\SiteModel;
use YZ\Core\Model\SslCertModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteManage;
use YZ\Core\Site\SslCert;

class CommandCheckSslStatus extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'CheckSslStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command For CheckSslStatus';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
		echo "Run command : ".static::class;
        \YZ\Core\Logger\Log::writeLog("command",static::class.' start');
        $list = SslCertModel::query()->where('status',0)->where('created_at','>',date('Y-m-d H:i:s',strtotime("-1 hours")))->get();
        foreach ($list as $item){
            $site = Site::initSiteForCli($item->site_id);
            $ssl = new SslCert($site);
            $info = $item->toArray();
            $ssl->checkSslIsActive($info);
        }
        \YZ\Core\Logger\Log::writeLog("command",static::class.' completed');
		sleep(1);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        /*return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];*/
		return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        /*return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];*/
		return [];
    }
}
