<?php
/**
 * A robust class which collects a single-dimension array of user data
 * ready for use by MODx snippets to set as placeholders.
 *
 * Example usage in snippets:
 * // Include the file or use $modx->loadClass
 * $UserInfo = new UserInfo($modx,$scriptProperties);
 * $UserInfo->setUser($user);
 * $UserInfo->process();
 * $userArray = $UserInfo->toArray();
 * 
 * Ready for extension: this class is designed to be extended
 * Tip: the snippets that come with it allow you to switch to a custom class
 * Example: class UserInfoCustom extends UserInfo
 * Example file structure:
 * /model/userinfo/userinfocustom/userinfocustom.class.php
 * 
 * File         userinfo.class.php (requires MODx Revolution 2.x)
 * Created on   May 14, 2011
 * @package     userinfo
 * @version     1.0
 * @category    User Extension
 * @author      Oleg Pryadko <oleg@websitezen.com>
 * @link        http://www.websitezen.com
 * @copyright   Copyright (c) 2011, Oleg Pryadko.  All rights reserved.
 * @license     GPL v.2 or later
 *
 */
class UserInfo {
/*
 * a modUser object
 * @var modUser
 */
	protected $user;
/*
 * A reference to $this->user->getOne('Profile')
 * @var modUserProfile
 */
	protected $profile;
/*
 * Stores the user data
 * Reset whenever $this->setUser() is called.
 * @var array
 */
	protected $data = array();
/*
 * An array of options
 * @var array
 */
	protected $config = array();

	
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        
        $corePath = $modx->getOption('userinfo.core_path',$config,$modx->getOption('core_path',null,MODX_CORE_PATH).'components/userinfo/');
        $this->config = array_merge(array(
            'corePath' => $corePath,
            'modelPath' => $corePath.'model/',
            'chunksPath' => $corePath.'chunks/',
            'processorsPath' => $corePath.'processors/',
        ),$config);
		
		/* prefixes */
		if (!is_array($this->config['prefixes'])) $this->config['prefixes'] = array();
		$this->config['prefixes']['remote_data'] = $this->modx->getOption('userinfo_remote_prefix',$this->config,'remote.');
		$this->config['prefixes']['extended'] = $this->modx->getOption('userinfo_extended_prefix',$this->config,'');
		$this->config['prefixes']['calculated'] = $this->modx->getOption('userinfo_calculated_prefix',$this->config,'');
  
		/* generate the default protected fields array - sets defaults as a security fallback */
        $this->config['protected_fields_array'] = $this->config['protected_fields'] ? explode(',',$this->config['protected_fields']) : array('sessionid','password','cachepwd');
	}

/* *************************** */
/*  IMPORTANT METHODS            */
/* *************************** */

/*
 * Sets the user object - "Begins"
 * @param $user modUser
 * @return bool success
 */
	public function setUser(modUser &$user) {
		$this->user =& $user;
		$this->profile = $this->user->getOne('Profile');
		$this->data = array();
		return true;
	}

/*
 * Returns $this->data array - "Ends"
 * @return array the final userdata array
 */
	public function toArray() {
		return $this->data;
	}

/*
 * The main controller function
 * Extend this class with your own site-specific class and write your own process() function to control the order of execution and add logic.
 * An alternative is to bypass this method and just call the various methods directly in your snippets
 * @return bool success
 */
// Possible change: maybe replace with hooks/ plugins?
	public function process() {
        // execute the methods
        // note: the order of execution may be important!
		$this->getUserData();
		$this->getUserProfile();
		$this->extendedData();
		$this->remoteData();
		$this->calculateData();
		// Unsets some protected fields - by default removes fields in $this->config['protected_fields']
		$this->_protectData();
		return true;
	}
    
    
/* *************************** */
/*   Meant for extension       */
/* *************************** */

/*
 * Calculates custom data
 * Extend this class with your own site-specific class and write your own calculateData() function to add custom calculations
 * @return bool success
 */
	public function calculateData() {
		$prefix = $prefix ? $prefix : $this->config['prefixes']['calculated'];
        
		// add calculations
		// the order of the calculations is important!
        // Here is an example calculation, which adds the "self" placeholder if user is logged in.
		$this->data[$prefix.'self'] = (int) $this->calculateSelf();
		
		return true;
	}

