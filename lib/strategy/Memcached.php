<?php
/**
 * Windwork
 * 
 * 一个用于快速开发高并发Web应用的轻量级PHP框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\cache\strategy;

/**
 * Memcached缓存操作实现类，需要安装Memcached扩展
 * 
 * @package     wf.cache.strategy
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
class Memcached extends \wf\cache\CacheAbstract 
{
    /**
     * 
     * @var \Memcached
     */
    private $obj;
    
    /**
     * 
     * @param array $cfg
     */
    public function __construct(array $cfg) 
    {
        parent::__construct($cfg);
        
        if (!$cfg['enabled']) {
            return;
        }
        
        $mmcCfg = $cfg['memcached'];

        if(!empty($mmcCfg['host'])) {
            $this->obj = new \Memcached();
            
            if($mmcCfg['pconnect']) {
                $connect = @$this->obj->pconnect($mmcCfg['host'], $mmcCfg['port'], $mmcCfg['timeout']);
            } else {
                $connect = @$this->obj->connect($mmcCfg['host'], $mmcCfg['port'], $mmcCfg['timeout']);
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
     * @return \wf\cache\CacheAbstract
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
     * @param int $expire = null 单位（s），不能超过30天， 默认使用配置中的过期设置， 如果要设置不删除缓存，请设置一个大点的整数
     */
    public function write($key, $value, $expire = null) 
    {
        if (!$this->enabled) {
            return ;
        }

        if ($expire === null) {
            $expire = $this->expire;
        }
        
        $cachePath = $this->getCachePath($key);
        $value = serialize($value);
        
        $this->stats['execTimes'] ++;
        $this->stats['writeTimes'] ++;
        $this->stats['writeSize'] += strlen($value)/1024;
            
        try {
            $this->waitUnlock($key);            
            $this->lock($key);
            $set = $this->obj->set($cachePath, $value, $expire);
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
     * @param string $dir = '' 该参数对于memcache扩展无效
     */
    public function clear($dir = '') 
    {
        $this->obj->flush();
    
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
        
        return $this;
    }
    
}

