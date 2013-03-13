<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// ThinkPHP 入口文件

// 记录开始运行时间
$GLOBALS['_beginTime'] = microtime(TRUE);

// 记录内存初始使用
define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
if (MEMORY_LIMIT_ON)
	$GLOBALS['_startUseMems'] = memory_get_usage();

defined('APP_PATH') 	or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
defined('RUNTIME_PATH') or define('RUNTIME_PATH', APP_PATH.'Runtime/');
defined('APP_DEBUG') 	or define('APP_DEBUG', false); // 是否调试模式
$runtime = defined('MODE_NAME') ? '~'.strtolower(MODE_NAME).'_runtime.php' : '~runtime.php';
defined('RUNTIME_FILE') or define('RUNTIME_FILE', RUNTIME_PATH.$runtime);

// 正式模式且缓存文件存在时直接载入运行缓存文件
if (!APP_DEBUG && is_file(RUNTIME_FILE))
{
    require RUNTIME_FILE;
}
// 调试模式或运行缓存文件不存在时
else
{
    // 系统目录定义
    defined('THINK_PATH') or define('THINK_PATH', dirname(__FILE__).'/');
	
    // 加载运行时文件
    require THINK_PATH.'Common/runtime.php';
}