<?php
/**
 * A class which collects a single-dimension array of user data
 * ready for use by MODx snippets to set as placeholders.
 *
 * Example usage in snippets:
 * // Include the file or use $modx->loadClass
 * $UserInfoPlus = new UserInfoPlus($modx,$scriptProperties);
 * $UserInfoPlus->setUser($user); // user information is cached, so switch users as often as necessary
 * $userArray = $UserInfoPlus->toArray();
 * 
 * Ready for extension: this class is designed to be extended
 * Add camel case methods in the format 'calculate'.$camelCaseFieldName
 * For example, for the field 'sent_massages', create a method called calculateSentMessages that returns the proper value
 *
 * This class also handles setting data with $UserInfoPlus->setToUser('color','blue','extended');
 * Add camel case methods in the format 'derive'.$camelCaseFieldName to set custom setters
 * For example, if you want to separate the field 'full_name' into first and last values automatically, create a method
 * called deriveFullName and make it automatically save the right values whenever saving the key 'full_name'
 *
 * Then, save the user with $UserInfoPlus->saveUser()
 *
 * Tip: the snippets that come with this package allow you to switch to a custom class
 * Example: class UserInfoCustom extends UserInfoPlus
 * Example file structure:
 * /model/userinfoplus/userinfocustom/userinfocustom.class.php
 * 
 * File         userinfoplus.class.php (requires MODx Revolution 2.x)
 * Created on   May 14, 2011
 * @package     userinfoplus
 * @version     2.0
 * @category    User Extension
 * @author      Oleg Pryadko <oleg@websitezen.com>
 * @link        http://www.websitezen.com
 * @copyright   Copyright (c) 2011, Oleg Pryadko.  All rights reserved.
 * @license     GPL v.2 or later
 *
 */
class UserInfoPlus {
    /** @var modUser A reference to the modUser object */
	protected $user;
    /** @var modUserProfile A reference to $this->user->getOne('Profile') */
	protected $_profile;
    /** @var array Stores the user data. */
	protected $_data = array();
    /** @var array Options array. */
	public $config = array();
    /** @var array Cache array. */
	protected $_cache = array();

    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;

        $corePath = $modx->getOption('userinfoplus.core_path',$config,$modx->getOption('core_path',null,MODX_CORE_PATH).'components/userinfoplus/');
        $this->config = array_merge(array(
            'corePath' => $corePath,
            'modelPath' => $corePath.'model/',
            'chunksPath' => $corePath.'chunks/',
            'processorsPath' => $corePath.'processors/',
        ),$config);

		/* prefixes */
		if (!isset($this->config['prefixes']) || !is_array($this->config['prefixes'])) $this->config['prefixes'] = array();
		$this->config['prefixes']['remote'] = $this->modx->getOption('userinfo_remote_prefix',$this->config,'remote.');
		$this->config['prefixes']['extended'] = $this->modx->getOption('userinfo_extended_prefix',$this->config,'');
		$this->config['prefixes']['calculated'] = $this->modx->getOption('userinfo_calculated_prefix',$this->config,'');

		/* generate the default protected fields array - sets defaults as a security fallback */
        $this->config['protected_fields_array'] = isset($this->config['protected_fields']) ? explode(',',$this->config['protected_fields']) : array('sessionid','password','cachepwd');
        $this->config['calculated_fields'] = array('self');

        $this->config['field_types'] = array(
            'user' => array_keys($modx->getFields('modUser')),
            'profile' => array_keys($modx->getFields('modUserProfile')),
        );
    }

