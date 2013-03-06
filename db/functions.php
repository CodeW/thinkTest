<?php
/**
 * ȡ�ö���ʵ��
 * @param string $name ����
 * @param string $method �������� -- ����Ϊ��̬�����������Ϊ����ͨ���÷�����ȡ����ʵ��
 * @param array $args ���캯���Ĳ��� 
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
 * ��PHP�������ͱ�������Ψһ�ı�ʶ��
 * @param mixed $mix ����
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
 * �������
 * @param mixed $name �������ƣ����Ϊ�����ʾ���л�������
 * @param mixed $value ����ֵ
 * @param mixed $options �������
 * @return mixed
 */
function S($name, $value='', $options=null)
{
    static $cache = '';
	
    // ���������ͬʱ��ʼ��
	if (is_array($options))
	{
        $type = isset($options['type']) ? $options['type'] : '';
        $cache = Cache::getInstance($type, $options);
    }
	// �����ʼ��
	elseif (is_array($name))
	{
        $type = isset($name['type']) ? $name['type'] : '';
        $cache = Cache::getInstance($type, $name);
        return $cache;
    }
	// �Զ���ʼ��
	elseif (empty($cache))
	{
        $cache = Cache::getInstance();
    }
	
	// ��ȡ����
    if ('' === $value)
	{
        return $cache->get($name);
    }
	// ɾ������
	elseif (is_null($value))
	{
        return $cache->rm($name);
    }
	// ��������
	else
	{
        $expire = is_numeric($options) ? $options : NULL;
        return $cache->set($name, $value, $expire);
    }
}
// S�����ı��� �Ѿ��ϳ� ���ٽ���ʹ��
function cache($name,$value='',$options=null){
    return S($name,$value,$options);
}