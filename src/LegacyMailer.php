<?php

use React\Promise;

class LegacyMailer {
    private $loop;
    private $log;
    private $amqp;
    private $pdo;
    
    private $timer;
    
    function __construct($loop, $log, $amqp, $pdo) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized legacy mailer');
    }
    
    public function start() {
        $th = $this;
        
        $this -> timer = $this -> loop -> addPeriodicTimer(
            5,
            function() use($th) {
                $th -> sendMails();
            }
        );
        
        $this -> log -> info('Started legacy mailer');
        
        return Promise\resolve(null);
    }
    
    public function stop() {
        $this -> loop -> cancelTimer($this -> timer);
        
        $this -> log -> info('Started legacy mailer');
        
        return Promise\resolve(null);
    }
    
    private function sendMails() {
        try {
            do {
                $sql = 'SELECT *
                        FROM mails
                        WHERE sent = FALSE
                        LIMIT 50';
     
                $rows = $this -> pdo -> query($sql);
                $rowsCount = 0;
            
                foreach($rows as $row) {
                    $rowsCount++;
                    
                    $this -> amqp -> pub(
                        'mail',
                        [
                            'uid' => $row['uid'],
                            'template' => $row['template'],
                            'context' => json_decode($row['data'], true),
                            'email' => $row['email']
                        ]
                    );
                
                    // Mark sent
                    $task = array(
                        ':mailid' => $row['mailid'],
                    );
        
                    $sql = 'UPDATE mails SET sent = TRUE WHERE mailid = :mailid';
    
                    $q = $this -> pdo -> prepare($sql);
                    $q -> execute($task);
                }
                
                if($rowsCount != 0)
                    $this -> log -> info("Processed $rowsCount mails");
            } while($rowsCount == 50);
        }
        catch(\Exception $e) {
            $this -> log -> error('Exception during processing mails: '.((string) $e));
        }
    }
}

?>