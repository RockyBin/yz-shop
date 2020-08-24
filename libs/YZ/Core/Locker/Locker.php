<?
namespace YZ\Core\Locker;

/**
 * 全局锁类，一般在某些过程不允许并发时使用（比如支付订单时，判断会员余额是否足够）
 */
class Locker {
	private $key = '';
	private $driver = 'DbLock';
	private $instance = null;
	private $isLocked = false;

	/**
	 * 初始化全局锁
	 *
	 * @param string $key 锁ID，全局唯一，要根据业务场景自行定义
	 * @param integer $expiry 锁的过期时间，超过此时间，锁自动失效，此时 $key 可重用
	 */
	public function __construct($key,$expiry = 60) {
		if(app('config')->get('database.redis.default.host')) $this->driver = 'RedisLock';
		$class = '\\YZ\\Core\\Locker\\Driver\\'.$this->driver;
		$this->instance = new $class($key,$expiry);
	}
	
	/**
	 * 判断锁是否已存在
	 *
	 * @return bool
	 */
    public function lockExists() {
	    return $this->instance->lockExists();
    }

	/**
	 * 加锁
	 *
	 * @return bool
	 */
	public function lock() {
        $this->isLocked = $this->instance->lock();
        return $this->isLocked;
	}

	/**
	 * 解锁
	 *
	 * @return void
	 */
	public function unlock() {
		if($this->isLocked) return $this->instance->unlock(); //只有在锁成功的情况下才会真正去做解锁的动作，否则，当锁ID一样的情况下，第二个进来可能会将第一个人的锁给解了
	}
}
?>