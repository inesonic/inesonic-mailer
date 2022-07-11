<?php
/**
 * Plugin Name:       Inesonic Mailer Plugin
 * Description:       A small plugin that will trigger delayed emails based on transitions between customer roles.
 * Version:           1.0.0
 * Author:            Inesonic,  LLC
 * Author URI:        https://inesonic.com
 * License:           GPLv3
 * License URI:
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Text Domain:       inesonic-mailer
 * Domain Path:       /locale
 ***********************************************************************************************************************
 * Copyright 2021-2022, Inesonic, LLC
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 * \file inesonic-mailer.php
 *
 * Main plug-in file.
 */

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/include/options.php";
require_once __DIR__ . "/include/plugin-page.php";

/**
 * Inesonic WordPress plug-in that sends promotional mails to customers.
 */
class InesonicMailer {
    const VERSION = '1.0.0';
    const SLUG    = 'inesonic-mailer';
    const NAME    = 'Inesonic Mailer';
    const AUTHOR  = 'Inesonic, LLC';
    const PREFIX  = 'InesonicMailer';

    /**
     * The plug-in template directory
     */
    const DEFAULT_TEMPLATE_DIRECTORY = __DIR__ . '/assets/templates/';

    /**
     * Options prefix.
     */
    const OPTIONS_PREFIX = 'inesonic_mailer';

    /**
     * The desired length of the nonce, in characters.
     */
    const NONCE_LENGTH = 32;

    /**
     * The singleton class instance.
     */
    private static $instance;  /* Plug-in instance */

    /**
     * Method that is called to initialize a single instance of the plug-in
     */
    public static function instance() {
        if (!isset(self::$instance) && !(self::$instance instanceof InesonicMailer)) {
            self::$instance = new InesonicMailer();
        }
    }

