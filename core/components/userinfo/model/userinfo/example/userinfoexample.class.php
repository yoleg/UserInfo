<?php
/**
 * Example custom userinfo class - make your own!
 */

require_once(dirname(dirname(__FILE__)).'/userinfo.class.php');

class UserInfoExample extends UserInfo {
    
    function __construct(modX &$modx,array $config = array() ) {
        parent::__construct($modx,$config);
    }
    
    /*
     * Overrides main calculateData from UserInfo parent class
     * Add the new custom calculations here
     */

  	public function calculateData() {
		$prefix = $this->config['prefixes']['calculated'];
		$this->data[$prefix.'self'] = (int) $this->calculateSelf();
		$this->data[$prefix.'email'] = $this->calculateEmail();
		$this->data[$prefix.'gravatar'] = $this->calculateGravatar(80);
		return true;
	}
    
	/* *************************** */
	/*  CALCULATION METHODS        */
	/* *************************** */
	
	/*
	 * If the email is left blank, uses the remote email
	 * Sets $this::$parameter to the result unless $parameter is set to false
	 */
	public function calculateEmail() {
		// ToDo: possibly add some email validation
		$output = $email = $this->data['email'];
		$remote_email = $this->data['remote.email'];
		if (!$output) $output = $remote_email;
		return $output;
	}
	
	/*
	 * Runs the gravatar snippet and returns the resulting URL
	 * Sets $this::$parameter to the result unless $parameter is set to false
	 */
	public function calculateGravatar($size=80) {
		$email = $this->data['email'];
		// if the email is empty, generate the gravatar anyways
		if (empty($email)) { $email = $this->data['id']; }
		$output = $this->modx->runSnippet('Gravatar',array(
			'email' => $email,
			'size' => $size
		));
		return $output;
	}
    
}
