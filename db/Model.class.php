<?php
class Model
{
    // 操作状态
    const MODEL_INSERT  =   1;      //  插入模型数据
    const MODEL_UPDATE  =   2;      //  更新模型数据
    const MODEL_BOTH    =   3;      //  包含上面两种方式
    const MUST_VALIDATE    =  1;	// 必须验证
    const EXISTS_VALIDATE  =  0;	// 表单存在字段则验证
    const VALUE_VALIDATE   =  2;	// 表单值不为空则验证

	protected $name = '';			// 模型名称
    protected $connection = '';		// 数据库连接配置
	protected $dbName = '';			// 数据库名称
    protected $trueTableName = '';	// 实际数据表名（包含表前缀）
    protected $fields = array();	// 字段信息
    protected $pk = 'id';			// 主键名称
    protected $db = null;			// 当前数据库操作对象
	
    protected $data = array();	// 数据信息
	
    protected $autoCheckFields = true;	// 是否自动检测数据表字段信息
    protected $patchValidate = false;	// 是否批处理验证
	
    // 链操作方法列表
    protected $methods = array('table','order','alias','having','group','lock','distinct','auto','filter','validate');
    
	// 查询表达式参数
    protected $options = array();
    protected $_validate = array();	// 自动验证定义
    protected $_auto = array();  	// 自动完成定义
    protected $_map  = array();		// 字段映射定义
    protected $_scope = array();	// 命名范围定义


    /**
     * 构造函数
     * 取得DB类的实例对象 字段检查
     * @access public
     * @param string $name 模型名称
     * @param string $tablePrefix 表前缀
     * @param mixed $connection 数据库连接信息
     */
    public function __construct($name = '', $tablePrefix = '', $connection = '')
	{
        // 模型初始化
        $this->_initialize();
		
        // 获取模型名称
        if (!empty($name))
		{
			// 支持 数据库名.模型名的 定义
            if(strpos($name, '.'))
			{
                list($this->dbName, $this->name) = explode('.', $name);
            }
			else
			{
                $this->name = $name;
            }
        }
		elseif (empty($this->name))
		{
            $this->name = $this->getModelName();
        }
		
        // 设置表前缀（前缀为Null表示没有前缀）
        if(is_null($tablePrefix))
		{
            $this->tablePrefix = '';
        }
		elseif('' != $tablePrefix)
		{
            $this->tablePrefix = $tablePrefix;
        }
		else
		{
            $this->tablePrefix = $this->tablePrefix ? $this->tablePrefix : C('DB_PREFIX');
        }

        // 获取数据库操作对象并存储在$this->db中
        $this->db(0, empty($this->connection) ? $connection : $this->connection);
    }
    // 回调方法 初始化模型
    protected function _initialize() {}
	
    /**
     * 切换当前的数据库连接
	 * 注：当前的数据库操作对象存储在$this->db，切换即新建立一个数据库连接，然后将新的数据库操作对象存储到$this->db
     * @access public
     * @param integer $linkNum  连接序号
     * @param mixed $config  	数据库连接配置
     * @param array $params  	模型参数
     * @return Model
     */
    public function db($linkNum = '', $config = '', $params = array())
	{
        if ('' === $linkNum && $this->db)
		{
            return $this->db;
        }
		
        static $_linkNum = array();
        static $_db = array();
		
        if (!isset($_db[$linkNum]) || (isset($_db[$linkNum]) && $config && $_linkNum[$linkNum] != $config))
		{
            // 创建一个新的实例（支持读取配置参数）
            if(!empty($config) && is_string($config) && false === strpos($config, '/'))
			{
                $config = C($config);
            }
            $_db[$linkNum] = Db::getInstance($config);
        }
		elseif (NULL === $config)
		{
            $_db[$linkNum]->close(); // 关闭数据库连接
            unset($_db[$linkNum]);
            return ;
        }
		
        if (!empty($params))
		{
            if(is_string($params))
				parse_str($params, $params);
			
            foreach ($params as $name=>$value)
			{
                $this->setProperty($name,$value);
            }
        }
		
        // 记录连接配置信息
        $_linkNum[$linkNum] = $config;
		
        // 切换数据库连接
        $this->db = $_db[$linkNum];
        $this->_after_db();
		
        // 检测字段信息是否已获取
        if (!empty($this->name) && $this->autoCheckFields)
			$this->_checkTableInfo();
        
		return $this;
    }
    // 数据库切换后回调方法
    protected function _after_db() {}
	
