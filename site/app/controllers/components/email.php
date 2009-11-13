<?php
/**
 * This is a component to send email from CakePHP using PHPMailer
 * @link http://wiki.cakephp.org/tutorials:sending_email_with_phpmailer
 * @see http://wiki.cakephp.org/tutorials:sending_email
 */
 
class EmailComponent
{
/**
 * Send email using SMTP Auth by default.
 */
    var $from         = NOBODY_EMAIL; 
    var $fromName     = SITE_NAME;
    var $sender       = null;
    //var $smtpUserName = 'username';  // SMTP username
    //var $smtpPassword = 'password'; // SMTP password
    //var $smtpHostNames= "smtp1.example.com;smtp2.example.com";  // specify main and backup server
    var $text_body = null;
    var $html_body = null;
    var $to = null;
    var $toName = null;
    var $subject = null;
    var $cc = null;
    var $bcc = null;
    var $template = null; // rendering template for email
    var $in_reply_to = null;
    var $reply_to = array();
 
    var $controller;
 
    function startup( &$controller ) {
        $this->controller = &$controller;
    }
 
    function bodyText() {
    /** 
     * This is the body in plain text for non-HTML mail client
     */
        $temp_layout = $this->controller->layout;
        $this->controller->layout = ""; // turn off the layout wrapping
        ob_start();
        $this->controller->render($this->template.'_plain');
        $mail = ob_get_clean();
        $this->controller->layout = $temp_layout;
        return $mail;
    }
 
    function bodyHTML() {
    /** 
     * This is HTML body text for HTML-enabled mail clients
     */
        $temp_layout = $this->controller->layout;
        $this->controller->layout = ""; // add html wrapper for emails here if necessary
        ob_start();
        $this->controller->render($this->template.'_html');
        $mail = ob_get_clean();
        $this->controller->layout = $temp_layout;
        return $mail;
    }
 
 
    function send($html = false, $reply = null)
    {
        vendor('phpmailer'.DS.'class.phpmailer');
 
        $mail = new PHPMailer();
        
        $mail->IsMail();            // set mailer to use PHP's mail()
        //$mail->IsSMTP();            // set mailer to use SMTP
        //$mail->SMTPAuth = true;     // turn on SMTP authentication
        //$mail->Host     = $this->smtpHostNames;
        //$mail->Username = $this->smtpUserName;
        //$mail->Password = $this->smtpPassword;
 
        $mail->From     = $this->from;
        // if "Sender" field is set, add correct Sender header (cf. RFC5322 § 3.6.2)
        if (!empty($this->sender)) {
            $mail->Sender   = $this->sender;
            $mail->AddCustomHeader("Sender: {$this->sender}");
        } else {
            $mail->Sender   = $this->from;
        }
        $mail->FromName = $this->fromName;
        $mail->AddAddress($this->to, $this->toName );

        if (!$reply)
            $reply = array();
        else if (!is_array($reply))
            $reply = array($reply);
        $reply = array_merge($reply, $this->reply_to);

        if ($reply)
            foreach($reply as $i)
                $mail->AddReplyTo($i);
        else
            $mail->AddReplyTo($this->from, $this->fromName );
 
        $mail->CharSet  = 'UTF-8';
        //$mail->WordWrap = 50;               // set word wrap to 50 characters
        //$mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
        //$mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
        $mail->IsHTML($html);                                  // set email format to HTML
        
        $mail->Subject = $this->subject;
        if ($html) {
            $mail->Body    = $this->bodyHTML();
            $mail->AltBody = $this->bodyText();
        } else {
            $mail->Body    = $this->bodyText();
        }

        if ($this->cc)
            foreach($this->cc as $i)
                $mail->AddCC($i);
        
        $success = $mail->Send($this->in_reply_to);
        return $success;
    }
}
?>
