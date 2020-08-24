<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/29
 * Time: 15:43
 */

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;

class CommandTaskRun extends Command
{
    // cli 命令 执行任务
    protected $signature = 'Task:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run all task';

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
        runAllTask();
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