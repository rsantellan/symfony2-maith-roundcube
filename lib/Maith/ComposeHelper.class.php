<?php

/**
 * Description of ComposeHelper
 *
 * @author Rodrigo Santellan
 */
class ComposeHelper {

  public static function createMailMime($rcube, $body, $subject, $from, $to, $messageCharset, $options = array()) {

    $mailtoList = FormatHelper::rcmail_email_input_format($rcube, rcube_utils::parse_input_value($to, TRUE, $messageCharset), true);
    $mailto = $mailtoList['emails'];
    $mailcc = '';
    if (isset($options['cc'])) {
      $mailccList = FormatHelper::rcmail_email_input_format($rcube, rcube_utils::parse_input_value($options['cc'], TRUE, $messageCharset), true);
      $mailcc = $mailccList['emails'];
    }
    $mailbcc = '';
    if (isset($options['bcc'])) {
      $mailbccList = FormatHelper::rcmail_email_input_format($rcube, rcube_utils::parse_input_value($options['bcc'], TRUE, $messageCharset), true);
      $mailbcc = $mailbccList['emails'];
    }

    if (empty($mailto) && !empty($mailcc)) {
      $mailto = $mailcc;
      $mailcc = null;
    } else if (empty($mailto)) {
      $mailto = 'undisclosed-recipients:;';
    }

    $from_string = $from;


    // compose headers array
    $headers = array();

    // if configured, the Received headers goes to top, for good measure
    if ($rcube->config->get('http_received_header')) {
      $nldlm = "\r\n\t";
      $encrypt = $rcube->config->get('http_received_header_encrypt');

      // FROM/VIA
      $http_header = 'from ';

      if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'], 2);
        $hostname = gethostbyaddr($hosts[0]);

        if ($encrypt) {
          $http_header .= self::rcmail_encrypt_header($rcube, $hostname);
          if ($host != $hostname)
            $http_header .= ' (' . self::rcmail_encrypt_header($rcube, $host) . ')';
        }
        else {
          $http_header .= (($host != $hostname) ? $hostname : '[' . $host . ']');
          if ($host != $hostname)
            $http_header .= ' ([' . $host . '])';
        }
        $http_header .= $nldlm . ' via ';
      }

      $host = $_SERVER['REMOTE_ADDR'];
      $hostname = gethostbyaddr($host);

      if ($encrypt) {
        $http_header .= self::rcmail_encrypt_header($rcube, $hostname);
        if ($host != $hostname)
          $http_header .= ' (' . self::rcmail_encrypt_header($rcube, $host) . ')';
      }
      else {
        $http_header .= (($host != $hostname) ? $hostname : '[' . $host . ']');
        if ($host != $hostname)
          $http_header .= ' ([' . $host . '])';
      }

      // BY
      $http_header .= $nldlm . 'by ' . $_SERVER['HTTP_HOST'];

      // WITH
      $http_header .= $nldlm . 'with HTTP (' . $_SERVER['SERVER_PROTOCOL'] .
              ' ' . $_SERVER['REQUEST_METHOD'] . '); ' . date('r');
      $http_header = wordwrap($http_header, 69, $nldlm);

      $headers['Received'] = $http_header;
    }

    $headers['Date'] = self::user_date($rcube);
    $headers['From'] = rcube_charset::convert($from_string, RCUBE_CHARSET, $messageCharset);
    $headers['To'] = $mailto;

    // additional recipients
    if (!empty($mailcc)) {
      $headers['Cc'] = $mailcc;
    }
    if (!empty($mailbcc)) {
      $headers['Bcc'] = $mailbcc;
    }

    $headers['Subject'] = $subject;

