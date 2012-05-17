<?php
/**
 * ProfilePlus
 * Revamped and expanded Profile snippet
 *
 * Copyright 2011 by Oleg Pryadko <oleg@websitezen.com>
 * Based on Profile Copyright 2010 by Shaun McCormick <shaun@modx.com>
 *
 * UserInfoPlus is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * UserInfoPlus is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * UserInfoPlus; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package userinfoplus
 */
/**
 * Expands on Profile snippet to add extended, remote, and custom data
 * Profile: MODx Profile Snippet. Sets Profile data for a user to placeholders
 *
 * @package userinfoplus
 * @uses login
 * @requires MODx Revolution Login package
 */
/**
 * @var MODx $modx
 * @var array $scriptProperties
 */
$user = $modx->getOption('user',$scriptProperties,'');

// Just in case you need the original lexicon
$load_login_lexicon = (bool)$modx->getOption('load_login_lexicon',$scriptProperties,false);
if ($load_login_lexicon) {
    $login = $modx->getService('login','Login',$modx->getOption('login.core_path',null,$modx->getOption('core_path').'components/login/').'model/login/',$scriptProperties);
    if (!($login instanceof Login)) return '';
    $modx->lexicon->load('login:profile');
}

/* setup default properties */
$prefix = $modx->getOption('prefix',$scriptProperties,'');
$userid = (int) $modx->getOption('user',$scriptProperties,'');
$default_to_current = (bool) $modx->getOption('default_to_current',$scriptProperties,true);
$default = $modx->getOption('default',$scriptProperties,'');
$use_get = $modx->getOption('use_get',$scriptProperties,$modx->getOption('userinfoplus.use_get_default',null,true));
$get_prefix = $modx->getOption('get_prefix',$scriptProperties,$modx->getOption('userinfoplus.get_prefix',null,'uu_'));
$get_param = $modx->getOption('get_param',$scriptProperties,$modx->getOption('userinfoplus.get_param',null,'userid'));
$get_name = $modx->getOption('get_name',$scriptProperties,$get_prefix.$get_param);

/* exit if no userid is possible */
if (empty($userid) && empty($use_get) && (!$default_to_current || !$modx->user->hasSessionContext($modx->context->get('key')))) {
    return '';
}

$c = array(); // use for query parameters
$user = false; // user object is empty
if ($userid) {
    /* specifying a specific user, so try and get it */
    $c['id'] = $userid;
    $user = $modx->getObject('modUser',$c);
} elseif ($use_get) {
    // get the userid from a GET parameter
    if (isset($_GET[$get_name])) {
        $c['id'] = intval($_GET[$get_name]);
        $user = $modx->getObject('modUser',$c);
    }
}
if (!$user && $default_to_current) {
    // Try defaulting to the current logged-in user
    if ($modx->user->hasSessionContext($modx->context->get('key'))) {
        $user =& $modx->user;
    }
}
if (!($user instanceof modUser)) {
    // user can't be found - return default text
    $modx->log(modX::LOG_LEVEL_ERROR,'Could not find user: '.$userid);
    return $default;
}
/*
 * UserInfoPlus: Allows you to load a custom class that overrides userinfoplus to add calculations or control order of processing
 */
$classname_custom = $modx->getOption('class_name',$scriptProperties,$modx->getOption('userinfoplus.class_name',null,''));
$class_subfolder = $modx->getOption('class_subfolder',$scriptProperties,$modx->getOption('userinfoplus.class_subfolder',null,''));
$class_path = $modx->getOption('class_path',$scriptProperties,$modx->getOption('userinfoplus.core_path',null,$modx->getOption('core_path').'components/userinfoplus/').'model/userinfoplus/');
$userinfoplus_config = $classname_custom ? array('class_name'=>$classname_custom, 'class_subfolder'=>$class_subfolder, 'class_path'=>$class_path): array();

/** @var $userinfoplusservice UserInfoPlusService */
$userinfoplusservice = $modx->getService('userinfoplusservice','UserInfoPlusService',$modx->getOption('userinfoplus.core_path',null,$modx->getOption('core_path').'components/userinfoplus/').'model/userinfoplus/',$userinfoplus_config);
if (!($userinfoplusservice instanceof UserInfoPlusService)) {
    $modx->log(modX::LOG_LEVEL_ERROR,'Could not find userinfoplus class');
    return 'Could not find userinfoplus class';
}

/* UserInfoPlus: process data */
$userinfoplus = $userinfoplusservice->getUserInfo($user);
$placeholders = $userinfoplus->toArray();

if ($modx->getOption('debug',$scriptProperties,false)) {
    $placeholders['debug'] = print_r($placeholders,1);
}
foreach(array('password','cachepwd') as $k) {
    if (isset($placeholders[$k])) unset($placeholders[$k]);
}
/* now set placeholders */
$modx->toPlaceholders($placeholders,$prefix,'');
return '';