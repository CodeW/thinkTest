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

/**
 * ThinkPHP Behavior基础类
 * @category   Think
 * @package  Think
 * @subpackage  Core
 * @author liu21st <liu21st@gmail.com>
 */
abstract class Behavior
{
    // 行为参数（会自动注册到配置参数中，可以用C('XXX')获取）
    protected $options = array();

    /**
     * 构造函数
     * @access public
     */
    public function __construct()
	{
        if (!empty($this->options))
		{
            foreach ($this->options as $name=>$val)
			{
				// 如果参数已在配置文件设置，则覆盖行为参数里的设置
                if (NULL !== C($name))
				{
                    $this->options[$name] = C($name);
                }
				// 如果参数未在配置文件设置，则将行为参数里的设置注册到配置参数中
				else
				{
                    C($name, $val);		// link: Common/common.php
                }
            }
            array_change_key_case($this->options);		// question: 此行代码貌似没什么用
        }
    }
    
    // 获取行为参数
    public function __get($name)
	{
        return $this->options[strtolower($name)];
    }

    /**
     * 执行行为 run方法是Behavior唯一的接口
     * @access public
     * @param mixed $params  行为参数
     * @return void
     */
    abstract public function run(&$params);

}