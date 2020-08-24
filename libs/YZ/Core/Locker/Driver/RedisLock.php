<?
namespace YZ\Core\Locker\Driver;

use Illuminate\Support\Facades\Redis;

/**
 * redis 全局锁类， 一般不直接调用，而是在 通过 new YZ\Core\Locker\Locker() 时根据配置自动选择是使用数据库实现还是redis实现
 */
class RedisLock {
	private $key = ''; //锁的名称
	private $expiry = 0;

	/**
	 * 初始化全局锁
	 *
	 * @param string $key 锁ID，全局唯一，要根据业务场景自行定义
	 * @param integer $expiry 锁的过期时间，超过此时间，锁自动失效，此时 $key 可重用
	 */
	public function __construct($key,$expiry = 0) {
		$this->key = 'locker:'.$key;
		$this->expiry = $expiry;
	}
	
	/**
	 * 判断锁是否已存在
	 *
	 * @return bool
	 */
	public function lockExists() {
	    $lock = Redis::exists($this->key);
	    return !!$lock;
    }

	/**
	 * 加锁
	 * @param int $wait 等待秒数 大于0则会重试 为0则不重试
	 * @return bool
	 */
	public function lock($wait = 0) {
		$errNum = 0;
		startLock:
		try{
			$exists = Redis::exists($this->key);
			if(!$exists){
				Redis::setex($this->key,$this->expiry ? $this->expiry : 86400,1);
				return true;
			}else{
			    if ($wait > 0) {
			        throw new \Exception('lock fail');
                } else {
                    return false;
                }
			}
		}catch(\Exception $e){
			$errNum++;
			// 重试加锁
			if($errNum < $wait){
				sleep(1);
				goto startLock;
			} else {
                return false;
            }
		}
	}

	/**
	 * 解锁
	 *
	 * @return void
	 */
	public function unlock() {
		Redis::delete($this->key);
	}
}
?>