<?php
class Db
{
    protected $dbType = null;		// 数据库类型
    protected $config = '';			// 数据库连接参数
    protected $pconnect = false;	// 是否使用永久连接
    protected $connected = false;	// 是否已经连接数据库

	protected $linkID = array();	// 数据库连接ID 支持多个连接
    protected $_linkID = null;		// 当前连接ID
    protected $queryStr = '';		// 当前SQL语句
	protected $modelSql = array();	// 存储SQL语句的数组，键为模型名，值为sql
    protected $queryID = null;		// 当前查询ID
    
    protected $lastInsID  = null;	// 最后插入ID
    protected $numRows = 0;			// 返回或者影响记录数
    protected $numCols = 0;			// 返回字段数
    protected $transTimes = 0;		// 事务指令数
	
    protected $model = '_why_';		// 当前操作所属的模型名
    protected $autoFree = false;	// 是否自动释放查询结果
    protected $error = '';			// 错误信息
	
    // 数据库表达式
    protected $comparison = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN');
	// 查询表达式
	protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%COMMENT%';
	
    /**
     * 取得驱动数据库的实例
     * @static
     * @access public
     * @return object
     */
    public static function getInstance()
	{
        $args = func_get_args();
        return get_instance_of(__CLASS__, 'factory', $args);
    }

    /**
     * 生产驱动数据库的实例
     * @access public
     * @param mixed $db_config 数据库连接信息（支持配置文件或者DSN）
     * @return object
	 * @throws Execption
     */
    public function factory($db_config='')
	{
        // 读取数据库配置
        $db_config = $this->parseConfig($db_config);
        if(empty($db_config['dbms']))
            throw_exception(L('_NO_DB_CONFIG_'));
		
        // 数据库类型
        $this->dbType = ucwords(strtolower($db_config['dbms']));
        
		// 检查驱动类
        $class = 'Db'. $this->dbType;
        if(class_exists($class))
		{
            $db = new $class($db_config);
            // 获取当前的数据库类型
            if('pdo' != strtolower($db_config['dbms']))
                $db->dbType = strtoupper($this->dbType);
            else
                $db->dbType = $this->_getDsnType($db_config['dsn']);
        }
		else
		{
            // 类没有定义
            throw_exception(L('_NO_DB_DRIVER_').': ' . $class);
        }
		
        return $db;
    }
	
    /**
     * 分析数据库配置信息，支持数组和DSN
     * @access private
     * @param mixed $db_config 数据库配置信息
     * @return array
     */
    private function parseConfig($db_config='')
	{
		if (!empty($db_config))
		{
			// DSN字符串
			if (is_string($db_config))
			{
				$db_config = $this->parseDSN($db_config);
			}
			// 数组配置
			elseif (is_array($db_config))
			{
				$db_config = array_change_key_case($db_config);
				$db_config = array(
					'dbms'      =>  $db_config['db_type'],
					'username'  =>  $db_config['db_user'],
					'password'  =>  $db_config['db_pwd'],
					'hostname'  =>  $db_config['db_host'],
					'hostport'  =>  $db_config['db_port'],
					'database'  =>  $db_config['db_name'],
					'dsn'       =>  $db_config['db_dsn'],
					'params'    =>  $db_config['db_params'],
				);
			}
		}
		// 如果参数为空，读取配置文件
		elseif(empty($db_config))
		{
            if(C('DB_DSN') && strtolower(C('DB_TYPE')) != 'pdo')
			{
                $db_config =  $this->parseDSN(C('DB_DSN'));
            }
			else
			{
                $db_config = array (
                    'dbms'      =>  C('DB_TYPE'),
                    'username'  =>  C('DB_USER'),
                    'password'  =>  C('DB_PWD'),
                    'hostname'  =>  C('DB_HOST'),
                    'hostport'  =>  C('DB_PORT'),
                    'database'  =>  C('DB_NAME'),
                    'dsn'       =>  C('DB_DSN'),
                    'params'    =>  C('DB_PARAMS'),
                );
            }
        }
		
        return $db_config;
    }
	
    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName
     * @static
     * @access public
     * @param string $dsnStr
     * @return array
     */
    public function parseDSN($dsnStr)
	{
        if(empty($dsnStr))
			return false;
		
        $info = parse_url($dsnStr);
        if($info['scheme'])
		{
            $dsn = array(
				'dbms'      =>  $info['scheme'],
				'username'  =>  isset($info['user']) ? $info['user'] : '',
				'password'  =>  isset($info['pass']) ? $info['pass'] : '',
				'hostname'  =>  isset($info['host']) ? $info['host'] : '',
				'hostport'  =>  isset($info['port']) ? $info['port'] : '',
				'database'  =>  isset($info['path']) ? substr($info['path'],1) : ''
            );
        }
		else
		{
            preg_match('/^(.*?)\:\/\/(.*?)\:(.*?)\@(.*?)\:([0-9]{1, 6})\/(.*?)$/', trim($dsnStr), $matches);
            $dsn = array (
				'dbms'      =>  $matches[1],
				'username'  =>  $matches[2],
				'password'  =>  $matches[3],
				'hostname'  =>  $matches[4],
				'hostport'  =>  $matches[5],
				'database'  =>  $matches[6]
            );
        }
		
        $dsn['dsn'] = ''; // 兼容配置信息数组
        return $dsn;
    }
	
