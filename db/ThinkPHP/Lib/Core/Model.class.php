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

    private $_extModel = null;		// 当前使用的扩展模型
	protected $name = '';			// 模型名称
	
    protected $connection = '';		// 数据库连接配置
	protected $dbName = '';			// 数据库名称
    protected $tablePrefix = '';	// 数据表前缀
    protected $tableName = '';		// 数据表名（不包含表前缀）
    protected $trueTableName = '';	// 实际数据表名（包含表前缀）
    protected $fields = array();	// 字段信息
    protected $pk = 'id';			// 主键名称
    protected $db = null;			// 当前数据库操作对象
    protected $error = '';			// 最近错误信息
	
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

// ------------------------------------------------ START 数据对象的操作（获取和写入）	
    
// {{{ START 简单手动设置（对数据没有过滤操作）	

	/**
     * 设置数据对象值
     * @access public
     * @param mixed $data 数据
     * @return Model
     */
    public function data($data = '')
	{
        if ('' === $data && !empty($this->data))
		{
            return $this->data;
        }
		
        if (is_object($data))
		{
            $data = get_object_vars($data);
        }
		elseif (is_string($data))
		{
            parse_str($data, $data);
        }
		elseif (!is_array($data))
		{
            throw_exception(L('_DATA_TYPE_INVALID_'));
        }
		
        $this->data = $data;
        return $this;
    }
	
// {{{ END 简单手动设置（对数据没有过滤操作）	

