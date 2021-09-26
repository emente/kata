<?php

class QueueUtility {
	const QM_MSGQUEUE = 1;
	const QM_ZEROMQ = 2;
	const QM_LIBEVENT = 3;
	const QM_FILESOCKET = 4;
	const QM_FILESYS = 99;

	private $queuePath = null;
	private $queueId = null;
	private $queueRes = null;
	private $method = null;

	public function setMethod($method) {
		//todo check obs das gibt und function/extension verfÃ¼gbar
		$this->method = $method;
	}

	public function initialize() {
		if ($this->queuePath) {
			return;
		}
		$this->queuePath = KATATMP . 'queue' . DS;

		if (defined('QUEUE_IDENTIFIER')) {
			$this->queueId = QUEUE_IDENTIFIER;
		} else {
			if (defined('CACHE_IDENTIFIER')) {
				$this->queueId = (int) hexdec(md5(CACHE_IDENTIFIER));
			} else {
				$this->queueId = ftok(__FILE__);
			}
		}

		switch ($this->method) {

			case null:
				throw new Exception('You have to setMethod() first');
				break;

			case self::QM_MSGQUEUE:
				$this->queueRes = msg_get_queue($this->queueId, 0666);
				break;

			case self::QM_ZEROMQ:
				break;

			case self::QM_LIBEVENT:
				break;

			case self::QM_FILESOCKET:
				break;

			case self::QM_FILESYS:
				break;
		}//switch
	}

	public function getQueuePath() {
		$this->initialize();
		return $this->queuePath;
	}

	public function getQueueId() {
		$this->initialize();
		return $this->queueId;
	}

	public function read($desiredTopic=0) {
		$this->initialize();

		switch ($this->method) {

			case null:
				throw new Exception('You have to setMethod() first');
				break;

			case self::QM_MSGQUEUE:
				$topic = 0;
				$msg = null;
				if (msg_receive($this->queueRes, $desiredTopic, &$topic, 16384, &$msg, true, MSG_IPC_NOWAIT | MSG_NOERROR)) {
					return $msg;
				}
				return false;
				break;

			case self::QM_ZEROMQ:
				break;

			case self::QM_LIBEVENT:
				break;

			case self::QM_FILESOCKET:
				$socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
				if (!$socket) {
					return false;
				}
				if (!@socket_connect($socket, $this->queuePath . 'queue.' . $this->queueId)) {
					$err = socket_last_error();
					if (ECONNREFUSED == $err) {
						throw new Exception('Connection error. Queue-server (service.php) not running?');
					}
				}
				if (false === socket_write($socket, serialize(array('topic' => $topic, 'msg' => $msg)))) {
					return false;
				}
				socket_close($socket);
				break;

			case self::QM_FILESYS:
				$files = glob($this->queuePath . $desiredTopic . DS . 'q.*', GLOB_NOSORT);
				if (empty($files)) {
					return null;
				}
				natsort($files);
				$file = array_shift($files);
				$fileContent = file_get_contents($file);
				@unlink($file);
				if (false !== $fileContent) {
					return unserialize($fileContent);
				}
				return false;
				break;
		}//switch
	}

	public function write($topic, $msg) {
		$this->initialize();

		switch ($this->method) {

			case null:
				throw new Exception('You have to setMethod() first');
				break;

			case self::QM_MSGQUEUE:
				$error = 0;
				msg_send($this->queueRes, $topic, $msg, true, false, &$error);
				return 0 == $error;
				break;

			case self::QM_ZEROMQ:
				break;

			case self::QM_LIBEVENT:
				break;

			case self::QM_FILESOCKET:
				$socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
				if (!$socket) {
					return false;
				}
				if (!@socket_connect($socket, $this->queuePath . 'queue.' . $this->queueId)) {
					$err = socket_last_error();
					if (ECONNREFUSED == $err) {
						throw new Exception('Connection error. Queue-server (service.php) not running?');
					}
				}
				if (false === socket_write($socket, json_encode(array('topic' => $topic)))) {
					return false;
				}

				//TODO while() read



				socket_close($socket);
				break;

			case self::QM_FILESYS:
				$topic = urlencode($topic);
				kataMakeTmpPath('queue' . DS . $topic);
				$handle = fopen($this->queuePath . DS . $topic . DS . 'q.' . microtime(true), 'xb');
				if (!$handle) {
					return false;
				}
				fwrite($handle, serialize($msg));
				fclose($handle);
				return true;
				break;
		}//switch
	}

	public function getQueueSize() {
		$this->initialize();

		switch ($this->method) {

			case null:
				throw new Exception('You have to setMethod() first');
				break;

			case self::QM_MSGQUEUE:
				$stats = msg_stat_queue($this->queueRes);
				return is($stats['msg_qnum'], 0);
				break;

			case self::QM_ZEROMQ:
				break;

			case self::QM_LIBEVENT:
				break;

			case self::QM_FILESOCKET:
				break;

			case self::QM_FILESYS:
				break;
		}//switch
	}

	public function clear($topic=0) {
		$this->initialize();

		switch ($this->method) {

			case null:
				throw new Exception('You have to setMethod() first');
				break;

			case self::QM_MSGQUEUE:
				break;

			case self::QM_ZEROMQ:
				break;

			case self::QM_LIBEVENT:
				break;

			case self::QM_FILESOCKET:
				break;

			case self::QM_FILESYS:
				break;
		}//switch
	}

}