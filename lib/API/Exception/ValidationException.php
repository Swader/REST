<?php

namespace API\Exception;

class ValidationException extends \Exception
{
    private $data = array();
    
    public function __construct($message, $code = 0, $data = array())
    {
        parent::__construct($message, $code);
        
        $this->data = $data;
    }
    
    public function getData()
    {
        return $this->data;
    }
}
