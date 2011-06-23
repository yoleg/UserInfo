<?php
/**
 * PeoplesPlus
 *
 * Copyright 2011 by Oleg Pryadko <oleg@websitezen.com>
 * Based on Peoples Copyright 2010 by Shaun McCormick <shaun@modx.com>
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
 * Expands on Peoples to add expanded, remote, and custom data types
 * Peoples: Displays a list of Users
 *
 * @package userinfoplus
 */
 
/*
 * UserInfoPlus: Allows you to load a custom class that overrides userinfoplus to add calculations or control order of processing
 */
$classname_custom = $modx->getOption('class',$scriptProperties,$modx->getOption('userinfoplus.class',null,''));
$classname_upper = $classname_custom ? $classname_custom : 'UserInfoPlus';
$classname_lower = $modx->getOption('class_lower',$scriptProperties,$modx->getOption('userinfoplus.class_lower',null,strtolower($classname_upper)));
$classname_subfolder = $classname_custom ? str_replace('userinfoplus','',$classname_lower).'/' : '';
$classname_path = $modx->getOption('class_path',$scriptProperties,$modx->getOption('userinfoplus.core_path',null,$modx->getOption('core_path').'components/userinfoplus/').'model/userinfoplus/'.$classname_subfolder);
$userinfoplus = $modx->getService('userinfoplus',$classname_upper,$classname_path,$scriptProperties);
if (!($userinfoplus instanceof UserInfoPlus)) {
    $modx->log(modX::LOG_LEVEL_ERROR,'Could not find class at: '.$classname_path);
    return 'Could not find class at: '.$classname_path;
}

$output = '';


/* setup default properties */
$tpl = $modx->getOption('tpl',$scriptProperties,'pplUser');
$active = (boolean)$modx->getOption('active',$scriptProperties,true);
$usergroups = $modx->getOption('usergroups',$scriptProperties,'');
$limit = (int)$modx->getOption('limit',$_REQUEST,$modx->getOption('limit',$scriptProperties,10));
/* fix start / offset to work with getPage */
$start = (int)$modx->getOption('start',$scriptProperties,0);
$offset = (int) $modx->getOption('offset',$_REQUEST,$modx->getOption('offset',$scriptProperties,0));
$offset += $start;
$sortBy = $modx->getOption('sortBy',$scriptProperties,'username');
$sortByAlias = $modx->getOption('sortByAlias',$scriptProperties,'User');
$sortDir = $modx->getOption('sortDir',$scriptProperties,'ASC');
$cls = $modx->getOption('cls',$scriptProperties,'ppl-user');
$altCls = $modx->getOption('altCls',$scriptProperties,'');
$firstCls = $modx->getOption('firstCls',$scriptProperties,'');
$lastCls = $modx->getOption('lastCls',$scriptProperties,'');
$placeholderPrefix = $modx->getOption('placeholderPrefix',$scriptProperties,'peoples.');
$profileAlias = $modx->getOption('profileAlias',$scriptProperties,'Profile');
$userClass = $modx->getOption('userClass',$scriptProperties,'modUser');
$userAlias = $modx->getOption('userAlias',$scriptProperties,'User');
$debug = (boolean)$modx->getOption('debug',$scriptProperties,false);

/* build query */
$c = $modx->newQuery($userClass);
$c->setClassAlias($userAlias);
if (is_bool($active) || $active < 2) {
    $c->where(array(
        $userAlias.'.active' => $active,
    ));
}
/* filter by user groups */
if (!empty($usergroups)) {
    $usergroups = explode(',',$usergroups);
    $c->leftJoin('modUserGroupMember','UserGroupMembers',$modx->getSelectColumns($userClass,$userAlias,'',array('id')).' = '.$modx->getSelectColumns('modUserGroupMember','UserGroupMembers','',array('member')));
    $c->leftJoin('modUserGroup','UserGroup',$modx->getSelectColumns('modUserGroupMember','UserGroupMembers','',array('user_group')).' = '.$modx->getSelectColumns('modUserGroup','UserGroup','',array('id')));
    $c->where(array(
        'UserGroup.name:IN' => $usergroups,
    ));
}
$count = $modx->getCount($userClass,$c);
$c->sortby($sortByAlias.'.'.$sortBy,$sortDir);
if (!empty($limit)) {
    $c->limit($limit,$start);
}
$c->bindGraph('{"'.$profileAlias.'":{}}');
$users = $modx->getCollectionGraph($userClass,'{"'.$profileAlias.'":{}}',$c);

/* iterate */
$list = array();
$alt = false;
$iterativeCount = count($users);
$idx = 0;
foreach ($users as $user) {
    if (empty($user->$profileAlias)) continue;
    
    /* PeoplesPlus: process data */
    $userinfoplus->setUser($user);
    $userinfoplus->process();
    $userArray = $userinfoplus->toArray();
    if ($debug) {
        $userArray['debug'] = '<pre>'.print_r($userArray,1).'</pre>';
    }
  
    $userArray['cls'] = array();
    $userArray['cls'][] = $cls;
    if ($alt && !empty($altCls)) $userArray['cls'][] = $altCls;
    if (!empty($firstCls) && $idx == 0) {
        $userArray['cls'][] = $firstCls;
        $userArray['_first'] = true;
    }
    if (!empty($lastCls) && $idx == $iterativeCount-1) {
        $userArray['cls'][] = $lastCls;
        $userArray['_last'] = true;
    }
    $userArray['cls'] = implode(' ',$userArray['cls']);
    $userArray['idx'] = $idx;
    
    // security fallback
    unset($userArray['password'],$userArray['cachepwd']);
  
    $list[] = $modx->getChunk($tpl,$userArray);
    $alt = !$alt;
    $idx++;
}

/* set total placeholders */
$placeholders = array(
    'total' => $count,
    'start' => $start,
    'offset' => $offset,
    'limit' => $limit,
);
$modx->setPlaceholders($placeholders,$placeholderPrefix);


/* output */
$outputSeparator = $modx->getOption('outputSeparator',$scriptProperties,"\n");
$output = implode($list,$outputSeparator);
$toPlaceholder = $modx->getOption('toPlaceholder',$scriptProperties,false);
if (!empty($toPlaceholder)) {
    $modx->setPlaceholder($toPlaceholder,$output);
    return '';
}
return $output;