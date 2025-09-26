<?php

class Database{
    private $host = "localhost";
    private $dbname = "u765389418_availability_s";
    private $username = "u765389418_z32345897";
    private $password = "Eaa890213/";
    private $pdo;

    public function connect(){
        if($this->pdo == null){
            try{
                $this->pdo = new PDO(
                    "mysql:host = {$this->host}; dbname={$this->dbname}; charset=utf8",
                    $this->username,
                    $this->password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]    
                );
            } catch (PDOException $e){
                die("連結失敗: " . $e->getMessage());
            }
        }
        return $this->pdo;
    }
}
?>