<?php

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;

class CommandClearXlsx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'errxlsx:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear xlsx file cache';

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
        $abs = public_path('tmpdata/product/errorxlsx/');

        echo "清理文件中....\n";

        if (is_dir($abs))
        {
            if ($dir = opendir($abs))
            {
                while(($file = readdir($dir)) !== false)
                {
                    if (is_file($filename = $abs . $file))
                    {
                        unlink($filename);
                    }
                }

                closedir($dir);
            }
        }

        echo "清理完毕....!";
    }
}
