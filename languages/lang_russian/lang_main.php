<?php

class Language implements ArrayAccess {
    private $tlr = array();
    
    public function __construct() {
        $this->tlr = (array) require_once('lang_constants.php');
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void {
        if ($this->offsetExists($offset)) {
            die('Изменение уже существующих значений запрещено');
        }
        $this->tlr[$offset] = $value;
    }
    
    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool {
        return isset($this->tlr[$offset]);
    }
    
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void {
        unset($this->tlr[$offset]);
    }
    
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->tlr[$offset] ?? 'NO_LANG_' . strtoupper($offset);
    }
}

$tracker_lang = new Language;

?>