/* 
 * A UserData calculation - sets "self" to true if data belongs to logged-in user
 * You can add more calculations like this in your own custom class
 * @return bool Returns true if self and false if not self
 */
	public function calculateSelf() {
		$user = $this->user->get('id');
        $self = false;
		if ($user && ($user == $this->modx->user->get('id'))) {
			$self = true;
		}
		return $self;
	}

/* *************************** */
/*  DEFUAL USER DATA METHODS   */
/* *************************** */

/* 
 * gets calculated data from $this::user and adds it to $this::data.
 * @return bool success
 */
	public function getUserData(array $fields = array('id','username','active','class_key')) {
		// $user_array = $this->user->toArray();
		$user_array = $this->user->get($fields);
		return $this->_mergeWithData($user_array);
	}
    
/* 
 * gets calculated data from $this::profile and adds it to $this::data.
 * @return bool success
 */
	public function getUserProfile() {
		/* get profile */
		$profile = $this->profile;
		if (empty($profile)) {
			$this->modx->log(modX::LOG_LEVEL_ERROR,'Could not find profile for user: '.$this->user->get('username'));
			return '';
		}
		$profile_array = $profile->toArray();
		unset($profile_array['extended']);
		return $this->_mergeWithData($profile_array);
	}
    
/* 
 * Parses extended data and adds it to $this::data
 * @param $prefix string Optional prefix to override the default extended prefix
 * @return bool success
 */
	public function extendedData($prefix = '') {
		if (!$prefix) $prefix = $this->config['prefixes']['extended'];
		$data = $this->profile->get('extended');
		return $this->_processDataArray($data,$prefix);
	}
    
/* 
 * Parses remote data and adds it to $this::data
 * @param $prefix string Optional prefix to override the default remote_data prefix
 * @return bool success
 */
	public function remoteData($prefix = '') {
		if (!$prefix) $prefix = $this->config['prefixes']['remote_data'];
		$data = $this->user->get('remote_data');
		return $this->_processDataArray($data,$prefix);
	}
    
   
/* *************************** */
/*  UTILITY METHODS            */
/* *************************** */
/*
 * Adds an array to $this::data
 * Also returns the same array
 * @param $data array   An associative array of fields with values to merge into $this::data
 * @return array    The resulting array
 */ 
	protected function _mergeWithData($data) {
		if (is_array($data) && (!empty($data))) {
			$this->data = array_merge($data,$this->data);
		}
		return $this->data;
	}

/* 
 * Unsets protected fields
 * If no parameter is passed, uses $this->config['protected_fields']
 * You HAVE to have protected_fields either in config or as a method parameter. To disable, just list a field that doesn't exist.
 * This is a security fall-back.
 * @param $fields string An optional comma-separated list of field names to unset
 * @return bool Always true
 */
	protected function _protectData(array $fields = array()) {
        $fields = $fields ? $fields : $this->config['protected_fields_array'];
        $success = false;
		foreach ($fields as $field) {
			if (isset($this->data[$field])) {
				unset($this->data[$field]);
			}
		}
		return true;
	}

    
/* 
 * Attaches a prefix to each key of an array
 * @param $array array    A single-dimensional array
 * @param $prefix string    The prefix to add
 * @return array    Same array, but prefixed!
 */
	protected function _attachPrefix(array $array,$prefix) {
		// attaches prefix
		if ($prefix) {
			$new_array = array();
			foreach ($array as $key => $value) {
				$new_array[$prefix.$key] = $value;
			}
			return $new_array;
		}
		return $array;
	}
	
/* 
 * Processes an array into proper format
 * Creates a single-dimension array from a multi-dimension array such as the extended and remoted_data fields
 * @param $data array An associative array of fields with values to process
 * @param $prefix string    The prefix to add
 * @return bool    Success.
 */
	protected function _processDataArray(array $data,$prefix) {
		$data_array = array();
		if (!empty($data) && is_array($data)) {
		  foreach($data as $key => $value) {
			if (is_array($value)) {
				foreach($value as $key2 => $value2) {
					if(is_array($value2)) {
						$new_value2 = print_r($value2,1);
					} else {
						$new_value2 = $value2;
					}   
					$data_array[$key.'.'.$key2] = $new_value2;
				}
			} else {
			  $data_array[$key] = $value;
			}
		  }
		  // $data_array['debug'] = 'Placeholder Array: '.print_r($data_array,1);
		}
		$data_array = $this->_attachPrefix($data_array,$prefix);
		return $this->_mergeWithData($data_array);
	}
}