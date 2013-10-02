<?php
/**
 * @package: core.components.userinfoplus.model.userinfoplus
 * @author: Oleg Pryadko (oleg@websitezen.com)
 * @createdon: 5/16/12
 * @license: GPL v.3 or later
 */
require_once (dirname(__FILE__).'/userinfoplus.class.php');
class UserInfoPlusService
{
    /** @var array Options array. */
	public $config = array();
    /** @var array User cache array. */
	protected $users = array();

    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;

        $corePath = $modx->getOption('userinfoplus.core_path',$config,$modx->getOption('core_path',null,MODX_CORE_PATH).'components/userinfoplus/');
        $this->config = array_merge(array(
            'corePath' => $corePath,
            'modelPath' => $corePath.'model/',
            'chunksPath' => $corePath.'chunks/',
            'processorsPath' => $corePath.'processors/',
            'class_name' => 'UserInfoPlus',
            'class_subfolder' => '',
            'class_path' => $corePath.'model/',
        ),$this->config,$config);

		/* prefixes */
		if (!isset($this->config['prefixes']) || !is_array($this->config['prefixes'])) $this->config['prefixes'] = array();
		$this->config['prefixes']['remote'] = $this->modx->getOption('userinfo_remote_prefix',$this->config,'remote.');
		$this->config['prefixes']['extended'] = $this->modx->getOption('userinfo_extended_prefix',$this->config,'');
		$this->config['prefixes']['calculated'] = $this->modx->getOption('userinfo_calculated_prefix',$this->config,'');

		/* generate the default protected fields array - sets defaults as a security fallback */
        $this->config['protected_fields_array'] = isset($this->config['protected_fields']) ?
                explode(',',$this->config['protected_fields']) : array('sessionid','password','cachepwd');
        $this->config['calculated_fields'] = array('self');

        $this->config['field_types'] = array(
            'user' => array_keys($modx->getFields('modUser')),
            'profile' => array_keys($modx->getFields('modUserProfile')),
        );
    }

    // todo: custom class etc...
    /**
     * Gets the userinfoplus object - "Begins"
     * @param $user modUser
     * @return UserInfoPlus
     */
	public function getUserInfo(modUser &$user, $debug=false) {
        $userid = $user->get('id');
        if (!$userid) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,"UserInfoPlus: userinfoplus only works with saved users who already have a user id. Could not set user with username {$user->get('username')}.");
            return false;
        }
        if (!isset($this->users[$userid])) {
            $service = $this;
            $classname = $this->config['class_name'];
            $fqn = $this->config['class_subfolder'] ? ($this->config['class_subfolder'].'.'.$classname) : $classname;
            $this->modx->loadClass($classname,$this->config['class_path'],true,true,$debug);
            /** @var $userinfo UserInfoPlus */
            $userinfo = new $classname($user, $this->config, $service);
            $this->users[$userid] = $userinfo;
        }
                return $this->users[$userid];
	}

    public function setConfig($key,$value) {
        $this->config[$key] = $value;
    }

}
