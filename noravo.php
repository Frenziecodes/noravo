<?php
/**
 * Plugin Name: Noravo
 * Plugin URI: https://github.com/Frenziecodes/Noravo
 * Description: Boost conversions with Noravo: Social Proof & FOMO Notifications for WordPress.
 * Version: 1.0.2
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: lewisushindi
 * Author URI: https://github.com/Frenziecodes/
 * Text Domain: noravo
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Noravo
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NORAVO_VERSION', '1.0.2' );
define( 'NORAVO_FILE', __FILE__ );
define( 'NORAVO_PATH', plugin_dir_path(__FILE__) );
define( 'NORAVO_URL', plugin_dir_url(__FILE__) );
define( 'NORAVO_BASENAME', plugin_basename(__FILE__) );

require_once NORAVO_PATH . 'includes/Autoloader.php';

\Noravo\Autoloader::register();

register_activation_hook(
	__FILE__,
	static function (): void {
		\Noravo\Plugin::activate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\Noravo\Plugin::instance()->boot();
	}
);
