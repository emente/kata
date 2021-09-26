<?php
/**
 * contains eventer utility
 * 
 * @package kata_utility
 */


/**
 * a event dispatcher class using shared memory for inter-process communication
 *
 * @author mnt@codeninja.de
 * @package kata_utility
 */
class EventerUtility {

	protected $shmHandle=null;

	protected $semHandle=null;

	protected $msgHandle=null;

	protected function getId() {
		//is kata there?
		if (defined('CACHE_IDENTIFIER')) {
			return CACHE_IDENTIFIER;
		}
		return 1;
	}

	protected function initializeShm() {
		if (null !== $this->shmHandle) { return; }

		$this->shmHandle = shm_attach($this->getId(), 60*1024);
		$this->semHandle = sem_get($this->getId());
	}

	protected function initQueue() {
		if (null !== $this->msgHandle) { return; }

		$this->msgHandle = msg_get_queue($this->getId());
	}

	function __destruct() {
		if (null !== $this->shmHandle) {
			shm_detach($this->shmHandle);
		}
		if (null !== $this->semHandle) {
			sem_release($this->semHandle);
		}
	}

/**
 * register a listener function 
 * 
 * @param string $eventname name of the event to register
 * @param mixed function to call in call_user_func format 
 */
	function registerListener($eventname,$func) {
		
	}

/**
 * send a event to ALL listeners
 * 
 * @param string $eventname name of the event to register
 */
	function notifyAll($eventname) {
		
	}

/**
 * send a event to ALL listeners
 * 
 * @param string $eventname name of the event to register
 * @param mixed $params optional parameters to send to function
 * @return mixed optional return params from listener
 */
	function notifyUntil($eventname,$params=null) {
		
	}

	function filter($eventname){
		
	}


}

