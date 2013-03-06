<?php
class Model
{
    // ����״̬
    const MODEL_INSERT  =   1;      //  ����ģ������
    const MODEL_UPDATE  =   2;      //  ����ģ������
    const MODEL_BOTH    =   3;      //  �����������ַ�ʽ
    const MUST_VALIDATE    =  1;	// ������֤
    const EXISTS_VALIDATE  =  0;	// �������ֶ�����֤
    const VALUE_VALIDATE   =  2;	// ��ֵ��Ϊ������֤

    private $_extModel = null;		// ��ǰʹ�õ���չģ��
	protected $name = '';			// ģ������
	
    protected $connection = '';		// ���ݿ���������
	protected $dbName = '';			// ���ݿ�����
    protected $tablePrefix = '';	// ���ݱ�ǰ׺
    protected $tableName = '';		// ���ݱ�������������ǰ׺��
    protected $trueTableName = '';	// ʵ�����ݱ�����������ǰ׺��
    protected $fields = array();	// �ֶ���Ϣ
    protected $pk = 'id';			// ��������
    protected $db = null;			// ��ǰ���ݿ��������
    protected $error = '';			// ���������Ϣ
	
    protected $data = array();	// ������Ϣ
	
    protected $autoCheckFields = true;	// �Ƿ��Զ�������ݱ��ֶ���Ϣ
    protected $patchValidate = false;	// �Ƿ���������֤
	
    // �����������б�
    protected $methods = array('table','order','alias','having','group','lock','distinct','auto','filter','validate');
    
	// ��ѯ���ʽ����
    protected $options = array();
    protected $_validate = array();	// �Զ���֤����
    protected $_auto = array();  	// �Զ���ɶ���
    protected $_map  = array();		// �ֶ�ӳ�䶨��
    protected $_scope = array();	// ������Χ����


