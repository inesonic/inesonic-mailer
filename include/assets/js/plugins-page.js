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
 * \file plugins-page.js
 *
 * JavaScript module that manages the mailer configuration via the WordPress Plug-Ins page.
 */

/***********************************************************************************************************************
 * Functions:
 */

/**
 * Function that displays the manual configuration fields.
 */
function inesonicMailerToggleConfiguration() {
    let areaRow = jQuery("#inesonic-mailer-configuration-area-row");
    if (areaRow.hasClass("inesonic-row-hidden")) {
        areaRow.prop("class", "inesonic-mailer-configuration-area-row inesonic-row-visible");
    } else {
        areaRow.prop("class", "inesonic-mailer-configuration-area-row inesonic-row-hidden");
    }
}

/**
 * Function that updates the mailer settings fields.
 *
 * \param[in] templateDirectory The Mailer template directory.
 *
 * \param[in] transitions       YAML description of all the role transitions.
 *
 * \param[in] events            Details on what needs to be done on each resulting event.
 */
function inesonicMailerUpdateSettingsFields(templateDirectory, transitions, events) {
    jQuery("#inesonic-mailer-template-directory").val(templateDirectory);
    jQuery("#inesonic-mailer-transitions").text(transitions);
    jQuery("#inesonic-mailer-events").text(events);
}

/**
 * Function that is triggered to update the mailer configuration fields.
 */
function inesonicMailerUpdateSettings() {
    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: { "action" : "inesonic_mailer_get_settings" },
            dataType: "json",
            success: function(response) {
                if (response !== null && response.status == 'OK') {
                    let templateDirectory = response.template_directory
                    let transitions = response.transitions;
                    let events = response.events;

                    inesonicMailerUpdateSettingsFields(templateDirectory, transitions, events);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not get Mailer settings: " + errorThrown);
            }
        }
    );
}

/**
 * Function that is triggered to update the mailer settings.
 */
function inesonicMailerConfigureSettingsSubmit() {
    let templateDirectory = jQuery("#inesonic-mailer-template-directory").val();
    let transitions = jQuery("#inesonic-mailer-transitions").val();
    let events = jQuery("#inesonic-mailer-events").val();

    jQuery.ajax(
        {
            type: "POST",
            url: ajax_object.ajax_url,
            data: {
                "action" : "inesonic_mailer_update_settings",
                "template_directory" : templateDirectory,
                "transitions" : transitions,
                "events" : events
            },
            dataType: "json",
            success: function(response) {
                if (response !== null) {
                    if (response.status == 'OK') {
                        inesonicMailerToggleConfiguration();
                    } else {
                        alert("Failed to update Mailer settings\n" + response.status);
                    }
                } else {
                    alert("Failed to update Mailer API key.");
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log("Could not update configuration: " + errorThrown);
            }
        }
    );
}

/***********************************************************************************************************************
 * Main:
 */

jQuery(document).ready(function($) {
    inesonicMailerUpdateSettings();
    $("#inesonic-mailer-configure-link").click(inesonicMailerToggleConfiguration);
    $("#inesonic-mailer-configure-submit-settings-button").click(inesonicMailerConfigureSettingsSubmit);
});
