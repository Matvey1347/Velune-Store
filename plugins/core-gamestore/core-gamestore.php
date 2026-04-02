<?php
/**
 * Plugin Name: Core Gamestore
 * Description: Core Gamestore
 * Version: 1.0.0
 * Author: Matvii
 * Author URI: https://github.com/Matvey1347
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

 define('GAMESTORE_VERSION', '1.0.0');
 define('GAMESTORE_PLUGIN_PATH', plugin_dir_path(__FILE__));
 define('GAMESTORE_PLUGIN_URL', plugin_dir_url(__FILE__));

 require_once GAMESTORE_PATH . 'includes/functions.php';
 require_once GAMESTORE_PATH . 'includes/hooks.php';
 require_once GAMESTORE_PATH . 'includes/admin-menu.php';
 require_once GAMESTORE_PATH . 'includes/admin-pages.php';