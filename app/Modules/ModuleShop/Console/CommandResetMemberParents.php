<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use YZ\Core\Member\Member;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use Illuminate\Support\Facades\DB;

class CommandResetMemberParents extends Command
{
    protected $signature = 'ResetMemberParents {--member_id=}';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ResetMemberParents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '此命令用来重置会员的 tbl_member_parents 表的记录';

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
        $memberId = $this->option('member_id');
        if($memberId){
            $this->resetParent($memberId);
        }else{
            $sql = "select id,invite1 from tbl_member where site_id > 1;";
            $conn = DB::getPdo();
            $db = new \Ipower\Db\DbLib($conn);
            $mlist = $db->query($sql,[],false);
            while ($row = $mlist->fetch(\PDO::FETCH_ASSOC))
            {
                if($row['invite1']){
                    $this->resetParent($row['id']);
                }
            }
        }
    }

    private function resetParent($memberId){
        Member::resetParent($memberId);
        echo $memberId." ok \r\n";
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
