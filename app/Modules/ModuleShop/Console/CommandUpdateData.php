<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/9/30
 * Time: 17:28
 */

namespace App\Modules\ModuleShop\Console;


use Illuminate\Console\Command;
use YZ\Core\Logger\Log;
use YZ\Core\UpdateData\UpdateOriginalData;

class CommandUpdateData extends Command
{
// cli 命令 updateName 更新的数据name
    protected $signature = 'updateData {updateName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update original data';

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
        $updateName = $this->argument('updateName');
        try {
            switch ($updateName) {
                case 'updateV23':
                    UpdateOriginalData::updateAgentConditionV23();
                    break;
                case 'updateV25':
                    UpdateOriginalData::updateDistributionConditionV25();
                    break;
				case 'updateDealerPerformance':
                    UpdateOriginalData::updateDealerPerformance();
                    break;
				case 'updateMemberLevel':
                    UpdateOriginalData::updateMemberLevel();
                    break;
                case 'updateDefaultMemberLevel':
                    UpdateOriginalData::setDefaultMemberLevel();
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            Log::writeLog('updateData', $updateName . '==>' . $e->getMessage());
        }
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