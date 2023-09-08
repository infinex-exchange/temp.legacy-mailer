<?php

require __DIR__.'/Sender.php';
require __DIR__.'/MailQueue.php';
require __DIR__.'/MailStorage.php';

class App extends Infinex\App\Daemon {
    private $pdo;
    private $mailer;
    
    function __construct() {
        parent::__construct('mailer.mailer-legacy');
        
        $this -> pdo = new Infinex\Database\PDO($this -> loop, $this -> log);
        $this -> pdo -> start();
        
        $this -> mailer = new MailerLegacy($this -> loop, $this -> log, $this -> amqp, $this -> pdo);
        $this -> mailer -> start();
    }
}

?>