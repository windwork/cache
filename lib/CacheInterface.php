<?php
/**
 * Windwork
 * 
 * 一个用于快速开发高并发Web应用的轻量级PHP框架
 * 
 * @copyright Copyright (c) 2008-2017 Windwork Team. (http://www.windwork.org)
 * @license   http://opensource.org/licenses/MIT
 */
namespace wf\cache;
 
/**
 * 缓存接口
 * 
 * 实现：file、memcache、memcached、redis
 * 
 * @package     wf.cache
 * @author      cm <cmpan@qq.com>
 * @link        http://docs.windwork.org/manual/wf.cache.html
 * @since       0.1.0
 */
interface CacheInterface
{
    /**
     * 设置缓存
     *
     * @param string $cacheKey
     * @param mixed $value 类型为可系列化的标量或数组，不支持资源类型
     * @param int $expire = null  单位秒，为null则使用配置文件中的缓存时间设置（3600秒），如果要设置不删除缓存，请设置一个大点的整数
     */
    public function write($cacheKey, $value, $expire = null);
    
    /**
     * 读取缓存
     *
     * @param string $cacheKey
     * @return mixed 不存在的缓存返回 null
     */
    public function read($cacheKey);
    
    /**
     * 删除缓存
     *
     * @param string $cacheKey
     */
    public function delete($cacheKey);
    
    /**
     * 清空指定目录下的所有缓存
     * 
     * @param string $dir = ''
     */
    public function clear($dir = '');
    
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
    public function getCacheStats();
    
}

