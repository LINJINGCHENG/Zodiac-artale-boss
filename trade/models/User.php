<?php 

class USER{
    private $db;

    public function __construct() {
        $this->db = (new Database())->connect();
    }
}
?>