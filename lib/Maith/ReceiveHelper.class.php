<?php

/**
 * Description of ReceiveHelper
 *
 * @author Rodrigo Santellan
 */
class ReceiveHelper {
  
    public static function rcmail_set_unseen_count($mbox_name, $count)
    {
        // @TODO: this data is doubled (session and cache tables) if caching is enabled

        // Make sure we have an array here (#1487066)
        if (!is_array($_SESSION['unseen_count'])) {
            $_SESSION['unseen_count'] = array();
        }

        $_SESSION['unseen_count'][$mbox_name] = $count;
    }

    public static function rcmail_get_unseen_count($mbox_name)
    {
        if (is_array($_SESSION['unseen_count']) && array_key_exists($mbox_name, $_SESSION['unseen_count'])) {
            return $_SESSION['unseen_count'][$mbox_name];
        }
    }

    public static function rcmail_send_unread_count($storage, $mbox_name, $force=false, $count=null)
    {

        $old_unseen = rcmail_get_unseen_count($mbox_name);

        if ($count === null)
        {
          $unseen = $storage->count($mbox_name, 'UNSEEN', $force);
        }
        else 
        {
          $unseen = $count;
        }
        rcmail_set_unseen_count($mbox_name, $unseen);
        return $unseen;
    }  
 
    public static function readMessages($rcube, $folder, $usePage = 1, $quantity = 0)
    {
      $threading = (bool) $rcube->get_storage()->get_threading();
      
      $imap = $rcube->get_storage();
      $imap->set_folder($folder);
      $mbox_name = $imap->get_folder();
      $imap->set_pagesize($quantity);
      
      $imap->folder_sync($mbox_name);
      $count = $imap->count($mbox_name, $threading ? 'THREADS' : 'ALL', true);
      $a_headers = array();
      
      if ($count) {
          $a_headers = $imap->list_messages($mbox_name, $usePage, 'date', 'desc', $quantity);
      }

      // update message count display
      $pages  = ceil($count/$imap->get_pagesize());
      $page   = $count ? $imap->get_page() : 1;
      $exists = $imap->count($mbox_name, 'EXISTS', true);

      $list_cols   = $rcube->config->get('list_cols');
      $a_show_cols = !empty($list_cols) && is_array($list_cols) ? $list_cols : array('subject');
      //var_dump($list_cols);
      // make sure 'threads' and 'subject' columns are present
      if (!in_array('subject', $a_show_cols))
          array_unshift($a_show_cols, 'subject');
      if (!in_array('threads', $a_show_cols))
          array_unshift($a_show_cols, 'threads');

      // remove 'threads', 'attachment', 'flag', 'status' columns, we don't need them here
      $removedCols = array('threads', 'attachment', 'flag', 'status', 'priority');
      foreach ($removedCols as $col) {
          if (($key = array_search($col, $a_show_cols)) !== FALSE) {
              unset($a_show_cols[$key]);
          }
      }

      $multifolder = false;
      $data  = array();
      
      foreach ($a_headers as $header) {
              if (empty($header))
                  continue;

              // make message UIDs unique by appending the folder name
              if ($multifolder) {
                  $header->uid .= '-'.$header->folder;
                  $header->flags['skip_mbox_check'] = true;
                  if ($header->parent_uid)
                      $header->parent_uid .= '-'.$header->folder;
              }

              $a_msg_cols  = array();
              $a_msg_flags = array();

              // format each col; similar as in rcmail_message_list()
              foreach ($a_show_cols as $col) {
                  $col_name = $col;// == 'fromto' ? $smart_col : $col;
                  if($col == 'fromto')
                  {
                   if($mbox_name != 'INBOX'){
                      $col_name = 'to';
                    }else{
                      $col_name = 'from';
                    }
                  }
                  
                  
                  if (in_array($col_name, array('from', 'to', 'cc', 'replyto')))
                  {
                    $cont = FormatHelper::rcmail_address_string($rcube, $header->$col_name, 3, false, null, $header->charset);
                  }
                  else if ($col == 'subject') {
                      $cont = trim(rcube_mime::decode_header($header->$col, $header->charset));
                      if (!$cont) $cont = $rcube->gettext('nosubject');
                      $cont = rcube::Q($cont);
                  }
                  else if ($col == 'size')
                      $cont = FormatHelper::show_bytes($rcube, $header->$col);
                  else if ($col == 'date'){

                    $cont = FormatHelper::format_date($rcube, $header->date);
                  }
                  else if ($col == 'folder'){
                    $cont = rcube::Q(rcube_charset::convert($header->folder, 'UTF7-IMAP'));
                  }
                  else
                  {
                    if(isset($header->$col))
                    {
                      $cont = rcube::Q($header->$col);
                    }
                    else
                    {
                      $cont = '';
                    }
                  }
                  $a_msg_cols[$col] = $cont;
              }


              $a_msg_flags = array_change_key_case(array_map('intval', (array) $header->flags));
              if (isset($header->depth))
                  $a_msg_flags['depth'] = $header->depth;
              else if (isset($header->has_children))
                  $roots[] = $header->uid;
              if (isset($header->parent_uid))
                  $a_msg_flags['parent_uid'] = $header->parent_uid;
              if (isset($header->has_children))
                  $a_msg_flags['has_children'] = $header->has_children;
              if (isset($header->unread_children))
                  $a_msg_flags['unread_children'] = $header->unread_children;
              if (isset($header->others['list-post']))
                  $a_msg_flags['ml'] = 1;
              if (isset($header->priority))
                  $a_msg_flags['prio'] = (int) $header->priority;

              $a_msg_flags['ctype'] = rcube::Q($header->ctype);
              $a_msg_flags['mbox']  = $header->folder;
              $data[] = array(
                  'data' => $a_msg_cols,
                  'flags' => $a_msg_flags,
                  'uid' => $header->uid
              );
      }
      return array(
          'pages' => $pages,
          'page' => $page,
          'exists' => $exists,
          'data' => $data,
          'headers' => $a_show_cols,

      );
    }
    
