<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CommandCheckCode extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'CheckCode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a CheckCode Command';

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
		echo "Run command : ".static::class."\r\n";
		$exceptFiles = [
				'.gitkeep',
				'CommandBackupClean.php',
				'CommandBackupSite.php',
				'CommandClearTmpImg.php',
				'CommandClearXlsx.php',
				'CommandCouponExpire.php',
				'CommandCouponNoUseForExpire.php',
				'CommandExportSite.php',
				'CommandResetMemberParents.php',
				'CommandResetSkusName.php',
				'CommandTaskRun.php',
				'CommandTest.php',
				'CommandTest2.php',
				'CommandUpdateData.php',
				'SendMessageJob.php',
		];
		$dirs = [
			realpath(__DIR__.'/../Console'),
			realpath(__DIR__.'/../Jobs'),
		];
		$error = 0;
		foreach($dirs as $dir) {
			$hdir = opendir($dir);
			while($file = readdir($hdir)){
				if($file == '.' || $file == '..' || array_search($file,$exceptFiles) !== false) continue;
				$fullFile = $dir.'/'.$file;
				$content = file_get_contents($fullFile);
				if(strpos($content,'initSiteForCli(') === false){
					echo $fullFile. " --- Error: not contains Site::InitForCli() \r\n";
					$error++;
				}
			}
		}
		if($error) echo "找到 $error 个文件有问题，请检查";
		else echo "没有找到有问题的文件";
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
