<?php
/*
 * Nucleus: PHP/MySQL Weblog CMS (http://nucleuscms.org/)
 * Copyright (C) 2002-2009 The Nucleus Group
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * (see nucleus/documentation/index.html#license for more info)
 */
/**
 * This files sets up the admin area it can be used as a template for admin
 * areas on plugins (simply skip the action chain). 
 * @license http://nucleuscms.org/license.txt GNU General Public License
 * @copyright Copyright (C) 2002-2009 The Nucleus Group
 * @version $Id$
 * @copyright This version Matt B.
 */

	$CONF = array(); // ideally Global Vars and defaults should init in just one location
        $CONF['debug'] = 0;
        require_once('../config.php');
        ADMINMANAGER::instance()->full_admin()->action($action);