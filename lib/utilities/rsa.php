<?php

/**
 * contains Rsa-class
 * @package kata
 */

/**
 * rsa asymetric crypto related functions
 *
 * expects pem-style rsa-keys as 'vendors/rsakeys/pub.pem' and
 * 'vendors/rsakeys/priv.pem'. You can change the path by
 * setKeyPath()
 *
 *
 * @package kata_utility
 * @author joachim.eckert@gameforge.de
 * @author mnt@codeninja.de
 */
class RsaUtility {

	function __construct() {
		if (!function_exists('openssl_public_encrypt')) {
			throw new Exception('openssl extension not loaded :(');
		}

		$this->setKeyPath('vendors' . DS . 'rsakeys' . DS);
		$this->setEncoder('json');
	}

	////////////////////////////////////////////////////////////////////////////

	/**
	 * filesystem path to key storage
	 * @var string
	 */
	private $keyPath = '';

	/**
	 * read current filesystem path for key storage
	 * @return string
	 */
	public function getKeyPath() {
		return ROOT . $this->keyPath;
	}

	/**
	 * set filesystempath where to find keys
	 * @param string $path
	 */
	public function setKeyPath($path) {
		$this->keyPath = str_replace(ROOT, '', $path);
	}

	/**
	 * storage for priv/pub key
	 * @var array
	 */
	private $keys = array();

	/**
	 * read key from filesystem
	 * @param string $what can be 'pub' or 'priv'
	 * @return string
	 */
	public function getKey($what) {
		if (('pub' != $what) && ('priv' != $what)) {
			throw new Exception('$what can only be "pub" or "priv". typo?');
		}

		if (!empty($this->keys[$what])) {
			return $this->keys[$what];
		}

		if (!file_exists($this->getKeyPath() . $what . '.pem')) {
			throw new Exception('Cant find ' . $what . '.pem in ' . $this->getKeyPath());
		}
		$key = file_get_contents($this->getKeyPath() . $what . '.pem');
		if (!empty($key)) {
			$this->keys[$what] = $key;
			return $key;
		}
		throw new Exception('Cant read ' . $what . '.pem in ' . $this->getKeyPath());
	}

	/**
	 * return our public key
	 * @return string
	 */
	public function getPublicKey() {
		return $this->getKey('pub');
	}

	/**
	 * return our private key
	 * @return string
	 */
	public function getPrivateKey() {
		return $this->getKey('priv');
	}

	////////////////////////////////////////////////////////////////////////////

	/**
	 * which de/encoder function to use
	 * @var string
	 */
	private $encoder = 'json';

	/**
	 * set de/encoder function. can be (for examle) json,base64 etc
	 * @param string $encoder
	 */
	public function setEncoder($encoder) {
		if (null === $encoder) {
			$this->encoder = null;
			return;
		}

		$encoder = strtolower($encoder);
		if (!function_exists($encoder . '_encode') || !function_exists($encoder . '_decode')) {
			throw new Exception($encoder . '_encode/decode not found');
		}

		$this->encoder = $encoder;
	}

	/**
	 * read currently used de/encoder function
	 * @return string
	 */
	public function getEncoder() {
		return $this->encoder;
	}

	/**
	 * encode given string using currently set encoder function
	 * @param string $data
	 * @return string
	 */
	public function encode($data) {
		if (null === $this->encoder) {
			return $data;
		}

		$func = $this->encoder . '_encode';
		return $func($data);
	}

	/**
	 * decode given string using currently set decoder function
	 * @param string $data
	 * @return string
	 */
	public function decode($data) {
		if (null === $this->encoder) {
			return $data;
		}
		if ('json' == $this->encoder) {
			return json_decode($data, true);
		}

		$func = $this->encoder . '_decode';
		return $func($data);
	}

	////////////////////////////////////////////////////////////////////////////

	/**
	 * encrypts data asymetrically
	 *
	 * first serializes the data, then encrypts it and finally base64-encodes it
	 * best used to crypt data which can be decrypted with decryptAsymetric()
	 *
	 * @param mixed $data:           the data to encrypt
	 * @param mixed $receiverPubKey: the public key of the receiver
	 * @param mixed $ownPrivKey:     own private key, needed if data should also be signed (defaults to null)
	 * @param bool  $sign:           true if signature should be added (defaults to false)
	 *
	 * @return mixed: the encrypted data, false on error
	 */
	public function encrypt($data, $receiverPubKey, $sign = false) {
		$crypted = null;
		$signature = null;

		if (!openssl_public_encrypt(serialize($data), $crypted, $receiverPubKey)) {
			return false;
		}

		// get signature
		if ($sign) {
			$signature = $this->getSignature($crypted);
			if ($signature === false) {
				return false;
			}
		}

		$crypted = $this->encode($crypted);

		// add signature
		if (!empty($signature)) {
			$crypted .= "|$signature";
		}

		return $crypted;
	}

	/**
	 * decrypts asymetric crypted data
	 * first base64-decodes it, then decrypts it and finally unserializes it
	 * best used to decrypt data encrypted with encryptAsymetric()
	 *
	 * @param string $data:           the encrypted data
	 * @param mixed  $senderPubKey:   the public key of the sender, needed for signature check (defaults to null)
	 * @param bool   $checkSignature: true if signature should be checked (defaults to false)
	 *
	 * @return mixed: the decrypted data, false on error
	 */
	public function decrypt($data, $senderPubKey = null, $checkSignature = false) {
		$data = explode("|", $data);
		$data[0] = base64_decode($data[0]);
		if ($data[0] == false) {
			return false;
		}

		$ownPrivKey = $this->getPrivateKey();

		// verify signature
		if ($checkSignature) {
			if (empty($senderPubKey) || empty($data[1]) || !$this->verifySignature($data[0], $data[1], $senderPubKey)) {
				return false;
			}
		}

		$decrypted = null;
		if (!openssl_private_decrypt($data[0], $decrypted, $ownPrivKey)) {
			return false;
		}

		return unserialize($decrypted);
	}

	////////////////////////////////////////////////////////////////////////////

	/**
	 * generates an asymetric signature
	 *
	 * @param string $data:          the data to sign
	 * @param mixed  $ownPrivateKey: the own private key to sign the data with
	 *                               (as resource or pem representation)
	 *
	 * @return string: the signature (base64-encoded)
	 */
	private function getSignature($data) {
		$signature = null;
		$ownPrivateKey = $this->getPrivateKey();

		if (!openssl_sign($data, $signature, $ownPrivateKey, OPENSSL_ALGO_SHA1)) {
			return false;
		}

		return $this->encode($signature);
	}

	/**
	 * verifies an asymetric signature
	 *
	 * @param string $data:            the data which has been signed
	 * @param string $signature:       the signature to check (base64-encoded)
	 * @param mixed  $senderPublicKey: the public key of the signing party
	 *
	 * @return bool: true if signature was correct, false if incorrect or other error
	 */
	private function verifySignature($data, $signature, $senderPublicKey) {
		if (openssl_verify($data, base64_decode($signature), $senderPublicKey, OPENSSL_ALGO_SHA1) !== 1) {
			return false;
		}
		return true;
	}

}