    /**
     * 检测数据表信息是否已经获取（如果字段信息还没注册到$this->fields，则进行获取并注册）
     * @access protected
     * @return void
     */
    protected function _checkTableInfo()
	{
        if (empty($this->fields))
		{
            // 如果数据表字段没有定义则自动获取
            if (C('DB_FIELDS_CACHE'))
			{
                $db = $this->dbName ? $this->dbName : C('DB_NAME');
                $fields = F('_fields/'.strtolower($db.'.'.$this->name));	// 取得指定的字段缓存信息
                if ($fields)
				{
                    $version = C('DB_FIELD_VERISON');
                    if (empty($version) || $fields['_version'] == $version)
					{
                        $this->fields = $fields;
                        return ;
                    }
                }
            }
			
            // 重新获取字段信息并缓存（如果C('DB_FIELDS_CACHE')为false，则不会对其缓存）
            $this->flush();
        }
    }
	
    /**
     * 获取字段信息并缓存
     * @access public
     * @return void
     */
    public function flush()
	{
        // 缓存不存在则查询数据表信息
        $this->db->setModel($this->name);
        $fields = $this->db->getFields($this->getTableName());
        
		// 无法获取字段信息
		if (!$fields)
		{
            return false;
        }
		
        $this->fields = array_keys($fields);
        $this->fields['_autoinc'] = false;
		
        foreach ($fields as $key=>$val)
		{
            // 记录字段类型
            $type[$key] = $val['type'];
			
			if ($val['primary'])
			{
                $this->fields['_pk'] = $key;
                if ($val['autoinc'])
					$this->fields['_autoinc'] = true;
            }
        }
        // 注册字段类型信息
        $this->fields['_type'] = $type;
        
		if (C('DB_FIELD_VERISON'))
			$this->fields['_version'] = C('DB_FIELD_VERISON');

        // 2008-3-7 增加缓存开关控制
        if (C('DB_FIELDS_CACHE'))
		{
            // 永久缓存数据表信息
            $db = $this->dbName ? $this->dbName : C('DB_NAME');
            F('_fields/'.strtolower($db.'.'.$this->name), $this->fields);
        }
    }
	
    /**
     * 得到完整的表名（包含表前缀，如果dbName不为空，则还包含dbName，格式为dbName.tableName）
     * @access public
     * @return string
     */
    public function getTableName()
	{
        if (empty($this->trueTableName))
		{
            $tableName = !empty($this->tablePrefix) ? $this->tablePrefix : '';
            if(!empty($this->tableName))
			{
                $tableName .= $this->tableName;
            }
			else
			{
                $tableName .= parse_name($this->name);
            }
            $this->trueTableName = strtolower($tableName);
        }
		
        return (!empty($this->dbName) ? $this->dbName.'.' : '').$this->trueTableName;
    }
	
    /**
     * 得到当前的模型名称
     * @access public
     * @return string
     */
    public function getModelName()
	{
        if(empty($this->name))
            $this->name = substr(get_class($this), 0, -5);
		
        return $this->name;
    }
	
    /**
     * 设置当前模型的属性值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return Model
     */
    public function setProperty($name, $value)
	{
        if(property_exists($this, $name))
            $this->$name = $value;
		
        return $this;
    }
	
