<?php
/**
 * 取得对象实例
 * @param string $name 类名
 * @param string $method 工厂方法 -- 可以为静态方法，如果不为空则通过该方法获取对象实例
 * @param array $args 构造函数的参数 
 * @return object
 */
function get_instance_of($name, $method='', $args=array())
{
    static $_instance = array();
    $identify = empty($args) ? $name . $method : $name . $method . to_guid_string($args);
    
	if (!isset($_instance[$identify]))
	{
        if (class_exists($name))
		{
            $o = new $name();
            if (method_exists($o, $method))
			{
                if (!empty($args))
				{
                    $_instance[$identify] = call_user_func_array(array(&$o, $method), $args);
                }
				else
				{
                    $_instance[$identify] = $o->$method();
                }
            }
            else
                $_instance[$identify] = $o;
        }
        else
            halt(L('_CLASS_NOT_EXIST_') . ':' . $name);
    }
	
    return $_instance[$identify];
}

/**
 * 给PHP各种类型变量生成唯一的标识号
 * @param mixed $mix 变量
 * @return string
 */
function to_guid_string($mix)
{
    if (is_object($mix) && function_exists('spl_object_hash'))
	{
        return spl_object_hash($mix);
    }
	elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    }
	else {
        $mix = serialize($mix);
    }
	
    return md5($mix);
}

/**
 * 缓存管理
 * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
 * @param mixed $value 缓存值
 * @param mixed $options 缓存参数
 * @return mixed
 */
function S($name, $value='', $options=null)
{
    static $cache = '';
	
    // 缓存操作的同时初始化
	if (is_array($options))
	{
        $type = isset($options['type']) ? $options['type'] : '';
        $cache = Cache::getInstance($type, $options);
    }
	// 缓存初始化
	elseif (is_array($name))
	{
        $type = isset($name['type']) ? $name['type'] : '';
        $cache = Cache::getInstance($type, $name);
        return $cache;
    }
	// 自动初始化
	elseif (empty($cache))
	{
        $cache = Cache::getInstance();
    }
	
	// 获取缓存
    if ('' === $value)
	{
        return $cache->get($name);
    }
	// 删除缓存
	elseif (is_null($value))
	{
        return $cache->rm($name);
    }
	// 缓存数据
	else
	{
        $expire = is_numeric($options) ? $options : NULL;
        return $cache->set($name, $value, $expire);
    }
}
// S方法的别名 已经废除 不再建议使用
function cache($name,$value='',$options=null){
    return S($name,$value,$options);
}