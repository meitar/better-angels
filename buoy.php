<?php
/**
 * Plugin Name: Buoy (a Better Angels crisis response system)
 * Plugin URI: https://github.com/meitar/better-angels
 * Description: A community-based crisis response system. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Better%20Angels&amp;item_number=better-angels&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of Better Angels">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.1
 * Author: Maymay <bitetheappleback@gmail.com>
 * Author URI: https://maymay.net/
 * Text Domain: buoy
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.

/**
 * Base class that WordPress uses to register and initialize plugin.
 *
 * @author maymay <bitetheappleback@gmail.com>
 * @copyright Copyright (c) 2015-2016 by Meitar "maymay" Moscovitz
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 * @package WordPress\Plugin\WP_Buoy_Plugin
 */
class WP_Buoy_Plugin {

    /**
     * @var string $prefix String to prefix option names, settings, etc.
     *
     * @access public
     */
    public static $prefix = 'buoy';

    /**
     * Instance of WP_Buoy_Settings used to handle plugin options.
     *
     * @var WP_Buoy_Settings
     */
    public $options;

    public function __construct () {
    }

    /**
     * Entry point for the WordPress framework into plugin code, that
     * registers various hooks.
     *
     * @return void
     */
    public static function register () {
        add_action('plugins_loaded', array(__CLASS__, 'registerL10n'));
        add_action('init', array(__CLASS__, 'initialize'));

        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__, array(__CLASS__, 'deactivate'));
    }

    /**
     * Loads localization files from plugin's `languages` directory.
     *
     * @return void
     */
    public static function registerL10n () {
        load_plugin_textdomain('buoy', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Loads plugin componentry and calls that component's register()
     * method. Called at the WordPress `init` hook.
     *
     * @uses WP_Buoy_Settings::register();
     * @uses WP_Buoy_Team::register();
     * @uses WP_Buoy_Notification::register();
     * @uses WP_Buoy_User::register();
     *
     * @return void
     */
    public static function initialize () {
        require_once 'class-buoy-settings.php';
        require_once 'class-buoy-user-settings.php';
        require_once 'class-buoy-team.php';
        require_once 'class-buoy-notification.php';
        require_once 'class-buoy-user.php';

        WP_Buoy_Settings::register();
        WP_Buoy_Team::register();
        WP_Buoy_Notification::register();
        WP_Buoy_User::register();
    }

    /**
     * Method to run when the plugin is activated by a user in the
     * WordPress Dashboard admin screen.
     *
     * @uses WP_Buoy_Settings::activate()
     *
     * @return void
     */
    public static function activate () {
        require_once 'class-buoy-settings.php';
        $options = WP_Buoy_Settings::get_instance();
        $options->activate();
    }

    /**
     * Method to run when the plugin is deactivated by a user in the
     * WordPress Dashboard admin screen.
     */
    public static function deactivate () {
    }

}

WP_Buoy_Plugin::register();
