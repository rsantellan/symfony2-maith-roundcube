<?php

/**
 * Description of FormatHelper
 *
 * @author Rodrigo Santellan
 */
class FormatHelper {

  /**
   * Parse and cleanup email address input (and count addresses)
   *
   * @param string  Address input
   * @param boolean Do count recipients (saved in global $RECIPIENT_COUNT)
   * @param boolean Validate addresses (errors saved in global $EMAIL_FORMAT_ERROR)
   * @return string Canonical recipients string separated by comma
   */
  public static function rcmail_email_input_format($rcube, $mailto, $count = false, $check = true) {
    $RECIPIENT_COUNT = 0;
    $EMAIL_FORMAT_ERROR = array();
    // simplified email regexp, supporting quoted local part
    $email_regexp = '(\S+|("[^"]+"))@\S+';

    $delim = trim($rcube->config->get('recipients_separator', ','));
    $regexp = array("/[,;$delim]\s*[\r\n]+/", '/[\r\n]+/', "/[,;$delim]\s*\$/m", '/;/', '/(\S{1})(<' . $email_regexp . '>)/U');
    $replace = array($delim . ' ', ', ', '', $delim, '\\1 \\2');

    // replace new lines and strip ending ', ', make address input more valid
    $mailto = trim(preg_replace($regexp, $replace, $mailto));
    $items = rcube_utils::explode_quoted_string($delim, $mailto);
    $result = array();

    foreach ($items as $item) {
      $item = trim($item);
      // address in brackets without name (do nothing)
      if (preg_match('/^<' . $email_regexp . '>$/', $item)) {
        $item = rcube_utils::idn_to_ascii(trim($item, '<>'));
        $result[] = $item;
      }
      // address without brackets and without name (add brackets)
      else if (preg_match('/^' . $email_regexp . '$/', $item)) {
        $item = rcube_utils::idn_to_ascii($item);
        $result[] = $item;
      }
      // address with name (handle name)
      else if (preg_match('/<*' . $email_regexp . '>*$/', $item, $matches)) {
        $address = $matches[0];
        $name = trim(str_replace($address, '', $item));
        if ($name[0] == '"' && $name[count($name) - 1] == '"') {
          $name = substr($name, 1, -1);
        }
        $name = stripcslashes($name);
        $address = rcube_utils::idn_to_ascii(trim($address, '<>'));
        $result[] = format_email_recipient($address, $name);
        $item = $address;
      } else if (trim($item)) {
        continue;
      }

      // check address format
      $item = trim($item, '<>');
      if ($item && $check && !rcube_utils::check_email($item)) {
        $EMAIL_FORMAT_ERROR[] = $item;
      }
    }

    if ($count) {
      $RECIPIENT_COUNT += count($result);
    }
    return array(
        'emails' => implode(', ', $result),
        'counter' => $RECIPIENT_COUNT,
        'errors' => $EMAIL_FORMAT_ERROR,
    );
  }

  
    /**
    * Convert the given date to a human readable form
    * This uses the date formatting properties from config
    *
    * @param mixed  Date representation (string, timestamp or DateTime object)
    * @param string Date format to use
    * @param bool   Enables date convertion according to user timezone
    *
    * @return string Formatted date string
    */
    public static function format_date($rcube, $date, $format = null, $convert = true)
    {
       if (is_object($date) && is_a($date, 'DateTime')) {
           $timestamp = $date->format('U');
       }
       else {

           if (!empty($date)) {
               $timestamp = rcube_utils::strtotime($date);
           }
           if (empty($timestamp)) {
               return '';
           }
           try {
               $date = new DateTime("@".$timestamp);
           }
           catch (Exception $e) {
               return '';
           }
       }

       if ($convert) {
           try {
               // convert to the right timezone
               $stz = date_default_timezone_get();
               $tz = new DateTimeZone($rcube->config->get('timezone'));
               $date->setTimezone($tz);
               date_default_timezone_set($tz->getName());

               $timestamp = $date->format('U');
           }
           catch (Exception $e) {
           }
       }
       return $timestamp;
    }  
    
    
    // Fixes some content-type names
    public static function rcmail_fix_mimetype($name)
    {
        // Some versions of Outlook create garbage Content-Type:
        // application/pdf.A520491B_3BF7_494D_8855_7FAC2C6C0608
        if (preg_match('/^application\/pdf.+/', $name)) {
            $name = 'application/pdf';
        }
        // treat image/pjpeg (image/pjpg, image/jpg) as image/jpeg (#1489097)
        else if (preg_match('/^image\/p?jpe?g$/', $name)) {
            $name = 'image/jpeg';
        }

        return $name;
    }    
    