/* *************************** */
/*  IMPORTANT METHODS            */
/* *************************** */

    /**
     * Sets the user object - "Begins"
     * @param $user modUser
     * @return bool success
     */
	public function setUser(modUser &$user) {
        $userid = $user->get('id');
        if (!$userid) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,"UserInfoPlus: userinfoplus only works with saved users who already have a user id. Could not set user with username {$user->get('username')}.");
            return false;
        }
        if (!is_null($this->user)) {
            $cache_array = array(
                'user' => $this->user,
                'profile' => $this->_profile,
                'data' => $this->_data,
                'cache' => $this->_cache,
            );
            $this->_setCache('setUser',$this->user->get('id'),$cache_array);
        }
        $old_data = $this->_getCache('setUser',$userid);
		$this->user = $user;
		$this->_profile = isset($old_data['profile']) &&
                $old_data['profile'] instanceof modUserProfile ? $old_data['profile'] : $user->getOne('Profile');
		$this->_data = isset($old_data['data']) ? $old_data['data'] : array();
		$this->_cache = isset($old_data['cache']) ? $old_data['cache'] : array();
		return true;
	}

    /**
     * Returns $this->data array after processing it
     * @param string $prefix
     * @return array the final userdata array
     */
	public function toArray($prefix = '') {
        $this->process();
        if (empty($prefix)) {
            $output = $this->_data;
        } else {
            $output = array();
            foreach ($this->_data as $k => $v) {
                $output[strval($prefix).strval($k)] = (string) $v;
            }
        }
        return $output;
	}

    /**
     * The main controller function
     * Extend this class with your own site-specific class and write your own process() function to control the order of execution and add logic.
     * An alternative is to bypass this method and just call the various methods directly in your snippets
     * @param array $methods
     * @return bool success
     */
	public function process($methods = array('getAllUserData','getAllProfileData','getAllExtendedData','getAllRemoteData','calculateData')) {
        // execute the methods
        // note: the order of execution may be important!
        foreach($methods as $method) {
            $this->_callMethodOnce($method);
        }
		// Unsets some protected fields - by default removes fields in $this->config['protected_fields']
		$this->_protectData($this->_data);
		return true;
	}

    /**
     * Returns a field value
     * @param $fieldname string the field to get
     * @param $source string The source type (other than user or profile data). Default source types: 'extended', 'remote_data', and 'calculated'.
     * @param null $folder
     * @param $methods_to_try array An array of methods to try in order
     * @return string the value
     */
	public function get($fieldname, $source=null, $folder=null, $methods_to_try=null) {
        if (is_null($source)) $source = $this->_getFieldType($fieldname);
        $prefix = is_string($source) && isset($this->config['prefixes'][$source]) ? $this->config['prefixes'][$source] : '';
        $fieldname = $prefix.$fieldname;
        if (isset($this->_data[$fieldname])) {
            $output = $this->_data[$fieldname];
        } else {
            $source_method = (is_string($source) && !empty($source) && $source != 'calculated') ? 'getAll'.ucfirst($source).'Data' : '';
            if (is_null($methods_to_try) &&  $source_method && method_exists($this,$source_method)) {
                $methods_to_try = array($source_method);
            } else {
                $methods_to_try = is_array($methods_to_try) && !empty($methods_to_try) ? $methods_to_try : array('getAllUserData','getAllProfileData','getAllExtendedData');
                $calc_method = $this->_getCalcMethodName($fieldname);
                $methods_to_try[] = $calc_method;
            }
            $output = $this->_findFieldValue($fieldname,$methods_to_try);   // will automatically set string values
            // if all else fails, throw a warning and return a blank string
            if (is_null($output)) {
                $output = $this->getDefault($fieldname, $source);
                $this->set($fieldname,$output);
            }
            $this->set($fieldname,$output);
        }
        return $output;
	}

    public function getDefault($fieldname, $source=null) {
        return '';
    }

    /**
     * Sets a field value
     * @param $field string the field name to set
     * @param $value string the value to set
     * @param $prefix_type string The prefix to use
     * @return mixed The value set
     */
	public function set($field,$value,$prefix_type = null) {
        if (is_string($field) && !empty($field)) {
            $this->_data[$prefix_type.$field] = (string) $value;
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR,"UserInfoPlus::set: Field must be a string.");
        }
        return $value;
	}

/* *************************** */
/*   Meant for extension       */
/* *************************** */
    /** Calculates custom data
     * @internal param null $prefix
     * @return bool success
     */
	public function calculateData() {
        $fields = $this->config['calculated_fields'];
        foreach($fields as $field) {
            $this->get($field,'calculated');
        }
		return true;
	}

    /**
     * A UserData calculation - sets "self" to true if data belongs to logged-in user
     * You can add more calculations like this in your own custom class
     * @return string Empty string if self and false if not self
     */
	public function calculateSelf() {
		$user = $this->user->get('id');
        $self = '';
		if ($user && ($user == $this->modx->user->get('id'))) {
			$self = 'self';
		}
		return $self;
	}

