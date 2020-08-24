<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use YZ\Core\Site\ExportUtil;

class CommandExportSite extends Command
{
    protected $signature = 'ExportSite {site_id}';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SiteExport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command For Export Site';

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
		echo "Run command : ".static::class;
        \YZ\Core\Logger\Log::writeLog("command",static::class.' start');
        $siteId = $this->argument('site_id');
        ExportUtil::exportSite($siteId);
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
