<?php
/**
 * Windwork
 * 
 * 一个开源的PHP轻量级高效Web开发框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\cache;
 
/**
 * 缓存操作抽象类
 * 
 * 实现：file、memcache、memcached、redis
 * 
 * @package     wf.cache
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
abstract class CacheAbstract implements CacheInterface
{    
    /**
     * 缓存统计信息
     * @var array
     */
    protected $stats = [
        'readTimes'  => 0,
        'writeTimes' => 0,
        'execTimes'  => 0,
        'readSize'   => 0,
        'writeSize'  => 0,
    ];
    
    /**
     * 是否启用缓存
     * @var bool
     */
    protected $enabled = true;
    
    /**
     * 是否压缩缓存内容
     *
     * @var bool
     */
    protected $isCompress = true;
    
    /**
     * 缓存过期时间长度(s)
     *
     * @var int
     */
    protected $expire = 3600;
    
    /**
     * 缓存目录
     *
     * @var string
     */
    protected $cacheDir = 'data/cache';
    
    /**
     * 配置信息
     * @var array
     */
    protected $cfg = [];

    /**
     * 构造函数中设置缓存实例相关选项
     * @param array $cfg
     */
    public function __construct(array $cfg) 
    {
        $this->cfg = $cfg;
        
        // 一旦启用缓存、启用内容压缩就不能再停用，因此只在构造函数中赋值
        $this->enabled = (bool)$cfg['enabled'];
        $this->isCompress = (bool)$cfg['compress'];
        
        $this->setCacheDir($cfg['dir'])
             ->setExpire($cfg['expire']);
    }
    
    /**
     * 设置缓存
     *
     * @param string $cacheKey
     * @param mixed $value 类型为可系列化的标量或数组，不支持资源类型
     * @param int $expire = null  单位秒，为null则使用配置文件中的缓存时间设置（3600秒），如果要设置不删除缓存，请设置一个大点的整数
     */
    abstract public function write($cacheKey, $value, $expire = null);
    
    /**
     * 读取缓存
     *
     * @param string $cacheKey
     * @return mixed 不存在的缓存返回 null
     */
    abstract public function read($cacheKey);
    
    /**
     * 删除缓存
     *
     * @param string $cacheKey
     */
    abstract public function delete($cacheKey);
    
    /**
     * 清空指定目录下的所有缓存
     *
     * @param string $dir = ''
     */
    abstract public function clear($dir = '');
    
    /**
     * 获取缓存操作统计信息
     * <pre>
     * [
     *     'readTimes'  => 0, // 缓存读取次数
     *     'writeTimes' => 0, // 缓存写入次数
     *     'execTimes'  => 0, // 缓存读写总次数
     *     'readSize'   => 0, // 当前请求读取缓存内容的总大小(k)
     *     'writeSize'  => 0, // 当前请求写入取缓存内容的总大小(k)
     * ]
     * </pre>
     */
    public function getCacheStats()
    {
        return $this->stats;
    }
        
    /**
     * 设置缓存目录
     * @param string $dir
     * @return \wf\cache\CacheAbstract
     */
    public function setCacheDir($dir)
    {
        $this->cacheDir = rtrim($dir, '/');
        
        return $this;
    }
    
    /**
     * 获取缓存文件
     *
     * @param string $key
     * @return string
     */
    protected function getCachePath($key)
    {
        if(empty($key)) {
            throw new \wf\cache\Exception("Invalid cache key: {$key}");
        }
        
        $path = $this->cacheDir . '/'. $key;
        
        return $path;
    }

    /**
     * 设置缓存默认过期时间（s）
     *
     * @param int $expire
     * @return \wf\cache\CacheAbstract
     */
    public function setExpire($expire) 
    {
        $this->expire = (int) $expire;
        return $this;
    }

    /**
     * 等待解锁，检查确保不是锁定状态
     * 最多做$tries次睡眠等待解锁，超时则跳过并解锁
     *
     * @param string $key 缓存下标
     * @return \wf\cache\CacheAbstract
     */
    protected function waitUnlock($key) 
    {
        if ($this->isLocked($key)) {
            $count = 0;
            do {
                usleep(100);
                $count ++;
            } while ($count < 10 && $this->isLocked($key));  // 最多做10次睡眠等待解锁，超时则跳过并解锁
        
            $this->isLocked($key) && $this->unlock($key);        
        }
        
        return $this;
    }
    
    /**
     * 缓存单元是否已经锁定
     *
     * @param string $key
     * @return bool
     */
    abstract protected function isLocked($key);
    
    /**
     * 锁定
     *
     * @param string $key
     * @return \wf\cache\CacheAbstract
     */
    abstract protected function lock($key);
    
    /**
     * 解锁
     *
     * @param string $key
     * @return \wf\cache\CacheAbstract
     */
    abstract protected function unlock($key);
}

