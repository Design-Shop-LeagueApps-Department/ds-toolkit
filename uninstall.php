<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// What is removed, and what is deliberately left, is one list: includes/class-ds-lifecycle.php.
require_once __DIR__ . '/includes/class-ds-lifecycle.php';
DS_Toolkit_Lifecycle::uninstall();