    /**
     * 动态切换扩展模型
     * @access public
     * @param string $type 模型类型名称
     * @param mixed $vars 要传入扩展模型的属性变量
     * @return Model
     */
    public function switchModel($type, $vars = array())
	{
        $class = ucwords(strtolower($type)).'Model';
        if (!class_exists($class))
            throw_exception($class.L('_MODEL_NOT_EXIST_'));
        
		// 实例化扩展模型
        $this->_extModel = new $class($this->name);
        if (!empty($vars))
		{
            // 传入当前模型的属性到扩展模型
            foreach ($vars as $var)
                $this->_extModel->setProperty($var, $this->$var);
        }
		
        return $this->_extModel;
    }
	
    /**
     * 设置数据对象的值
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set($name,$value)
	{
        // 注册数据信息
        $this->data[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
	{
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
	
    /**
     * 检测数据对象的值
     * @access public
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
	{
        return isset($this->data[$name]);
    }
	
    /**
     * 销毁数据对象的值
     * @access public
     * @param string $name 名称
     * @return void
     */
    public function __unset($name)
	{
        unset($this->data[$name]);
    }
	
    /**
     * 利用__call方法实现一些特殊的Model方法
     * @access public
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
	{
        if (in_array(strtolower($method), $this->methods, true))
		{
            // 连贯操作的实现
            $this->options[strtolower($method)] = $args[0];
            return $this;
        }
		elseif (in_array(strtolower($method), array('count','sum','min','max','avg'), true))
		{
            // 统计查询的实现
            $field = isset($args[0]) ? $args[0] : '*';
            return $this->getField(strtoupper($method).'('.$field.') AS tp_'.$method);
        }
		elseif (strtolower(substr($method, 0, 5)) == 'getby')
		{
            // 根据某个字段获取记录
            $field = parse_name(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        }
		elseif (strtolower(substr($method, 0, 10)) == 'getfieldby')
		{
            // 根据某个字段获取记录的某个值
            $name = parse_name(substr($method, 10));
            $where[$name] = $args[0];
            return $this->where($where)->getField($args[1]);
        }
		elseif (isset($this->_scope[$method]))
		{	// 命名范围的单独调用支持
            return $this->scope($method, $args[0]);
        }
		else
		{
            throw_exception(__CLASS__.':'.$method.L('_METHOD_NOT_EXIST_'));
            return;
        }
    }
    // 回调方法 初始化模型
    protected function _initialize() {}
	
    /**
     * SELECT查询
     * @access public
     * @param array $options 表达式参数
     * @return mixed
     */
    public function select($options = array())
	{
        if (is_string($options) || is_numeric($options))
		{
            // 获取主键信息并将其添加到表达式中
            $pk = $this->getPk();
            if (strpos($options, ','))
			{
                $where[$pk] = array('IN', $options);
            }
			else
			{
                $where[$pk] = $options;
            }
            $options = array();
            $options['where'] = $where;
        }
		// 只返回SQL（用于子查询）
		elseif (false === $options)
		{
            $options = array();
            // 表达式过滤
            $options = $this->_parseOptions($options);
            return '( '.$this->db->buildSelectSql($options).' )';
        }
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 执行查询
		$resultSet = $this->db->select($options);
        if(false === $resultSet)
		{
            return false;
        }
		
		// 查询结果为空
        if(empty($resultSet))
		{
            return null;
        }
        $this->_after_select($resultSet,$options);
        return $resultSet;
    }
    // 查询成功后的回调方法
    protected function _after_select(&$resultSet,$options) {}
	
    /**
     * 获取单条数据
     * @access public
     * @param mixed $options 表达式参数
     * @return mixed
     */
    public function find($options = array())
	{
        if (is_numeric($options) || is_string($options))
		{
			// 获取主键信息并将其添加到表达式中
            $where[$this->getPk()] = $options;
            $options = array();
            $options['where'] = $where;
        }
		
        // 总是查找一条记录
        $options['limit'] = 1;
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 执行查询
		$resultSet = $this->db->select($options);
        if(false === $resultSet)
		{
            return false;
        }
		
        if(empty($resultSet))
		{
            return null;
        }
		
        $this->data = $resultSet[0];
        $this->_after_find($this->data,$options);
        return $this->data;
    }
    // 查询成功的回调方法
    protected function _after_find(&$result,$options) {}
	
