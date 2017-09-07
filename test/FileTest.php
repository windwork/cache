<?php
require_once '../lib/CacheInterface.php';
require_once '../lib/CacheAbstract.php';
require_once '../lib/adapter/File.php';

/**
 * File test case.
 */
class FileTest extends PHPUnit_Framework_TestCase 
{
    
    /**
     *
     * @var \wf\cache\CacheAbstract
     */
    private $cache;
    
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp() 
    {
        parent::setUp ();
        $cfg = array(
            // 缓存设置
            'enabled'           => 1,                  // 是否启用缓存
            'dir'               => __DIR__.'/tmp',   // 缓存文件夹，如果使用缓存服务器，设置通过wrapper访问，如：radius://localhost:1812/data/cache
            'expire'            => 3600,               // 缓存更新周期(默认：3600s)
            'compress'          => 0,                  // 是否启用缓存内容压缩后存贮
            'class'             => '',
        );
        
        $this->cache = new \wf\cache\adapter\File($cfg);
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown() 
    {
        $this->cache->clear();
        parent::tearDown ();
    }
    
    /**
     * Constructs the test case.
     */
    public function __construct() 
    {
    }
    
    /**
     * Tests File->write()
     * Tests File->read()
     */
    public function testReadWrite() 
    {
        $cacheKey = 'unit_test/key-read-write';
        $value = 'test value';
        // write, expire in 2 second
        $this->cache->write($cacheKey, $value, 2);

        // write array data
        $arr = [
            'aa',
            'bb',
            11,
            22,
            33,
            'test' => '测试',
        ];
        $this->cache->write('unit_test/array/key', $arr);
        
        $ret = $this->cache->read('unit_test/array/key');
        $this->assertEquals($arr['test'], $ret['test']);
        
        // read
        $ret = $this->cache->read($cacheKey);
        $this->assertEquals($value, $ret);
        
        // check expire
        sleep(3);
        $retExp = $this->cache->read($cacheKey);
        $this->assertNull($retExp);
        
        $this->cache->clear('unit_test');
    }    
    
    /**
     * Tests File->write()
     * Tests File->read()
     */
    public function testDelete() 
    {
        $cacheKey = 'unit_test/key-read-write';
        $value = 'test value';
        
        $this->cache->write($cacheKey, $value);
        $val = $this->cache->read($cacheKey);
        $this->assertNotNull($val);
        $this->cache->delete($cacheKey);
        $val = $this->cache->read($cacheKey);
        $this->assertNull($val);
        
    }    
    
    public function testClear() 
    {
        $cacheKey = 'unit_test/key-read-write';
        $value = 'test value';
        
        $cacheDir = 'unit_test/test_dir/';
        $this->cache->write($cacheDir . '1', '111');
        $this->cache->write($cacheKey, $value);
        $this->cache->write($cacheDir . '2/2', '222');
        $this->assertEquals($this->cache->read($cacheDir . '1'), '111');
        
        $this->cache->clear($cacheDir);
        $this->assertNull($this->cache->read($cacheDir . '1'));
        $this->assertNull($this->cache->read($cacheDir . '2/2'));
        $this->assertEquals($this->cache->read($cacheKey), $value);
        
        $this->cache->clear();
        $this->assertNull($this->cache->read($cacheKey));
    }
}

