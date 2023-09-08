<?php

class MailerLegacy {
    private $loop;
    private $log;
    private $amqp;
    private $pdo;
    
    function __construct($loop, $log, $amqp, $pdo) {
        $this -> loop = $loop;
        $this -> log = $log;
        $this -> amqp = $amqp;
        $this -> pdo = $pdo;
        
        $this -> log -> debug('Initialized legacy mailer');
    }
    
    public function start() {
        $th = $this;
        $this -> loop -> addPeriodicTimer(5, function() use($th) {
            $th -> sendMails();
        });
    }
    
    public function sendMails() {
        try {
            do {
                $sql = 'SELECT mails.*,
                            users.email
                        FROM mails,
                            users
                        WHERE mails.sent = FALSE
                        AND mails.uid = users.uid
                        LIMIT 50';
     
                $rows = $this -> pdo -> query($sql);
                $rowsCount = 0;
            
                foreach($rows as $row) {
                    $rowsCount++;
                    
                    $this -> amqp -> pub(
                        'mail',
                        [
                            'email' => $row['email'],
                            'template' => $row['template'],
                            'context' => json_decode($row['data'], true)
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
                
                $this -> log -> info("Processed $rowsCount mails");
            } while($rowsCount == 50);
        }
        catch(\Exception $e) {
            $this -> log -> error('Exception during processing mails: '.$e -> getMessage());
        }
    }
}

?>