    /**
     * Static method that is triggered when the plug-in is activated.
     */
    public static function plugin_activated() {
        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('activate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query(
                    'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_mailer_transitions' . ' (' .
                        'user_id BIGINT UNSIGNED NOT NULL,' .
                        'old_role VARCHAR(48) NOT NULL,' .
                        'new_role VARCHAR(48) NOT NULL,' .
                        'change_timestamp BIGINT UNSIGNED NOT NULL,' .
                        'PRIMARY KEY (user_id),' .
                        'FOREIGN KEY (user_id) REFERENCES ' . $wpdb->prefix . 'users (ID) ' .
                            'ON DELETE CASCADE' .
                    ')'
                );
                $wpdb->query(
                    'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_mailer_processed_events' . ' (' .
                        'user_id BIGINT UNSIGNED NOT NULL,' .
                        'processed_event VARCHAR(32) NOT NULL,' .
                        'one_time BOOLEAN NOT NULL,' .
                        'PRIMARY KEY (user_id, processed_event),' .
                        'FOREIGN KEY (user_id) REFERENCES ' . $wpdb->prefix . 'users (ID) ' .
                            'ON DELETE CASCADE' .
                    ')'
                );
                $wpdb->query(
                    'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'inesonic_mailer_nonces' . ' (' .
                        'user_id BIGINT UNSIGNED NOT NULL,' .
                        'nonce CHAR(' . self::NONCE_LENGTH . ') NOT NULL,' .
                        'PRIMARY KEY (user_id),' .
                        'FOREIGN KEY (user_id) REFERENCES ' . $wpdb->prefix . 'users (ID) ' .
                            'ON DELETE CASCADE' .
                    ')'
                );
            }
        }
    }

    /**
     * Static method that is triggered when the plug-in is deactivated.
     */
    public static function plugin_uninstalled() {
        if (defined('ABSPATH') && current_user_can('activate_plugins')) {
            $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
            if (check_admin_referer('deactivate-plugin_' . $plugin)) {
                global $wpdb;
                $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'inesonic_mailer_transitions');
                $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'inesonic_mailer_processed_events');
                $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'inesonic_mailer_nonces');
            }
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->loader = null;
        $this->twig_template_environment = null;

        $this->mailer_transitions = null;
        $this->mailer_events = null;

        $this->options = new Inesonic\Mailer\Options(self::OPTIONS_PREFIX, self::DEFAULT_TEMPLATE_DIRECTORY);
        $this->plugin_page = new Inesonic\Mailer\PlugInsPage(plugin_basename(__FILE__), self::NAME, $this->options);

        add_action('init', array($this, 'customize_on_initialization'));
    }

    /**
     * Method that performs various initialization tasks during WordPress init phase.
     */
    public function customize_on_initialization() {
        add_action('set_user_role', array($this, 'user_role_changed'), 10, 3);

        add_filter(
            'inesonic-filter-page-cancellation-survey',
            array($this, 'validate_cancellation_survey_page')
        );

        add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));
        add_action('inesonic-mailer-send-pending', array($this, 'send_pending'));
        if (!wp_next_scheduled('inesonic-mailer-send-pending')) {
            $time = time() + 20;
            wp_schedule_event($time, 'inesonic-every-ten-minutes', 'inesonic-mailer-send-pending');
        }
    }

    /**
     * Method that adds custom CRON intervals for testing.
     *
     * \param[in] $schedules The current list of CRON intervals.
     *
     * \return Returns updated schedules with new CRON entries added.
     */
    public function add_custom_cron_interval($schedules) {
        $schedules['inesonic-every-ten-minutes'] = array(
            'interval' => 10 * 60,
            'display' => esc_html__('Every 10 minutes')
        );

        return $schedules;
    }

    /**
     * Method that is triggered to send pending emails.
     */
    public function send_pending() {
        // When we get larger, we will probably want to farm this work out as a distinct microservice.  For now, we do
        // this here.

        $pending_events = $this->identify_pending();
        if (count($pending_events) > 0) {
            $this->process_pending($pending_events);
        }
    }

    /**
     * Method that is triggered when a user's role is changed.
     *
     * \param[in] $user_id   The ID of the user that just changed.
     *
     * \param[in] $new_role  The new user role.
     *
     * \param[in] $old_roles The list of old roles tied to this user.
     */
    public function user_role_changed($user_id, $new_role, $old_roles) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'inesonic_mailer_transitions',
            array('user_id' => $user_id),
            array('%d')
        );

        $wpdb->query(
            'DELETE FROM ' . $wpdb->prefix . 'inesonic_mailer_processed_events' . ' WHERE ' .
                'user_id = ' . $user_id . ' AND one_time = FALSE'
        );

        $wpdb->insert(
            $wpdb->prefix . 'inesonic_mailer_transitions',
            array(
                'user_id' => $user_id,
                'old_role' => end($old_roles),
                'new_role' => $new_role,
                'change_timestamp' => time()
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%d'
            )
        );
    }

    /**
     * Method that is triggered when the cancellation survey is triggered.  We use this to validate that the supplied
     * nonce is valid.
     *
     * \param[in] $page_value The current page value.
     */
    public function validate_cancellation_survey_page($page_value) {
        if (array_key_exists('nonce', $_GET)) {
            $nonce = sanitize_text_field($_GET['nonce']);
        } else {
            $nonce = null;
        }

        global $wpdb;
        $query_results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT user_id FROM ' . $wpdb->prefix . 'inesonic_mailer_nonces' . ' WHERE nonce = %s',
                $nonce
            )
        );

        if ($wpdb->num_rows == 0) {
            $page_value = __(
                '<p>&nbsp;</p>
                 <p>&nbsp;</p>
                 <div class="et_pb_text_inner">
                   <p align="center"
                      style="font-size: 18px; color: #006DFA; font-family: Open Sans, Arial, sans-serif"
                   >
                     Your anti-spam access code (nonce) is invalid.  Please try again.
                   </p>
                 </div>
                 <p>&nbsp;</p>
                 <p>&nbsp;</p>',
                'inesonic-mailer'
            );
        }

        return $page_value;
    }

    /**
     * Method that obtains a list of pending events to be processed.
     *
     * \return Returns an array of pending processed events.  The array is keyed by event type where each entry
     *         contains a list of user IDs.
     */
    private function identify_pending() {
        $current_timestamp = time();
        $transitions = $this->transitions();

        $pending_events = array();
        foreach ($transitions as $old_role => $new_roles) {
            foreach ($new_roles as $new_role => $role_rules) {
                foreach ($role_rules as $event => $delay) {
                    global $wpdb;

                    $tt = $wpdb->prefix . 'inesonic_mailer_transitions';
                    $et = $wpdb->prefix . 'inesonic_mailer_processed_events';
                    $query_string = $wpdb->prepare(
                        'SELECT tt.user_id AS user_id FROM ' . $tt . ' AS tt WHERE ' .
                            'old_role = %s AND ' .
                            'new_role = %s AND ' .
                            'change_timestamp < %d AND ' .
                            '%s NOT IN (SELECT et.processed_event FROM ' . $et . ' AS et WHERE user_id = tt.user_id)',
                        $old_role,
                        $new_role,
                        $current_timestamp - $delay,
                        $event
                    );
                    $query_results = $wpdb->get_results($query_string);

                    if (count($query_results) > 0) {
                        $user_ids = array();
                        foreach ($query_results as $query_result) {
                            $user_ids[] = $query_result->user_id;
                        }

                        if (array_key_exists($event, $pending_events)) {
                            $pending_events[$event] = array_merge($pending_events[$event], $user_ids);
                        } else {
                            $pending_events[$event] = $user_ids;
                        }
                    }
                }
            }
        }

        return $pending_events;
    }

    /**
     * Method that is triggered to process pending events.
     *
     * \param[in] $pending_events An array keyed by event type containing a list of users to be processed for each
     *                            event.
     */
    private function process_pending($pending_events) {
        $events = $this->events();

        foreach ($pending_events as $event => $user_ids) {
            if (array_key_exists($event, $events)) {
                $event_data = $events[$event];
                if (array_key_exists('one-time', $event_data)) {
                    $one_time = $event_data['one-time'];
                } else {
                    $one_time = false;
                }

                if (array_key_exists('action', $event_data)) {
                    $action = $event_data['action'];
                } else {
                    $action = 'email';
                }

                if ($action == 'email' || $action == 'email_with_nonce') {
                    $include_nonce = ($action == 'email_with_nonce');
                    $mark_processed = $this->process_email_event($event, $event_data, $user_ids, $include_nonce);
                } else if ($action == 'none') {
                    $mark_processed = true;
                } else if ($action == 'ignore') {
                    $mark_processed = false;
                } else {
                    $mark_processed = false;
                    self::log_error('Inesonic Mailer: Event ' . $event . ' unknown action ' . $action);
                }

                if ($mark_processed) {
                    $this->mark_event_processed($event, $one_time, $user_ids);
                }
            } else {
                self::log_error('Inesonic Mailer: Unknown event ' . $event);
            }
        }
    }

    /**
     * Method that is triggered to process a pending email event.
     *
     * \param[in] $event_name    The name of the event.
     *
     * \param[in] $event_data    Data required for the event.
     *
     * \param[in] $user_ids      A list of users to receive notifications.
     *
     * \param[in] $include_nonce If true, then a per-user nonce should be included in each email.
     *
     * \return Returns true if the event was processed.  Returns false if the event was not processed.
     */
    private function process_email_event($event_name, $event_data, $user_ids, $include_nonce) {
        if (array_key_exists('template', $event_data)) {
            if (array_key_exists('subject', $event_data)) {
                if (array_key_exists('from', $event_data)) {
                    $template = $event_data['template'];
                    $subject = $event_data['subject'];
                    $from_address = $event_data['from'];

                    $template_environment = $this->template_environment();

                    $parameters = $event_data;
                    $parameters['site_url'] = site_url();

                    foreach ($user_ids as $user_id) {
                        $user_data = get_user_by('ID', $user_id);
                        $email_address = $user_data->user_email;
                        $display_name = $user_data->display_name;
                        $user_login = $user_data->user_login;
                        $user_role = end($user_data->roles);

                        $parameters['email_address'] = $email_address;
                        $parameters['display_name'] = $display_name;
                        $parameters['user_login'] = $user_login;
                        $parameters['role'] = $user_role;

                        if ($include_nonce) {
                            $nonce = $this->generate_user_nonce($user_id);
                            $parameters['nonce'] = $nonce;
                        }

                        try {
                            $message = $this->template_environment()->render($template, $parameters);
                        } catch (\Twig\Error\LoaderError $e) {
                            $message = null;
                            $error_message = $e->getMessage();
                        }

                        if ($message !== null) {
                            $headers = array(
                                'Content-Type: text/html; charset=UTF-8',
                                'Reply-To: <' . $from_address . '>'
                            );

                            $success = wp_mail($email_address, $subject, $message, $headers);
                            if ($success) {
                                do_action(
                                    'inesonic_add_history',
                                    $user_data->ID,
                                    'MAILER',
                                    $event_name . ' -> ' . $user_data->user_email
                                );
                            } else {
                                self::log_error(
                                    'Inesonic Mailer: Failed to send reset ' . $event_name . ' email to ' .
                                    $user_data->user_email
                                );
                            }

                            $mark_processed = true;
                        } else {
                            $mark_processed = false;
                            self::log_error(
                                'Inesonic Mailer: Event ' . $event_name . ' template error: ' . $error_message
                            );
                        }
                    }
                } else {
                    $mark_processed = false;
                    self::log_error(
                        'Inesonic Mailer: Event ' . $event_name . ' requires from parameter.'
                    );
                }
            } else {
                $mark_processed = false;
                self::log_error(
                    'Inesonic Mailer: Event ' . $event_name . ' requires subject parameter.'
                );
            }
        } else {
            $mark_processed = false;
            self::log_error(
                'Inesonic Mailer: Event ' . $event_name . ' requires templates parameter.'
            );
        }

        return $mark_processed;
    }

    /**
     * Method that is triggered to mark processed events for a group of users.
     *
     * \param[in] $event_name The name of the event to be processed.
     *
     * \param[in] $one_time   A flag indicating if this event should only ever occur once for a user.  Setting this
     *                        flag will cause the processed event entry to remain even if the user has a transition
     *                        that retriggers the event.
     *
     * \param[in] $user_ids   A list of user IDs.
     */
    private function mark_event_processed($event_name, $one_time, $user_ids) {
        if (count($user_ids) > 0) {
            $event = esc_sql($event_name);

            global $wpdb;
            $wpdb->query(
                'DELETE FROM ' . $wpdb->prefix . 'inesonic_mailer_processed_events' . ' WHERE ' .
                    "processed_event = '" . $event . "' AND " .
                    'user_id IN (' . implode(',', $user_ids) . ')'
            );

            $one_time_str = $one_time ? 'TRUE' : 'FALSE';
            $query_string = 'INSERT INTO ' . $wpdb->prefix . 'inesonic_mailer_processed_events' . ' ' .
                            '(user_id, processed_event, one_time) VALUES ';
            $first = true;
            foreach ($user_ids as $user_id) {
                if ($first) {
                    $query_string .= '(' . $user_id . ",'" . $event . "'," . $one_time_str . ')';
                    $first = false;
                } else {
                    $query_string .= ',(' . $user_id . ",'" . $event . "'," . $one_time_str . ')';
                }
            }

            $wpdb->query($query_string);
        }
    }

    /**
     * Method that generates a per-user nonce.
     *
     * \param[in] $user_id The ID of the user.
     *
     * \return Returns a nonce for this user.
     */
    private function generate_user_nonce($user_id) {
        global $wpdb;
        $query_result = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT nonce FROM ' . $wpdb->prefix . 'inesonic_mailer_nonces' . ' WHERE user_id = %d',
                $user_id
            )
        );

        if ($wpdb->num_rows > 0) {
            $nonce = $query_result[0]->nonce;
        } else {
            $nonce = '';
            $random_sequence = random_bytes((self::NONCE_LENGTH * 6 + 7)/8);
            $nonce = substr(strtr(base64_encode($random_sequence), '+/', '-_'), 0, self::NONCE_LENGTH);

            $wpdb->insert(
                $wpdb->prefix . 'inesonic_mailer_nonces',
                array('user_id' => $user_id, 'nonce' => $nonce),
                array('%d', '%s')
            );
        }

        return $nonce;
    }

    /**
     * Method that returns the mailer transitions.
     *
     * \return Returns the array of mailer transitions.
     */
    private function transitions() {
        if ($this->mailer_transitions === null) {
            $this->mailer_transitions = \Symfony\Component\Yaml\Yaml::parse($this->options->transitions());
        }

        return $this->mailer_transitions;
    }

    /**
     * Method that returns the mailer events.
     *
     * \return Returns the array of mailer events.
     */
    private function events() {
        if ($this->mailer_events === null) {
            $this->mailer_events = \Symfony\Component\Yaml\Yaml::parse($this->options->events());
        }

        return $this->mailer_events;
    }

    /**
     * Method that returns the TWIG template environment.
     *
     * \return Returns the current Twig template environment.
     */
    private function template_environment() {
        if ($this->loader === null || $this->twig_template_environment === null) {
            $this->loader = new \Twig\Loader\FilesystemLoader($this->options->template_directory());
            $this->twig_template_environment = new \Twig\Environment($this->loader);
        }

        return $this->twig_template_environment;
    }

    /**
     * Static method that logs an error.
     *
     * \param[in] $error_message The error to be logged.
     */
    static private function log_error($error_message) {
        error_log($error_message);
        do_action('inesonic-logger-1', $error_message);
    }
}

/* Instatiate our plug-in. */
InesonicMailer::instance();

/* Define critical global hooks. */
register_activation_hook(__FILE__, array('InesonicMailer', 'plugin_activated'));
register_uninstall_hook(__FILE__, array('InesonicMailer', 'plugin_uninstalled'));
