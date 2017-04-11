Windwork 缓存组件
============================

## 缓存读写
默认使用文件缓存

```
// 缓存参数
$cfg = [
    'enabled'    => 1,             // 是否启用缓存
    'dir'        => 'data/cache',  // 缓存文件夹，如果使用缓存服务器，则是缓存变量的前缀
    'expire'     => 3600,          // 缓存更新周期(默认：3600s)
    'compress'   => 0,             // 是否启用缓存内容压缩后存贮
    'class'    => '\\wf\\cache\\adapter\\File',        // 缓存模式，File）文件缓存；Memcache）使用Memcache缓存；Memcached）使用Memcached缓存；Redis）使用Redis缓存
];
$cache = new $cfg['class']($cfg);

// 从缓存读取数据
if(null === ($ret = $cache->read('cache/key'))) {
    // 缓存中无数据则初始化并写入缓存，下次就可以直接从缓存中获取
    $ret = '缓存内容'; // 可以是标量或数组内容，不能是资源类型
    $cache->write('cache/key', $ret);
}

// 在这里使用缓存过的$ret变量

```

## 删除缓存内容
```
// 删除一条缓存
$cache->delete('cache/key');

// 清空全部缓存
$cache->clear();

// 通过前缀清空缓存
$cache->clear('user/info'); // 清空所有以 'user/info/' 开头的缓存内容

```


## 使用memcache/memcached缓存

```
// Memcache
$cfg = [
	'enabled'    => 1,             // 是否启用缓存
	'dir'        => 'data/cache',  // 缓存文件夹，如果使用缓存服务器，则是缓存变量的前缀
	'expire'     => 3600,          // 缓存更新周期(默认：3600s)
	'compress'   => 0,             // 是否启用缓存内容压缩后存贮
	'class'    => '\\wf\\cache\\adapter\\Memcache',        // 缓存模式，File）文件缓存；Memcache）使用Memcache缓存；Memcached）使用Memcached缓存；Redis）使用Redis缓存

    ['memcache'] => [
        'host'        => '127.0.0.1',     //
        'port'        => 11211,           //
        'pconnect'    => 1,               //
        'timeout'     => 1,               // 超时时间（秒）
    ],
];
$cache = new $cfg['class']($cfg);

// Memcached
$cfg = [
    'enabled'    => 1,             // 是否启用缓存
    'dir'        => 'data/cache',  // 缓存文件夹，如果使用缓存服务器，则是缓存变量的前缀
    'expire'     => 3600,          // 缓存更新周期(默认：3600s)
    'compress'   => 0,             // 是否启用缓存内容压缩后存贮
    'class'      => '\\wf\\cache\\adapter\\Memcached',        // 缓存模式，File）文件缓存；Memcache）使用Memcache缓存；Memcached）使用Memcached缓存；Redis）使用Redis缓存

    ['memcached'] => [
        'host'        => '127.0.0.1',     //
        'port'        => 11211,           //
        'pconnect'    => 1,               //
        'timeout'     => 1,               // 超时时间（秒）
    ],
];

$cache = new $cfg['class']($cfg);

```


## 使用Redis缓存
使用phpredis扩展进行操作
https://github.com/phpredis/phpredis

Windows php_redis.dll 模块下载（根据你的PHP版本选择下载）
http://windows.php.net/downloads/pecl/snaps/redis/
```
$cfg = [
	'enabled'    => 1,             // 是否启用缓存
	'dir'        => 'data/cache',  // 缓存文件夹，如果使用缓存服务器，则是缓存变量的前缀
	'expire'     => 3600,          // 缓存更新周期(默认：3600s)
	'compress'   => 0,             // 是否启用缓存内容压缩后存贮
	'class'      => '\\wf\\cache\\adapter\\Redis',        // 缓存模式，File）文件缓存；Memcache）使用Memcache缓存；Redis）使用Redis缓存
    
    'redis' => [
        'host'           => '127.0.0.1',     //
        'port'           => 6379,            //
        'pconnect'       => 1,               //
        'password'       => '',              // redis密码，不需要密码验证则留空
        'timeout'        => 0,               // 超时时间（秒），0为不限
    ],
];

$cache = new $cfg['class']($cfg);

```


# 在Windwork中使用缓存组件
在Windwork项目中设置好缓存配置后，使用cache()函数访问缓存对象实例。

1、在配置文件 config/app.php中设置缓存配置（默认使用文件缓存）
```
// config/app.php
return [    
    // 缓存组件设置
    'cache' => [
        'enabled'          => 1,                   // 是否启用缓存
        'class'            => '\\wf\\cache\\adapter\\File',              // 缓存模式，File|Memcache|Memcached|Redis
        'dir'              => dirname(__DIR__) . '/data/cache',        // 缓存文件夹
        'expire'           => 7200,                // 缓存更新周期(默认：7200s)
        'compress'         => 0,                   // 是否启用缓存内容压缩后存贮（建议只在虚拟主机中使用文件缓存时启用，以便省出存储空间）
    
        // redis
        'redis' => [
            'host'         => '127.0.0.1',     //
            'port'         => 6379,            //
            'pconnect'     => 1,               //
            'timeout'      => 0,               // 超时时间（秒），0为不限
        ],
        // memcache
        'memcache' => [
            'host'         => '127.0.0.1',     //
            'port'         => 11211,           //
            'pconnect'     => 1,               //
            'timeout'      => 1,               // 超时时间（秒），0为不限
        ],
        // memcached
        'memcached' => [
            'host'         => '127.0.0.1',     //
            'port'         => 11211,           //
            'pconnect'     => 1,               //
            'timeout'      => 1,               // 超时时间（秒），0为不限
        ],
    ],
];
```

2、使用缓存组件
```
// 读取 
cache()->read('some/var/data');

// 写入 
cache()->write('some/scalar/data/key', 'some data');
cache()->write('some/array/data/key', ['some data 1', 'some data 2']);

// 删除'some/scalar/data/key'缓存
cache()->delete('some/scalar/data/key');

// 清除some/*缓存
cache()->clear('some');

// 清除全部缓存
cache()->clear();
```
