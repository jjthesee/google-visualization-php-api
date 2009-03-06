<?php

class GvizDataTableException extends Exception
{
    
}

class GvizDataTable
{
    protected $_version = 0.5;
    protected $_responseHandler = 'google.visualization.Query.setResponse';
    protected $_reqId = 0;
    protected $_status = 'ok';
    protected $_sig;
    protected $_errors = array();
    protected $_warnings = array();
    protected $_columns = array();
    protected $_rows = array();
    
    public function __construct($tqx = '', $responseHandler = '')
    {
        if(!empty($tqx))
        {
            $pairs = explode(';', $tqx);
            foreach($pairs as $pair)
            {
                list($key, $value) = explode(':', $pair);
                $key = '_' . $key;
                $this->$key = $value;
            }
        }
        if(!empty($responseHandler))
        {
            $this->_responseHandler = $responseHandler;
        }
        if(empty($this->_sig))
        {
            $this->_sig = md5(uniqid(rand(), true));
        }
    }
    
    public function addColumn($id, $label = '', $type = '', $pattern = '')
    {
        $this->_columns[$id] = new GvizDataTableColumn($id, $label, $type, $pattern);
    }
    
    public function getColumnType($id)
    {
        if(isset($this->_columns[$id]) && is_a($this->_columns[$id], 'GvizDataTableColumn'))
            return $this->_columns[$id]->getType();
        else
            throw new GvizDataTableException("Column id, $id, not found");
    }
    
    public function addWarning($reason, $message = '', $detailed_message = '')
    {
        $this->_warnings[] = new GvizDataTableWarning($reason, $message, $detailed_message);
        $this->_status = 'warning';
    }
    
    public function addError($reason, $message = '', $detailed_message = '')
    {
        $this->_errors[] = new GvizDataTableError($reason, $message, $detailed_message);
        $this->_status = 'error';
    }
    
    public function addRow(GvizDataTableRow $row)
    {
        $this->_rows[] = $row;
    }

    protected function _arrayToJson($arrayName, $jsonName = '')
    {
        $arrayName = ltrim($arrayName, '_');
        if(empty($jsonName))
        {
            $jsonName = $arrayName;
        }
        $arrayName = '_' . $arrayName;
        if(!isset($this->$arrayName) || !is_array($this->$arrayName))
        {
            throw new GvizDataTableException("Array name, $arrayName, is not set or is not an array.");
        }
        
        $jsonItems = array();
        foreach($this->$arrayName as $item)
        {
            $jsonItems[] = $item->toJson();
        }
        $itemsInner = implode(',', $jsonItems);
        
        $json = sprintf('%s:[%s]',$jsonName, $itemsInner);
        
        return $json;
    }
    
    public function toJson()
    {
        $table = array();
        $table[] = $this->_arrayToJson('columns', 'cols');
        $table[] = $this->_arrayToJson('rows');
        $tableInner = implode(',', $table);
        $json = sprintf('table: {%s}', $tableInner);
        return $json;
    }
    
    public function toJsonResponse()
    {
        $response = array();
        $response[] = "version: '{$this->_version}'";
        $response[] = "reqId: '{$this->_reqId}'";
        $response[] = "sig: '{$this->_sig}'";
        $response[] = "status: '{$this->_status}'";
        switch($this->_status)
        {
            case "error":
                $response[] = $this->_arrayToJson('errors');
                break;
            
            case "warning":
                $response[] = $this->_arrayToJson('warnings');
            case "ok":
                $response[] = $this->toJson();
                break;
        }
        $responseInner = implode(',', $response);
        $json = sprintf('%s({%s});', $this->_responseHandler, $responseInner);
        
        return $json;
    }
}

class GvizDataTableColumn
{
    protected $_id;
    protected $_label = '';
    protected $_type = 'string';
    protected $_pattern = '';
    protected $_validTypes = array(
        'boolean',
        'number',
        'string',
        'date',
        'datetime',
        'timeofday',
    );
    
    public function __construct($id, $label = '', $type = 'string', $pattern = '')
    {
        $this->_id = $id;
        
        if(empty($label))
        {
            $this->_label = $id;
        }
        else
        {
            $this->_label = $label;
        }
        
        if(in_array($type, $this->_validTypes))
        {
            $this->_type = $type;
        }
        else
        {
            throw new GvizDataTableException("Type, $type, not supported by this api.");
        }
        
        $this->_pattern = $pattern;
    }
    
    public function getType()
    {
        return $this->_type;
    }
    
    public function toJson()
    {
        $column = array();
        $column[] = "id: '{$this->_id}'";
        $column[] = "label: '{$this->_label}'";
        $column[] = "type: '{$this->_type}'";
        if(!empty($this->_pattern))
        {
            $column[] = "pattern: '{$this->_pattern}'";
        }
        $columnInner = implode(',', $column);
        $json = sprintf('{%s}', $columnInner);
        
        return $json;
    }
    
    public static function getValidTypes()
    {
        $obj = new self(0);
        return $obj->_validTypes;
    }
}

class GvizDataTableRow
{
    protected $_cells = array();
    
    public function addCell($type, $value, $formatted)
    {
        $this->_cells[] = GvizDataTableCell::factory($type, $value, $formatted);
    }
    
    public function toJson()
    {
        $row = array();
        foreach($this->_cells as $cell)
        {
            $row[] = $cell->toJson();
        }
        $rowInner = implode(',', $row);
        $json = sprintf('{c:[%s]}', $rowInner);
        
        return $json;
    }
}

class GvizDataTableCell
{
    protected $_value;
    protected $_formatted = '';
    
    public function __construct($value, $formatted = '')
    {
        $this->_value = $value;
        $this->_formatted = $formatted;
    }
    