    /**
     * Handle links and citation marks in plain text message
     *
     * @param string  Plain text string
     * @param boolean Set to True if the source text is in format=flowed
     *
     * @return string Formatted HTML string
     */
    public static function rcmail_plain_body($body, $flowed = false)
    {
        $options   = array('flowed' => $flowed, 'wrap' => !$flowed, 'replacer' => 'rcmail_string_replacer');
        $text2html = new rcube_text2html($body, false, $options);
        $body      = $text2html->get_html();

        return $body;
    }    
    
    
    /**
     * Returns real size (calculated) of the message part
     *
     * @param rcube_message_part  Message part
     *
     * @return string Part size (and unit)
     */
    public static function message_part_size($rcube, $part)
    {
        if (isset($part->d_parameters['size'])) {
            $size = show_bytes($rcube, (int)$part->d_parameters['size']);
        }
        else {
          $size = $part->size;
          if ($part->encoding == 'base64') {
            $size = $size / 1.33;
          }

          $size = '~' . self::show_bytes($rcube, $size);
        }

        return $size;
    }    
    
    public static function rcmail_attachment_name($rcube, $attachment, $display = false)
    {
        $filename = $attachment->filename;

        if ($filename === null || $filename === '') {
            if ($attachment->mimetype == 'text/html') {
                $filename = $rcube->gettext('htmlmessage');
            }
            else {
                $ext      = (array) rcube_mime::get_mime_extensions($attachment->mimetype);
                $ext      = array_shift($ext);
                $filename = $rcube->gettext('messagepart') . ' ' . $attachment->mime_id;
                if ($ext) {
                    $filename .= '.' . $ext;
                }
            }
        }

        $filename = preg_replace('[\r\n]', '', $filename);

        // Display smart names for some known mimetypes
        if ($display) {
            if (preg_match('/application\/(pgp|pkcs7)-signature/i', $attachment->mimetype)) {
                $filename = $rcube->gettext('digitalsig');
            }
        }

        return $filename;
    }    
    
    /**
     * Create a human readable string for a number of bytes
     *
     * @param int    Number of bytes
     * @param string Size unit
     *
     * @return string Byte string
     */
    public static function show_bytes($rcube, $bytes, &$unit = null)
    {
        if ($bytes >= 1073741824) {
            $unit = 'GB';
            $gb   = $bytes/1073741824;
            $str  = sprintf($gb >= 10 ? "%d " : "%.1f ", $gb) . $rcube->gettext($unit);
        }
        else if ($bytes >= 1048576) {
            $unit = 'MB';
            $mb   = $bytes/1048576;
            $str  = sprintf($mb >= 10 ? "%d " : "%.1f ", $mb) . $rcube->gettext($unit);
        }
        else if ($bytes >= 1024) {
            $unit = 'KB';
            $str  = sprintf("%d ",  round($bytes/1024)) . $rcube->gettext($unit);
        }
        else {
            $unit = 'B';
            $str  = sprintf('%d ', $bytes) . $rcube->gettext($unit);
        }

        return $str;
    }    
    
