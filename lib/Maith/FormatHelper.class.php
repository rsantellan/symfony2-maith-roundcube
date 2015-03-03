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
    function format_date($rcube, $date, $format = null, $convert = true)
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

       // define date format depending on current time
       if (!$format) {
           $now         = time();
           $now_date    = getdate($now);
           $today_limit = mktime(0, 0, 0, $now_date['mon'], $now_date['mday'], $now_date['year']);
           $week_limit  = mktime(0, 0, 0, $now_date['mon'], $now_date['mday']-6, $now_date['year']);
           $pretty_date = $rcube->config->get('prettydate');

           if ($pretty_date && $timestamp > $today_limit && $timestamp <= $now) {
               $format = $rcube->config->get('date_today', $rcube->config->get('time_format', 'H:i'));
               $today  = true;
           }
           else if ($pretty_date && $timestamp > $week_limit && $timestamp <= $now) {
               $format = $rcube->config->get('date_short', 'D H:i');
           }
           else {
               $format = $rcube->config->get('date_long', 'Y-m-d H:i');
           }
       }

       // strftime() format
       if (preg_match('/%[a-z]+/i', $format)) {
           $format = strftime($format, $timestamp);
           if ($stz) {
               date_default_timezone_set($stz);
           }
           return $today ? ($rcube->gettext('today') . ' ' . $format) : $format;
       }

       // parse format string manually in order to provide localized weekday and month names
       // an alternative would be to convert the date() format string to fit with strftime()
       $out = '';
       for ($i=0; $i<strlen($format); $i++) {
           if ($format[$i] == "\\") {  // skip escape chars
               continue;
           }

           // write char "as-is"
           if ($format[$i] == ' ' || $format[$i-1] == "\\") {
               $out .= $format[$i];
           }
           // weekday (short)
           else if ($format[$i] == 'D') {
               $out .= $rcube->gettext(strtolower(date('D', $timestamp)));
           }
           // weekday long
           else if ($format[$i] == 'l') {
               $out .= $rcube->gettext(strtolower(date('l', $timestamp)));
           }
           // month name (short)
           else if ($format[$i] == 'M') {
               $out .= $rcube->gettext(strtolower(date('M', $timestamp)));
           }
           // month name (long)
           else if ($format[$i] == 'F') {
               $out .= $rcube->gettext('long'.strtolower(date('M', $timestamp)));
           }
           else if ($format[$i] == 'x') {
               $out .= strftime('%x %X', $timestamp);
           }
           else {
               $out .= date($format[$i], $timestamp);
           }
       }

       if ($today) {
           $label = $rcube->gettext('today');
           // replcae $ character with "Today" label (#1486120)
           if (strpos($out, '$') !== false) {
               $out = preg_replace('/\$/', $label, $out, 1);
           }
           else {
               $out = $label . ' ' . $out;
           }
       }

       if ($stz) {
           date_default_timezone_set($stz);
       }

       return $out;
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
    function rcmail_plain_body($body, $flowed = false)
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
    function message_part_size($rcube, $part)
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
    
    function rcmail_attachment_name($rcube, $attachment, $display = false)
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
    function show_bytes($rcube, $bytes, &$unit = null)
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
}