// {{{ START 复杂自动或手动设置

    /**
     * 创建数据库更新和插入操作要使用的数据对象（可根据表单提交的数据创建，也可以自己传值创建）
     * @access public
     * @param mixed $data 创建数据
     * @param string $type 状态
     * @return mixed
     */
     public function create($data = '', $type = '')
	 {
        // 获取数据源（默认是POST数组）
        if (empty($data))
		{
            $data = $_POST;
        }
		elseif (is_object($data))
		{
            $data = get_object_vars($data);
        }
		
        // 验证数据源合法性（如果数据源为空或者不为空但非数组非对象则为非法）
        if (empty($data) || !is_array($data))
		{
            $this->error = L('_DATA_TYPE_INVALID_');	// link: Common/common.php
            return false;
        }

        // 解析字段映射（将数据对象中数据的名称根据映射表替换为其在数据库对应的字段名）
        $data = $this->parseFieldsMap($data, 0);

        // 获取要对这个数据对象进行的操作的类型（更新 or 插入）
        $type = $type ? $type : (!empty($data[$this->getPk()]) ? self::MODEL_UPDATE : self::MODEL_INSERT);

        // 获取要进行操作的字段信息
        if (isset($this->options['field']))
		{
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
	
    /**
     * 验证表单字段 支持批量验证
     * 如果批量验证返回错误的数组信息
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function _validationField($data,$val) {
        if(false === $this->_validationFieldItem($data,$val)){
            if($this->patchValidate) {
                $this->error[$val[0]]   =   $val[2];
            }else{
                $this->error            =   $val[2];
                return false;
            }
        }
        return ;
    }
	
    /**
     * 根据验证因子验证字段
     * @access protected
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function _validationFieldItem($data,$val) {
        switch(strtolower(trim($val[4]))) {
            case 'function':// 使用函数进行验证
            case 'callback':// 调用方法进行验证
                $args = isset($val[6])?(array)$val[6]:array();
                if(is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                if(is_array($val[0])){
                    // 支持多个字段验证
                    foreach($val[0] as $field)
                        $_data[$field] = $data[$field];
                    array_unshift($args, $_data);
                }else{
                    array_unshift($args, $data[$val[0]]);
                }
                if('function'==$val[4]) {
                    return call_user_func_array($val[1], $args);
                }else{
                    return call_user_func_array(array(&$this, $val[1]), $args);
                }
            case 'confirm': // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            case 'unique': // 验证某个值是否唯一
                if(is_string($val[0]) && strpos($val[0],','))
                    $val[0]  =  explode(',',$val[0]);
                $map = array();
                if(is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field)
                        $map[$field]   =  $data[$field];
                }else{
                    $map[$val[0]] = $data[$val[0]];
                }
                if(!empty($data[$this->getPk()])) { // 完善编辑的时候验证唯一
                    $map[$this->getPk()] = array('neq',$data[$this->getPk()]);
                }
                if($this->where($map)->find())   return false;
                return true;
            default:  // 检查附加规则
                return $this->check($data[$val[0]],$val[1],$val[4]);
        }
    }
// {{{ END 复杂自动或手动设置
	
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
// ------------------------------------------------ END 数据对象的操作（获取和写入）

// ------------------------------------------------ START 数据库操作（代理模式）

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
     * SQL查询
     * @access public
     * @param string $sql  SQL指令
     * @param mixed $parse  是否需要解析SQL
     * @return mixed
     */
    public function query($sql, $parse = false)
	{
        if (!is_bool($parse) && !is_array($parse))
		{
            $parse = func_get_args();
            array_shift($parse);
        }
		
        $sql = $this->parseSql($sql, $parse);
        return $this->db->query($sql);
    }
	
    /**
     * 执行SQL语句
     * @access public
     * @param string $sql  SQL指令
     * @param mixed $parse  是否需要解析SQL
     * @return false | integer
     */
    public function execute($sql, $parse = false)
	{
        if (!is_bool($parse) && !is_array($parse))
		{
            $parse = func_get_args();
            array_shift($parse);
        }
        $sql = $this->parseSql($sql, $parse);
        return $this->db->execute($sql);
    }
	
    /**
     * 解析SQL语句
     * @access public
     * @param string $sql  SQL指令
     * @param boolean $parse  是否需要解析SQL
     * @return string
     */
    protected function parseSql($sql, $parse)
	{
        if (true === $parse)
		{	// 表达式过滤
            $options = $this->_parseOptions();
            $sql = $this->db->parseSql($sql, $options);
        }
		elseif (is_array($parse))
		{
			// SQL预处理
            $sql  = vsprintf($sql, $parse);
        }
		else
		{
            $sql = strtr($sql, array('__TABLE__'=>$this->getTableName(), '__PREFIX__'=>C('DB_PREFIX')));
        }
        $this->db->setModel($this->name);
        
		return $sql;
    }
	
	/**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
	{
        $this->commit();
        $this->db->startTrans();
        return ;
    }

    /**
     * 提交事务
     * @access public
     * @return boolean
     */
    public function commit()
	{
        return $this->db->commit();
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     */
    public function rollback()
	{
        return $this->db->rollback();
    }
	
    /**
     * 返回模型的错误信息
     * @access public
     * @return string
     */
    public function getError()
	{
        return $this->error;
    }

    /**
     * 返回数据库的错误信息
     * @access public
     * @return string
     */
    public function getDbError()
	{
        return $this->db->getError();
    }

    /**
     * 返回最后插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
	{
        return $this->db->getLastInsID();
    }

    /**
     * 返回最后执行的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
	{
        return $this->db->getLastSql($this->name);
    }
    // 鉴于getLastSql比较常用 增加_sql 别名
    public function _sql(){
        return $this->getLastSql();
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
	
// ------------------------------------------------ END 数据库操作（代理模式）

// ------------------------------------------------ START 链操作的实现支持（return $this）

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
     * 查询SQL组装 join
     * @access public
     * @param mixed $join
     * @return Model
     */
    public function join($join)
	{
        if (is_array($join))
		{
            $this->options['join'] = $join;
        }
		elseif (!empty($join))
		{
            $this->options['join'][] = $join;
        }
		
        return $this;
    }
	
    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed $union
     * @param boolean $all
     * @return Model
     */
    public function union($union, $all = false)
	{
        if(empty($union))
			return $this;
        
		if ($all)
		{
            $this->options['union']['_all'] = true;
        }
		
        if (is_object($union))
		{
            $union = get_object_vars($union);
        }
		
        // 转换union表达式
        if (is_string($union))
		{
            $options =  $union;
        }
		elseif (is_array($union))
		{
            if (isset($union[0]))
			{
                $this->options['union'] = array_merge($this->options['union'], $union);
                return $this;
            }
			else
			{
                $options =  $union;
            }
        }
		else
		{
            throw_exception(L('_DATA_TYPE_INVALID_'));
        }
        $this->options['union'][]  =   $options;
        return $this;
    }
	
    /**
     * 查询缓存
     * @access public
     * @param mixed $key
     * @param integer $expire
     * @param string $type
     * @return Model
     */
    public function cache($key = true, $expire = null, $type = '')
	{
        if (false !== $key)
            $this->options['cache'] = array('key'=>$key, 'expire'=>$expire, 'type'=>$type);
        return $this;
    }
	
    /**
     * 指定查询字段 支持字段排除
     * @access public
     * @param mixed $field
     * @param boolean $except 是否排除
     * @return Model
     */
    public function field($field, $except=false)
	{
		// 获取全部字段
        if(true === $field)
		{
            $fields = $this->getDbFields();
            $field = $fields ? $fields : '*';
        }
		// 字段排除
		elseif ($except) 
		{
            if(is_string($field))
			{
                $field = explode(',',$field);
            }
			
            $fields = $this->getDbFields();
            $field  = $fields ? array_diff($fields, $field) : $field;
        }
        $this->options['field']   =   $field;
        return $this;
    }
	
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
     * 指定查询条件 支持安全过滤
     * @access public
     * @param mixed $where 条件表达式
     * @param mixed $parse 预处理参数
     * @return Model
     */
    public function where($where,$parse=null){
        if(!is_null($parse) && is_string($where)) {
            if(!is_array($parse)) {
                $parse = func_get_args();
                array_shift($parse);
            }
            $parse = array_map(array($this->db,'escapeString'),$parse);
            $where =   vsprintf($where,$parse);
        }elseif(is_object($where)){
            $where  =   get_object_vars($where);
        }
        if(is_string($where) && '' != $where){
            $map    =   array();
            $map['_string']   =   $where;
            $where  =   $map;
        }        
        if(isset($this->options['where'])){
            $this->options['where'] =   array_merge($this->options['where'],$where);
        }else{
            $this->options['where'] =   $where;
        }
        
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return Model
     */
    public function limit($offset,$length=null){
        $this->options['limit'] =   is_null($length)?$offset:$offset.','.$length;
        return $this;
    }

    /**
     * 指定分页
     * @access public
     * @param mixed $page 页数
     * @param mixed $listRows 每页数量
     * @return Model
     */
    public function page($page,$listRows=null){
        $this->options['page'] =   is_null($listRows)?$page:$page.','.$listRows;
        return $this;
    }

    /**
     * 查询注释
     * @access public
     * @param string $comment 注释
     * @return Model
     */
    public function comment($comment){
        $this->options['comment'] =   $comment;
        return $this;
    }
// ------------------------------------------------ END 链操作的实现支持（return $this） 

// ------------------------------------------------ START 执行SQL前的一些过滤操作	
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
// ------------------------------------------------ END 执行SQL前的一些过滤操作
	
// ------------------------------------------------ START 表单提交处理
    /**
     * 验证数据 支持 in between equal length regex expire ip_allow ip_deny
     * @access public
     * @param string $value 验证数据
     * @param mixed $rule 验证表达式
     * @param string $type 验证方式 默认为正则验证
     * @return boolean
     */
    public function check($value, $rule, $type = 'regex')
	{
        $type = strtolower(trim($type));
        switch($type)
		{
            case 'in': 			// 验证是否在某个离散的范围之内 逗号分隔字符串或者数组
            case 'notin':		// 验证是否不在某个离散的范围之内 逗号分隔字符串或者数组
                $range = is_array($rule) ? $rule : explode(',', $rule);
                return $type == 'in' ? in_array($value, $range) : !in_array($value, $range);
            case 'between': 	// 验证是否在某个连续的范围内
            case 'notbetween': 	// 验证是否不在某个连续的范围内            
                if (is_array($rule))
				{
                    $min = $rule[0];
                    $max = $rule[1];
                }
				else
				{
                    list($min, $max) = explode(',', $rule);
                }
                return $type == 'between' ? $value >= $min && $value <= $max : $value < $min || $value > $max;
            case 'equal': 		// 验证是否等于某个值
            case 'notequal': 	// 验证是否不等于某个值            
                return $type == 'equal' ? $value == $rule : $value != $rule;
            case 'length': 		// 验证长度
                $length = mb_strlen($value, 'utf-8');
                // 长度区间
				if (strpos($rule, ','))
				{
                    list($min, $max) = explode(',', $rule);
                    return $length >= $min && $length <= $max;
                }
				// 指定长度
				else
				{
                    return $length == $rule;
                }
            case 'expire':
                list($start, $end) = explode(',', $rule);
                if(!is_numeric($start)) $start = strtotime($start);
                if(!is_numeric($end)) $end = strtotime($end);
                return NOW_TIME >= $start && NOW_TIME <= $end;			// link: Lib/Core/App.class.php
            case 'ip_allow': 	// IP 操作许可验证
                return in_array(get_client_ip(), explode(',', $rule));	// link: Common/functions.php
            case 'ip_deny': 	// IP 操作禁止验证
                return !in_array(get_client_ip(), explode(',', $rule));
            case 'regex':
            default:    		// 默认使用正则验证 可以使用验证类中定义的验证名称
                return $this->regex($value, $rule);
        }
    }
	
    /**
     * 使用正则验证数据
     * @access public
     * @param string $value  要验证的数据
     * @param string $rule 验证规则
     * @return boolean
     */
    public function regex($value, $rule)
	{
        $validate = array(
            'require'   =>  '/.+/',
            'email'     =>  '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url'       =>  '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
            'currency'  =>  '/^\d+(\.\d+)?$/',
            'number'    =>  '/^\d+$/',
            'zip'       =>  '/^\d{6}$/',
            'integer'   =>  '/^[-\+]?\d+$/',
            'double'    =>  '/^[-\+]?\d+(\.\d+)?$/',
            'english'   =>  '/^[A-Za-z]+$/',
        );
        // 检查是否有内置的正则表达式
        if (isset($validate[strtolower($rule)]))
            $rule = $validate[strtolower($rule)];
        
		return preg_match($rule, $value) === 1;
    }
	
    // 自动表单令牌验证
    // TODO  ajax无刷新多次提交暂不能满足
    public function autoCheckToken($data)
	{
        if (C('TOKEN_ON'))					// link: Common/common.php
		{
            $name = C('TOKEN_NAME');		// link: Common/common.php
            // 令牌数据不存在
			if (!isset($data[$name]) || !isset($_SESSION[$name]))
			{
                return false;
            }

            // 令牌验证，防止重复提交
            list($key, $value) = explode('_', $data[$name]);
            if ($value && $_SESSION[$name][$key] === $value)
			{
                unset($_SESSION[$name][$key]); // 验证完成销毁session
                return true;
            }
			
            // 开启TOKEN重置
            if (C('TOKEN_RESET')) unset($_SESSION[$name][$key]);
            return false;
        }
        return true;
    }
	
    /**
     * 处理字段映射
	 * 说明：表单中的数据元素比如input，它们的名称可以不和数据库中对应的字段的名称保持一致
	 *       在不一致时，需要定义$this->_map，其存储的是 数据库中的字段名 => 表单数据元素的名称 的映射关系）
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
// ------------------------------------------------ END 表单提交处理
	
}