/* *************************** */
/*  DEFUAL USER DATA METHODS   */
/* *************************** */

    /** gets standard user data from $this::user and adds it to $this::data.
     * @param array $fields
     * @return array
     */
	public function getAllUserData(array $fields = array('id','username','active','class_key')) {
		// $user_array = $this->user->toArray();
		$user_array = $this->user->get($fields);
		$this->_mergeWithData($user_array);
        return $user_array;
	}
    /**
     * gets calculated data from $this::profile and adds it to $this::data.
     * @return array|null
     */
	public function getAllProfileData() {
		/* get profile */
		$profile = $this->_profile;
		if (empty($profile)) {
			$this->modx->log(modX::LOG_LEVEL_ERROR,'Could not find profile for user: '.$this->user->get('username'));
			return null;
		}
		$profile_array = $profile->toArray();
		unset($profile_array['extended']);
		$this->_mergeWithData($profile_array);
        return $profile_array;
	}

    /**
     * Parses extended data and adds it to $this::data
     * @param string $prefix Optional prefix to override the default extended prefix
     * @return array
     */
	public function getAllExtendedData($prefix = '') {
		if (!$prefix) $prefix = $this->config['prefixes']['extended'];
		$data = $this->_profile->get('extended');
		$data = $this->_processDataArray($data,$prefix);
        $this->_mergeWithData($data);
        return $data;
	}

    /**
     * Parses remote data and adds it to $this::data
     * @param string $prefix Optional prefix to override the default remote_data prefix
     * @return array
     */
	public function getAllRemoteData($prefix = '') {
		if (!$prefix) $prefix = $this->config['prefixes']['remote'];
		$data = $this->user->get('remote_data');
        $data = $this->_processDataArray($data,$prefix);
        $this->_mergeWithData($data);
        return $data;
	}
    
   
/* *************************** */
/*  UTILITY METHODS            */
/* *************************** */
    /**
     * Adds an array to $this::data
     * Also returns the same array
     * @param $data array An associative array of fields with values to merge into $this::data
     * @return array The resulting array
     */
	protected function _mergeWithData($data) {
		if (is_array($data) && (!empty($data))) {
			$this->_data = array_merge($this->_data,$data);
		}
		return $this->_data;
	}

    /**
     * Unsets protected fields
     * If no parameter is passed, uses $this->config['protected_fields']
     * You HAVE to have protected_fields either in config or as a method parameter. To disable, just list a field that doesn't exist.
     * This is a security fall-back.
     * @param $data
     * @param $fields array An optional array of field names to unset
     * @return bool Always true
     */
	protected function _protectData($data, array $fields = array()) {
        $output = $data;
        $fields = $fields ? $fields : $this->config['protected_fields_array'];
		foreach ($fields as $field) {
			if (isset($output[$field])) {
				unset($output[$field]);
			}
		}
		return $output;
	}

    /**
     * Attaches a prefix to each key of an array
     * @param array $array A single-dimensional array
     * @param string $prefix The prefix to add
     * @return array Same array, but prefixed!
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
	
    /**
     * Processes an array into proper format
     * Creates a single-dimension array from a multi-dimension array such as the extended and remoted_data fields
     * @param $data mixed An associative array of fields with values to process
     * @param $prefix string    The prefix to add
     * @return bool    Success.
     */
	protected function _processDataArray($data,$prefix) {
		$data_array = array();
		if (!empty($data) && is_array($data)) {
            foreach($data as $key => $value) {
                if (is_array($value)) {
                    foreach($value as $key2 => $value2) {
                        if(is_array($value2)) {
                            $new_value2 = $this->modx->toJSON($value2);
                        } else {
                            $new_value2 = $value2;
                        }   
                        $data_array[$key.'.'.$key2] = $new_value2;
                    }
                } elseif (is_string($value) || is_int($value)) {
                    $data_array[$key] = $value;
                } else {
                    $this->modx->log(modX::LOG_LEVEL_INFO,"UserInfoPlus: Skipping {$key} for user {$this->user->get('username')} because it is not an array or string.");
                }
            }
            // $data_array['debug'] = 'Placeholder Array: '.print_r($data_array,1);
            $data_array = $this->_attachPrefix($data_array,$prefix);
		}
		return $data_array;
	}
    protected function _getCache($method,$key) {
        if (isset($this->_cache[$method]) && isset($this->_cache[$method][$key])) {
            return $this->_cache[$method][$key];
        }
        return null;
    }
    protected function _setCache($method,$key,$value) {
        if (!isset($this->_cache[$method])) {
            $this->_cache[$method] = array();
        }
        $this->_cache[$method][$key] = $value;
    }

    /**
     * Figures out the method to use for calculating a field value
     * @param $fieldname
     * @param string $type
     * @return string
     */
    protected function _getCalcMethodName($fieldname,$type = 'getter') {
        $methodname = $this->_getCache('_getCalcMethodName',$fieldname.$type);
        if (!is_null($methodname)) return $methodname;
        $calcprefix = $this->config['prefixes']['calculated'];
        if ($calcprefix && strpos($fieldname,$calcprefix) === 0) {
            $fieldname = substr($fieldname,(strlen($calcprefix)-strlen($fieldname)));
        }
        $methodname = ($type == 'setter') ? 'derive' : 'calculate';
        foreach (explode('_',$fieldname) as $namepart) {
            $methodname .= ucfirst($namepart);
        }
        $this->_setCache('_getCalcMethodName',$fieldname,$methodname);
        return $methodname;
    }
    /**
     * Calls a method with caching to make sure method is only called once.
     * @param string $methodname The method name
     * @return bool|null The value returned by the method. Always null if method is being called for a second time.
     */
    protected function _callMethodOnce($methodname) {
        $object = $this;
        $output = null;
        $already_tried = $this->_getCache('_callMethodOnce',$methodname);
        if ($already_tried) return null;
        if(method_exists($object,$methodname)) {
            $output = $this->$methodname();
        }
        $this->_setCache('_callMethodOnce',$methodname,true);
        return $output;
    }
    /**
     * Finds a field value
     * @param $fieldname string the field to get
     * @param $methods_to_try array An array of methods to try in order (overrides system-wide setting)
     * @return string The value. Null if value not found.
     */
    protected function _findFieldValue($fieldname, $methods_to_try= null) {
        foreach(array_unique($methods_to_try) as $methodname) {
            $returned_value = $this->_callMethodOnce($methodname);
            if (!is_null($returned_value) && !is_array($returned_value)) {
                $this->set($fieldname,$returned_value);
            }
            $value = isset($this->_data[$fieldname]) ? $this->_data[$fieldname] : null;
            if (!is_null($value)) {
                return $value;
            }
        }
        return null;
    }


    protected function _getFieldType($fieldname) {
        if (in_array($fieldname,$this->config['field_types']['user'])) {
            return 'user';
        } elseif (in_array($fieldname,$this->config['field_types']['profile'])) {
            return 'profile';
        }
        return null;
    }
    protected function _fieldInProfile($fieldname) {
        return false;
    }