    public static function readMessage($rcube, $folder, $uid)
    {
        $imap = $rcube->get_storage();
        $imap->set_folder($folder);
        $message = new rcube_message($uid);
        //var_dump($message->headers);
        // set message charset as default
        if (!empty($message->headers->charset)) {
            $imap->set_charset($message->headers->charset);
        }
        // mimetypes supported by the browser (default settings)
        $mimetypes = (array)$rcube->config->get('client_mimetypes');
        
        // Remove unsupported types, which makes that attachment which cannot be
        // displayed in a browser will be downloaded directly without displaying an overlay page
        if (empty($_SESSION['browser_caps']['pdf']) && ($key = array_search('application/pdf', $mimetypes)) !== false) {
            unset($mimetypes[$key]);
        }
        if (empty($_SESSION['browser_caps']['flash']) && ($key = array_search('application/x-shockwave-flash', $mimetypes)) !== false) {
            unset($mimetypes[$key]);
        }
        if (empty($_SESSION['browser_caps']['tif']) && ($key = array_search('image/tiff', $mimetypes)) !== false) {
            // we can convert tiff to jpeg
            if (!rcube_image::is_convertable('image/tiff')) {
                unset($mimetypes[$key]);
            }
        }
        $attachments = array();
        if (empty($message->headers->flags['SEEN'])) {
            $mbox_name = $rcube->get_storage()->get_folder();
            $rcube->storage->set_flag($message->uid, 'SEEN', $mbox_name);
        }
        if (sizeof($message->attachments)) {
            foreach ($message->attachments as $attach_prop) {
                $filename = FormatHelper::rcmail_attachment_name($rcube, $attach_prop, true);
                $filesize = FormatHelper::message_part_size($rcube, $attach_prop);
                
                if (isset($attrib) && $attrib['maxlength'] && mb_strlen($filename) > $attrib['maxlength']) {
                    $title    = $filename;
                    $filename = abbreviate_string($filename, $attrib['maxlength']);
                }
                else {
                    $title = '';
                }

                if ($attach_prop->size) {
                    $size = ' ' .rcube::Q($filesize);
                }

                $mimetype = FormatHelper::rcmail_fix_mimetype($attach_prop->mimetype);
                $class    = rcube_utils::file2class($mimetype, $filename);
                $id       = 'attach' . $attach_prop->mime_id;
                $name = rcube::Q($filename) . $size;
                $attachments[$attach_prop->mime_id] = array(
                    'mimetype' => $mimetype,
                    'class' => $class,
                    'id' => $id,
                    'name' => $name,
                    'title' => rcube::Q($title),
                    'filename' => rcube::Q($filename),
                    'href' => $message->get_part_url($attach_prop->mime_id, false),
                  );
            }
        }
        $out = '';
        foreach($message->parts as $part)
        {
          
          if ($part->type == 'headers') {
                $out .= html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
          }
          else if ($part->type == 'content') {
              // unsupported (e.g. encrypted)
              if (isset($part->realtype)) {
                  if ($part->realtype == 'multipart/encrypted' || $part->realtype == 'application/pkcs7-mime') {
                      $out .= html::span('part-notice', $message->gettext('encryptedmessage'));
                  }
                  continue;
              }
              else if (!$part->size) {
                  continue;
              }

              // Check if we have enough memory to handle the message in it
              // #1487424: we need up to 10x more memory than the body
              else if (!rcube_utils::mem_check($part->size * 10)) {
                  $out .= html::span('part-notice', $rcube->gettext('messagetoobig'). ' '
                      . html::a('?_task=mail&_action=get&_download=1&_uid='.$message->uid.'&_part='.$part->mime_id
                          .'&_mbox='. urlencode($message->folder), $rcube->gettext('download')));
                  continue;
              }

              // fetch part body
              $body = $message->get_part_body($part->mime_id, true);

              // extract headers from message/rfc822 parts
              if ($part->mimetype == 'message/rfc822') {
                  $msgpart = rcube_mime::parse_message($body);
                  if (!empty($msgpart->headers)) {
                      $part = $msgpart;
                      $out .= html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
                  }
              }

              // message is cached but not exists (#1485443), or other error
              if ($body === false) {
                  rcmail_message_error($message->uid);
              }

              $plugin = $rcube->plugins->exec_hook('message_body_prefix',
                  array('part' => $part, 'prefix' => ''));
              
              $safe_mode = false;
              
              $body = FormatHelper::rcmail_print_body($rcube, $body, $part, array('safe' => $safe_mode, 'plain' => !$rcube->config->get('prefer_html')));

              if ($part->ctype_secondary == 'html') {
                  $body     = $body;//rcmail_html4inline($body, $attrib['id'], 'rcmBody', $attrs, $safe_mode);
                  $div_attr = array('class' => 'message-htmlpart');
                  $style    = array();

                  if (!empty($attrs)) {
                      foreach ($attrs as $a_idx => $a_val)
                          $style[] = $a_idx . ': ' . $a_val;
                      if (!empty($style))
                          $div_attr['style'] = implode('; ', $style);
                  }

                  $out .= html::div($div_attr, $plugin['prefix'] . $body);
              }
              else
                  $out .= html::div('message-part', $plugin['prefix'] . $body);
          }
        }
        return array(
            'message'=> $message,
            'attachments' => $attachments,
            'body' => $out,
        );
    }
    
