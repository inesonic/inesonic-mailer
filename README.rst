===============
inesonic-mailer
===============
You can use this plugin to trigger customer facing emails a specified time
after after a customer's WordPress role changes.  This plugin supports the
following additional features:

* Emails are generated using the Symfony\Twig templating engine.

* Delay after a role transition is specified in seconds and checked on 10
  minute intervals allowing for close to immediate transmission of emails up
  to months.

* Subsequent role changes will cancel scheduled emails.  If needed, new emails
  will be scheduled based on the new role change.

* You can include custom email template fields based on event.

* The inesonic-mailer plugin can optionally generate random, customer unique
  nonces that can be embedded in emails.  The inesonic-mailer plugin includes
  facilities to validate the nonce when pages are rendered.

This plugin will optionally use the
`Inesonic Logger <https://github.com/tuxidriver/inesonic-logger>` plugin to
report errors in your settings.  Errors are also logged to the WordPress/PHP
error log file.


Using This Plugin
=================
To use, copy this entire directory into your WordPress plugins directory
and then activate the plugin from the WordPress admin panel.

Once activated, you can use the "Configure" on the WordPress plugins page to
tie this plugin to Redmine.  You'll need to set the Email Template Directory,
Transitions, and Events fields.

The plugin works by tracking transitions which trigger events.  In theory, the
plugin could be extended to support a wide range of events, although only
four event types are currently supported.

You control the plugin by defining transitions, with a delay, which then
triggers a requested event.  Multiple transitions can trigger the same event
with the same or different delays.

The "Transitions" entry allows you to specify the transitions while the
"Events" entry allows you to define what to do on each event.


Transitions
------------------------------
The Transitions field should contain a YAML description of the transitions and
events that should be triggered, with delay.

Below is a simple example transitions specification:

.. code-block::

   new:
     personal_user:
       send-welcome: 1
       provide-review: 604800
     professional_user:
       send-welcome: 1
       provide-review: 604800
     enterprise_user:
       send-welcome: 1
       provide-review: 1209600
   personal_user:
     inactive:
       cancellation-survey: 1
   professional_user:
     inactive:
       cancellation-survey: 1

The example above will trigger the send-welcome event 1 second after the user
transitions from the "new" role to the "personal_user", "professional_user", or
"enterprise_user" role.   The example will trigger emails 1 week (604800
seconds) after users transitions from the "new" user role to the
"personal_user" or "professional_user" role.  The example will also trigger
emails 2 weeks (1209600 seconds) after transitioning from the "new" user role
to the "enterprise_user" role.  Lastly, the example will trigger the
cancellation-survey event when "personal_user"'s or "professional_user"'s
transition to an inactive role.

Note that the role names are as stored internally in WordPress, not as shown
in the dialog.  You will need to determine the correct internal role names in
order to configure this plugin.   Also note that delay values can vary by as
much as 10 minutes so setting a delay of 1 second really means between 1 second
and 10 minutes.  Lastly note that the indentation shown above matters and that
you should always use spaces, not tabs.


Events
------
The "Events" field allows you to specify how events should be handled.  Every
event you reference in the "Transitions" field should have an event defined
here.

The "Events" field, like the "Transitions" field is a YAML specification.  You
should specify the event names, left justified as dictionary keys with indented
fields describing the event.  Note that the dictionary keys are somewhat
open-ended and can be used as email template parameters allowing you to use one
email template to send out several different simlar types of emails.

The table below lists the expected event fields.

