<?php

//define('RCUBE_CONFIG_DIR',  __dir__.'/config');
//define('RCUBE_PLUGINS_DIR',  __dir__.'/');

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
    $this->rcube = rcube::get_instance(rcube::INIT_WITH_DB);
    $sql_arr = array(
        'user_id' => $userId,
        'language' => 'es',
    );
    $user = new rcube_user($userId, $sql_arr);
    $this->rcube->set_user($user);
    
    

    $imap = $this->rcube->get_storage();

    // do cool stuff here...

    /*
    $host = 'rs9.websitehostserver.net';
    $user = 'info@rodrigosantellan.com';
    $pass = 'S8zxwOTD4kq3';
    // Change to non ssl connection because of problems with perl Mail_mime
    $hostSend = 'mail.rodrigosantellan.com';
    $portSend = '26';
    $messageTo = 'rswibmer@hotmail.com';
    */
    $imap->connect($this->hostImap, $this->userImap, $this->passImap);

    //$retrieved = ReceiveHelper::readMessages($this->rcube);

    
  }

  public function __destruct() {
    $this->rcube->get_storage()->close();
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
  
  public function readEmails($folder = 'Inbox', $pager = 1, $quantity = 10)
  {
    
    return ReceiveHelper::readMessages($this->rcube, $pager, $quantity);
  }
  
  public function readEmail($folder, $uid)
  {
    
    return ReceiveHelper::readMessage($this->rcube, $uid);
  }
  
  public function retrieveFolders($getSpecial = true)
  {
    $special = array();
    if($getSpecial)
    {
      $special = $this->rcube->get_storage()->get_special_folders();
    }
    return array_merge($this->rcube->get_storage()->list_folders(), $special);
    //$delimiter =  $this->rcube->get_storage()->get_hierarchy_delimiter();
    /*
    var_dump($this->rcube->get_storage()->get_folder());
    var_dump($this->rcube->get_storage()->get_namespace());
    var_dump($this->rcube->get_storage()->folder_namespace($this->rcube->get_storage()->get_folder()));
    var_dump($delimiter);
    echo '<hr/>';
    var_dump($this->rcube->get_storage()->list_mailboxes());
    var_dump($this->rcube->get_storage()->list_folders_subscribed());
    var_dump($this->rcube->get_storage()->list_folders_subscribed_direct());
    var_dump($special);
    var_dump($this->rcube->get_storage()->list_folders());
    */    
  }
}