    public static function rcmail_address_string($rcube, $input, $max=null, $linked=false, $addicon=null, $default_charset=null, $title=null)
    {
        //global $RCMAIL, $PRINT_MODE;
        $a_parts = rcube_mime::decode_address_list($input, null, true, $default_charset);
        
        if (!sizeof($a_parts)) {
            return $input;
        }

        $c   = count($a_parts);
        $j   = 0;
        $out = '';
        $allvalues  = array();
        $show_email = $rcube->config->get('message_show_email');
        

        foreach ($a_parts as $part) {
            $j++;
            
            $name   = $part['name'];
            $mailto = $part['mailto'];
            $string = $part['string'];
            $valid  = rcube_utils::check_email($mailto, false);
            // phishing email prevention (#1488981), e.g. "valid@email.addr <phishing@email.addr>"
            if (!$show_email && $valid && $name && $name != $mailto && strpos($name, '@')) {
                $name = '';
            }
            // IDNA ASCII to Unicode
            if ($name == $mailto)
            {
              $name = rcube_utils::idn_to_utf8($name);
            }
            if ($string == $mailto)
            {
              $string = rcube_utils::idn_to_utf8($string);
            }
            $mailto = rcube_utils::idn_to_utf8($mailto);
             if ($valid) {
                if ($linked) {
                    $attrs = array(
                        'href'    => 'mailto:' . $mailto,
                        'class'   => 'rcmContactAddress',
                        'onclick' => sprintf("return %s.command('compose','%s',this)",
                            rcmail_output::JS_OBJECT_NAME, rcube::JQ(format_email_recipient($mailto, $name))),
                    );

                    if ($show_email && $name && $mailto) {
                        $content = rcube::Q($name ? sprintf('%s <%s>', $name, $mailto) : $mailto);
                    }
                    else {
                        $content = rcube::Q($name ? $name : $mailto);
                        $attrs['title'] = $mailto;
                    }

                    $address = html::a($attrs, $content);
                }
                else {
                    $address = html::span(array('title' => $mailto, 'class' => "rcmContactAddress"),
                        rcube::Q($name ? $name : $mailto));
                }

                if ($addicon && $_SESSION['writeable_abook']) {
                    $address .= html::a(array(
                            'href'    => "#add",
                            'title'   => $RCMAIL->gettext('addtoaddressbook'),
                            'class'   => 'rcmaddcontact',
                            'onclick' => sprintf("return %s.command('add-contact','%s',this)",
                                rcmail_output::JS_OBJECT_NAME, rcube::JQ($string)),
                        ),
                        html::img(array(
                            'src' => $RCMAIL->output->abs_url($addicon, true),
                            'alt' => "Add contact",
                    )));
                }
            }
            else {
                $address = '';
                if ($name)
                    $address .= rcube::Q($name);
                if ($mailto)
                    $address = trim($address . ' ' . rcube::Q($name ? sprintf('<%s>', $mailto) : $mailto));
            }
            $address = html::span('adr', $address);
            $allvalues[] = $address;

            //if (isset($moreadrs))
            $out .= ($out ? ', ' : '') . $address;

            if ($max && $j == $max && $c > $j) {
                if ($linked) {
                    $moreadrs = $c - $j;
                }
                else {
                    $out .= '...';
                    break;
                }
            }
        }
        if (isset($moreadrs)) {
            
            $out .= ' ' . html::a(array(
                    'href'    => '#more',
                    'class'   => 'morelink',
                    'onclick' => sprintf("return %s.show_popup_dialog('%s','%s')",
                        rcmail_output::JS_OBJECT_NAME,
                        rcube::JQ(join(', ', $allvalues)),
                        rcube::JQ($title))
                ),
                rcube::Q($rcube->gettext(array('name' => 'andnmore', 'vars' => array('nr' => $moreadrs)))));
            
        }

        return $out;
    }
    
    /**
     * Convert the given message part to proper HTML
     * which can be displayed the message view
     *
     * @param string             Message part body
     * @param rcube_message_part Message part
     * @param array              Display parameters array
     *
     * @return string Formatted HTML string
     */
    public static function rcmail_print_body($rcube, $body, $part, $p = array())
    {
      //var_dump($body);
      //var_dump($part);
        // trigger plugin hook
        $data = $rcube->plugins->exec_hook('message_part_before',
            array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id)
                + $p + array('safe' => false, 'plain' => false, 'inline_html' => true));

        // convert html to text/plain
        if ($data['plain'] && ($data['type'] == 'html' || $data['type'] == 'enriched')) {
            if ($data['type'] == 'enriched') {
                $data['body'] = rcube_enriched::to_html($data['body']);
            }

            $txt  = new rcube_html2text($data['body'], false, true);
            $body = $txt->get_text();
            $part->ctype_secondary = 'plain';
        }
        // text/html
        else if ($data['type'] == 'html') {
            $replaces = '';
            if(isset($part->replaces))
            {
              $replaces = $part->replaces;
            }
            $body = self::rcmail_wash_html($data['body'], $data, $replaces);
            $part->ctype_secondary = $data['type'];
        }
        // text/enriched
        else if ($data['type'] == 'enriched') {
            $body = rcube_enriched::to_html($data['body']);
            $body = rcmail_wash_html($body, $data, $part->replaces);
            $part->ctype_secondary = 'html';
        }
        else {
            // assert plaintext
            $body = $data['body'];
            $part->ctype_secondary = $data['type'] = 'plain';
        }

