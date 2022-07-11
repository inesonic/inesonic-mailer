<?php
/***********************************************************************************************************************
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
    /**
     * Trivial class that provides an API to plug-in specific options.
     */
    class Options {
        /**
         * Static method that is triggered when the plug-in is activated.
         */
        public function plugin_activated() {}

        /**
         * Static method that is triggered when the plug-in is deactivated.
         */
        public function plugin_deactivated() {}

        /**
         * Static method that is triggered when the plug-in is uninstalled.
         */
        public function plugin_uninstalled() {
            $this->delete_option('template_directory');
            $this->delete_option('transitions');
            $this->delete_option('events');
        }

        /**
         * Constructor
         *
         * \param[in] $options_prefix             The options prefix to apply to plug-in specific options.
         *
         * \param[in] $default_template_directory The default template directory to use.
         */
        public function __construct(string $options_prefix, string $default_template_directory) {
            $this->options_prefix = $options_prefix . '_';
            $this->default_template_directory = $default_template_directory;
        }

        /**
         * Method you can use to obtain the current plugin version.
         *
         * \return Returns the current plugin version.  Returns null if the version has not been set.
         */
        public function version() {
            return $this->get_option('version', null);
        }

        /**
         * Method you can use to set the current plugin version.
         *
         * \param $version The desired plugin version.
         *
         * \return Returns true on success.  Returns false on error.
         */
        public function set_version(string $version) {
            return $this->update_option('version', $version);
        }

        /**
         * Method you can use to obtain the Mailer template directory.
         *
         * \return Returns the path to the Mailer template directory.
         */
        public function template_directory() {
            return $this->get_option('template_directory', $this->default_template_directory);
        }

        /**
         * Method you can use to update the template directory.
         *
         * \param[in] $new_template_directory The new template directory.
         */
        public function set_template_directory(string $new_template_directory) {
            $this->update_option('template_directory', $new_template_directory);
        }

        /**
         * Method you can use to obtain the Mailer transitions.
         *
         * \return Returns the path to the Mailer transitions.
         */
        public function transitions() {
            return $this->get_option('transitions', null);
        }

        /**
         * Method you can use to update the transitions.
         *
         * \param[in] $transitions The new transitions.
         */
        public function set_transitions(string $transitions) {
            $this->update_option('transitions', $transitions);
        }

        /**
         * Method you can use to obtain the Mailer events.
         *
         * \return Returns the path to the Mailer events.
         */
        public function events() {
            return $this->get_option('events', null);
        }

        /**
         * Method you can use to update the events.
         *
         * \param[in] $events The new events.
         */
        public function set_events(string $events) {
            $this->update_option('events', $events);
        }

        /**
         * Method you can use to obtain a specific option.  This function is a thin wrapper on the WordPress get_option
         * function.
         *
         * \param $option  The name of the option of interest.
         *
         * \param $default The default value.
         *
         * \return Returns the option content.  A value of false is returned if the option value has not been set and
         *         the default value is not provided.
         */
        private function get_option(string $option, $default = false) {
            return \get_option($this->options_prefix . $option, $default);
        }

        /**
         * Method you can use to add a specific option.  This function is a thin wrapper on the WordPress update_option
         * function.
         *
         * \param $option The name of the option of interest.
         *
         * \param $value  The value to assign to the option.  The value must be serializable or scalar.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function update_option(string $option, $value = '') {
            return \update_option($this->options_prefix . $option, $value);
        }

        /**
         * Method you can use to delete a specific option.  This function is a thin wrapper on the WordPress
         * delete_option function.
         *
         * \param $option The name of the option of interest.
         *
         * \return Returns true on success.  Returns false on error.
         */
        private function delete_option(string $option) {
            return \delete_option($this->options_prefix . $option);
        }
    }
