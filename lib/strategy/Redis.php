<?php
/**
 * Windwork
 * 
 * 一个开源的PHP轻量级高效Web开发框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\cache\strategy;

/**
 * Redis 缓存操作实现类
 * 
 * 需要安装php扩展：https://github.com/phpredis/phpredis
 * 
 * @package     wf.cache.strategy
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
class Redis extends \wf\cache\CacheAbstract 
{
    /**
     * 
     * @var \Memcache
     */
    private $obj;
    
    /**
     * 
     * @param array $cfg
     * @throws \wf\cache\Exception
     */
    public function __construct(array $cfg) 
    {
        parent::__construct($cfg);
        
        if (!$cfg['enabled']) {
            return;
        }
        
        $redisCfg = $cfg['redis'];

        if(!empty($redisCfg['host'])) {
            $this->obj = new \Redis();
            
            if($redisCfg['pconnect']) {
                $connect = @$this->obj->pconnect($redisCfg['host'], $redisCfg['port'], $redisCfg['timeout']);
            } else {
                $connect = @$this->obj->connect($redisCfg['host'], $redisCfg['port'], $redisCfg['timeout']);
            }
            
            if ($redisCfg['password'] && !$this->obj->auth($redisCfg['password'])) {
                throw new \wf\cache\Exception('Redis密码不正确');
            }
            
            $this->enabled = $connect ? true : false;
        }
        // 
    }
    
    /**
     * 获取缓存锁路径
     * @param string $key
     * @return string
     */
    private function lockPath($key) {
        $lockPath = $this->getCachePath('cache_lock.' . $key);
        
        return $lockPath;
    }
    
    /**
     * 锁定
     *
     * @param string $key
     * @return \wf\cache\File
     */
    protected function lock($key) 
    {
        // 设定缓存锁文件的访问和修改时间
        $this->obj->set($this->lockPath($key), 1);
        
        return $this;
    }
  
    
    /**
     * 缓存单元是否已经锁定
     *
     * @param string $key
     * @return bool
     */
    protected function isLocked($key) 
    {
        return $this->obj->get($this->lockPath($key));
    }
        
    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $expire = 0 单位（s），不能超过30天， 默认使用配置中的过期设置， 如果要设置不删除缓存，请设置一个大点的整数
     */
    public function write($key, $value, $expire = null) 
    {
        if (!$this->enabled) {
            return ;
        }

        if ($expire === null) {
            $expire = $this->expire;
        }
        
        $value = serialize($value);
        $cachePath = $this->getCachePath($key);
        
        $this->stats['execTimes'] ++;
        $this->stats['writeTimes'] ++;
        $this->stats['writeSize'] += strlen($value)/1024;
            
        try {            
            $this->waitUnlock($key);
            $this->lock($key);
            $set = $this->obj->set($cachePath, $value);
            $this->obj->setTimeout($cachePath, $expire);
            $this->unlock($key);
        } catch (\wf\cache\Exception $e) {
            $this->unlock($key);
            throw $e;
        }
    }
    
    /**
     * 读取缓存
     *
     * @param string $key
     * @return mixed
     */
    public function read($key) 
    {
        if (!$this->enabled) {
            return null;
        }
        
        $this->stats['execTimes'] ++;
        $this->stats['readTimes'] ++;
        
        $cachePath = $this->getCachePath($key);
        
        $this->waitUnlock($key);
        $data = $this->obj->get($cachePath);
        
        if (false !== $data) {
            $this->stats['readSize'] += strlen($data)/1024;
            $data = unserialize($data);
            return $data;
        }
    
        return null;
    }
        
    /**
     * 删除缓存
     *
     * @param string $key
     */
    public function delete($key) 
    {
        if(empty($key)) {
            return false;
        }
    
        $this->stats['execTimes'] ++;
        
        $path = $this->getCachePath($key);
        $this->waitUnlock($key);
        $this->lock($key);
        $this->obj->delete($path);        
        $this->unlock($key);
    }
    
    /**
     * 清空指定目录下所有缓存
     *
     * @param string $dir = '' 该参数对于redis扩展无效
     */
    public function clear($dir = '') 
    {
        $this->obj->flushAll();
    
        $this->stats['execTimes'] ++;
    }
    
    /**
     * 解锁
     *
     * @param string $key
     * @return \wf\cache\File
     */
    protected function unlock($key) 
    {
        $this->obj->delete($this->lockPath($key));
    }
    
}

