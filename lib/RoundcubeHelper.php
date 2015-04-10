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
    $imap->connect($this->hostImap, $this->userImap, $this->passImap);

    //$retrieved = ReceiveHelper::readMessages($this->rcube);

    
  }

  public function __destruct() {
    $this->rcube->get_storage()->close();
  }

  
  public function sendMessage($folder, $message)
  {
    $imap = $this->rcube->get_storage();
    imap_append($imap,$folder,$message."\r\n","\\Seen"); 
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
    
    return ReceiveHelper::readMessages($this->rcube, $folder, $pager, $quantity);
  }
  
  public function readEmail($folder, $uid)
  {
    
    return ReceiveHelper::readMessage($this->rcube, $folder, $uid);
  }
  
  public function moveMessage($folderFrom, $folderTo, $uid)
  {
    
    return $this->rcube->get_storage()->move_message($uid, $folderTo, $folderFrom);
  }


  public function search($folder, $criteria, $pager = 1, $quantity = 1000)
  {
    $searchStringFound = true;
    $searchString = '';
    if(substr_count($criteria, 'FROM:'))
    {
      $searchStringFound = false;
      $searchString = str_replace('FROM  ', 'FROM ', str_replace('FROM:', 'FROM ', $criteria));
    }
    
    if($searchStringFound && substr_count($criteria, 'TEXT:'))
    {
      $searchStringFound = false;
      $searchString = str_replace('TEXT:', 'TEXT ', $criteria);
    }
    
    if($searchStringFound && substr_count($criteria, 'SUBJECT:'))
    {
      $searchStringFound = false;
      $searchString = str_replace('SUBJECT:', 'SUBJECT ', $criteria);
    }
    /*
    if($criteria != 'ALL')
    {
        $this->searchAndReverse(sprintf('FROM %s', $criteria));
        $this->searchAndReverse(sprintf('TEXT %s', $criteria));
        $this->searchAndReverse(sprintf('SUBJECT %s', $criteria));
    }
    else
    {
      $this->searchAndReverse($criteria);
    }  
    */
    /**
     * 
     * @param  array  $set  Search set, result from rcube_imap::get_search_set():
     *                      0 - searching criteria, string
     *                      1 - search result, rcube_result_index|rcube_result_thread
     *                      2 - searching character set, string
     *                      3 - sorting field, string
     *                      4 - true if sorted, bool
     */
    $search = array(
      $criteria,
      new \rcube_result_index($folder, '* SORT'),
      '',
      'date',
      true
        
    );
    $this->rcube->get_storage()->set_search_set($search);
    return ReceiveHelper::readMessages($this->rcube, $folder, $pager, $quantity);
  }
  
  
  public function closeConnections()
  {
    $this->rcube->get_storage()->close();
  }
  
  /**
   * 
   * @deprecated since version 0.1 Use Zend Mail
   * @param type $getSpecial
   * @return type
   * 
   */
  public function retrieveFolders($getSpecial = true)
  {
    $special = array();
    if($getSpecial)
    {
      $special = $this->rcube->get_storage()->get_special_folders();
    }
    return array_merge($this->rcube->get_storage()->list_folders(), $special);
  }
}