    if (isset($options['organization'])) {
      $headers['Organization'] = $options['organization'];
    }
    if (isset($options['Reply-To'])) {
      $replyTo = FormatHelper::rcmail_email_input_format($rcube, rcube_utils::parse_input_value($options['Reply-To'], TRUE, $messageCharset), true);
      if (!empty($replyTo['emails'])) {
        $headers['Reply-To'] = FormatHelper::rcmail_email_input_format($rcube, $replyTo['emails']);
      }
    }
    if (isset($options['Reply-To'])) {
      $headers['Mail-Reply-To'] = $headers['Reply-To'];
    }
    if (isset($options['Mail-Followup-To'])) {
      $replyTo = FormatHelper::rcmail_email_input_format($rcube, rcube_utils::parse_input_value($options['Mail-Followup-To'], TRUE, $messageCharset), true);
      if (!empty($replyTo['emails'])) {
        $headers['Mail-Followup-To'] = FormatHelper::rcmail_email_input_format($rcube, $replyTo['emails']);
      }
    }
    if (isset($options['_priority'])) {
      $priority = intval($options['_priority']);
      $a_priorities = array(1 => 'highest', 2 => 'high', 4 => 'low', 5 => 'lowest');
      if (isset($a_priorities[$priority])) {
        $headers['X-Priority'] = sprintf("%d (%s)", $priority, ucfirst($a_priorities[$priority]));
      }
    }
    $userAgent = $rcube->config->get('useragent');
    if ($userAgent) {
      $headers['User-Agent'] = $userAgent;
    }

    $isHtml = true;
    if (isset($options['textonly'])) {
      $isHtml = false;
    }
    if ($isHtml) {
      $bstyle = array();

      if ($font_size = $rcube->config->get('default_font_size')) {
        $bstyle[] = 'font-size: ' . $font_size;
      }
      if ($font_family = $rcube->config->get('default_font')) {
        $bstyle[] = 'font-family: ' . self::font_defs($font_family);
      }

      // append doctype and html/body wrappers
      $bstyle = !empty($bstyle) ? (" style='" . implode($bstyle, '; ') . "'") : '';
      $body = '<html><head>'
              . '<meta http-equiv="Content-Type" content="text/html; charset=' . $messageCharset . '" /></head>'
              . "<body" . $bstyle . ">\r\n" . $body;
    }
    if ($isHtml) {
      $b_style = 'padding: 0 0.4em; border-left: #1010ff 2px solid; margin: 0';
      $pre_style = 'margin: 0; padding: 0; font-family: monospace';

      $body = preg_replace(
              array(
          // remove signature's div ID
          '/\s*id="_rc_sig"/',
          // add inline css for blockquotes and container
          '/<blockquote>/',
          '/<div class="pre">/'
              ), array(
          '',
          '<blockquote type="cite" style="' . $b_style . '">',
          '<div class="pre" style="' . $pre_style . '">'
              ), $body);
    }

    if ($isHtml) {
      $body .= "\r\n</body></html>\r\n";
    }

    $line_length = $rcube->config->get('line_length', 72);

    $MAIL_MIME = new Mail_mime("\r\n");

    $flowed = false;

    // For HTML-formatted messages, construct the MIME message with both
    // the HTML part and the plain-text part
    if ($isHtml) {

      $MAIL_MIME->setHTMLBody($body);

      $h2t = new rcube_html2text($body, false, true, 0, $messageCharset);
      $plainTextPart = rcube_mime::wordwrap($h2t->get_text(), $line_length, "\r\n", false, $messageCharset);
      $plainTextPart = wordwrap($plainTextPart, 998, "\r\n", true);
      // make sure all line endings are CRLF (#1486712)
      $plainTextPart = preg_replace('/\r?\n/', "\r\n", $plainTextPart);
      $MAIL_MIME->setTXTBody($plainTextPart);
      // replace emoticons
      //$plugin['body'] = $RCMAIL->replace_emoticons($plugin['body']);
      // look for "emoticon" images from TinyMCE and change their src paths to
      // be file paths on the server instead of URL paths.
      //rcmail_fix_emoticon_paths($MAIL_MIME);
      // Extract image Data URIs into message attachments (#1488502)
      //rcmail_extract_inline_images($MAIL_MIME, $from);
    } else {
      // compose format=flowed content if enabled
      $flowed = $rcube->config->get('send_format_flowed', true);
      if ($flowed)
        $body = rcube_mime::format_flowed($body, min($line_length + 2, 79), $messageCharset);
      else
        $body = rcube_mime::wordwrap($body, $line_length, "\r\n", false, $messageCharset);

      $body = wordwrap($body, 998, "\r\n", true);

      $MAIL_MIME->setTXTBody($body, false, true);
    }

