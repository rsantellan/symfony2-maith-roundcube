<?php

define('RCUBE_CONFIG_DIR',  __dir__.'/config');
define('RCUBE_PLUGINS_DIR',  __dir__.'/');

require_once 'bootstrap.php';


function my_autoload($class) {
    if (file_exists(__dir__."/Maith/" . $class . ".class.php")) {
        require_once(__dir__."/Maith/" . $class . ".class.php");
    }
}

spl_autoload_register("my_autoload");

/**
 * Description of RoundcubeHelper
 *
 * @author Rodrigo Santellan
 */
class RoundcubeHelper {
  
  
  private $userId;
  
  private $hostImap;
  
  private $userImap;
  
  private $passImap;
  
  private $rcube;
  
  private $hostSend;
  
  private $userSend;
  
  private $passSend;
  
  function __construct($userId, $hostImap, $userImap, $passImap, $hostSend, $userSend, $passSend) {
    $this->userId = $userId;
    $this->hostImap = $hostImap;
    $this->userImap = $userImap;
    $this->passImap = $passImap;
    $this->hostSend = $hostSend;
    $this->userSend = $userSend;
    $this->passSend = $passSend;
    $this->rcube = rcube::get_instance(rcube::INIT_WITH_DB | rcube::INIT_WITH_PLUGINS);
    $sql_arr = array(
        'user_id' => $userId,
        'language' => 'es',
    );
    $user = new rcube_user($userId, $sql_arr);
    $this->rcube->set_user($user);
    
  }

  
  
  /*
  public function retrieveInstance($userId, $hostImap, $userImap, $passImap)
  {
    $rcube = rcube::get_instance(rcube::INIT_WITH_DB | rcube::INIT_WITH_PLUGINS);
    $sql_arr = array(
        'user_id' => $userId,
        'language' => 'es',
    );
    $user = new rcube_user($userId, $sql_arr);
    $rcube->set_user($user);
    $imap = $rcube->get_storage();
    
  }
  */
  
  
  function sendEmail($rcube, $host, $port, $user, $pass, $messageTo, $options = array())
  {
    $rcube->smtp = new rcube_smtp();

    $rcube->smtp->connect($host, $port, $user, $pass);
    $error = '';
    // create PEAR::Mail_mime instance
    $body = 'test';

    $subject = 'Esto es un test de que va en HTML';

    $messageCharset = $rcube->config->get('message_charset', 'UTF-8');
    // function createMailMime($rcube, $body, $subject, $from, $to, $messageCharset, $options = array())
    $mailMime = ComposeHelper::createMailMime($rcube, $body, $subject, $user, $messageTo, $messageCharset, $options);
    //var_dump($mailMime);
    //var_dump($mailMime->getHTMLBody());
    //var_dump($mailMime->getTXTBody());
    //die;
    $sent = $rcube->deliver_message($mailMime, $user, $messageTo, $error);
    //var_dump($error);
    //var_dump($sent);
    $rcube->smtp->disconnect();
    return $sent;
  }
  
  
}