    /**
     * 获取一条记录的某个字段值
     * @access public
     * @param string $field  字段名
     * @param string $spea  字段数据间隔符号 NULL返回数组
     * @return mixed
     */
    public function getField($field, $sepa = null)
	{
        $options['field'] = $field;
        $options = $this->_parseOptions($options);
        $field = trim($field);
		
		// 多字段
        if(strpos($field, ','))
		{
            if (!isset($options['limit']))
			{
                $options['limit'] = is_numeric($sepa) ? $sepa : '';
            }
			
            $resultSet = $this->db->select($options);
            if (!empty($resultSet))
			{
                $_field = explode(',', $field);
                $field  = array_keys($resultSet[0]);
                $key    = array_shift($field);
                $key2   = array_shift($field);
                $cols   = array();
                $count  = count($_field);
                foreach ($resultSet as $result)
				{
                    $name = $result[$key];
                    if(2 == $count)
					{
                        $cols[$name] = $result[$key2];
                    }
					else
					{
                        $cols[$name] = is_string($sepa) ? implode($sepa, $result) : $result;
                    }
                }
				
                return $cols;
            }
        }
		// 单字段
		else
		{
            // 设置返回个数（当sepa指定为true的时候 返回所有数据）
			if (true !== $sepa)
			{
                $options['limit'] = is_numeric($sepa) ? $sepa : 1;
            }
			
            $result = $this->db->select($options);
            if (!empty($result))
			{
                if (true !== $sepa && 1 == $options['limit'])
					return reset($result[0]);
				
                foreach ($result as $val)
				{
                    $array[] = $val[$field];
                }
                return $array;
            }
        }
        return null;
    }
	
    /**
     * 新增单条数据
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @param boolean $replace 是否replace
     * @return mixed
     */
    public function add($data = '', $options = array(), $replace = false)
	{
        if (empty($data))
		{
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data))
			{
                $data = $this->data;
                $this->data = array();	// 重置数据
            }
			else
			{
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 数据过滤
        $data = $this->_facade($data);
        
		if (false === $this->_before_insert($data,$options))
		{
            return false;
        }
		
        // 写入数据到数据库
        $result = $this->db->insert($data, $options, $replace);
        if (false !== $result)
		{
            $insertId = $this->getLastInsID();
            if ($insertId)
			{
                // 自增主键返回插入ID
                $data[$this->getPk()] = $insertId;
                $this->_after_insert($data, $options);
                return $insertId;
            }
            $this->_after_insert($data,$options);
        }
        return $result;
    }
    // 插入数据前的回调方法
    protected function _before_insert(&$data,$options) {}
    // 插入成功后的回调方法
    protected function _after_insert($data,$options) {}
	
