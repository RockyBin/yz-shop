<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CommandResetSkusName extends Command
{
    protected $signature = 'ResetSkusName';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ResetSkusName';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '此命令用来重置产品规格的名称';

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
        //读取全部规格的名称
        $list = DB::table('tbl_product_sku_value')->get();
        $names = [];
        $images = [];
        foreach ($list as $item) {
            $names[$item->id] = $item->value;
            $images[$item->id] = $item->small_image;
        }
        //读取全部产品的全部规格
        $list = DB::table('tbl_product_skus')->get();
        foreach($list as $item){
            $skucodes = explode(',',$item->sku_code);
            $n = [];
            $image = '';
            foreach($skucodes as $c){
                if($c && $names[$c]) $n[] = $names[$c];
                if($c && $images[$c]) $image = $images[$c];
            }
            if(count($n)){
                DB::table('tbl_product_skus')->where('id', $item->id)->update(['sku_name' => json_encode($n,JSON_UNESCAPED_UNICODE),'sku_image' => $image]);
            }
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