    public static function getAttachmentOfMessage($rcube, $folder, $uid, $attachmentName)
    {
      $imap = $rcube->get_storage();
      $imap->set_folder($folder);
      $message = new rcube_message($uid);
      //var_dump($message->headers);
      // set message charset as default
      if (!empty($message->headers->charset)) {
          $imap->set_charset($message->headers->charset);
      }
      if (sizeof($message->attachments)) {
          foreach ($message->attachments as $attach_prop) {
              $filename = FormatHelper::rcmail_attachment_name($rcube, $attach_prop, true);
              $filesize = FormatHelper::message_part_size($rcube, $attach_prop);
              if($filename == $attachmentName)
              {
                if (isset($attrib) && $attrib['maxlength'] && mb_strlen($filename) > $attrib['maxlength']) {
                  $title    = $filename;
                  $filename = abbreviate_string($filename, $attrib['maxlength']);
                }
                else {
                    $title = '';
                }

                if ($attach_prop->size) {
                    $size = ' ' .rcube::Q($filesize);
                }

                $mimetype = FormatHelper::rcmail_fix_mimetype($attach_prop->mimetype);
                $class    = rcube_utils::file2class($mimetype, $filename);
                $id       = 'attach' . $attach_prop->mime_id;
                $name = rcube::Q($filename) . $size;
                
                $attachments[$attach_prop->mime_id] = array(
                    'mimetype' => $mimetype,
                    'class' => $class,
                    'id' => $id,
                    'name' => $name,
                    'title' => rcube::Q($title),
                    'filename' => rcube::Q($filename),
                    'href' => $message->get_part_url($attach_prop->mime_id, false),
                  );
              }
              
          }
      }
      
    }
    
    
      /**
       * Handler for the 'messagebody' GUI object
       *
       * @param array Named parameters
       * @return string HTML content showing the message body
       */
      public static function rcmail_message_body($attrib)
      {
          //global $OUTPUT, $MESSAGE, $RCMAIL, $REMOTE_OBJECTS;

          if (!is_array($MESSAGE->parts) && empty($MESSAGE->body)) {
              return '';
          }

          if (!$attrib['id'])
              $attrib['id'] = 'rcmailMsgBody';

          $safe_mode = $MESSAGE->is_safe || intval($_GET['_safe']);
          $out = '';

          $header_attrib = array();
          foreach ($attrib as $attr => $value) {
              if (preg_match('/^headertable([a-z]+)$/i', $attr, $regs)) {
                  $header_attrib[$regs[1]] = $value;
              }
          }

          if (!empty($MESSAGE->parts)) {
              foreach ($MESSAGE->parts as $part) {
                  if ($part->type == 'headers') {
                      $out .= html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
                  }
                  else if ($part->type == 'content') {
                      // unsupported (e.g. encrypted)
                      if ($part->realtype) {
                          if ($part->realtype == 'multipart/encrypted' || $part->realtype == 'application/pkcs7-mime') {
                              $out .= html::span('part-notice', $RCMAIL->gettext('encryptedmessage'));
                          }
                          continue;
                      }
                      else if (!$part->size) {
                          continue;
                      }

                      // Check if we have enough memory to handle the message in it
                      // #1487424: we need up to 10x more memory than the body
                      else if (!rcube_utils::mem_check($part->size * 10)) {
                          $out .= html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                              . html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part='.$part->mime_id
                                  .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
                          continue;
                      }

                      // fetch part body
                      $body = $MESSAGE->get_part_body($part->mime_id, true);

                      // extract headers from message/rfc822 parts
                      if ($part->mimetype == 'message/rfc822') {
                          $msgpart = rcube_mime::parse_message($body);
                          if (!empty($msgpart->headers)) {
                              $part = $msgpart;
                              $out .= html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
                          }
                      }

                      // message is cached but not exists (#1485443), or other error
                      if ($body === false) {
                          rcmail_message_error($MESSAGE->uid);
                      }

                      $plugin = $RCMAIL->plugins->exec_hook('message_body_prefix',
                          array('part' => $part, 'prefix' => ''));

                      $body = rcmail_print_body($body, $part, array('safe' => $safe_mode, 'plain' => !$RCMAIL->config->get('prefer_html')));

                      if ($part->ctype_secondary == 'html') {
                          $body     = rcmail_html4inline($body, $attrib['id'], 'rcmBody', $attrs, $safe_mode);
                          $div_attr = array('class' => 'message-htmlpart');
                          $style    = array();

                          if (!empty($attrs)) {
                              foreach ($attrs as $a_idx => $a_val)
                                  $style[] = $a_idx . ': ' . $a_val;
                              if (!empty($style))
                                  $div_attr['style'] = implode('; ', $style);
                          }

                          $out .= html::div($div_attr, $plugin['prefix'] . $body);
                      }
                      else
                          $out .= html::div('message-part', $plugin['prefix'] . $body);
                  }
              }
          }
          else {
              // Check if we have enough memory to handle the message in it
              // #1487424: we need up to 10x more memory than the body
              if (!rcube_utils::mem_check(strlen($MESSAGE->body) * 10)) {
                  $out .= html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                      . html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part=0'
                          .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
              }
              else {
                  $plugin = $RCMAIL->plugins->exec_hook('message_body_prefix',
                      array('part' => $MESSAGE, 'prefix' => ''));

                  $out .= html::div('message-part',
                      $plugin['prefix'] . FormatHelper::rcmail_plain_body($MESSAGE->body));
              }
          }

          return html::div($attrib, $out);
      }    
}