    /**
     * ���캯��
     * ȡ��DB���ʵ������ �ֶμ��
     * @access public
     * @param string $name ģ������
     * @param string $tablePrefix ��ǰ׺
     * @param mixed $connection ���ݿ�������Ϣ
     */
    public function __construct($name = '', $tablePrefix = '', $connection = '')
	{
        // ģ�ͳ�ʼ��
        $this->_initialize();
		
        // ��ȡģ������
        if (!empty($name))
		{
			// ֧�� ���ݿ���.ģ������ ����
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
		
        // ���ñ�ǰ׺��ǰ׺ΪNull��ʾû��ǰ׺��
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

        // ��ȡ���ݿ�������󲢴洢��$this->db��
        $this->db(0, empty($this->connection) ? $connection : $this->connection);
    }
    // �ص����� ��ʼ��ģ��
    protected function _initialize() {}
	
    /**
     * �л���ǰ�����ݿ�����
	 * ע����ǰ�����ݿ��������洢��$this->db���л����½���һ�����ݿ����ӣ�Ȼ���µ����ݿ��������洢��$this->db
     * @access public
     * @param integer $linkNum  �������
     * @param mixed $config  	���ݿ���������
     * @param array $params  	ģ�Ͳ���
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
            // ����һ���µ�ʵ����֧�ֶ�ȡ���ò�����
            if(!empty($config) && is_string($config) && false === strpos($config, '/'))
			{
                $config = C($config);
            }
            $_db[$linkNum] = Db::getInstance($config);
        }
		elseif (NULL === $config)
		{
            $_db[$linkNum]->close(); // �ر����ݿ�����
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
		
        // ��¼����������Ϣ
        $_linkNum[$linkNum] = $config;
		
        // �л����ݿ�����
        $this->db = $_db[$linkNum];
        $this->_after_db();
		
        // ����ֶ���Ϣ�Ƿ��ѻ�ȡ
        if (!empty($this->name) && $this->autoCheckFields)
			$this->_checkTableInfo();
        
		return $this;
    }
    // ���ݿ��л���ص�����
    protected function _after_db() {}
	
    /**
     * ������ݱ���Ϣ�Ƿ��Ѿ���ȡ������ֶ���Ϣ��ûע�ᵽ$this->fields������л�ȡ��ע�ᣩ
     * @access protected
     * @return void
     */
    protected function _checkTableInfo()
	{
        if (empty($this->fields))
		{
            // ������ݱ��ֶ�û�ж������Զ���ȡ
            if (C('DB_FIELDS_CACHE'))
			{
                $db = $this->dbName ? $this->dbName : C('DB_NAME');
                $fields = F('_fields/'.strtolower($db.'.'.$this->name));	// ȡ��ָ�����ֶλ�����Ϣ
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
			
            // ���»�ȡ�ֶ���Ϣ�����棨���C('DB_FIELDS_CACHE')Ϊfalse���򲻻���仺�棩
            $this->flush();
        }
    }
	
    /**
     * ��ȡ�ֶ���Ϣ������
     * @access public
     * @return void
     */
    public function flush()
	{
        // ���治�������ѯ���ݱ���Ϣ
        $this->db->setModel($this->name);
        $fields = $this->db->getFields($this->getTableName());
        
		// �޷���ȡ�ֶ���Ϣ
		if (!$fields)
		{
            return false;
        }
		
        $this->fields = array_keys($fields);
        $this->fields['_autoinc'] = false;
		
        foreach ($fields as $key=>$val)
		{
            // ��¼�ֶ�����
            $type[$key] = $val['type'];
			
			if ($val['primary'])
			{
                $this->fields['_pk'] = $key;
                if ($val['autoinc'])
					$this->fields['_autoinc'] = true;
            }
        }
        // ע���ֶ�������Ϣ
        $this->fields['_type'] = $type;
        
		if (C('DB_FIELD_VERISON'))
			$this->fields['_version'] = C('DB_FIELD_VERISON');

        // 2008-3-7 ���ӻ��濪�ؿ���
        if (C('DB_FIELDS_CACHE'))
		{
            // ���û������ݱ���Ϣ
            $db = $this->dbName ? $this->dbName : C('DB_NAME');
            F('_fields/'.strtolower($db.'.'.$this->name), $this->fields);
        }
    }
	
    /**
     * �õ������ı�����������ǰ׺�����dbName��Ϊ�գ��򻹰���dbName����ʽΪdbName.tableName��
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
     * �õ���ǰ��ģ������
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
     * ���õ�ǰģ�͵�����ֵ
     * @access public
     * @param string $name ����
     * @param mixed $value ֵ
     * @return Model
     */
    public function setProperty($name, $value)
	{
        if(property_exists($this, $name))
            $this->$name = $value;
		
        return $this;
    }
	
    /**
     * ��̬�л���չģ��
     * @access public
     * @param string $type ģ����������
     * @param mixed $vars Ҫ������չģ�͵����Ա���
     * @return Model
     */
    public function switchModel($type, $vars = array())
	{
        $class = ucwords(strtolower($type)).'Model';
        if (!class_exists($class))
            throw_exception($class.L('_MODEL_NOT_EXIST_'));
        
		// ʵ������չģ��
        $this->_extModel = new $class($this->name);
        if (!empty($vars))
		{
            // ���뵱ǰģ�͵����Ե���չģ��
            foreach ($vars as $var)
                $this->_extModel->setProperty($var, $this->$var);
        }
		
        return $this->_extModel;
    }
	
    /**
     * �������ݶ���ֵ
     * @access public
     * @param mixed $data ����
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
            parse_str($data,$data);
        }
		elseif (!is_array($data))
		{
            throw_exception(L('_DATA_TYPE_INVALID_'));
        }
		
        $this->data = $data;
        return $this;
    }
	
    /**
     * �������ݶ����ֵ
     * @access public
     * @param string $name ����
     * @param mixed $value ֵ
     * @return void
     */
    public function __set($name,$value)
	{
        // ע��������Ϣ
        $this->data[$name] = $value;
    }

    /**
     * ��ȡ���ݶ����ֵ
     * @access public
     * @param string $name ����
     * @return mixed
     */
    public function __get($name)
	{
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
	
    /**
     * ������ݶ����ֵ
     * @access public
     * @param string $name ����
     * @return boolean
     */
    public function __isset($name)
	{
        return isset($this->data[$name]);
    }
	
    /**
     * �������ݶ����ֵ
     * @access public
     * @param string $name ����
     * @return void
     */
    public function __unset($name)
	{
        unset($this->data[$name]);
    }
	
    /**
     * ����__call����ʵ��һЩ�����Model����
     * @access public
     * @param string $method ��������
     * @param array $args ���ò���
     * @return mixed
     */
    public function __call($method, $args)
	{
        if (in_array(strtolower($method), $this->methods, true))
		{
            // ���������ʵ��
            $this->options[strtolower($method)] = $args[0];
            return $this;
        }
		elseif (in_array(strtolower($method), array('count','sum','min','max','avg'), true))
		{
            // ͳ�Ʋ�ѯ��ʵ��
            $field = isset($args[0]) ? $args[0] : '*';
            return $this->getField(strtoupper($method).'('.$field.') AS tp_'.$method);
        }
		elseif (strtolower(substr($method, 0, 5)) == 'getby')
		{
            // ����ĳ���ֶλ�ȡ��¼
            $field = parse_name(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        }
		elseif (strtolower(substr($method, 0, 10)) == 'getfieldby')
		{
            // ����ĳ���ֶλ�ȡ��¼��ĳ��ֵ
            $name = parse_name(substr($method, 10));
            $where[$name] = $args[0];
            return $this->where($where)->getField($args[1]);
        }
		elseif (isset($this->_scope[$method]))
		{	// ������Χ�ĵ�������֧��
            return $this->scope($method, $args[0]);
        }
		else
		{
            throw_exception(__CLASS__.':'.$method.L('_METHOD_NOT_EXIST_'));
            return;
        }
    }
    // �ص����� ��ʼ��ģ��
    protected function _initialize() {}
	
    /**
     * SELECT��ѯ
     * @access public
     * @param array $options ���ʽ����
     * @return mixed
     */
    public function select($options = array())
	{
        if (is_string($options) || is_numeric($options))
		{
            // ��ȡ������Ϣ��������ӵ����ʽ��
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
		// ֻ����SQL�������Ӳ�ѯ��
		elseif (false === $options)
		{
            $options = array();
            // ���ʽ����
            $options = $this->_parseOptions($options);
            return '( '.$this->db->buildSelectSql($options).' )';
        }
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// ִ�в�ѯ
		$resultSet = $this->db->select($options);
        if(false === $resultSet)
		{
            return false;
        }
		
		// ��ѯ���Ϊ��
        if(empty($resultSet))
		{
            return null;
        }
        $this->_after_select($resultSet,$options);
        return $resultSet;
    }
    // ��ѯ�ɹ���Ļص�����
    protected function _after_select(&$resultSet,$options) {}
	
    /**
     * ��ȡ��������
     * @access public
     * @param mixed $options ���ʽ����
     * @return mixed
     */
    public function find($options = array())
	{
        if (is_numeric($options) || is_string($options))
		{
			// ��ȡ������Ϣ��������ӵ����ʽ��
            $where[$this->getPk()] = $options;
            $options = array();
            $options['where'] = $where;
        }
		
        // ���ǲ���һ����¼
        $options['limit'] = 1;
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// ִ�в�ѯ
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
    // ��ѯ�ɹ��Ļص�����
    protected function _after_find(&$result,$options) {}
	
    /**
     * ��ȡһ����¼��ĳ���ֶ�ֵ
     * @access public
     * @param string $field  �ֶ���
     * @param string $spea  �ֶ����ݼ������ NULL��������
     * @return mixed
     */
    public function getField($field, $sepa = null)
	{
        $options['field'] = $field;
        $options = $this->_parseOptions($options);
        $field = trim($field);
		
		// ���ֶ�
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
		// ���ֶ�
		else
		{
            // ���÷��ظ�������sepaָ��Ϊtrue��ʱ�� �����������ݣ�
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
     * ������������
     * @access public
     * @param mixed $data ����
     * @param array $options ���ʽ
     * @param boolean $replace �Ƿ�replace
     * @return mixed
     */
    public function add($data = '', $options = array(), $replace = false)
	{
        if (empty($data))
		{
            // û�д������ݣ���ȡ��ǰ���ݶ����ֵ
            if (!empty($this->data))
			{
                $data = $this->data;
                $this->data = array();	// ��������
            }
			else
			{
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// ���ݹ���
        $data = $this->_facade($data);
        
		if (false === $this->_before_insert($data,$options))
		{
            return false;
        }
		
        // д�����ݵ����ݿ�
        $result = $this->db->insert($data, $options, $replace);
        if (false !== $result)
		{
            $insertId = $this->getLastInsID();
            if ($insertId)
			{
                // �����������ز���ID
                $data[$this->getPk()] = $insertId;
                $this->_after_insert($data, $options);
                return $insertId;
            }
            $this->_after_insert($data,$options);
        }
        return $result;
    }
    // ��������ǰ�Ļص�����
    protected function _before_insert(&$data,$options) {}
    // ����ɹ���Ļص�����
    protected function _after_insert($data,$options) {}
	
    /**
     * ������������
     * @access public
     * @param mixed $dataList 	����
     * @param array $options 	���ʽ
     * @param boolean $replace 	�Ƿ�replace
     * @return mixed
     */
    public function addAll($dataList, $options = array(), $replace = false)
	{
        if (empty($dataList))
		{
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// ���ݹ���
        foreach ($dataList as $key=>$data)
		{
            $dataList[$key] = $this->_facade($data);
        }
		
        // д�����ݵ����ݿ�
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
     * ͨ��Select��ʽ��Ӽ�¼
     * @access public
     * @param string $fields Ҫ��������ݱ��ֶ���
     * @param string $table Ҫ��������ݱ���
     * @param array $options ���ʽ
     * @return boolean
     */
    public function selectAdd($fields = '', $table = '', $options = array())
	{
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// д�����ݵ����ݿ�
		$result = $this->db->selectInsert($fields ? $fields : $options['field'], $table ? $table : $this->getTableName(), $options);
        if(false === $result)
		{
            // ���ݿ�������ʧ��
            $this->error = L('_OPERATION_WRONG_');
            return false;
        }
		else
		{
            // ����ɹ�
            return $result;
        }
    }
	
    /**
     * ��������
     * @access public
     * @param mixed $data ����
     * @param array $options ���ʽ
     * @return boolean
     */
    public function save($data = '', $options = array())
	{
        if (empty($data))
		{
            // û�д������ݣ���ȡ��ǰ���ݶ����ֵ
            if (!empty($this->data))
			{
                $data = $this->data;
                $this->data = array();	// ��������
            }
			else
			{
                $this->error = L('_DATA_TYPE_INVALID_');
                return false;
            }
        }
		
        // ���ݹ���
        $data = $this->_facade($data);
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
		
        if (false === $this->_before_update($data,$options))
		{
            return false;
        }
		
        if (!isset($options['where']))
		{
            // ��������������� ���Զ���Ϊ��������
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
                // ���û���κθ���������ִ��
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
    // ��������ǰ�Ļص�����
    protected function _before_update(&$data,$options) {}
    // ���³ɹ���Ļص�����
    protected function _after_update($data,$options) {}
	
    /**
     * ����ĳ���ֶε�ֵ
     * ֧��ʹ�����ݿ��ֶκͷ���
     * @access public
     * @param string|array $field  �ֶ���
     * @param string $value  �ֶ�ֵ
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
     * ����ĳ���ֶε�ֵ
     * @access public
     * @param string $field  �ֶ���
     * @param integer $step  ����ֵ
     * @return boolean
     */
    public function setInc($field, $step=1)
	{
        return $this->setField($field, array('exp', $field.'+'.$step));
    }
	
    /**
     * ����ĳ���ֶε�ֵ
     * @access public
     * @param string $field  �ֶ���
     * @param integer $step  ����ֵ
     * @return boolean
     */
    public function setDec($field, $step = 1)
	{
        return $this->setField($field, array('exp', $field.'-'.$step));
    }
	
    /**
     * ɾ������
     * @access public
     * @param mixed $options ���ʽ
     * @return mixed
     */
    public function delete($options = array())
	{
        if (empty($options) && empty($this->options['where']))
		{
            // ���ɾ������Ϊ�գ���ɾ����ǰ���ݶ����е���������Ӧ�ļ�¼��
            if (!empty($this->data) && isset($this->data[$this->getPk()]))
                return $this->delete($this->data[$this->getPk()]);
            else
                return false;
        }
		
        if (is_numeric($options) || is_string($options))
		{
            // ��������Ϣ�ӵ����ʽ��
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
		
        // ���ʽ����
        $options = $this->_parseOptions($options);
        
		// ɾ����¼
		$result = $this->db->delete($options);
        if (false !== $result)
		{
            $data = array();
            if (isset($pkValue))
				$data[$pk] = $pkValue;
            $this->_after_delete($data,$options);
        }
        // ����ɾ����¼����
        return $result;
    }
    // ɾ���ɹ���Ļص�����
    protected function _after_delete($data,$options) {}
	
    /**
     * SQL��ѯ
     * @access public
     * @param string $sql  SQLָ��
     * @param mixed $parse  �Ƿ���Ҫ����SQL
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
     * ִ��SQL���
     * @access public
     * @param string $sql  SQLָ��
     * @param mixed $parse  �Ƿ���Ҫ����SQL
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
     * ����SQL���
     * @access public
     * @param string $sql  SQLָ��
     * @param boolean $parse  �Ƿ���Ҫ����SQL
     * @return string
     */
    protected function parseSql($sql, $parse)
	{
        if (true === $parse)
		{	// ���ʽ����
            $options = $this->_parseOptions();
            $sql = $this->db->parseSql($sql, $options);
        }
		elseif (is_array($parse))
		{
			// SQLԤ����
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
     * ��������
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
     * �ύ����
     * @access public
     * @return boolean
     */
    public function commit()
	{
        return $this->db->commit();
    }

    /**
     * ����ع�
     * @access public
     * @return boolean
     */
    public function rollback()
	{
        return $this->db->rollback();
    }
	
    /**
     * ����ģ�͵Ĵ�����Ϣ
     * @access public
     * @return string
     */
    public function getError()
	{
        return $this->error;
    }

    /**
     * �������ݿ�Ĵ�����Ϣ
     * @access public
     * @return string
     */
    public function getDbError()
	{
        return $this->db->getError();
    }

    /**
     * �����������ID
     * @access public
     * @return string
     */
    public function getLastInsID()
	{
        return $this->db->getLastInsID();
    }

    /**
     * �������ִ�е�sql���
     * @access public
     * @return string
     */
    public function getLastSql()
	{
        return $this->db->getLastSql($this->name);
    }
    // ����getLastSql�Ƚϳ��� ����_sql ����
    public function _sql(){
        return $this->getLastSql();
    }

	/**
     * ��ȡ��������
     * @access public
     * @return string
     */
    public function getPk()
	{
        return isset($this->fields['_pk']) ? $this->fields['_pk'] : $this->pk;
    }
	
    /**
     * ��ȡ���ݱ��ֶ���Ϣ�������ֶ����ͣ�
     * @access public
     * @return array
     */
    public function getDbFields()
	{
		// ��ָ̬������
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
	
//{{{ START return $this	--	������ʱʹ�� 
    /**
     * ��ѯSQL��װ join
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
     * ��ѯSQL��װ union
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
		
        // ת��union���ʽ
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
     * ��ѯ����
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
     * ָ����ѯ�ֶ� ֧���ֶ��ų�
     * @access public
     * @param mixed $field
     * @param boolean $except �Ƿ��ų�
     * @return Model
     */
    public function field($field, $except=false)
	{
		// ��ȡȫ���ֶ�
        if(true === $field)
		{
            $fields = $this->getDbFields();
            $field = $fields ? $fields : '*';
        }
		// �ֶ��ų�
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
     * ����������Χ
     * @access public
     * @param mixed $scope ������Χ���� ֧�ֶ�� ��ֱ�Ӷ���
     * @param array $args ����
     * @return Model
     */
    public function scope($scope = '', $args = NULL)
	{
		// ʹ��Ĭ�ϵ�������Χ
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
		// ֧�ֶ��������Χ���� �ö��ŷָ�
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
		// ֱ�Ӵ���������Χ����
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
     * ָ����ѯ���� ֧�ְ�ȫ����
     * @access public
     * @param mixed $where �������ʽ
     * @param mixed $parse Ԥ�������
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
     * ָ����ѯ����
     * @access public
     * @param mixed $offset ��ʼλ��
     * @param mixed $length ��ѯ����
     * @return Model
     */
    public function limit($offset,$length=null){
        $this->options['limit'] =   is_null($length)?$offset:$offset.','.$length;
        return $this;
    }

    /**
     * ָ����ҳ
     * @access public
     * @param mixed $page ҳ��
     * @param mixed $listRows ÿҳ����
     * @return Model
     */
    public function page($page,$listRows=null){
        $this->options['page'] =   is_null($listRows)?$page:$page.','.$listRows;
        return $this;
    }

    /**
     * ��ѯע��
     * @access public
     * @param string $comment ע��
     * @return Model
     */
    public function comment($comment){
        $this->options['comment'] =   $comment;
        return $this;
    }
//{{{ END return $this	--	������ʱʹ�� 
	
    /**
     * �Խ�Ҫ���浽���ݿ�����ݽ��д���
     * @access protected
     * @param mixed $data Ҫ����������
     * @return boolean
     */
     protected function _facade($data)
	 {
        // ���������ֶ�
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
                    // �����ֶ����Ͷ��������ͽ��м��
                    $this->_parseType($data,$key);
                }
            }
        }
		
        // ��ȫ����
        if (!empty($this->options['filter']))
		{
            $data = array_map($this->options['filter'], $data);
            unset($this->options['filter']);
        }
        $this->_before_write($data);
		
		return $data;
     }
    // д������ǰ�Ļص����� ���������͸���
    protected function _before_write(&$data) {}
	
    /**
     * �������ͼ��
     * @access protected
     * @param mixed $data ����
     * @param string $key �ֶ���
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
     * �������ʽ���Ա��ʽ��Ϣ���й��ˣ�
     * @access proteced
     * @param array $options ���ʽ����
     * @return array
     */
    protected function _parseOptions($options = array())
	{
        if (is_array($options))
            $options =  array_merge($this->options, $options);
		
        // ��ձ���sql���ʽ��װ��Ϣ������Ӱ���´���װ
        $this->options = array();
		
        if (!isset($options['table']))
		{
            // �Զ���ȡ�������ֶ���Ϣ
            $options['table'] = $this->getTableName();
            $fields = $this->fields;
        }
		else
		{
            // ���û��ָ�����ݱ����$this->options['table']��ȡ�ã�Ȼ���ٽ��������ֶ���Ϣ
            $fields = $this->getDbFields();
        }

        if (!empty($options['alias']))
		{
            $options['table'] .= ' '.$options['alias'];
        }
		
        // ��¼������ģ������
        $options['model'] = $this->name;

        // �ֶ�������֤
        if (isset($options['where']) && is_array($options['where']) && !empty($fields))
		{
            // �������ѯ���������ֶ����ͼ��
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

        // ���ʽ����
        $this->_options_filter($options);
		
        return $options;
    }
    // ���ʽ���˻ص�����
    protected function _options_filter(&$options) {}
	
    /**
     * �������ݶ��� �������浽���ݿ�
     * @access public
     * @param mixed $data ��������
     * @param string $type ״̬
     * @return mixed
     */
     public function create($data = '', $type = '')
	 {
        // ���û�д�ֵĬ��ȡPOST����
        if (empty($data))
		{
            $data = $_POST;
        }
		elseif (is_object($data))
		{
            $data = get_object_vars($data);
        }
		
        // ��֤����
        if (empty($data) || !is_array($data))
		{
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }

        // ����ֶ�ӳ��
        $data = $this->parseFieldsMap($data, 0);

        // ״̬
        $type = $type ? $type : (!empty($data[$this->getPk()]) ? self::MODEL_UPDATE : self::MODEL_INSERT);

        // ����ύ�ֶεĺϷ���
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
			
            // �ж�������֤�ֶ�
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

        // �����Զ���֤
        if (!$this->autoValidation($data,$type))
			return false;

        // ��������֤
        if (C('TOKEN_ON') && !$this->autoCheckToken($data))
		{
            $this->error = L('_TOKEN_ERROR_');
            return false;
        }

        // ����������ֶμ�� ����˷Ƿ��ֶ�����
        if ($this->autoCheckFields)
		{
            $fields = $this->getDbFields();
            foreach ($data as $key=>$val)
			{
                if (!in_array($key, $fields))
				{
                    unset($data[$key]);
                }
				elseif(MAGIC_QUOTES_GPC && is_string($val))	// link: MAGIC_QUOTES_GPC������Common\runtime.php
				{
                    $data[$key] =   stripslashes($val);
                }
            }
        }

        // ������ɶ����ݽ����Զ�����
        $this->autoOperation($data, $type);
		
        // ��ֵ��ǰ���ݶ���
        $this->data = $data;
        
		// ���ش����������Թ���������
        return $data;
    }
	
    /**
     * �����ֶ�ӳ��
     * @access public
     * @param array $data 	��ǰ����
     * @param integer $type ���� 0 д�� 1 ��ȡ
     * @return array
     */
    public function parseFieldsMap($data, $type = 1)
	{
        // ����ֶ�ӳ��
        if (!empty($this->_map))
		{
            foreach ($this->_map as $key=>$val)
			{
				// ��ȡ
                if ($type == 1)
				{
                    if (isset($data[$val]))
					{
                        $data[$key] = $data[$val];
                        unset($data[$val]);
                    }
                }
				// д��
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
	
    // �Զ���������֤
    // TODO  ajax��ˢ�¶���ύ�ݲ�������
    public function autoCheckToken($data) {
        if(C('TOKEN_ON')){
            $name   = C('TOKEN_NAME');
            if(!isset($data[$name]) || !isset($_SESSION[$name])) { // ����������Ч
                return false;
            }

            // ������֤
            list($key,$value)  =  explode('_',$data[$name]);
            if($value && $_SESSION[$name][$key] === $value) { // ��ֹ�ظ��ύ
                unset($_SESSION[$name][$key]); // ��֤�������session
                return true;
            }
            // ����TOKEN����
            if(C('TOKEN_RESET')) unset($_SESSION[$name][$key]);
            return false;
        }
        return true;
    }
	
    /**
     * �Զ�����֤
     * @access protected
     * @param array $data ��������
     * @param string $type ��������
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
		
        // ��������������Զ���֤�����������֤
        if (isset($_validate))
		{
            if ($this->patchValidate)
			{
                $this->error = array();		// ������֤������Ϣ
            }
			
            foreach ($_validate as $key=>$val)
			{
                // ��֤���Ӷ����ʽ
                // array(field,rule,message,condition,type,when,params)
                // �ж��Ƿ���Ҫִ����֤
                if (empty($val[5]) || $val[5]== self::MODEL_BOTH || $val[5]== $type)
				{
                    if (0 == strpos($val[2], '{%') && strpos($val[2] ,'}'))
                        $val[2] = L(substr($val[2],2,-1));	// ֧����ʾ��Ϣ�Ķ����� ʹ�� {%���Զ���} ��ʽ
                    $val[3] = isset($val[3])?$val[3]:self::EXISTS_VALIDATE;
                    $val[4] = isset($val[4])?$val[4]:'regex';
					
                    // �ж���֤����
                    switch($val[3])
					{
                        case self::MUST_VALIDATE:   // ������֤ ���ܱ��Ƿ������ø��ֶ�
                            if(false === $this->_validationField($data,$val)) 
                                return false;
                            break;
                        case self::VALUE_VALIDATE:  // ֵ��Ϊ�յ�ʱ�����֤
                            if('' != trim($data[$val[0]]))
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                            break;
                        default:    				// Ĭ�ϱ����ڸ��ֶξ���֤
                            if(isset($data[$val[0]]))
                                if(false === $this->_validationField($data,$val)) 
                                    return false;
                    }
                }
            }
			
            // ������֤��ʱ����󷵻ش���
            if(!empty($this->error)) return false;
        }
        return true;
    }
	
    /**
     * �Զ�������
     * @access public
     * @param array $data ��������
     * @param string $type ��������
     * @return mixed
     */
    private function autoOperation(&$data,$type) {
        if(!empty($this->options['auto'])) {
            $_auto   =   $this->options['auto'];
            unset($this->options['auto']);
        }elseif(!empty($this->_auto)){
            $_auto   =   $this->_auto;
        }
        // �Զ����
        if(isset($_auto)) {
            foreach ($_auto as $auto){
                // ������Ӷ����ʽ
                // array('field','�������','�������','���ӹ���',[�������])
                if(empty($auto[2])) $auto[2] = self::MODEL_INSERT; // Ĭ��Ϊ������ʱ���Զ����
                if( $type == $auto[2] || $auto[2] == self::MODEL_BOTH) {
                    switch(trim($auto[3])) {
                        case 'function':    //  ʹ�ú���������� �ֶε�ֵ��Ϊ����
                        case 'callback': // ʹ�ûص�����
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
                        case 'field':    // �������ֶε�ֵ�������
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'ignore': // Ϊ�պ���
                            if(''===$data[$auto[0]])
                                unset($data[$auto[0]]);
                            break;
                        case 'string':
                        default: // Ĭ����Ϊ�ַ������
                            $data[$auto[0]] = $auto[1];
                    }
                    if(false === $data[$auto[0]] )   unset($data[$auto[0]]);
                }
            }
        }
        return $data;
    }
	
    /**
     * ʹ��������֤����
     * @access public
     * @param string $value  Ҫ��֤������
     * @param string $rule ��֤����
     * @return boolean
     */
    public function regex($value,$rule) {
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
        // ����Ƿ������õ�������ʽ
        if(isset($validate[strtolower($rule)]))
            $rule       =   $validate[strtolower($rule)];
        return preg_match($rule,$value)===1;
    }
	
    /**
     * ��֤���ֶ� ֧��������֤
     * ���������֤���ش����������Ϣ
     * @access protected
     * @param array $data ��������
     * @param array $val ��֤����
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
     * ������֤������֤�ֶ�
     * @access protected
     * @param array $data ��������
     * @param array $val ��֤����
     * @return boolean
     */
    protected function _validationFieldItem($data,$val) {
        switch(strtolower(trim($val[4]))) {
            case 'function':// ʹ�ú���������֤
            case 'callback':// ���÷���������֤
                $args = isset($val[6])?(array)$val[6]:array();
                if(is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                if(is_array($val[0])){
                    // ֧�ֶ���ֶ���֤
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
            case 'confirm': // ��֤�����ֶ��Ƿ���ͬ
                return $data[$val[0]] == $data[$val[1]];
            case 'unique': // ��֤ĳ��ֵ�Ƿ�Ψһ
                if(is_string($val[0]) && strpos($val[0],','))
                    $val[0]  =  explode(',',$val[0]);
                $map = array();
                if(is_array($val[0])) {
                    // ֧�ֶ���ֶ���֤
                    foreach ($val[0] as $field)
                        $map[$field]   =  $data[$field];
                }else{
                    $map[$val[0]] = $data[$val[0]];
                }
                if(!empty($data[$this->getPk()])) { // ���Ʊ༭��ʱ����֤Ψһ
                    $map[$this->getPk()] = array('neq',$data[$this->getPk()]);
                }
                if($this->where($map)->find())   return false;
                return true;
            default:  // ��鸽�ӹ���
                return $this->check($data[$val[0]],$val[1],$val[4]);
        }
    }
	
    /**
     * ��֤���� ֧�� in between equal length regex expire ip_allow ip_deny
     * @access public
     * @param string $value ��֤����
     * @param mixed $rule ��֤���ʽ
     * @param string $type ��֤��ʽ Ĭ��Ϊ������֤
     * @return boolean
     */
    public function check($value,$rule,$type='regex'){
        $type   =   strtolower(trim($type));
        switch($type) {
            case 'in': // ��֤�Ƿ���ĳ��ָ����Χ֮�� ���ŷָ��ַ�����������
            case 'notin':
                $range   = is_array($rule)? $rule : explode(',',$rule);
                return $type == 'in' ? in_array($value ,$range) : !in_array($value ,$range);
            case 'between': // ��֤�Ƿ���ĳ����Χ
            case 'notbetween': // ��֤�Ƿ���ĳ����Χ            
                if (is_array($rule)){
                    $min    =    $rule[0];
                    $max    =    $rule[1];
                }else{
                    list($min,$max)   =  explode(',',$rule);
                }
                return $type == 'between' ? $value>=$min && $value<=$max : $value<$min || $value>$max;
            case 'equal': // ��֤�Ƿ����ĳ��ֵ
            case 'notequal': // ��֤�Ƿ����ĳ��ֵ            
                return $type == 'equal' ? $value == $rule : $value != $rule;
            case 'length': // ��֤����
                $length  =  mb_strlen($value,'utf-8'); // ��ǰ���ݳ���
                if(strpos($rule,',')) { // ��������
                    list($min,$max)   =  explode(',',$rule);
                    return $length >= $min && $length <= $max;
                }else{// ָ������
                    return $length == $rule;
                }
            case 'expire':
                list($start,$end)   =  explode(',',$rule);
                if(!is_numeric($start)) $start   =  strtotime($start);
                if(!is_numeric($end)) $end   =  strtotime($end);
                return NOW_TIME >= $start && NOW_TIME <= $end;
            case 'ip_allow': // IP ���������֤
                return in_array(get_client_ip(),explode(',',$rule));
            case 'ip_deny': // IP ������ֹ��֤
                return !in_array(get_client_ip(),explode(',',$rule));
            case 'regex':
            default:    // Ĭ��ʹ��������֤ ����ʹ����֤���ж������֤����
                // ��鸽�ӹ���
                return $this->regex($value,$rule);
        }
    }
}