        // free some memory (hopefully)
        unset($data['body']);

        // plaintext postprocessing
        if ($part->ctype_secondary == 'plain') {
            $body = FormatHelper::rcmail_plain_body($body, true);
        }

        // allow post-processing of the message body
        $data = $rcube->plugins->exec_hook('message_part_after',
            array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id) + $data);

        return $data['body'];
    }    
    
    /**
      * Cleans up the given message HTML Body (for displaying)
      *
      * @param string HTML
      * @param array  Display parameters 
      * @param array  CID map replaces (inline images)
      * @return string Clean HTML
      */
     public static function rcmail_wash_html($html, $p, $cid_replaces)
     {
         //global $REMOTE_OBJECTS;

         $p += array('safe' => false, 'inline_html' => true);

         // charset was converted to UTF-8 in rcube_storage::get_message_part(),
         // change/add charset specification in HTML accordingly,
         // washtml cannot work without that
         $meta = '<meta http-equiv="Content-Type" content="text/html; charset='.RCUBE_CHARSET.'" />';

         // remove old meta tag and add the new one, making sure
         // that it is placed in the head (#1488093)
         $html = preg_replace('/<meta[^>]+charset=[a-z0-9-_]+[^>]*>/Ui', '', $html);
         $html = preg_replace('/(<head[^>]*>)/Ui', '\\1'.$meta, $html, -1, $rcount);
         if (!$rcount) {
             $html = '<head>' . $meta . '</head>' . $html;
         }

         // clean HTML with washhtml by Frederic Motte
         $wash_opts = array(
             'show_washed'   => false,
             'allow_remote'  => true,
             'blocked_src'   => 'program/resources/blocked.gif',
             'charset'       => RCUBE_CHARSET,
             'cid_map'       => $cid_replaces,
             'html_elements' => array('body'),
         );

         if (!$p['inline_html']) {
             $wash_opts['html_elements'] = array('html','head','title','body');
         }
         if ($p['safe']) {
             $wash_opts['html_elements'][] = 'link';
             $wash_opts['html_attribs'] = array('rel','type');
         }

         // overwrite washer options with options from plugins
         if (isset($p['html_elements']))
             $wash_opts['html_elements'] = $p['html_elements'];
         if (isset($p['html_attribs']))
             $wash_opts['html_attribs'] = $p['html_attribs'];

         // initialize HTML washer
         $washer = new rcube_washtml($wash_opts);

         //if (!$p['skip_washer_form_callback']) {
             $washer->add_callback('form', 'rcmail_washtml_callback');
         //}

         // allow CSS styles, will be sanitized by rcmail_washtml_callback()
         //if (!$p['skip_washer_style_callback']) {
             $washer->add_callback('style', 'rcmail_washtml_callback');
         //}

         // Remove non-UTF8 characters (#1487813)
         $html = rcube_charset::clean($html);

         $html = $washer->wash($html);
         //$REMOTE_OBJECTS = $washer->extlinks;

         return $html;
     }
     
    /**
     * Callback function for washtml cleaning class
     */
    public static function rcmail_washtml_callback($tagname, $attrib, $content, $washtml)
    {
        switch ($tagname) {
        case 'form':
            $out = html::div('form', $content);
            break;

        case 'style':
            // decode all escaped entities and reduce to ascii strings
            $stripped = preg_replace('/[^a-zA-Z\(:;]/', '', rcube_utils::xss_entity_decode($content));

            // now check for evil strings like expression, behavior or url()
            if (!preg_match('/expression|behavior|javascript:|import[^a]/i', $stripped)) {
                if (!$washtml->get_config('allow_remote') && stripos($stripped, 'url('))
                    $washtml->extlinks = true;
                else
                    $out = html::tag('style', array('type' => 'text/css'), $content);
                break;
            }

        default:
            $out = '';
        }

        return $out;
    }     
}



