moodle-tool_selfsignuphardlifecycle
===================================

[![Moodle Plugin CI](https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=MOODLE_403_STABLE)](https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3AMOODLE_403_STABLE)

Moodle admin tool to suspend and delete users based on their account creation date.


Requirements
------------

This plugin requires Moodle 4.3+


Motivation for this plugin
--------------------------

With this tool, users can be deleted (and optionally suspended) based on their account creation date. It is especially intended to get rid of users who have signed up themselves to Moodle based on a static schedule. 

The tool is quite simple and just acts on the user's account creation date. It does not consider if the user is still actively using his account or not. Furthermore, no notification emails are sent to the user before the account is suspended or deleted.


Installation
------------

Install the plugin like any other plugin to folder
/admin/tool/selfsignuphardlifecycle

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it does not do anything to Moodle yet.

To configure the plugin and its behaviour, please visit:
Site administration -> Users -> Hard life cycle for self-signup users

There, you find multiple pages:

### 1. Settings

On this page, you can enable and configure the behaviour this tool.

#### 1.1. Authentication methods

Here, you can configure which users are covered by this tool. If you select a particular authentication method, all users with this authentication method will become candidates for (suspension and) deletion. If you do not select a particular authentication method, all users with this authentication method will not be touched by this tool in any way.

#### 1.2. User deletion

Here, you can configure the number of days after which a user will be deleted by the tool.

#### 1.4. User suspension

Here, you can optionally configure the number of days after which a user will be suspended by the tool.

#### 1.5 User overrides

Here, you can allow the admin to override deletion and suspension dates for individual users.

#### 1.6 Cohort exceptions

Here, you can optionally configure cohorts which should be ignored by the tool.

### 2. User list

On this page, there is a list which shows all users which are covered by this tool according to the current configuration. You will also see the current status of each user and when the next step of the user's hard lifecycle will happen.


Capabilities
------------

This plugin does not add any additional capabilities.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

This plugin also introduces these additional scheduled tasks:

### \tool_selfsignuphardlifecycle\task\process_lifecycle

This is the main scheduled task which carries out all the lifecycle processes of this plugin.\
By default, the task is enabled and runs every night at one minute after midnight.


Theme support
-------------

This plugin acts behind the scenes, therefore it should work with all Moodle themes.
This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is published and regularly updated in the Moodle plugins repository:
http://moodle.org/plugins/view/tool_selfsignuphardlifecycle

The latest development version can be found on Github:
https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle


Bug and problem reports
-----------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle/issues


Community feature proposals
---------------------------

The functionality of this plugin is primarily implemented for the needs of our clients and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle/issues

Please create pull requests on Github:
https://github.com/lernlink/moodle-tool_selfsignuphardlifecycle/pulls


Paid support
------------

We are always interested to read about your issues and feature proposals or even get a pull request from you on Github. However, please note that our time for working on community Github issues is limited.

As certified Moodle Partner, we also offer paid support for this plugin. If you are interested, please have a look at our services on https://lern.link or get in touch with us directly via team@lernlink.de.


Moodle release support
----------------------

This plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

If you are running a legacy version of Moodle, but want or need to run the latest version of this plugin, you can get the latest version of the plugin, remove the line starting with $plugin->requires from version.php and use this latest plugin version then on your legacy Moodle. However, please note that you will run this setup completely at your own risk. We can't support this approach in any way and there is an undeniable risk for erratic behavior.


Translating this plugin
-----------------------

This Moodle plugin is shipped with an english language pack only. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.

As the plugin creator, we manage the translation into german for our own local needs on AMOS. Please contribute your translation into all other languages in AMOS where they will be reviewed by the official language pack maintainers for Moodle.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

lern.link GmbH\
Alexander Bias


Copyright
---------

lern.link GmbH\
Alexander Bias
