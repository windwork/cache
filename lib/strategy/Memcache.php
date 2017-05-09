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
 * Memcache缓存操作实现类
 * 需要安装Memcache扩展
 * 
 * @package     wf.cache.strategy
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
class Memcache extends \wf\cache\ACache 
{
    /**
     * 
     * @var \Memcache
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
        
        $mmcCfg = $cfg['memcache'];

        if(!empty($mmcCfg['host'])) {
            $this->obj = new \Memcache();
            
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
     * 锁定
     *
     * @param string $key
     * @return \wf\cache\ACache
     */
    protected function lock($key) 
    {
        $cachePath = $this->getCachePath($key);

        // 设定缓存锁文件的访问和修改时间
        $this->obj->set($cachePath . '.lock', 1);
        
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
        $cachePath = $this->getCachePath($key);
        return $this->obj->get($cachePath . '.lock');
    }
            
    /**
     * 获取缓存文件
     *
     * @param string $key
     * @return string
     */
    private function getCachePath($key) 
    {
        $path = $this->cacheDir . "/{$key}";
        $path = preg_replace('/[^a-z0-9_\\/]/is', '', $path);
        return $path;
    }
        
    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $expire = null 单位（s），不能超过30天， 默认使用配置中的过期设置， 如果要设置不删除缓存，请设置一个大点的整数
     * @return \wf\cache\ACache
     */
    public function write($key, $value, $expire = null) 
    {
        if (!$this->enabled) {
            return ;
        }

        if ($expire === null) {
            $expire = $this->expire;
        }
    
        $this->execTimes ++;
        $this->writeTimes ++;
        
        $this->checkLock($key);
        
        $this->lock($key);
    
        try {
            $cachePath = $this->getCachePath($key);
            $flag = $this->isCompress ? MEMCACHE_COMPRESSED : 0;
            $set = $this->obj->set($cachePath, $value, $flag, $expire);
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
        
        $this->execTimes ++;
        $this->readTimes ++;
        
        $this->checkLock($key);
        
        $cachePath = $this->getCachePath($key);
        $flag = $this->isCompress ? MEMCACHE_COMPRESSED : 0;
        $data = $this->obj->get($cachePath, $flag);
        
        if (false !== $data) {
            return $data;
        }
    
        return null;
    }
        
    /**
     * 删除缓存
     *
     * @param string $key
     * @return \wf\cache\ACache
     */
    public function delete($key) 
    {
        if(empty($key)) {
            return false;
        }
    
        $this->execTimes ++;
        
        $this->checkLock($key);
        $this->lock($key);

        $path = $this->getCachePath($key);
        $this->obj->delete($path);
        
        $this->unlock($key);
    }
    
    /**
     * 清空指定目录下所有缓存
     *
     * @param string $dir = '' 该参数对于memcache扩展无效
     * @return \wf\cache\ACache
     */
    public function clear($dir = '') 
    {
        $this->obj->flush();
    
        $this->execTimes ++;
    }
    
    /**
     * 解锁
     *
     * @param string $key
     * @return \wf\cache\ACache
     */
    protected function unlock($key) 
    {
        $cachePath = $this->getCachePath($key);
        $this->obj->delete($cachePath . '.lock');
        
        return $this;
    }
    
    /**
     * 设置缓存目录
     * @param string $dir
     * @return \wf\cache\ACache
     */
    public function setCacheDir($dir)
    {
        $this->cacheDir = trim($dir, '/');
            
        return $this;
    }
    
}

