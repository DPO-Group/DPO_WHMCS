<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * Global defines used for the two endpoints
 *
 */

$docroot = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
if ( isset( $_SERVER['SERVER_PORT'] ) ) {
    $docroot .= ':' . $_SERVER['SERVER_PORT'];
}
$docroot .= '/';
define( "DOC_ROOT", $docroot );
