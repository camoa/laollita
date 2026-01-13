<?php

/**
 * @file
 * Local development settings.
 *
 * Copy this file to settings.local.php and customize as needed.
 */

// Enable the local config split.
$config['config_split.config_split.local']['status'] = TRUE;

// Disable the production config split.
$config['config_split.config_split.prod']['status'] = FALSE;

// Local development hash_salt (generate your own for production).
// $settings['hash_salt'] = 'your-unique-hash-salt-here';

// Disable CSS/JS aggregation for development.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Enable verbose error display.
$config['system.logging']['error_level'] = 'verbose';
