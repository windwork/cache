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
 * 实现逻辑：通过var_dump把变量保存到文件，读取缓存时再包含文件。
 * （旧版通过变量系列化保存到文件再读取，新版本PHP默认开启opcache通过包含文件性能更高。）
 * 
 * @package     wf.cache.strategy
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
class File extends \wf\cache\ACache 
{
    /**
     * 已读取缓存内容
     * @var array
     */
    protected $temp = [];
    
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
        if(!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
            throw new \wf\cache\Exception("Could not make cache directory");
        }
        
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
     * @param int $expire = null  过期时间，请设置一个大点的整数
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
    
        $data = [
            'time'   => time(), 
            'expire' => time() + $expire,
            'value'  => $value,            
        ];
        
        $this->lock($key);
    
        try {
            if($this->store($key, $data)) {
                $this->temp[$key] = $data;
            }
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
        
        // 从已读取缓存中读取未过期缓存
        if (!empty($this->temp[$key]) && (time() < $this->temp[$key]['expire'])) {
            return $this->temp[$key]['value'];
        }
        
        $this->checkLock($key);
        
        $isAvailable = false;
        $cachePath = $this->getCachePath($key);
        if (is_file($cachePath)) {
            $data = include $cachePath;
            
            if (time() < $data['expire']) {                
                $isAvailable = true;
            }
        }
             
        if ($isAvailable) {
            // 
            if($this->isCompress && function_exists('gzdeflate') && function_exists('gzinflate')) {
                $data['value'] = gzinflate($data['value']);
            }

            $this->readSize += strlen(var_export($data['value'], true))/1024;
            $this->temp[$key] = $data;
            
            return $data['value'];
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
        unset($this->temp[$key]);
        
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
        $path = $this->getCachePath($dir . '/tmp');
        $dir  = dirname($path);
        
        is_dir($dir) && static::clearDir($dir, false);
        $this->temp = [];
    
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
     *
     * @param string $key 缓存变量下标
     * @param string $data 缓存信息
     * @return bool
     */
    private function store($key, $data) 
    {
        $cachePath = $this->getCachePath($key);
        $cacheDir  = dirname($cachePath);
    
        if(!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
            throw new \wf\cache\Exception("Could not make cache directory");
        }
            
        if($this->isCompress && function_exists('gzdeflate') && function_exists('gzinflate')) {
            $data['value'] = gzdeflate($data['value']);
        }
    
        $this->writeSize += strlen(var_export($data['value'], true))/1024;
        
        $text = "<?php\n/**\n * Auto generate by windwork cache engine,\nplease don't edit me.\n */\nreturn " . var_export($data, true) . ';';        
        return @file_put_contents($cachePath, $text);
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
        $dir = rtrim($dir, '\\/');
        
        // 不处理非法路径
        $dir = static::safePath($dir);
        $dir = rtrim($dir, '\\/') . '/';
        
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

