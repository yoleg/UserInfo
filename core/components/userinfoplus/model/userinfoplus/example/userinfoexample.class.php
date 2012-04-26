﻿<?php
/**
 * Example custom userinfoplus class - make your own!
 * Original class is already required in the snippet
 */
class UserInfoPlusExample extends UserInfoPlus {
    
    function __construct(modX &$modx,array $config = array() ) {
        parent::__construct($modx,$config);
    }
    
    /*
     * Overrides main calculateData from UserInfoPlus parent class
     * Add the new custom calculations here
     */

  	public function calculateData() {
		$prefix = $this->config['prefixes']['calculated'];
		$this->_data[$prefix.'self'] = (int) $this->calculateSelf();
		$this->_data[$prefix.'email'] = $this->calculateEmail();
		$this->_data[$prefix.'gravatar'] = $this->calculateGravatar(80);
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
		$output = $email = $this->_data['email'];
		$remote_email = $this->_data['remote.email'];
		if (!$output) $output = $remote_email;
		return $output;
	}
	
	/*
	 * Runs the gravatar snippet and returns the resulting URL
	 * Sets $this::$parameter to the result unless $parameter is set to false
	 */
	public function calculateGravatar($size=80) {
		$email = $this->_data['email'];
		// if the email is empty, generate the gravatar anyways
		if (empty($email)) { $email = $this->_data['id']; }
		$output = $this->modx->runSnippet('Gravatar',array(
			'email' => $email,
			'size' => $size
		));
		return $output;
	}
    
}
