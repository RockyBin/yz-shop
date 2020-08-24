<?php

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Model\TmpImg;
use Carbon\Carbon;
use Illuminate\Console\Command;
use YZ\Core\Site\Site;

class CommandClearTmpImg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'tmpImgClean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear tmp img cache';

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

        $tmp = TmpImg::query();

        $all = $tmp
            ->whereDate('created_at','<', Carbon::now()->hour(-1))
            ->groupBy(['site_id'])
            ->get();

        foreach ($all as $k => $model)
        {
            $rootPath = Site::getSiteComdataDir($model->site_id, true);

            foreach ($model->where('site_id', $model->site_id)->get() as $collectionModel)
            {
                if (is_array($collectionModel->img_path))
                {
                    foreach ($collectionModel->img_path as $path)
                    {
                        $path = $rootPath . str_replace('\\','/', $path);

                        if (is_file($path))
                        {
                            unlink($path);
                        }

                    }
                }

                $collectionModel->delete();
            }
        }
    }
}
