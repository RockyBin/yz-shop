<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CommandTest extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'TestCommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a Test Command';

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
		\YZ\Core\Logger\Log::writeLog("command",static::class.' has run');
		sleep(10);
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