    /**
     * 根据DSN获取数据库类型 返回大写
     * @access protected
     * @param string $dsn  dsn字符串
     * @return string
     */
    protected function _getDsnType($dsn)
	{
        $match  =  explode(':',$dsn);
        $dbType = strtoupper(trim($match[0]));
        return $dbType;
    }
	
    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function initConnect($master = true)
	{
		// 采用分布式数据库
        if(1 == C('DB_DEPLOY_TYPE'))
		{
            $this->_linkID = $this->multiConnect($master);
        }
		// 默认单数据库
		else
		{
			if (!$this->connected)
				$this->_linkID = $this->connect();
		}
    }
	
    /**
     * 连接分布式服务器
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function multiConnect($master = false)
	{
        static $_config = array();
        
		if(empty($_config))
		{
            // 缓存分布式数据库配置解析
            foreach ($this->config as $key=>$val){
                $_config[$key] = explode(',',$val);
            }
        }
		
        // 数据库读写是否分离
        if(C('DB_RW_SEPARATE'))
		{
            if($master)
                // 指定主服务器写入
                $r = floor(mt_rand(0, C('DB_MASTER_NUM')-1));	// 每次随机连接的主数据库
            else
			{
				// 指定服务器读
                if(is_numeric(C('DB_SLAVE_NO')))
				{
                    $r = C('DB_SLAVE_NO');
                }
				else
				{
                    // 读操作连接从服务器
                    $r = floor(mt_rand(C('DB_MASTER_NUM'), count($_config['hostname'])-1));   // 每次随机连接的从数据库
                }
            }
        }
		else
		{
            // 读写操作不区分服务器
            $r = floor(mt_rand(0, count($_config['hostname'])-1));   // 每次随机连接的数据库
        }
		
        $db_config = array(
            'username'  =>  isset($_config['username'][$r]) ? $_config['username'][$r] : $_config['username'][0],
            'password'  =>  isset($_config['password'][$r]) ? $_config['password'][$r] : $_config['password'][0],
            'hostname'  =>  isset($_config['hostname'][$r]) ? $_config['hostname'][$r] : $_config['hostname'][0],
            'hostport'  =>  isset($_config['hostport'][$r]) ? $_config['hostport'][$r] : $_config['hostport'][0],
            'database'  =>  isset($_config['database'][$r]) ? $_config['database'][$r] : $_config['database'][0],
            'dsn'       =>  isset($_config['dsn'][$r]) ? $_config['dsn'][$r] : $_config['dsn'][0],
            'params'    =>  isset($_config['params'][$r]) ? $_config['params'][$r] : $_config['params'][0],
        );
		
        return $this->connect($db_config, $r);
    }
	
    /**
     * 数据库调试 记录当前SQL
     * @access protected
     */
    protected function debug()
	{
        $this->modelSql[$this->model] = $this->queryStr;
        $this->model = '_why_';
		
        // 如果需要记录SQL执行日志，则记录SQL语句以及执行的时间
        if (C('DB_SQL_LOG'))
		{
            G('queryEndTime');
            trace($this->queryStr.' [ RunTime:'.G('queryStartTime','queryEndTime',6).'s ]','','SQL');
        }
    }
	
    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str)
	{
        return addslashes($str);
    }
	
    /**
     * 对表名和字段名特殊处理（MYSQL中会为其加反引号以防止表名或字段名用了MYSQL保留字而发生错误）
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key)
	{
        return $key;
    }
	
    /**
     * table分析
     * @access protected
     * @param mixed $table
     * @return string
     */
    protected function parseTable($tables)
	{
		// 支持别名定义
        if(is_array($tables))
		{
            $array = array();
            foreach ($tables as $table=>$alias)
			{
                if(!is_numeric($table))
                    $array[] =  $this->parseKey($table).' '.$this->parseKey($alias);
                else
                    $array[] =  $this->parseKey($table);
            }
            $tables = $array;
        }
		elseif(is_string($tables))
		{
            $tables = explode(',', $tables);
            array_walk($tables, array(&$this, 'parseKey'));
        }
		
        return implode(',',$tables);
    }
	
    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields)
	{
        if(is_string($fields) && strpos($fields, ','))
		{
            $fields = explode(',',$fields);
        }
		
        if(is_array($fields))
		{
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array   =  array();
            foreach ($fields as $key=>$field)
			{
                if(!is_numeric($key))
                    $array[] =  $this->parseKey($key).' AS '.$this->parseKey($field);
                else
                    $array[] =  $this->parseKey($field);
            }
            $fieldsStr = implode(',', $array);
        }
		elseif(is_string($fields) && !empty($fields))
		{
            $fieldsStr = $this->parseKey($fields);
        }
		else
		{
            $fieldsStr = '*';
        }
		
        //TODO 如果是查询全部字段，并且是join的方式，那么就把要查的表加个别名，以免字段被覆盖
        return $fieldsStr;
    }
	
    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value)
	{
        if(is_string($value))
		{
            $value =  '\''.$this->escapeString($value).'\'';
        }
		elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp')
		{
            $value =  $this->escapeString($value[1]);
        }
		elseif(is_array($value))
		{
            $value =  array_map(array($this, 'parseValue'),$value);
        }
		elseif(is_bool($value))
		{
            $value =  $value ? '1' : '0';
        }
		elseif(is_null($value))
		{
            $value =  'null';
        }
		
        return $value;
    }
	
    /**
     * set分析
     * @access protected
     * @param array $data
     * @return string
     */
    protected function parseSet($data)
	{
        foreach ($data as $key=>$val)
		{
            $value = $this->parseValue($val);
            // 过滤非标量数据
			if(is_scalar($value))
                $set[] = $this->parseKey($key).'='.$value;
        }
		
        return ' SET '.implode(',',$set);
    }
	
    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return string
     */
    protected function parseWhere($where)
	{
        $whereStr = '';
        if(is_string($where))
		{
            $whereStr = $where;
        }
		else
		{
            $operate = isset($where['_logic']) ? strtoupper($where['_logic']) : '';
            if(in_array($operate,array('AND','OR','XOR')))
			{
                $operate = ' '.$operate.' ';
                unset($where['_logic']);
            }
			else
			{
                $operate = ' AND ';
            }
			
            foreach ($where as $key=>$val)
			{
                $whereStr .= '( ';
                if(0 === strpos($key, '_'))
				{
                    // 解析特殊条件表达式
                    $whereStr .= $this->parseThinkWhere($key, $val);
                }
				else
				{
                    // 查询字段的安全过滤
                    if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/',trim($key)))
					{
                        throw_exception(L('_EXPRESS_ERROR_').':'.$key);
                    }
					
                    // 多条件支持（key为fieldName，val为fieldValue，如果val中有$val['_multi']，则说明值有多个，且与key里面的fieldName一一对应）
                    $multi = is_array($val) && isset($val['_multi']);
                    $key = trim($key);
                    if(strpos($key, '|'))	// fieldName1|fieldName2|fieldName3 方式定义查询字段（这些字段之间是"或"的关系）
					{
                        $array = explode('|', $key);
                        $str = array();
                        foreach ($array as $m=>$k)
						{
                            $v = $multi ? $val[$m] : $val;
                            $str[] = '('.$this->parseWhereItem($this->parseKey($k), $v).')';
                        }
                        $whereStr .= implode(' OR ',$str);
                    }
					elseif(strpos($key, '&'))
					{
                        $array = explode('&',$key);
                        $str = array();
                        foreach ($array as $m=>$k)
						{
                            $v = $multi ? $val[$m] : $val;
                            $str[] = '('.$this->parseWhereItem($this->parseKey($k),$v).')';
                        }
                        $whereStr .= implode(' AND ',$str);
                    }
					else
					{
                        $whereStr .= $this->parseWhereItem($this->parseKey($key),$val);
                    }
                }
                $whereStr .= ' )'.$operate;
            }
            $whereStr = substr($whereStr, 0, -strlen($operate));
        }
		
        return empty($whereStr) ? '' : ' WHERE '.$whereStr;
    }
	
    /**
     * 特殊条件分析
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return string
     */
    protected function parseThinkWhere($key, $val)
	{
        $whereStr = '';
		
        switch($key)
		{
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = substr($this->parseWhere($val), 6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val, $where);
                if(isset($where['_logic']))
				{
                    $op = ' '.strtoupper($where['_logic']).' ';
                    unset($where['_logic']);
                }
				else
				{
                    $op = ' AND ';
                }
				
                $array = array();
                foreach ($where as $field=>$data)
                    $array[] = $this->parseKey($field).' = '.$this->parseValue($data);
                $whereStr = implode($op, $array);
                break;
        }
		
        return $whereStr;
    }
	
    // where子单元分析
    protected function parseWhereItem($key, $val)
	{
        $whereStr = '';
        if(is_array($val))
		{
            if(is_string($val[0]))
			{
				// 比较运算
                if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT)$/i', $val[0]))
				{
                    $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                }
				// 模糊查找
				elseif(preg_match('/^(NOTLIKE|LIKE)$/i', $val[0]))
				{
                    if(is_array($val[1]))
					{
                        $likeLogic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                        if(in_array($likeLogic, array('AND','OR','XOR')))
						{
                            $likeStr = $this->comparison[strtolower($val[0])];
                            $like = array();
                            foreach ($val[1] as $item)
							{
                                $like[] = $key.' '.$likeStr.' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' '.$likeLogic.' ',$like).')';                          
                        }
                    }
					else
					{
                        $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                    }
                }
				// 使用表达式
				elseif ('exp'==strtolower($val[0]))
				{
                    $whereStr .= ' ('.$key.' '.$val[1].') ';
                }
				// IN 运算
				elseif (preg_match('/IN/i',$val[0]))
				{
                    if (isset($val[2]) && 'exp' == $val[2])
					{
                        $whereStr .= $key.' '.strtoupper($val[0]).' '.$val[1];
                    }
					else
					{
                        if(is_string($val[1]))
						{
                             $val[1] =  explode(',', $val[1]);
                        }
                        $zone = implode(',', $this->parseValue($val[1]));
                        $whereStr .= $key.' '.strtoupper($val[0]).' ('.$zone.')';
                    }
                }
				// BETWEEN运算
				elseif (preg_match('/BETWEEN/i',$val[0]))
				{
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $whereStr .=  ' ('.$key.' '.strtoupper($val[0]).' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]).' )';
                }
				else
				{
                    throw_exception(L('_EXPRESS_ERROR_').':'.$val[0]);
                }
            }
			else
			{
                $count = count($val);
                $rule = isset($val[$count-1]) ? strtoupper($val[$count-1]) : '';
                if(in_array($rule,array('AND','OR','XOR')))
				{
                    $count  = $count -1;
                }
				else
				{
                    $rule   = 'AND';
                }
				
                for($i=0;$i<$count;$i++)
				{
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0]))
					{
                        $whereStr .= '('.$key.' '.$data.') '.$rule.' ';
                    }
					else
					{
                        $op = is_array($val[$i])?$this->comparison[strtolower($val[$i][0])]:'=';
                        $whereStr .= '('.$key.' '.$op.' '.$this->parseValue($data).') '.$rule.' ';
                    }
                }
                $whereStr = substr($whereStr,0,-4);
            }
        }
		else
		{
            //对字符串类型字段采用模糊匹配
            if(C('DB_LIKE_FIELDS') && preg_match('/('.C('DB_LIKE_FIELDS').')/i',$key))
			{
                $val = '%'.$val.'%';
                $whereStr .= $key.' LIKE '.$this->parseValue($val);
            }
			else
			{
                $whereStr .= $key.' = '.$this->parseValue($val);
            }
        }
		
        return $whereStr;
    }
	
    /**
     * limit分析
     * @access protected
     * @param mixed $lmit
     * @return string
     */
    protected function parseLimit($limit)
	{
        return !empty($limit) ? ' LIMIT '.$limit.' ' : '';
    }
	
    /**
     * join分析
     * @access protected
     * @param mixed $join
     * @return string
     */
    protected function parseJoin($join)
	{
        $joinStr = '';
        if(!empty($join))
		{
            if(is_array($join))
			{
                foreach ($join as $key=>$_join)
				{
                    if(false !== stripos($_join, 'JOIN'))
                        $joinStr .= ' '.$_join;
                    else
                        $joinStr .= ' LEFT JOIN ' .$_join;
                }
            }
			else
			{
                $joinStr .= ' LEFT JOIN ' .$join;
            }
        }
		
		//将__TABLE_NAME__这样的字符串替换成正规的表名,并且带上前缀和后缀
		$joinStr = preg_replace("/__([A-Z_-]+)__/esU", C("DB_PREFIX").".strtolower('$1')", $joinStr);
        return $joinStr;
    }
	
    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order)
	{
        if(is_array($order))
		{
            $array = array();
            foreach ($order as $key=>$val)
			{
                if(is_numeric($key))
				{
                    $array[] =  $this->parseKey($val);
                }
				else
				{
                    $array[] =  $this->parseKey($key).' '.$val;
                }
            }
            $order = implode(',', $array);
        }
		
        return !empty($order) ? ' ORDER BY '.$order : '';
    }
	
    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group)
	{
        return !empty($group) ? ' GROUP BY '.$group : '';
    }
	
    /**
     * having分析
     * @access protected
     * @param string $having
     * @return string
     */
    protected function parseHaving($having)
	{
        return !empty($having) ? ' HAVING '.$having : '';
    }
	
    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment)
	{
        return !empty($comment) ? ' /* '.$comment.' */':'';
    }
	
    /**
     * distinct分析
     * @access protected
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct)
	{
        return !empty($distinct) ? ' DISTINCT ' : '';
    }
	
    /**
     * union分析
     * @access protected
     * @param mixed $union
     * @return string
     */
    protected function parseUnion($union)
	{
        if(empty($union))
			return '';
        
		if(isset($union['_all']))
		{
            $str = 'UNION ALL ';
            unset($union['_all']);
        }
		else
		{
            $str = 'UNION ';
        }
		
        foreach ($union as $u)
		{
            $sql[] = $str.(is_array($u) ? $this->buildSelectSql($u) : $u);
        }
        return implode(' ',$sql);
    }
	
    /**
     * 设置锁机制
     * @access protected
     * @return string
     */
    protected function parseLock($lock = false)
	{
        if(!$lock)
			return '';
		
        if('ORACLE' == $this->dbType)
		{
            return ' FOR UPDATE NOWAIT ';
        }
		
        return ' FOR UPDATE ';
    }
	
    /**
     * 替换SQL语句中表达式
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function parseSql($sql, $options = array())
	{
        $sql = str_replace(
            array('%TABLE%','%DISTINCT%','%FIELD%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%','%UNION%','%COMMENT%'),
            array(
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
                $this->parseField(!empty($options['field'])?$options['field']:'*'),
                $this->parseJoin(!empty($options['join'])?$options['join']:''),
                $this->parseWhere(!empty($options['where'])?$options['where']:''),
                $this->parseGroup(!empty($options['group'])?$options['group']:''),
                $this->parseHaving(!empty($options['having'])?$options['having']:''),
                $this->parseOrder(!empty($options['order'])?$options['order']:''),
                $this->parseLimit(!empty($options['limit'])?$options['limit']:''),
                $this->parseUnion(!empty($options['union'])?$options['union']:''),
                $this->parseComment(!empty($options['comment'])?$options['comment']:'')
            ),$sql);
		
        return $sql;
    }
	
    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function buildSelectSql($options = array())
	{
        if(isset($options['page']))
		{
            // 根据页数计算limit
            if(strpos($options['page'], ','))
			{
                list($page, $listRows) =  explode(',', $options['page']);
            }
			else
			{
                $page = $options['page'];
            }
			
            $page = $page ? $page : 1;
            $listRows = isset($listRows) ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows*((int)$page-1);
            $options['limit'] = $offset.','.$listRows;
        }
		
		// 判断SQL创建的缓存是否存在
        if(C('DB_SQL_BUILD_CACHE'))
		{
            $key = md5(serialize($options));
            $value = S($key);
            if(false !== $value)	// 如果该SQL创建缓存是存在的则直接返回缓存而不用重新拼接
			{
                return $value;
            }
        }
		
        $sql = $this->parseSql($this->selectSql, $options);
        $sql .= $this->parseLock(isset($options['lock']) ? $options['lock'] : false);
        
		// 生成SQL创建缓存
		if(isset($key))
		{
            S($key, $sql, array('expire'=>0, 'length'=>C('DB_SQL_BUILD_LENGTH'), 'queue'=>C('DB_SQL_BUILD_QUEUE')));
        }
		
        return $sql;
    }
	
    /**
     * 插入单条记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insert($data, $options = array(), $replace = false)
	{
        $values = $fields = array();
        $this->model = $options['model'];
		
        foreach ($data as $key=>$val){
            $value = $this->parseValue($val);
            // 过滤非标量数据
			if(is_scalar($value))
			{
                $values[] = $value;
                $fields[] = $this->parseKey($key);
            }
        }
		
        $sql = ($replace ? 'REPLACE' : 'INSERT').' INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        $sql .= $this->parseLock(isset($options['lock']) ? $options['lock'] : false);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
		
        return $this->execute($sql);
    }
	
    /**
     * 通过Select方式插入记录
     * @access public
     * @param string $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @param array $option  查询数据参数
     * @return false | integer
     */
    public function selectInsert($fields, $table, $options = array())
	{
        $this->model = $options['model'];
        if(is_string($fields))
			$fields = explode(',', $fields);
        array_walk($fields, array($this, 'parseKey'));

        $sql = 'INSERT INTO '.$this->parseTable($table).' ('.implode(',', $fields).') ';
        $sql .= $this->buildSelectSql($options);
		
        return $this->execute($sql);
    }
	
    /**
     * 更新记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($data, $options)
	{
        $this->model = $options['model'];
		
        $sql = 'UPDATE '
            .$this->parseTable($options['table'])
            .$this->parseSet($data)
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseOrder(!empty($options['order'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseLock(isset($options['lock'])?$options['lock']:false)
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
		
        return $this->execute($sql);
    }
	
    /**
     * 删除记录
     * @access public
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($options = array())
	{
        $this->model = $options['model'];
		
        $sql = 'DELETE FROM '
            .$this->parseTable($options['table'])
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseOrder(!empty($options['order'])?$options['order']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseLock(isset($options['lock'])?$options['lock']:false)
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        
		return $this->execute($sql);
    }
	
    /**
     * 查找记录
     * @access public
     * @param array $options 表达式
     * @return mixed
     */
    public function select($options=array())
	{
        $this->model = $options['model'];
        $sql = $this->buildSelectSql($options);
        $cache = isset($options['cache']) ? $options['cache'] : false;
		
		// 查询缓存检测
        if($cache)
		{
            $key = is_string($cache['key']) ? $cache['key'] : md5($sql);
            $value = S($key,'',$cache);
            if(false !== $value)
			{
                return $value;
            }
        }
		
        $result = $this->query($sql);
        
		// 查询缓存写入
		if($cache && false !== $result )
		{
            S($key, $result, $cache);
        }
		
        return $result;
    }
	
    /**
     * 获取最近一次查询的sql语句 
     * @param string $model  模型名
     * @access public
     * @return string
     */
    public function getLastSql($model = '')
	{
        return $model ? $this->modelSql[$model] : $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
	{
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     * @access public
     * @return string
     */
    public function getError()
	{
        return $this->error;
    }

    /**
     * 设置当前操作模型
     * @access public
     * @param string $model  模型名
     * @return void
     */
    public function setModel($model)
	{
        $this->model = $model;
    }

	/**
     * 析构方法
     * @access public
     */
    public function __destruct()
	{
        // 释放查询
        if ($this->queryID)
		{
            $this->free();
        }
		
        // 关闭连接
        $this->close();
    }

    // 关闭数据库 - 由驱动类定义
    public function close(){}
}