+----------+------------------------------------------------------------------+
| Field    | Description                                                      |
+==========+==================================================================+
| action   | The action to be performed.  Supported actions are:              |
|          |                                                                  |
|          | * **none** - Do nothing.  Do not try to process this event in    |
|          |   future.                                                        |
|          | * **ignore** - Do nothing.  Allow this event to be processed in  |
|          |   future.                                                        |
|          | * **email** - Send out an email.  The event will not be          |
|          |   processed in future.                                           |
|          | * **email_with_nonce** - Send out an email with a customer       |
|          |   unique nonce that can be used to validate a page when viewed   |
|          |   by the customer.                                               |
+----------+------------------------------------------------------------------+
| one-time | If true, then this event will never be processed again for the   |
|          | customer if the same role change occurs again.  This can be used |
|          | to block repeat emails if a customer role could transition more  |
|          | than once.                                                       |
+----------+------------------------------------------------------------------+
| subject  | Subject line for email actions.  Ignored for the **none** and    |
|          | **ignore** actions.                                              |
+----------+------------------------------------------------------------------+
| from     | From address to use for sent emails.                             |
+----------+------------------------------------------------------------------+
| template | The email template file.                                         |
+----------+------------------------------------------------------------------+

Below is a simple example "Events" specification:

.. code-block::

   provide-review:
     one-time: true
     action: ignore
     subject: "Please review our great product!"
     from: "ignored@mysite.com"
     template: "request_review.html"
     review-url: "https://www.greatreviews.com"
     review-site: "Great Product Reviews"

   cancellation-survey:
     one-time: false
     action: email_with_nonce
     subject: "Please tell us why you cancelled your subscription."
     from: "ignored@mysite.com"
     template: cancellation_survey_invite.html

In the example above, the event "provide-review" is staged but doesn't do
anything.  Eventually we can change the "ignore" action to "email" to send out
invitations to review our product.

The "cancellation-survey" event will send out an email with a customer unique
random nonce.   The event can be triggered multiple times should a transition
occur that re-triggers this event has been processed.  The email will
include the subject "Please tell us why you cancelled your subscription." and
the email will be generated using the "cancellation_survey_invite.html"
template.


Email Template Directory
------------------------
To send out emails, you'll need to create email template files, placing those
files on your website.  Email templates can be placed anywhere that you have
access to.  You can specify a directory where these templates are placed in
this field.  Email templates are discussed in more detail below.


Email Templates
===============
Email text content is generated using the Symfony\Twig library.  Documentation
can be found at https://twig.symfony.com/.  Templates will contain all the
fields defined for the event.  In addition, templates will contain the
following parameters.

+--------------------+--------------------------------------------------------+
| Template Parameter | Description                                            |
+====================+========================================================+
| email_address      | The user's email address.                              |
+--------------------+--------------------------------------------------------+
| display_name       | The user's display name (first name last name).        |
+--------------------+--------------------------------------------------------+
| user_login         | The user's login username.                             |
+--------------------+--------------------------------------------------------+
| role               | The current user role.                                 |
+--------------------+--------------------------------------------------------+
| nonce              | This parameter is only provided if the                 |
|                    | **email_with_nonce** action is used.  This parameter   |
|                    | contains the generated nonce.  The nonce is suitable   |
|                    | for use in URL query strings.                          |
+--------------------+--------------------------------------------------------+
| site_url           | The website URL.  You can use to to reference          |
|                    | back to your website.                                  |
+--------------------+--------------------------------------------------------+

Template parameters can be referenced in templates by surrounding them in
double braces.  For example, to include the user's display name, you would
include ``{{ display_name }}`` in your template.

A simple example template is shown below:

.. code-block::html

   <!DOCTYPE html>
   <html dir="ltr" lang="en-us">
     <head>
       <title>Your Subscription Is Cancelled</title>
       </head>
     <body>
       <p>
         Dear {{ display_name }},
       </p>
       <p>
         We're sad to see that you cancelled your subscription.
       </p>
       <p>
         In order for us to win you back someday, could you please tell us why
         you decided to cancel by clicking on the link below ?
       </p>
       <p align="center">
         <a href="{{ site_url }}/cancellation-survey/?nonce={{ nonce }}">Survey</a>
       </p>
       <p>
         Thank you !
       </p>
     </body>
   </html>

We provide several examples we use at `Inesonic <https://https://inesonic.com>`
in the assets/templates directory.