    /**
     * 新增多条数据
     * @access public
     * @param mixed $dataList 	数据
     * @param array $options 	表达式
     * @param boolean $replace 	是否replace
     * @return mixed
     */
    public function addAll($dataList, $options = array(), $replace = false)
	{
        if (empty($dataList))
		{
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 数据过滤
        foreach ($dataList as $key=>$data)
		{
            $dataList[$key] = $this->_facade($data);
        }
		
        // 写入数据到数据库
        $result = $this->db->insertAll($dataList, $options, $replace);
        if (false !== $result)
		{
            $insertId = $this->getLastInsID();
            if ($insertId)
			{
                return $insertId;
            }
        }
        return $result;
    }
	
    /**
     * 通过Select方式添加记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $options 表达式
     * @return boolean
     */
    public function selectAdd($fields = '', $table = '', $options = array())
	{
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 写入数据到数据库
		$result = $this->db->selectInsert($fields ? $fields : $options['field'], $table ? $table : $this->getTableName(), $options);
        if(false === $result)
		{
            // 数据库插入操作失败
            $this->error = L('_OPERATION_WRONG_');
            return false;
        }
		else
		{
            // 插入成功
            return $result;
        }
    }
	
    /**
     * 更新数据
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return boolean
     */
    public function save($data = '', $options = array())
	{
        if (empty($data))
		{
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data))
			{
                $data = $this->data;
                $this->data = array();	// 重置数据
            }
			else
			{
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
		
        // 数据过滤
        $data = $this->_facade($data);
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
		
        if (false === $this->_before_update($data,$options))
		{
            return false;
        }
		
        if (!isset($options['where']))
		{
            // 如果存在主键数据 则自动作为更新条件
            if(isset($data[$this->getPk()]))
			{
                $pk                 =   $this->getPk();
                $where[$pk]         =   $data[$pk];
                $options['where']   =   $where;
                $pkValue            =   $data[$pk];
                unset($data[$pk]);
            }
			else
			{
                // 如果没有任何更新条件则不执行
                $this->error = L('_OPERATION_WRONG_');
                return false;
            }
        }
		
        $result = $this->db->update($data,$options);
        if (false !== $result)
		{
            if (isset($pkValue))
				$data[$pk] = $pkValue;
            $this->_after_update($data,$options);
        }
        return $result;
    }
    // 更新数据前的回调方法
    protected function _before_update(&$data,$options) {}
    // 更新成功后的回调方法
    protected function _after_update($data,$options) {}
	
    /**
     * 更新某个字段的值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field  字段名
     * @param string $value  字段值
     * @return boolean
     */
    public function setField($field, $value='')
	{
        if(is_array($field))
		{
            $data = $field;
        }
		else
		{
            $data[$field] = $value;
        }
		
        return $this->save($data);
    }
	
    /**
     * 增长某个字段的值
     * @access public
     * @param string $field  字段名
     * @param integer $step  增长值
     * @return boolean
     */
    public function setInc($field, $step=1)
	{
        return $this->setField($field, array('exp', $field.'+'.$step));
    }
	
    /**
     * 减少某个字段的值
     * @access public
     * @param string $field  字段名
     * @param integer $step  减少值
     * @return boolean
     */
    public function setDec($field, $step = 1)
	{
        return $this->setField($field, array('exp', $field.'-'.$step));
    }
	
    /**
     * 删除数据
     * @access public
     * @param mixed $options 表达式
     * @return mixed
     */
    public function delete($options = array())
	{
        if (empty($options) && empty($this->options['where']))
		{
            // 如果删除条件为空（则删除当前数据对象中的主键所对应的记录）
            if (!empty($this->data) && isset($this->data[$this->getPk()]))
                return $this->delete($this->data[$this->getPk()]);
            else
                return false;
        }
		
        if (is_numeric($options) || is_string($options))
		{
            // 将主键信息加到表达式中
            $pk = $this->getPk();
            if (strpos($options, ','))
			{
                $where[$pk] = array('IN', $options);
            }
			else
			{
                $where[$pk] = $options;
            }
            $pkValue = $where[$pk];
            $options = array();
            $options['where'] = $where;
        }
		
        // 表达式过滤
        $options = $this->_parseOptions($options);
        
		// 删除记录
		$result = $this->db->delete($options);
        if (false !== $result)
		{
            $data = array();
            if (isset($pkValue))
				$data[$pk] = $pkValue;
            $this->_after_delete($data,$options);
        }
        // 返回删除记录个数
        return $result;
    }
    // 删除成功后的回调方法
    protected function _after_delete($data,$options) {}
	
    /**
     * 调用命名范围
     * @access public
     * @param mixed $scope 命名范围名称 支持多个 和直接定义
     * @param array $args 参数
     * @return Model
     */
    public function scope($scope = '', $args = NULL)
	{
		// 使用默认的命名范围
        if ('' === $scope)
		{
            if(isset($this->_scope['default']))
			{
                $options = $this->_scope['default'];
            }
			else
			{
                return $this;
            }
        }
		// 支持多个命名范围调用 用逗号分割
		elseif (is_string($scope))
		{
            $scopes  = explode(',', $scope);
            $options = array();
            foreach ($scopes as $name)
			{
                if (!isset($this->_scope[$name]))
					continue;
                
				$options = array_merge($options, $this->_scope[$name]);
            }
			
            if (!empty($args) && is_array($args))
			{
                $options = array_merge($options, $args);
            }
        }
		// 直接传入命名范围定义
		elseif (is_array($scope))
		{
            $options = $scope;
        }
        
        if (is_array($options) && !empty($options))
		{
            $this->options = array_merge($this->options, array_change_key_case($options));
        }
        return $this;
    }
	
    /**
     * 获取数据表字段信息（不带字段类型）
     * @access public
     * @return array
     */
    public function getDbFields()
	{
		// 动态指定表名
        if (isset($this->options['table']))
		{
            $fields = $this->db->getFields($this->options['table']);
            return $fields ? array_keys($fields) : false;
        }
		
        if ($this->fields)
		{
            $fields = $this->fields;
            unset($fields['_autoinc'], $fields['_pk'], $fields['_type'], $fields['_version']);
            return $fields;
        }
		
        return false;
    }
	
    /**
     * 获取主键名称
     * @access public
     * @return string
     */
    public function getPk()
	{
        return isset($this->fields['_pk']) ? $this->fields['_pk'] : $this->pk;
    }
	
    /**
     * 对将要保存到数据库的数据进行处理
     * @access protected
     * @param mixed $data 要操作的数据
     * @return boolean
     */
     protected function _facade($data)
	 {
        // 检查非数据字段
        if (!empty($this->fields))
		{
            foreach ($data as $key=>$val)
			{
                if(!in_array($key, $this->fields, true))
				{
                    unset($data[$key]);
                }
				elseif (is_scalar($val))
				{
                    // 依据字段类型对数据类型进行检查
                    $this->_parseType($data,$key);
                }
            }
        }
		
        // 安全过滤
        if (!empty($this->options['filter']))
		{
            $data = array_map($this->options['filter'], $data);
            unset($this->options['filter']);
        }
        $this->_before_write($data);
		
		return $data;
     }
    // 写入数据前的回调方法 包括新增和更新
    protected function _before_write(&$data) {}
	
    /**
     * 数据类型检测
     * @access protected
     * @param mixed $data 数据
     * @param string $key 字段名
     * @return void
     */
    protected function _parseType(&$data, $key)
	{
        $fieldType = strtolower($this->fields['_type'][$key]);
        if (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType,'int'))
		{
            $data[$key] = intval($data[$key]);
        }
		elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType,'double'))
		{
            $data[$key] = floatval($data[$key]);
        }
		elseif(false !== strpos($fieldType,'bool'))
		{
            $data[$key] = (bool) $data[$key];
        }
    }
	
    /**
     * 分析表达式（对表达式信息进行过滤）
     * @access proteced
     * @param array $options 表达式参数
     * @return array
     */
    protected function _parseOptions($options = array())
	{
        if (is_array($options))
            $options =  array_merge($this->options, $options);
		
        // 清空本次sql表达式组装信息，避免影响下次组装
        $this->options = array();
		
        if (!isset($options['table']))
		{
            // 自动获取表名和字段信息
            $options['table'] = $this->getTableName();
            $fields = $this->fields;
        }
		else
		{
            // 如果没有指定数据表则从$this->options['table']中取得，然后再解析它的字段信息
            $fields = $this->getDbFields();
        }

        if (!empty($options['alias']))
		{
            $options['table'] .= ' '.$options['alias'];
        }
		
        // 记录操作的模型名称
        $options['model'] = $this->name;

        // 字段类型验证
        if (isset($options['where']) && is_array($options['where']) && !empty($fields))
		{
            // 对数组查询条件进行字段类型检查
            foreach ($options['where'] as $key=>$val)
			{
                $key = trim($key);
                if(in_array($key, $fields, true))
				{
                    if(is_scalar($val))
					{
                        $this->_parseType($options['where'], $key);
                    }
                }
				elseif ('_' != substr($key, 0, 1) && false === strpos($key, '.') && false === strpos($key, '|') && false === strpos($key,'&'))
				{
                    unset($options['where'][$key]);
                }
            }
        }

        // 表达式过滤
        $this->_options_filter($options);
		
        return $options;
    }
    // 表达式过滤回调方法
    protected function _options_filter(&$options) {}
	
    /**
     * 创建数据对象 但不保存到数据库
     * @access public
     * @param mixed $data 创建数据
     * @param string $type 状态
     * @return mixed
     */
     public function create($data = '', $type = '')
	 {
        // 如果没有传值默认取POST数据
        if (empty($data))
		{
            $data = $_POST;
        }
		elseif (is_object($data))
		{
            $data = get_object_vars($data);
        }
		
        // 验证数据
        if (empty($data) || !is_array($data))
		{
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }

        // 检查字段映射
        $data = $this->parseFieldsMap($data, 0);

        // 状态
        $type = $type ? $type : (!empty($data[$this->getPk()]) ? self::MODEL_UPDATE : self::MODEL_INSERT);

        // 检测提交字段的合法性
        if (isset($this->options['field']))
		{ // $this->field('field1,field2...')->create()
            $fields = $this->options['field'];
            unset($this->options['field']);
        }
		elseif ($type == self::MODEL_INSERT && isset($this->insertFields))
		{
            $fields = $this->insertFields;
        }
		elseif ($type == self::MODEL_UPDATE && isset($this->updateFields))
		{
            $fields =   $this->updateFields;
        }
		
        if (isset($fields))
		{
            if (is_string($fields))
			{
                $fields = explode(',', $fields);
            }
			
            // 判断令牌验证字段
            if (C('TOKEN_ON'))
				$fields[] = C('TOKEN_NAME');
			
            foreach ($data as $key=>$val)
			{
                if (!in_array($key,$fields))
				{
                    unset($data[$key]);
                }
            }
        }

        // 数据自动验证
        if (!$this->autoValidation($data,$type))
			return false;

        // 表单令牌验证
        if (C('TOKEN_ON') && !$this->autoCheckToken($data))
		{
            $this->error = L('_TOKEN_ERROR_');
            return false;
        }

        // 如果开启了字段检测 则过滤非法字段数据
        if ($this->autoCheckFields)
		{
            $fields = $this->getDbFields();
            foreach ($data as $key=>$val)
			{
                if (!in_array($key, $fields))
				{
                    unset($data[$key]);
                }
				elseif(MAGIC_QUOTES_GPC && is_string($val))	// link: MAGIC_QUOTES_GPC来自于Common\runtime.php
				{
                    $data[$key] =   stripslashes($val);
                }
            }
        }

        // 创建完成对数据进行自动处理
        $this->autoOperation($data, $type);
		
        // 赋值当前数据对象
        $this->data = $data;
        
		// 返回创建的数据以供其他调用
        return $data;
    }
	
    /**
     * 处理字段映射
     * @access public
     * @param array $data 	当前数据
     * @param integer $type 类型 0 写入 1 读取
     * @return array
     */
    public function parseFieldsMap($data, $type = 1)
	{
        // 检查字段映射
        if (!empty($this->_map))
		{
            foreach ($this->_map as $key=>$val)
			{
				// 读取
                if ($type == 1)
				{
                    if (isset($data[$val]))
					{
                        $data[$key] = $data[$val];
                        unset($data[$val]);
                    }
                }
				// 写入
				else
				{
                    if(isset($data[$key]))
					{
                        $data[$val] = $data[$key];
                        unset($data[$key]);
                    }
                }
            }
        }
		
        return $data;
    }
	
    /**
     * 自动表单验证
     * @access protected
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return boolean
     */
    protected function autoValidation($data, $type)
	{
        if (!empty($this->options['validate']))
		{
            $_validate = $this->options['validate'];
            unset($this->options['validate']);
        }
		elseif (!empty($this->_validate))
		{
            $_validate = $this->_validate;
        }
		
        // 如果设置了数据自动验证则进行数据验证
        if (isset($_validate))
		{
            if ($this->patchValidate)
			{
                $this->error = array();		// 重置验证错误信息
            }
			
            foreach ($_validate as $key=>$val)
			{
                // 验证因子定义格式
                // array(field,rule,message,condition,type,when,params)
                // 判断是否需要执行验证
                if (empty($val[5]) || $val[5]== self::MODEL_BOTH || $val[5]== $type)
				{
                    if (0 == strpos($val[2], '{%') && strpos($val[2] ,'}'))
                        $val[2] = L(substr($val[2],2,-1));	// 支持提示信息的多语言 使用 {%语言定义} 方式
                    $val[3] = isset($val[3])?$val[3]:self::EXISTS_VALIDATE;
                    $val[4] = isset($val[4])?$val[4]:'regex';
					
                    // 判断验证条件
                    switch($val[3])
					{
                        case self::MUST_VALIDATE:   // 必须验证 不管表单是否有设置该字段
                            if(false === $this->_validationField($data,$val)) 
                                return false;
                            break;
                        case self::VALUE_VALIDATE:  // 值不为空的时候才验证
                            if('' != trim($data[$val[0]]))
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                            break;
                        default:    				// 默认表单存在该字段就验证
                            if(isset($data[$val[0]]))
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                    }
                }
            }
			
            // 批量验证的时候最后返回错误
            if(!empty($this->error)) return false;
        }
        return true;
    }
	
    /**
     * 自动表单处理
     * @access public
     * @param array $data 创建数据
     * @param string $type 创建类型
     * @return mixed
     */
    private function autoOperation(&$data,$type) {
        if(!empty($this->options['auto'])) {
            $_auto   =   $this->options['auto'];
            unset($this->options['auto']);
        }elseif(!empty($this->_auto)){
            $_auto   =   $this->_auto;
        }
        // 自动填充
        if(isset($_auto)) {
            foreach ($_auto as $auto){
                // 填充因子定义格式
                // array('field','填充内容','填充条件','附加规则',[额外参数])
                if(empty($auto[2])) $auto[2] = self::MODEL_INSERT; // 默认为新增的时候自动填充
                if( $type == $auto[2] || $auto[2] == self::MODEL_BOTH) {
                    switch(trim($auto[3])) {
                        case 'function':    //  使用函数进行填充 字段的值作为参数
                        case 'callback': // 使用回调方法
                            $args = isset($auto[4])?(array)$auto[4]:array();
                            if(isset($data[$auto[0]])) {
                                array_unshift($args,$data[$auto[0]]);
                            }
                            if('function'==$auto[3]) {
                                $data[$auto[0]]  = call_user_func_array($auto[1], $args);
                            }else{
                                $data[$auto[0]]  =  call_user_func_array(array(&$this,$auto[1]), $args);
                            }
                            break;
                        case 'field':    // 用其它字段的值进行填充
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'ignore': // 为空忽略
                            if(''===$data[$auto[0]])
                                unset($data[$auto[0]]);
                            break;
                        case 'string':
                        default: // 默认作为字符串填充
                            $data[$auto[0]] = $auto[1];
                    }
                    if(false === $data[$auto[0]] )   unset($data[$auto[0]]);
                }
            }
        }
        return $data;
    }
}