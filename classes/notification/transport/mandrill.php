<?php

class Notification_Transport_Mandrill extends Notification_Transport {

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
	 * Send email using Mandrill API.
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
	 *       'reply-to' => array(), // optional
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
		$config = Kohana::$config->load('mandrill');

		$message = $config['default_message'];

		$from = Arr::get($args, 'from', array());
		foreach ($from as $email => $name)
		{
			$message['from_name'] = $name;
			$message['from_email'] = $email;
		}

		$reply_to = Arr::path($args, 'email.reply-to', array());
		foreach ($reply_to as $email => $name)
		{
			$message['headers']['Reply-To'] = $email ? $email : $name;
		}

		$recipients_to = Arr::path($args, 'recipients.to', array());
		foreach ($recipients_to as $email => $name)
		{
			$message['to'][] = array(
				'email' => $email,
				'name' => $name,
			);
		}

		$recipients_cc = Arr::path($args, 'recipients.cc', array());
		foreach ($recipients_cc as $email => $name)
		{
			$message['to'][] = array(
				'email' => $email,
				'name' => $name,
			);
		}

		$recipients_bcc = Arr::path($args, 'recipients.bcc', array());
		foreach ($recipients_bcc as $email => $name)
		{
			$message['bcc_address'] = $email;
		}

		$message['subject'] = Arr::get($args, 'subject');

		foreach (array('html', 'text') as $format)
		{
			if ($body_content = Arr::path($args, 'body-content.' . $format))
			{
				$message[$format] = $body_content;
			}
			else if ($body_tmpl = Arr::path($args, 'body.' . $format))
			{
				$view = new View($body_tmpl, $args['body_args']);
				$message[$format] = $view->render();
			}
		}

		$message['metadata'] = Arr::get($args, 'metadata', array());

		$attachments = Arr::path($args, 'email.attachments', array());
		foreach ($attachments as $attachment)
		{
			$content = '';
			if ($content = Arr::get($attachment, 'content'))
			{
			}
			else if ($filename = Arr::get($attachment, 'src_filename'))
			{
				$content = file_get_contents($filename);
			}
			else
			{
				continue;
			}

			$message['attachments'][] = array(
				'type' => $attachment['type'],
				'name' => $attachment['filename'],
				'content' => $content,
			);
		}

		$request = array(
			'type' => 'messages',
			'call' => 'send',
			'key' => $config['api_key'],
			'message' => $message,
		);

		try
		{
			$ret = Mandrill::call($request);

			Log::instance()->add(Log::DEBUG, "Mandrill API result: " . $ret);
			return $ret;
		}
		catch (Exception $e)
		{
			$token = uniqid();
			Log::instance()->add(Log::DEBUG, "Token: $token, \n" . var_export($request, 1));
			Log::instance()->add(Log::DEBUG, $token . ':' . var_export($e, 1));

			throw new Notification_Exception('Failed to send email notification, token ' . $token);
		}
		return null;
	}
}
