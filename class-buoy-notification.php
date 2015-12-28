<?php
/**
 * Buoy Notification
 *
 * @package WordPress\Plugin\WP_Buoy_Plugin\WP_Buoy_Notification
 *
 * @copyright Copyright (c) 2015-2016 by Meitar "maymay" Moscovitz
 *
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html
 */

if (!defined('ABSPATH')) { exit; } // Disallow direct HTTP access.
/**
 * Class responsible for sending notifications triggered by the right
 * events via the right mechanisms.
 */
class WP_Buoy_Notification extends WP_Buoy_Plugin {

    /**
     * Constructor.
     */
    public function __construct () {
    }

    /**
     * @return void
     */
    public static function register () {
        add_action('publish_' . parent::$prefix . '_team', array(__CLASS__, 'inviteMembers'), 10, 2);

        add_action(parent::$prefix . '_team_member_added', array(__CLASS__, 'addedToTeam'), 10, 2);
        add_action(parent::$prefix . '_team_member_removed', array(__CLASS__, 'removedFromTeam'), 10, 2);
    }

    /**
     * Schedules a notification to be sent to the user.
     *
     * @param int $user_id
     * @param WP_Buoy_Team $team
     *
     * @return void
     */
    public static function addedToTeam ($user_id, $team) {
        add_post_meta($team->wp_post->ID, '_' . parent::$prefix . '_notify', $user_id, false);

        // Call the equivalent of the "status_type" hook since adding
        // a member may have happened after publishing the post itself.
        // This catches any just-added members.
        do_action("{$team->wp_post->post_status}_{$team->wp_post->post_type}", $team->wp_post->ID, $team->wp_post);
    }

    /**
     * Removes any scheduled notices to be sent to the user.
     *
     * @param int $user_id
     * @param WP_Buoy_Team $team
     *
     * @return void
     */
    public static function removedFromTeam ($user_id, $team) {
        delete_post_meta($team->wp_post->ID, '_' . parent::$prefix . '_notify', $user_id);
    }

    /**
     * Invites users added to a team when it is published.
     *
     * @todo Support inviting via other means than email.
     *
     * @uses wp_mail()
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public static function inviteMembers ($post_id, $post) {
        $team      = new WP_Buoy_Team($post_id);
        $buoy_user = new WP_Buoy_User($post->post_author);
        $to_notify = array_unique(get_post_meta($post_id, '_' . parent::$prefix . '_notify'));
        $subject = sprintf(
            __('%1$s wants you to join %2$s crisis response team.', 'buoy'),
            $buoy_user->wp_user->display_name, $buoy_user->get_pronoun()
        );
        foreach ($to_notify as $user_id) {
            // TODO: Write a better message.
            $msg = admin_url(
                'edit.php?post_type=' . $team->wp_post->post_type . '&page=' . parent::$prefix . '_team_membership'
            );
            $user = get_userdata($user_id);
            wp_mail($user->user_email, $subject, $msg);

            delete_post_meta($post_id, '_' . parent::$prefix . '_notify', $user_id);
        }
    }

    /**
     * Runs whenever an alert is published. Sends notifications to an
     * alerter's response team informing them of the alert.
     *
     * @param int $post_id
     * @param WP_Post $post
     *
     * @return void
     */
    public static function publishAlert ($post_id, $post) {
        $alert = new WP_Buoy_Alert($post_id);

        $responder_link = admin_url(
            '?page=' . parent::$prefix . '_review_alert'
            . '&' . parent::$prefix . '_hash=' . $alert->get_hash()
        );
        $responder_short_link = home_url(
            '?' . parent::$prefix . '_alert='
            . substr($alert->get_hash(), 0, 8)
        );
        $subject = $post->post_title;

        $alerter = get_userdata($post->post_author);
        $headers = array(
            "From: \"{$alerter->display_name}\" <{$alerter->user_email}>"
        );

        foreach ($alert->get_teams() as $team_id) {
            $team = new WP_Buoy_Team($team_id);
            foreach ($team->get_confirmed_members() as $user_id) {
                $responder = new WP_Buoy_User($user_id);

                // TODO: Write a more descriptive message.
                wp_mail($responder->wp_user->user_email, $subject, $responder_link, $headers);

                $smsemail = $responder->get_sms_email();
                if (!empty($smsemail)) {
                    $sms_max_length = 160;
                    // We need to ensure that SMS notifications fit within the 160 character
                    // limit of SMS transmissions. Since we're using email-to-SMS gateways,
                    // a subject will be wrapped inside of parentheses, making it two chars
                    // longer than whatever its original contents are. Then a space is
                    // inserted between the subject and the message body. The total length
                    // of strlen($subject) + 2 + 1 + strlen($message) must be less than 160.
                    $extra_length = 3; // two parenthesis and a space
                    // but in practice, there seems to be another 7 chars eaten up somewhere?
                    $extra_length += 7;
                    $url_length = strlen($responder_short_link);
                    $full_length = strlen($subject) + $extra_length + $url_length;
                    if ($full_length > $sms_max_length) {
                        // truncate the $subject since the link must be fully included
                        $subject = substr($subject, 0, $sms_max_length - $url_length - $extra_length);
                    }
                    wp_mail($smsemail, $subject, $responder_short_link, $headers);
                }
            }
        }
    }

    /**
     * Utility function to return the domain name portion of a given
     * telco's email-to-SMS gateway address.
     *
     * The returned string includes the prefixed `@` sign.
     *
     * @param string $provider A recognized `sms_provider` key.
     *
     * @see WP_Buoy_User_Settings::$default['sms_provider']
     *
     * @return string
     */
    public static function getEmailToSmsGatewayDomain ($provider) {
        $provider_domains = array(
            'AT&T' => '@txt.att.net',
            'Alltel' => '@message.alltel.com',
            'Boost Mobile' => '@myboostmobile.com',
            'Cricket' => '@sms.mycricket.com',
            'Metro PCS' => '@mymetropcs.com',
            'Nextel' => '@messaging.nextel.com',
            'Ptel' => '@ptel.com',
            'Qwest' => '@qwestmp.com',
            'Sprint' => array(
                '@messaging.sprintpcs.com',
                '@pm.sprint.com'
            ),
            'Suncom' => '@tms.suncom.com',
            'T-Mobile' => '@tmomail.net',
            'Tracfone' => '@mmst5.tracfone.com',
            'U.S. Cellular' => '@email.uscc.net',
            'Verizon' => '@vtext.com',
            'Virgin Mobile' => '@vmobl.com'
        );
        if (is_array($provider_domains[$provider])) {
            $at_domain = array_rand($provider_domains[$provider]);
        } else {
            $at_domain = $provider_domains[$provider];
        }
        return $at_domain;
    }

}
