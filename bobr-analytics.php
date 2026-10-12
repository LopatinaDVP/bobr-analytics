<?php
/**
 * Plugin Name:       Bobr Analytics
 * Plugin URI:        https://github.com/LopatinaDVP/bobr-analytics
 * Description:       Self-hosted website statistics without cookies or third-party services: visitors, sources, AI assistants, bots, geography, page speed. Data stays in this site's database.
 * Version:           1.3.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Ekaterina Lopatina
 * Author URI:        https://lop-pa.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bobr-analytics
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BOBR_VER', '1.3.0' );
define( 'BOBR_DB_VER', '1' );
define( 'BOBR_FILE', __FILE__ );
define( 'BOBR_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOBR_URL', plugin_dir_url( __FILE__ ) );
define( 'BOBR_GITHUB', 'https://github.com/LopatinaDVP/bobr-analytics' );

require_once BOBR_DIR . 'includes/class-bobr-util.php';
require_once BOBR_DIR . 'includes/class-bobr-mmdb.php';
require_once BOBR_DIR . 'includes/class-bobr-geo.php';
require_once BOBR_DIR . 'includes/class-bobr-db.php';
require_once BOBR_DIR . 'includes/class-bobr-collector.php';
require_once BOBR_DIR . 'includes/class-bobr-cron.php';

register_activation_hook( __FILE__, array( 'Bobr_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'Bobr_Cron', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'Bobr_Cron', 'unschedule' ) );

add_action( 'plugins_loaded', array( 'Bobr_DB', 'maybe_upgrade' ) );
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'bobr-analytics', false, dirname( plugin_basename( BOBR_FILE ) ) . '/languages' );
	},
	0
);
Bobr_Collector::init();
Bobr_Cron::init();

if ( is_admin() ) {
	require_once BOBR_DIR . 'includes/class-bobr-charts.php';
	require_once BOBR_DIR . 'includes/class-bobr-report.php';
	require_once BOBR_DIR . 'includes/class-bobr-admin.php';
	Bobr_Admin::init();
}

function bobr_settings() {
	$def = array(
		'exclude_roles' => array( 'administrator', 'editor', 'shop_manager' ),
		'exclude_ips'   => array(),
		'retention'     => 180,
		'geo'           => 1,
		'bots'          => 1,
	);
	$s = get_option( 'bobr_settings', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), $def );
}
