<?php
 /**********************************************************************************************************************
 * Copyright 2021, Inesonic, LLC
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
 */

namespace Inesonic\Mailer;
    require_once dirname(__FILE__) . '/helpers.php';
    require_once dirname(__FILE__) . '/options.php';

    /**
     * Class that manages options displayed within the WordPress Plugins page.
     */
    class PlugInsPage {
        /**
         * Static method that is triggered when the plug-in is activated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_activated(Options $options) {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         *
         * \param $options The plug-in options instance.
         */
        public static function plugin_deactivated(Options $options) {}

        /**
         * Constructor
         *
         * \param[in] $plugin_basename    The base name for the plug-in.
         *
         * \param[in] $plugin_name        The user visible name for this plug-in.
         *
         * \param[in] $options            The plug-in options API.
         */
        public function __construct(
                string  $plugin_basename,
                string  $plugin_name,
                Options $options
            ) {
            $this->plugin_basename = $plugin_basename;
            $this->plugin_name = $plugin_name;
            $this->options = $options;

            add_action('init', array($this, 'on_initialization'));
        }

        /**
         * Method that is triggered during initialization to bolt the plug-in settings UI into WordPress.
         */
        public function on_initialization() {
            add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_plugin_page_links'));
            add_action(
                'after_plugin_row_' . $this->plugin_basename,
                array($this, 'add_plugin_configuration_fields'),
                10,
                3
            );

            add_action('wp_ajax_inesonic_mailer_get_settings' , array($this, 'get_settings'));
            add_action('wp_ajax_inesonic_mailer_update_settings' , array($this, 'update_settings'));
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_page_links(array $links) {
            $configuration = "<a href=\"###\" id=\"inesonic-mailer-configure-link\">" .
                               __("Configure", 'inesonic-mailer') .
                             "</a>";
            array_unshift($links, $configuration);

            return $links;
        }

        /**
         * Method that adds links to the plug-ins page for this plug-in.
         */
        public function add_plugin_configuration_fields(string $plugin_file, array $plugin_data, string $status) {
            echo '<tr id="inesonic-mailer-configuration-area-row"
                      class="inesonic-mailer-configuration-area-row inesonic-row-hidden">
                    <th></th> .
                    <td class="inesonic-mailer-configuration-area-column" colspan="3">
                      <table style="width: 100%;"><tbody>
                        <tr>
                          <td colspan="3">' .
                            __("Email Template Directory:", 'inesonic-mailer') . '<br/>
                            <input type="text"
                                   class="inesonic-mailer-input"
                                   id="inesonic-mailer-template-directory"/>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="3">
                            <div class="inesonic-mailer-settings-area-outer">
                              <span class="inesonic-mailer-settings-area">
                                <label>' . __("Transitions", 'inesonic-mailer') . '
                                  <br/>
                                  <textarea placeholder="' . __("Enter YAML role transitions", 'inesonic-mailer') . '"
                                            class="inesonic-mailer-textarea"
                                            id="inesonic-mailer-transitions"
                                  ></textarea>
                                </label>
                              </span>
                              <span class="inesonic-mailer-settings-area">
                                <label>' . __("Events", 'inesonic-mailer') . '
                                  <br/>
                                  <textarea placeholder="' . __("Enter YAML events", 'inesonic-mailer') . '"
                                            class="inesonic-mailer-textarea"
                                            id="inesonic-mailer-events"
                                  ></textarea>
                                </label>
                              </span>
                            </div>
                          </td>
                        </tr>
                        <tr>
                          <td colspan="3">
                            <div class="inesonic-mailer-button-wrapper">
                              <a id="inesonic-mailer-configure-submit-settings-button"
                                 class="button action inesonic-mailer-button-anchor"
                              >' .
                                __("Submit", 'inesonic-mailer') . '
                              </a>
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>';

            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'inesonic-mailer-plugins-page',
                \Inesonic\Mailer\javascript_url('plugins-page'),
                array('jquery'),
                null,
                true
            );
            wp_localize_script(
                'inesonic-mailer-plugins-page',
                'ajax_object',
                array('ajax_url' => admin_url('admin-ajax.php'))
            );

            wp_enqueue_style(
                'inesonic-mailer-styles',
                \Inesonic\Mailer\css_url('inesonic-mailer-styles'),
                array(),
                null
            );
        }

        /**
         * Method that is triggered to get the current Mailer settings.
         */
        public function get_settings() {
            if (current_user_can('activate_plugins')) {
                $response = array(
                    'status' => 'OK',
                    'template_directory' => $this->options->template_directory(),
                    'transitions' => $this->options->transitions(),
                    'events' => $this->options->events()
                );
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }

        /**
         * Method that is triggered to update the Mailer settings.
         */
        public function update_settings() {
            if (current_user_can('activate_plugins')           &&
                array_key_exists('template_directory', $_POST) &&
                array_key_exists('transitions', $_POST)        &&
                array_key_exists('events', $_POST)                ) {
                $template_directory = sanitize_text_field($_POST['template_directory']);
                $transitions = stripslashes(sanitize_textarea_field($_POST['transitions']));
                $events = stripslashes(sanitize_textarea_field($_POST['events']));

                if (is_dir($template_directory)) {
                    $yaml_error = null;
                    try {
                        $parse_yaml = \Symfony\Component\Yaml\Yaml::parse($transitions);
                    } catch (Exception $e) {
                        $yaml_error = $e->getMessage();
                        $parse_yaml = null;
                    }

                    if ($yaml_error === null && is_array($parse_yaml)) {
                        try {
                            $parse_yaml = \Symfony\Component\Yaml\Yaml::parse($transitions);
                        } catch (Exception $e) {
                            $yaml_error = $e->getMessage();
                            $parse_yaml = null;
                        }

                        if ($yaml_error === null && is_array($parse_yaml)) {
                            $this->options->set_template_directory($template_directory);
                            $this->options->set_transitions($transitions);
                            $this->options->set_events($events);

                            $response = array('status' => 'OK');
                        } else {
                            $response = array('status' => 'Invalid Events YAML: ' . $yaml_error);
                        }
                    } else {
                        $response = array('status' => 'Invalid Transitions YAML: ' . $yaml_error);
                    }
                } else {
                    $response = array('status' => 'Template directory does not exist');
                }
            } else {
                $response = array('status' => 'failed');
            }

            echo json_encode($response);
            wp_die();
        }
    };
