<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 */
/**
 * This class makes sure the admin area gets objects as efficiently as possible.
 *
 * The class is a singleton, meaning that there will be only one object of it
 * active at all times. 
 * 
 * The object can be requested using ADMINMANAGER::instance()
 *
 * @license GNU General Public License
 * @copyright Copyright (C) 2014
 * @version $Id$
 */
class ADMINMANAGER {

	/**
	 * Cached instences as needed
	 */
	protected $objects = array();
        protected $last_id = 0;



        private static $_instances = array();
        /**
         * If you version of PHP supports late static binding you could extend 
         * and/or replace this class.
         * @return type 
         */
        public static function instance() {
            $class = get_called_class();
            if (!isset(self::$_instances[$class])) {
                self::$_instances[$class] = new $class();
            }
            return self::$_instances[$class];
        }

	 /*
	  * Let us be clear about what we cannot do here
	  */
	private function __construct() {}
        private function __clone() {}
        
        public function set_using_admin_area(){
            // we are using admin stuff:
            $CONF = array();
            $CONF['UsingAdminArea'] = 1;
            return $this;
        }
        
        public function secuirty_tests(){
            if ($CONF['alertOnSecurityRisk'] == 1) {
                    // check if files exist and generate an error if so
                    $aFiles = array(
                            '../install.sql' => _ERRORS_INSTALLSQL,
                            '../install.php' => _ERRORS_INSTALLPHP,
                            'upgrades' => _ERRORS_UPGRADESDIR,
                            'convert' => _ERRORS_CONVERTDIR
                    );
                    $aFound = array();
                    foreach($aFiles as $fileName => $fileDesc)
                    {
                            if (@file_exists($fileName))
                                    array_push($aFound, $fileDesc);
                    }
                    if (@is_writable('../config.php')) {
                            array_push($aFound, _ERRORS_CONFIGPHP);
                    }
                    if (sizeof($aFound) > 0)
                    {
                            startUpError(
                                    _ERRORS_STARTUPERROR1. implode($aFound, '</li><li>')._ERRORS_STARTUPERROR2,
                                    _ERRORS_STARTUPERROR3
                            );
                    }
            }
        }
        
        public function do_other_actions($action){
            global $member, $error;
            $bNeedsLogin = false;
            $bIsActivation = in_array($action, array('activate', 'activatesetpwd'));

            if ($action == 'logout')
                    $bNeedsLogin = true;

            if (!$member->isLoggedIn() && !$bIsActivation)
                    $bNeedsLogin = true;

            // show error if member cannot login to admin
            if ($member->isLoggedIn() && !$member->canLogin() && !$bIsActivation) {
                    $error = _ERROR_LOGINDISALLOWED;
                    $bNeedsLogin = true;
            }

            if ($bNeedsLogin)
            {
                    setOldAction($action);	// see ADMIN::login() (sets old action in POST vars)
                    $action = 'showlogin';
            }
        }
        
        /**
         * "chainable" shortcut to MANAGER
         * @return type 
         */
        public function &manager(){
            $MANAGER = MANAGER::instance();
            return $MANAGER;
        }
        
        /**
         * Based on old index.php
         * @global type $action 
         */
        public function &full_admin(){
            global $action;
            $this->secuirty_tests();
            $this->do_other_actions($action);
            sendContentType('text/html', 'admin-' . $action);
            return $this->finish_admin($action);
        }
        
        public function &quick_admin(){
            global $action;
            $this->set_using_admin_area();    
            return $this->finish_admin($action);
        }
        
        protected function &finish_admin($action){
            $admin = $this->ADMIN();
            $admin->action($action);
            return $admin;
        }    
        
        /**
         * Grants the ADMIN class
         */
        public function &ADMIN(){
            return $this->simple_object('ADMIN');
        }
        
        public function get_last_id(){
            return $this->last_id;
        }
        
        public function &force_spare_object($what){
            $this->objects[$what][] = new $what ();
            $this->last_id = count($this->objects[$what])-1;
            return $this->objects[$what][$this->last_id];
        }
        
        public function &get_spare_by_id($what,$id){
            return $this->objects[$what][$id];
        }
        
        protected function &simple_object($what){
            if(isset($this->objects[$what][0]) && is_object($this->objects[$what][0])){
                return $this->objects[$what][0];
            }else{
                $this->objects[$what][0] = new $what ();
                return $this->objects[$what][0];
            }
        }
}