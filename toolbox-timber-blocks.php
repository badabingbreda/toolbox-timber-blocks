<?php
/**
 * Toolbox Timber Blocks
 *
 * @package     Toolbox
 * @author      Badabingbreda
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Toolbox Timber Blocks
 * Plugin URI:  https://www.badabing.nl
 * Description: Create ACF Blocks by assigning Twig Templates
 * Version:     1.0.0
 * Author:      Badabingbreda
 * Author URI:  https://www.badabing.nl
 * Text Domain: toolbox-blocks
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


use ToolboxTimberBlocks\Autoloader;
use ToolboxTimberBlocks\Init;

if ( defined( 'ABSPATH' ) && ! defined( 'TOOLBOXTIMBERBLOCKS_VERION' ) ) {
 register_activation_hook( __FILE__, 'TOOLBOXTIMBERBLOCKS_check_php_version' );

 /**
  * Display notice for old PHP version.
  */
 function TOOLBOXTIMBERBLOCKS_check_php_version() {
     if ( version_compare( phpversion(), '5.3', '<' ) ) {
        die( esc_html__( 'ToolboxTimberBlocks requires PHP version 5.3+. Please contact your host to upgrade.', 'mortgagebroker-calculator' ) );
    }
 }

  define( 'TOOLBOXTIMBERBLOCKS_VERSION'   , '1.0.0' );
  define( 'TOOLBOXTIMBERBLOCKS_DIR'     , plugin_dir_path( __FILE__ ) );
  define( 'TOOLBOXTIMBERBLOCKS_FILE'    , __FILE__ );
  define( 'TOOLBOXTIMBERBLOCKS_URL'     , plugins_url( '/', __FILE__ ) );

  define( 'CHECK_TOOLBOXTIMBERBLOCKS_PLUGIN_FILE', __FILE__ );

}

if ( ! class_exists( 'ToolboxTimberBlocks\Init' ) ) {

 /**
  * The file where the Autoloader class is defined.
  */
  require_once 'inc/Autoloader.php';
  spl_autoload_register( array( new Autoloader(), 'autoload' ) );

 $toolboxtimberblocks = new Init();
 // looking for the init hooks? Find them in the Check_Plugin_Dependencies.php->run() callback

}
