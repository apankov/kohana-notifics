<?php

class Notification_Transport_Email extends Notification_Transport {

	public function send_async($args = array(), $when = null)
	{
		// @todo: decouple
		// @todo: sync about time when message should be sent
		Resque::enqueue('notifications_queue', get_class($this), $args);
	}

	// called by PHP-Resque
	public function perform()
	{
		return $this->send($this->args);
	}

	/**
	 * Send email.
	 *
	 * Example of arguments array:
	 *
	 * <pre> 
	 *   $args = array(
	 *     'subject' => 'order not paid', // move to config / i18n
	 *     'body-content' => array(
	 *       'html' => '<h1>hello world</h1>', // rendered body content
	 *       'text' => '', // optional
	 *     ),
	 *     'body' => array(
	 *       'html' => 'order_not_paid', // full path to template, for example notifications/email/order_not_paid
	 *       'text' => '', // optional
	 *     ),
	 *     'from' => '',
	 *     'recipients' => array(
	 *       'to' => array(), // optional
	 *       'cc' => array(), // optional
	 *       'bcc' => array(), // optional
	 *     ),
	 *     'email' => array( // email specific options
	 *       'smtp' => 'default', // name of smtp config
	 *       'reply-to' => array(), // optional
	 *       'return-path' => '', // optional
	 *       'signature' => '', // optional, used in template
	 *       'attachments' => array( // optional
	 *         array(
	 *           'src_filename' => '', // used either filename ...
	 *           'content' => '', // ... or content
	 *           'type' => '', // mime
	 *           'dst_filename' => '',
	 *         ),
	 *       ),
	 *     ),
	 *     'body_args' => array(),
	 *   );
	 * </pre> 
	 *
	 * @param $args array
	 * @param $when
	 */
	public function send($args = array(), $when = null)
	{
		$smtp_config = Arr::path($args, 'email.smtp', 'default');
		$smtp_config = Kohana::config('notification.smtp.' . $smtp_config);

		$transport_smtp = new Zend_Mail_Transport_Smtp($smtp_config['host'], $smtp_config);

		$mail = new Zend_Mail();

		if ($return_path = Arr::path($args, 'email.return-path'))
		{
			$mail->setReturnPath($return_path);
		}
		else
		{
			$mail->setReturnPath(Arr::get($smtp_config, 'return-path', ''));
		}

		$from = Arr::get($args, 'from', array());
		if (empty($from))
		{
			$from = Arr::get($smtp_config, 'from', array());
		}
		foreach ($from as $email => $name)
		{
			$mail->setFrom($email, $name);
		}

		$reply_to = Arr::path($args, 'email.reply-to', array());
		foreach ($reply_to as $email => $name)
		{
			$mail->setReplyTo($email, $name);
		}

		$recipients_to = Arr::path($args, 'recipients.to', array());
		foreach ($recipients_to as $email => $name)
		{
			$mail->addTo($email, $name);
		}

		$recipients_cc = Arr::path($args, 'recipients.cc', array());
		foreach ($recipients_cc as $email => $name)
		{
			$mail->addCc($email, $name);
		}

		$recipients_bcc = Arr::path($args, 'recipients.bcc', array());
		foreach ($recipients_bcc as $email => $name)
		{
			$mail->addBcc($email, $name);
		}

		$mail->setSubject(Arr::get($args, 'subject'));

		foreach (array('html' => 'setBodyHtml', 'text' => 'setBody') as $format => $method)
		{
			if ($body_content = Arr::path($args, 'body-content.' . $format))
			{
				call_user_func(array($mail, $method), $body_content);
			}
			else if ($body_tmpl = Arr::path($args, 'body.' . $format))
			{
				$view = new View($body_tmpl, $args['body_args']);
				call_user_func(array($mail, $method), $view->render());
			}
		}

		$attachments = Arr::path($args, 'email.attachments', array());
		foreach ($attachments as $attachment)
		{
			if ($filename = Arr::get($attachment, 'src_filename'))
			{
				$attach = $mail->createAttachment(file_get_contents($filename));
			}
			else if ($content = Arr::get($attachment, 'content'))
			{
				$attach = $mail->createAttachment($content);
			}
			else
			{
				continue;
			}
			$attach->type = $attachment['type'];
			$attach->filename = $attachment['filename'];
		}

		try
		{
			$mail->send($transport_smtp);
		}
		catch (Exception $e)
		{
			$token = uniqid();
			Kohana::$log->add(Kohana::DEBUG, "Token: $token, \n" . var_export($mail, 1));

			// do not want full stacktrace
			$exc = array(
				'string' => $e->__toString(),
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString(),
			);
			Kohana::$log->add(Kohana::ERROR, $token . ':' . var_export($exc, 1));

			throw new Notification_Exception('Failed to send email notification, token ' . $token);
		}
	}
}
