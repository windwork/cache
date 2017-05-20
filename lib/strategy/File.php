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
 * 文件缓存操作实现类
 * 
 * @package     wf.cache.strategy
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
class File extends \wf\cache\ACache 
{
    const CACHE_SUMMARY = "<?php\n/**\n * Auto generate by windwork cache engine,please don't edit me.\n */\nexit;\n?>";
    
    /**
     * 锁定
     *
     * @param string $key
     * @return \wf\cache\ACache
     */
    protected function lock($key) 
    {
        $cachePath = $this->getCachePath($key);
        $cacheDir  = dirname($cachePath);
        if(!is_dir($cacheDir)) {
            if(!@mkdir($cacheDir, 0755, true)) {
                if(!is_dir($cacheDir)) {
                    throw new \wf\cache\Exception("Could not make cache directory");
                }
            }
        }

        // 设定缓存锁文件的访问和修改时间
        @touch($cachePath . '.lock');
        
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
        clearstatcache();
        return is_file($cachePath . '.lock');
    }
            
    /**
     * 获取缓存文件
     *
     * @param string $key
     * @return string
     */
    private function getCachePath($key) 
    {
        $path = $this->cacheDir . "/{$key}.php";
        $path = static::safePath($path);
        return $path;
    }
        
    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $expire = null  如果要设置不删除缓存，请设置一个大点的整数
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
    
        $data = array('time' => time(), 'expire' => $expire, 'valid' => true, 'data' => $value);
        
        $this->lock($key);
    
        try {
            $this->store($key, $data);
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
        if (is_file($cachePath) && is_readable($cachePath)) {
            $data = substr(file_get_contents($cachePath), strlen(static::CACHE_SUMMARY));
            
            if ($data) {
                $this->readSize += strlen($data)/1024;
    
                if($this->isCompress && function_exists('gzdeflate') && function_exists('gzinflate')) {
                    $data = gzinflate($data);
                }
    
                $data = unserialize($data);
                 
                $data['isExpired'] = ($data['expire'] && (time() - $data['time']) > $data['expire']) ? true : false;                
            }
        }
             
        if (!empty($data) && ($data['valid'] && !$data['isExpired'])) {
            return $data['data'];
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
    
        $this->execTimes ++;
    
        $file = $this->getCachePath($key);
        if(is_file($file)) {
            $this->checkLock($key);
            $this->lock($key);
            @unlink($file);
            $this->unlock($key);
        }
    }
    
    /**
     * 清空指定目录下所有缓存
     *
     * @param string $dir = ''
     * @return \wf\cache\ACache
     */
    public function clear($dir = '') 
    {
        $dir = $this->getCachePath($dir);
        $dir = dirname($dir);
        
        is_dir($dir) && static::clearDir($dir, false);
    
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
        @unlink($cachePath . '.lock');
        
        return $this;
    }
    
    
    /**
     * 缓存变量
     * 为防止信息泄露，缓存文件格式为php文件，并以"<?php exit;?>"开头
     *
     * @param string $key 缓存变量下标
     * @param string $value 缓存变量的值
     * @return bool
     */
    private function store($key, $value) 
    {
        $cachePath = $this->getCachePath($key);
        $cacheDir  = dirname($cachePath);
    
        if(!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
            throw new \wf\cache\Exception("Could not make cache directory");
        }
    
        $value = serialize($value);
    
        if($this->isCompress && function_exists('gzdeflate') && function_exists('gzinflate')) {
            $value = gzdeflate($value);
        }
    
        $this->writeSize += strlen($value)/1024;
        
        return @file_put_contents($cachePath, static::CACHE_SUMMARY. $value);
    }

    /**
     * 删除文件夹（包括有子目录或有文件）
     *
     * @param string $dir 目录
     * @param bool $rmSelf = false 是否删除本身
     * @return bool
     */
    private static function clearDir($dir, $rmSelf = false) 
    {
        $dir = rtrim($dir, '/');
        
        // 不处理非法路径
        $dir = static::safePath($dir);
        $dir = rtrim($dir, '/') . '/';
        
        if(!$dir || !$d = dir($dir)) {
            return;
        }

        $do = true;
        while (false !== ($entry = @$d->read())) {
            if($entry[0] == '.') {
                continue;
            }
            
            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $do = $do && static::clearDir($path, true);
            } else {
                $do = $do && false !== @unlink($path);
            }
        }
            
        @$d->close();
        $rmSelf && @rmdir($dir);
        
        return $do;
    }
    /**
     * 文件安全路径
     * 过滤掉文件路径中非法的字符
     * @param string $path
     * @return string
     */
    private static function safePath($path) 
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/(\.+\\/)/', './', $path);
        $path = preg_replace('/(\\/+)/', '/', $path);
        return $path;
    }
}

