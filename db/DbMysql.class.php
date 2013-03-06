<?php
class DbMysql extends Db
{
    /**
     * 构造函数 注册数据库连接信息
     * @access public
     * @param array $config 数据库连接信息数组
     */
    public function __construct($config='')
	{
        if (!extension_loaded('mysql'))
		{
            throw_exception(L('_NOT_SUPPERT_').':mysql');
        }
		
        if(!empty($config))
		{
            $this->config = $config;
            if(empty($this->config['params']))
			{
                $this->config['params'] = '';
            }
        }
    }
	
    /**
     * 连接数据库
     * @access public
     * @throws Execption
     */
    public function connect($config='', $linkNum=0, $force=false)
	{
        if (!isset($this->linkID[$linkNum]))
		{
			$config = empty($config) ? $this->config : $config;
			if (empty($config))
				throw_exception(L('_NO_DB_CONFIG_'));
			if (!is_array($config))
				throw_exception(L('_DB_CONFIG_NOT_ARRAY_'));
			
            // 处理不带端口号的socket连接情况
            $host = $config['hostname'].($config['hostport'] ? ":{$config['hostport']}" : '');
			
			// 连接数据库
            $pconnect = !empty($config['params']['persist']) ? $config['params']['persist'] : $this->pconnect;
            if($pconnect)
			{
                $this->linkID[$linkNum] = mysql_pconnect($host, $config['username'], $config['password'], 131072);	// 最后一个参数表示允许使用存储过程
            }else{
                $this->linkID[$linkNum] = mysql_connect($host, $config['username'], $config['password'], true, 131072);
            }
			
			// 如果连接或者选定数据库失败则抛出异常
            if (!$this->linkID[$linkNum] || (!empty($config['database']) && !mysql_select_db($config['database'], $this->linkID[$linkNum])))
			{
                throw_exception(mysql_error());
            }
			
            mysql_query("SET NAMES '".C('DB_CHARSET')."'", $this->linkID[$linkNum]);
			
            //设置 sql_model
            $dbVersion = mysql_get_server_info($this->linkID[$linkNum]);
            if($dbVersion >'5.0.1')
			{
                mysql_query("SET sql_mode=''", $this->linkID[$linkNum]);
            }
			
            // 标记连接成功
            $this->connected = true;
			
            // 如果数据库的部署方式不为分布式服务器的话在连接成功后将数据库连接信息销毁
            if(1 != C('DB_DEPLOY_TYPE'))
				unset($this->config);
        }
		
        return $this->linkID[$linkNum];
    }
	
    /**
     * 释放查询结果
     * @access public
     */
    public function free()
	{
        mysql_free_result($this->queryID);
        $this->queryID = null;
    }
	
    /**
     * 执行SELECT查询语句
     * @access public
     * @param string $str
     * @return mixed	成功返回结果集，失败返回false
     */
    public function query($str)
	{
		// 存储过程查询支持
        if (0 === stripos($str, 'call'))
		{
            $this->close();
        }
		
        $this->initConnect(false);
        if (!$this->_linkID)
			return false;
        
		// 记录当前的sql指令
		$this->queryStr = $str;
		
        // 释放前次的查询结果
        if ($this->queryID)
			$this->free();
        
		// 记录查询次数
		N('db_query',1);
		
        // 记录开始执行时间
        G('queryStartTime');
		
        $this->queryID = mysql_query($str, $this->_linkID);
        $this->debug();
		
        if (false === $this->queryID)
		{
            $this->error();
            return false;
        }
		else
		{
            $this->numRows = mysql_num_rows($this->queryID);
            return $this->getAll();
        }
    }
	
    /**
     * 执行更新（UPDATE，INSERT，DELETE）语句
     * @access public
     * @param string $str  sql语句
     * @return integer|false
     */
    public function execute($str)
	{
        $this->initConnect(true);
        if (!$this->_linkID)
			return false;
		
		// 记录当前的sql指令
        $this->queryStr = $str;
		
        //释放前次的查询结果
        if ($this->queryID)
			$this->free();
		
		// 记录更新操作的次数
        N('db_write',1);
		
        // 记录开始执行时间
        G('queryStartTime');
		
        $result = mysql_query($str, $this->_linkID);
        $this->debug();
		
        if (false === $result)
		{
            $this->error();
            return false;
        }
		else
		{
            $this->numRows = mysql_affected_rows($this->_linkID);
            $this->lastInsID = mysql_insert_id($this->_linkID);
            return $this->numRows;
        }
    }
	
    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
	{
        $this->initConnect(true);
        if (!$this->_linkID)
			return false;
        
		//数据rollback支持
        if ($this->transTimes == 0) {
            mysql_query('START TRANSACTION', $this->_linkID);
        }
        $this->transTimes++;
		
        return ;
    }
	