    if (isset($options['attachments']) && is_array($options['attachments'])) {
      foreach ($options['attachments'] as $id => $attachment) {
        $ctype = str_replace('image/pjpeg', 'image/jpeg', $attachment['mimetype']); // #1484914
        $file = $attachment['data'] ? $attachment['data'] : $attachment['path'];
        $folding = (int) $rcube->config->get('mime_param_folding');

        $MAIL_MIME->addAttachment($file, $ctype, $attachment['name'], $attachment['data'] ? false : true, $ctype == 'message/rfc822' ? '8bit' : 'base64', 'attachment', $attachment['charset'], '', '', $folding ? 'quoted-printable' : NULL, $folding == 2 ? 'quoted-printable' : NULL, '', RCUBE_CHARSET
        );
      }
    }

    // choose transfer encoding for plain/text body
    if (preg_match('/[^\x00-\x7F]/', $MAIL_MIME->getTXTBody())) {
      $text_charset = $messageCharset;
      $transfer_encoding = $rcube->config->get('force_7bit') ? 'quoted-printable' : '8bit';
    } else {
      $text_charset = 'US-ASCII';
      $transfer_encoding = '7bit';
    }

    if ($flowed) {
      $text_charset .= ";\r\n format=flowed";
    }


    // encoding settings for mail composing
    $MAIL_MIME->setParam('text_encoding', $transfer_encoding);
    $MAIL_MIME->setParam('html_encoding', 'quoted-printable');
    $MAIL_MIME->setParam('head_encoding', 'quoted-printable');
    $MAIL_MIME->setParam('head_charset', $messageCharset);
    $MAIL_MIME->setParam('html_charset', $messageCharset);
    $MAIL_MIME->setParam('text_charset', $text_charset);

    // encoding subject header with mb_encode provides better results with asian characters
    if (function_exists('mb_encode_mimeheader')) {
      mb_internal_encoding($messageCharset);
      $headers['Subject'] = mb_encode_mimeheader($headers['Subject'], $messageCharset, 'Q', "\r\n", 8);
      mb_internal_encoding(RCUBE_CHARSET);
    }

    // pass headers to message object
    $MAIL_MIME->headers($headers);

    return $MAIL_MIME;
  }

  function rcmail_encrypt_header($rcube, $what)
  {
      if (!$rcube->config->get('http_received_header_encrypt')) {
          return $what;
      }
      return $rcube->encrypt($what);
  }
  
  /**
    * Returns RFC2822 formatted current date in user's timezone
    *
    * @return string Date
    */
   function user_date($rcube)
   {
       // get user's timezone
       try {
           $tz   = new DateTimeZone($rcube->config->get('timezone'));
           $date = new DateTime('now', $tz);
       }
       catch (Exception $e) {
           $date = new DateTime();
       }

       return $date->format('r');
   }
   
    /**
    * Returns supported font-family specifications
    *
    * @param string $font  Font name
    *
    * @param string|array Font-family specification array or string (if $font is used)
    */
    function font_defs($font = null)
    {
       $fonts = array(
           'Andale Mono'   => '"Andale Mono",Times,monospace',
           'Arial'         => 'Arial,Helvetica,sans-serif',
           'Arial Black'   => '"Arial Black","Avant Garde",sans-serif',
           'Book Antiqua'  => '"Book Antiqua",Palatino,serif',
           'Courier New'   => '"Courier New",Courier,monospace',
           'Georgia'       => 'Georgia,Palatino,serif',
           'Helvetica'     => 'Helvetica,Arial,sans-serif',
           'Impact'        => 'Impact,Chicago,sans-serif',
           'Tahoma'        => 'Tahoma,Arial,Helvetica,sans-serif',
           'Terminal'      => 'Terminal,Monaco,monospace',
           'Times New Roman' => '"Times New Roman",Times,serif',
           'Trebuchet MS'  => '"Trebuchet MS",Geneva,sans-serif',
           'Verdana'       => 'Verdana,Geneva,sans-serif',
       );

       if ($font) {
           return $fonts[$font];
       }

       return $fonts;
    }   
}

