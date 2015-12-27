<?php
if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.
/**
 * Class that manages interaction between WordPress API and Buoy user
 * settings.
 *
 * @author maymay <bitetheappleback@gmail.com>
 * @copyright Copyright (c) 2015-2016 by Meitar "maymay" Moscovitz
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 * @package WordPress\Plugin\WP_Buoy_Plugin\User
 */
class WP_Buoy_User extends WP_Buoy_Plugin {

    /**
     * The WordPress user.
     *
     * @var WP_User
     */
    public $wp_user;

    /**
     * The user's plugin settings.
     *
     * @var WP_Buoy_User_Settings
     */
    private $_options;

    /**
     * The user's teams.
     *
     * @var int[]
     */
    private $_teams;

    /**
     * Constructor.
     *
     * If the $user_id is invalid (doesn't refer to an existing user),
     * a `WP_Error` will be returned with an `invalid-user-id` code.
     *
     * @see https://developer.wordpress.org/reference/classes/WP_Error/
     *
     * @uses get_userdata()
     * @uses WP_Error
     * @uses WP_Buoy_User_Settings
     *
     * @param int $user_id
     *
     * @return WP_Buoy_User|WP_Error
     */
    public function __construct ($user_id) {
        $this->wp_user = get_userdata($user_id);
        if (false === $this->wp_user) {
            return new WP_Error(
                'invalid-user-id',
                __('Invalid user ID.', 'buoy'),
                $user_id
            );
        }
        $this->_options = new WP_Buoy_User_Settings($this->wp_user);
        return $this;
    }

    /**
     * Gets the user's (published) teams.
     *
     * @return int[]
     */
    public function get_teams () {
        $this->_teams = get_posts(array(
            'post_type' => parent::$prefix . '_team',
            'author' => $this->wp_user->ID,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        return $this->_teams;
    }

    /**
     * Gets the user's default team.
     *
     * @return int
     */
    public function get_default_team () {
        return $this->get_option('default_team');
    }

    /**
     * Checks whether or not the user has at least one responder.
     *
     * A "responder" in this context is a "confirmed" team member.
     * At least one responder is needed before the "Activate Alert"
     * screen will be of any use, obviously. This looks for confirmed
     * members on any of the user's teams and returns as soon as it
     * can find one.
     *
     * @uses WP_Buoy_Team::has_responder()
     *
     * @return bool
     */
    public function has_responder () {
        if (null === $this->_teams) {
            $this->get_teams();
        }
        // We need a loop here because, unless we use straight SQL,
        // we can't do a REGEXP compare on the `meta_key`, only the
        // `meta_value` itself. There's an experimental way to do it
        // over on Stack Exchange but this is more standard for now.
        //
        // See https://wordpress.stackexchange.com/a/193841/66139
        foreach ($this->_teams as $team_id) {
            $team = new WP_Buoy_Team($team_id);
            if ($team->has_responder()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Alias of WP_Buoy_User::get_gender_pronoun_possessive()
     *
     * @uses WP_Buoy_User::get_gender_pronoun_possessive()
     *
     * @return string
     */
    public function get_pronoun () {
        return $this->get_gender_pronoun_possessive();
    }

    /**
     * Gets the possessive gender pronoun of a user.
     *
     * @uses WP_Buoy_User::get_option()
     * @uses sanitize_text_field()
     *
     * @return string
     */
    public function get_gender_pronoun_possessive () {
        return sanitize_text_field($this->get_option('gender_pronoun_possessive', __('their', 'buoy')));
    }

    /**
     * Get a user's pre-defined crisis message, or a default message if empty.
     *
     * @uses WP_Buoy_User::get_option()
     * @uses sanitize_text_field()
     *
     * @return string
     */
    public function get_crisis_message () {
        return sanitize_text_field($this->get_option('crisis_message', __('Please help!', 'buoy')));
    }

    /**
     * Gets a user's email-to-SMS address based on their profile.
     *
     * @return string
     */
    public function get_sms_email () {
        $sms_email = '';

        $sms = $this->get_phone_number();
        $provider = $this->get_option('sms_provider');

        if (!empty($sms) && !empty($provider)) {
            $sms_email = $sms . WP_Buoy_Notification::getEmailToSmsGatewayDomain($provider);
        }


        return $sms_email;
    }

    /**
     * Gets a user's phone number, without dashes or other symbols.
     *
     * @uses WP_Buoy_User::get_option()
     * @uses sanitize_text_field()
     *
     * @return string
     */
    private function get_phone_number () {
        return sanitize_text_field(preg_replace('/[^0-9]/', '', $this->get_option('phone_number', '')));
    }

    /**
     * Gets the value of a user option they have set.
     *
     * @uses WP_Buoy_User_Settings::get()
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     *
     * @access private
     */
    private function get_option ($name, $default = null) {
        return $this->_options->get($name, $default);
    }

    /**
     * Registers user-related WordPress hooks.
     *
     * @uses WP_Buoy_Plugin::addHelpTab()
     *
     * @return void
     */
    public static function register () {
        add_action('load-profile.php', array('WP_Buoy_Plugin', 'addHelpTab'));
        add_action('show_user_profile', array(__CLASS__, 'renderProfile'));
        add_action('personal_options_update', array(__CLASS__, 'saveProfile'));

        add_action(parent::$prefix . '_team_emptied', array(__CLASS__, 'warnIfNoResponder'));
    }

    /**
     * Sends a warning to a user if they no longer have responders.
     *
     * @uses WP_Buoy_User::hasResponders()
     *
     * @param WP_Buoy_Team $team The team that has been emptied.
     *
     * @return bool
     */
    public static function warnIfNoResponder ($team) {
        $buoy_user = new self($team->author->ID);
        if (false === $buoy_user->has_responder()) {
            // TODO: This should be a bit cleaner. Maybe part of the WP_Buoy_Notification class?
            $subject = __('You no longer have crisis responders.', 'buoy');
            $msg = __('Either you have removed the last of your Buoy crisis response team members, or they have all left your teams. You will not be able to send a Buoy alert to anyone until you add more people to your team(s).', 'buoy');
            wp_mail($buoy_user->wp_user->user_email, $subject, $msg);
        }
    }

    /**
     * Prints the HTML for the custom profile fields.
     *
     * @param WP_User $profileuser
     *
     * @uses WP_Buoy_User_Settings::get()
     *
     * @return void
     */
    public static function renderProfile ($profileuser) {
        $options = new WP_Buoy_User_Settings($profileuser);
        require_once 'pages/profile.php';
    }

    /**
     * Saves profile field values to the database on profile update.
     *
     * @global $_POST Used to access values submitted by profile form.
     *
     * @param int $user_id
     *
     * @uses WP_Buoy_User_Settings::set()
     * @uses WP_Buoy_User_Settings::save()
     *
     * @return void
     */
    public static function saveProfile ($user_id) {
        $options = new WP_Buoy_User_Settings(get_userdata($user_id));
        $options
            ->set('gender_pronoun_possessive', sanitize_text_field($_POST[WP_Buoy_Plugin::$prefix.'_gender_pronoun_possessive']))
            ->set('phone_number', sanitize_text_field($_POST[WP_Buoy_Plugin::$prefix . '_phone_number']))
            ->set('sms_provider', sanitize_text_field($_POST[WP_Buoy_Plugin::$prefix . '_sms_provider']))
            ->set('crisis_message', sanitize_text_field($_POST[WP_Buoy_Plugin::$prefix . '_crisis_message']))
            ->set('public_responder', (isset($_POST[WP_Buoy_Plugin::$prefix . '_public_responder'])) ? true : false)
            ->save();
    }

}