    /**
     * 用于非自动提交状态下面的事务提交
     * @access public
     * @return boolen
     */
    public function commit()
	{
        if ($this->transTimes > 0)
		{
            $result = mysql_query('COMMIT', $this->_linkID);
            $this->transTimes = 0;
            if(!$result)
			{
                $this->error();
                return false;
            }
        }
		
        return true;
    }
	
    /**
     * 事务回滚
     * @access public
     * @return boolen
     */
    public function rollback()
	{
        if ($this->transTimes > 0)
		{
            $result = mysql_query('ROLLBACK', $this->_linkID);
            $this->transTimes = 0;
            if(!$result)
			{
                $this->error();
                return false;
            }
        }
		
        return true;
    }
	
    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getAll() {
        //返回数据集
        $result = array();
        if($this->numRows >0)
		{
            while($row = mysql_fetch_assoc($this->queryID))
			{
                $result[]   =   $row;
            }
            mysql_data_seek($this->queryID,0);
        }
		
        return $result;
    }
	
    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName)
	{
        $result = $this->query('SHOW COLUMNS FROM '.$this->parseKey($tableName));
        $info = array();
        if($result)
		{
            foreach ($result as $key => $val)
			{
                $info[$val['Field']] = array(
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'notnull' => (bool) ($val['Null'] === ''), // not null is empty, null is yes
                    'default' => $val['Default'],
                    'primary' => (strtolower($val['Key']) == 'pri'),
                    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
                );
            }
        }
        return $info;
    }
	
    /**
     * 取得数据库的表信息
     * @access public
     * @return array
     */
    public function getTables($dbName='')
	{
        if(!empty($dbName))
		{
           $sql = 'SHOW TABLES FROM '.$dbName;
        }
		else
		{
           $sql = 'SHOW TABLES ';
        }
        $result = $this->query($sql);
		$info = array();
        foreach ($result as $key => $val)
		{
            $info[$key] = current($val);
        }
		
        return $info;
    }
	
    /**
     * 替换记录
     * @access public
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @return false | integer
     */
    public function replace($data, $options=array())
	{
        foreach ($data as $key=>$val)
		{
            $value = $this->parseValue($val);
            // 过滤非标量数据
			if(is_scalar($value))
			{
                $values[] = $value;
                $fields[] = $this->parseKey($key);
            }
        }
        $sql = 'REPLACE INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        
		return $this->execute($sql);
    }
	
    /**
     * 插入多条记录
     * @access public
     * @param mixed $datas 数据
     * @param array $options 参数表达式
     * @param boolean $replace 是否replace
     * @return false | integer
     */
    public function insertAll($datas, $options = array(), $replace = false)
	{
        if(!is_array($datas[0]))
			return false;
		
		// 列名
        $fields = array_keys($datas[0]);
        array_walk($fields, array($this, 'parseKey'));
		
		// 列值（可以为多行）
        $values  =  array();
        foreach ($datas as $data)
		{
            $value = array();
            foreach ($data as $key=>$val)
			{
                $val = $this->parseValue($val);
                // 过滤非标量数据
				if(is_scalar($val))
				{
                    $value[]   =  $val;
                }
            }
            $values[] = '('.implode(',', $value).')';
        }
		
        $sql = ($replace ? 'REPLACE' : 'INSERT').' INTO '.$this->parseTable($options['table']).' ('.implode(',', $fields).') VALUES '.implode(',',$values);
        return $this->execute($sql);
    }
	
    /**
     * 字段和表名处理添加`
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey(&$key)
	{
        $key = trim($key);
        if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key))
		{
           $key = '`'.$key.'`';
        }
        return $key;
    }
	
    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str)
	{
        if($this->_linkID)
		{
            return mysql_real_escape_string($str,$this->_linkID);
        }
		else
		{
            return mysql_escape_string($str);
        }
    }
	
    /**
     * 关闭数据库
     * @access public
     * @return void
     */
    public function close()
	{
        if ($this->_linkID)
		{
            mysql_close($this->_linkID);
        }
        $this->_linkID = null;
    }
	
    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @access public
     * @return string
     */
    public function error()
	{
        $this->error = mysql_error($this->_linkID);
        if('' != $this->queryStr)
		{
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        trace($this->error,'','ERR');
        
		return $this->error;
    }
} 