/******************************************/
/*             SAVING INFO TO USER        */
/******************************************/
    /**
     * @param string $fieldname The key to access by
     * @param mixed|null $value The value to set, or null to unset.
     * @param null $type
     * @param string $folder An optional folder
     * @throws Exception
     * @return bool Success
     */
    public function setToUser($fieldname, $value = null, $type = null, $folder = null) {
        if (is_null($type)) $type = $this->_getFieldType($fieldname);
        try {
            $setter = $this->_getCalcMethodName($fieldname,'setter');
            if (method_exists($this,$setter)) {
                $this->$setter($value);
            } elseif ($type == 'user') {
                $this->user->set($fieldname,$value);
            } elseif ($type == 'profile') {
                $this->_profile->set($fieldname,$value);
            } elseif ($type == 'remote') {
                $this->_setRemote($fieldname,$value,$folder);
            } else {
                $this->_setExtended($fieldname,$value,$folder);
            }
        } catch (Exception $e) {
            throw new Exception('An error occured while saving the field '.$fieldname.': '.$e->getMessage());
        }
        return True;
    }
    /**
     * @return bool Success
     */
    public function saveUser() {
        return $this->user->save();
    }
    /**
     * @param string $key The key to access by
     * @param mixed|null $value The value to set, or null to unset.
     * @param string $folder An optional folder
     * @return void
     * @throws Exception If user does not have an id.
     */
    private function _setExtended($key,$value = null,$folder = null) {
        $profile = $this->_profile;
        $extended = $profile->get('extended');
        $extended = is_array($extended) ? $extended : array();
        if (!is_null($value)) {
            $extended[$key] = $value;
        } elseif (isset($extended[$key])) {
            unset($extended[$key]);
        }
        $profile->set('extended',$extended);
    }
    private function _setRemote($key,$value = null,$folder = null) {
        $remote = $this->user->get('remote');
        $remote = is_array($remote) ? $remote : array();
        if (!is_null($value)) {
            $remote[$key] = $value;
        } elseif (isset($remote[$key])) {
            unset($remote[$key]);
        }
        $this->user->set('remote',$remote);
    }

}