    public static function factory($type, $value, $formatted = '')
    {
        $type = strtolower($type);
        $class = "GvizDataTable" . ucfirst($type) . "Cell";
        if(!in_array($type, GvizDataTableColumn::getValidTypes()) || !class_exists($class))
        {
            throw new GvizDataTableException("Type, $type, not supported by this api");
        }
        
        if(empty($value))
        {
            $class = 'GvizDataTableEmptyCell';
        }
        return new $class($value, $formatted);
    }
    
    public function toJson()
    {
        $cell = array();
        $cell[] = "v: '{$this->_value}'";
        if(!empty($this->_formatted))
        {
            $cell[] = "f: '{$this->_formatted}'";
        }
        $cellInner = implode(',', $cell);
        
        $json = sprintf('{%s}', $cellInner);
        
        return $json;
    }
}

class GvizDataTableEmptyCell
{
    public function toJson()
    {
        return '';
    }
}

class GvizDataTableBooleanCell extends GvizDataTableCell
{
    public function __construct($value, $formatted = '')
    {
        $possibleFalses = array('false', '0', 0, FALSE, '', NULL);
        $value = strtolower($value);
        if(in_array($value, $possibleFalses, true))
        {
            $value = 'false';
        }
        else
        {
            $value = 'true';
        }
        
        parent::__construct($value, $formatted);
    }
}

class GvizDataTableNumberCell extends GvizDataTableCell
{
    public function toJson()
    {
        $cell = array();
        $cell[] = "v: {$this->_value}";
        if(!empty($this->_formatted))
        {
            $cell[] = "f: '{$this->_formatted}'";
        }
        $cellInner = implode(',', $cell);
        
        $json = sprintf('{%s}', $cellInner);
        
        return $json;
    }
}

class GvizDataTableStringCell extends GvizDataTableCell
{
    
}

class GvizDataTableDatetimeCell extends GvizDataTableCell
{
    public function toJson()
    {
        $cell = array();
        $date = $this->_getDateParts($this->_value);
        $cell[] = sprintf("v: new Date(%d, %d, %d, %d, %d, %d)",
            $date['year'],
            ($date['month'] - 1), //JS uses 0-11 for month
            $date['day'],
            $date['hour'],
            $date['minute'],
            $date['second']
        );
        if(!empty($this->_formatted))
        {
            $cell[] = "f: '{$this->_formatted}'";
        }
        $cellInner = implode(',', $cell);
        
        $json = sprintf('{%s}', $cellInner);
        
        return $json;
    }
    
    protected function _getDateParts($date)
    {
        if(!is_numeric($date)) $date = strtotime($date);
        $month = (int) strftime("%m", $date);
        $day = (int) strftime("%d", $date);
        $year = (int) strftime("%Y", $date);
        $hour = (int) strftime("%H", $date);
        $minute = (int) strftime("%M", $date);
        $second = (int) strftime("%S", $date);
        return compact('month', 'day', 'year', 'hour', 'minute', 'second');
    }
}

class GvizDataTableDateCell extends GvizDataTableDatetimeCell
{
    public function toJson()
    {
        $cell = array();
        $date = $this->_getDateParts($this->_value);
        $cell[] = sprintf("v: new Date(%d, %d, %d)",
            $date['year'],
            ($date['month'] - 1),
            $date['day']
        );
        if(!empty($this->_formatted))
        {
            $cell[] = "f: '{$this->_formatted}'";
        }
        $cellInner = implode(',', $cell);
        
        $json = sprintf('{%s}', $cellInner);
        
        return $json;
    }
}

class GvizDataTableTimeofdayCell extends GvizDataTableDatetimeCell
{
    public function toJson()
    {
        $cell = array();
        $date = $this->_getDateParts($this->_value);
        $cell[] = sprintf("v: [%d, %d, %d])",
            $date['hour'],
            $date['minute'],
            $date['second']
        );
        if(!empty($this->_formatted))
        {
            $cell[] = "f: '{$this->_formatted}'";
        }
        $cellInner = implode(',', $cell);
        
        $json = sprintf('{%s}', $cellInner);
        
        return $json;
    }
}

class GvizDataTableWarning
{
    protected $_reason;
    protected $_message = '';
    protected $_detailed_message = '';
    protected $_validReasons = array(
        'data_truncated',
        'other',
    );
    
    public function __construct($reason, $message = '', $detailed_message = '')
    {
        if(in_array($reason, $this->_validReasons))
        {
            $this->_reason = $reason;
        }
        else
        {
            throw new GvizDataTableException("Reason, $reason, is not a valid reason.");
        }
        
        $this->_message = $message;
        $this->_detailed_message = $detailed_message;
    }
    
    public function toJson()
    {
        $items = array();
        $items[] = "reason:'{$this->_reason}'";
        if(!empty($this->_message))
        {
            $items[] = "message:'{$this->_message}'";
        }
        if(!empty($this->_detailed_message))
        {
            $items[] = "detailed_message:'{$this->_detailed_message}'";
        }
        
        $warningInner = implode(',', $items);
        
        $json = sprintf('{%s}', $warningInner);
        
        return $json;
    }
    
    public static function getValidReasons()
    {
        $obj = new self('other');
        return $obj->_validReasons;
    }
}

class GvizDataTableError extends GvizDataTableWarning
{
    protected $_validReasons = array(
        'not_modified',
        'user_not_authenticated',
        'unknown_data_source_id',
        'access_denied',
        'unsupported_query_operation',
        'invalid_query',
        'invalid_request',
        'internal_error',
        'not_supported',
        'illegal_formatting_patterns',
        'other',
    );
    
    public static function getValidReasons()
    {
        $obj = new self('other');
        return $obj->_validReasons;
    }
}

?>