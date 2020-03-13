<?php
/**
 * Created by PhpStorm.
 * User: SPDC-07
 * Date: 2019/10/9
 * Time: 9:02
 */

namespace App\Console\Commands;


use Illuminate\Console\Command;

class TESTSQLSRV extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test_sqlsrv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试sqlserver访问';

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
        $res= DB::connection('sqlsrv')->select();
    }
}