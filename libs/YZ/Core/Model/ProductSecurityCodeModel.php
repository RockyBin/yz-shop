<?php
/**
 * 防伪码模型 虚拟数据表 使用sqlite
 * User: liyaohui
 * Date: 2019/10/29
 * Time: 15:06
 */

namespace YZ\Core\Model;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProductSecurityCodeModel extends BaseModel
{
    protected $connection = 'security_code_sqlite';
    protected $table = "tbl_product_security_code";
    protected $primaryKey = "id";

    public function __construct(array $attributes = array())
    {
        $this->initSqliteConfig();
        parent::__construct($attributes);
//        $this->initTable();
    }

    public function initSqliteConfig()
    {
        $database = 'database.connections.' . $this->connection;
        if (!config($database)) {
            $config = [
                'driver' => 'sqlite',
                'database' => getSecurityDatabasePath(true),
                'prefix' => '',
            ];
            config([$database => $config]);
        }
    }

    /**
     * 用来初始化防伪码数据表的
     */
    public function initTable()
    {
        $connec = Schema::connection($this->connection);
        // 防伪码数据表结构
        if (!$connec->hasTable('tbl_product_security_code')) {
            $connec->create('tbl_product_security_code', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('site_id')->nullable(false);
                $table->integer('batch_id')->nullable(false);        // 批次id
                $table->string('batch_code', 30)->nullable(false); // 批次号
                $table->string('code', 30)->nullable(false); // 防伪码
                $table->integer('query_times')->default(0); // 查询次数
                $table->dateTime('last_query_at')->nullable();// 最后一次查询时间
                $table->unique(['site_id', 'code'], 'IX_site_code');
            });
        }
        // 防伪码批次数据表结构 使用我们的主库
        if (false && !$connec->hasTable('security_code_batch')) {
            $connec->create('security_code_batch', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('site_id');
                $table->integer('batch_count'); // 防伪码数量
                $table->string('batch_code', 30)->nullable(false); // 批次号
                $table->integer('product_id'); // 关联的产品id json数组
                $table->integer('export_times')->default(0); // 导出次数
                $table->dateTime('created_at');// 创建时间
                $table->unique(['site_id', 'batch_code'], 'IX_site_batch_code');
            });
        }
    }

    public function batch()
    {
        return $this->belongsToMany(ProductSecurityCodeBatchModel::class, 'id', 'batch_id');
    }
}
