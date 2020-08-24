<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YZ\Core\Model\SiteModel;
use YZ\Core\Site\SiteManage;

class CommandBackupSite extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SiteBackup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command For Backup Sites';

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
        SiteManage::autoBackSite();
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
