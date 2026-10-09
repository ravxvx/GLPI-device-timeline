<?php

/**
 * Device Timeline - GLPI 11
 * Shows the history of the "Comments" field of a Computer as a timeline.
 * Data comes from GLPI's own history log (glpi_logs) - no custom hook needed.
 */

use GlpiPlugin\Devicetimeline\Timeline;

define('PLUGIN_DEVICETIMELINE_VERSION', '2.11.0');

function plugin_init_devicetimeline()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['devicetimeline'] = true;

    // Adds the "Timeline" tab on Computer items
    Plugin::registerClass(Timeline::class, ['addtabon' => ['Computer']]);
}

function plugin_version_devicetimeline()
{
    return [
        'name'         => 'Device Timeline',
        'version'      => PLUGIN_DEVICETIMELINE_VERSION,
        'author'       => 'School IT',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '11.0.99',
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

function plugin_devicetimeline_check_prerequisites()
{
    return true;
}

function plugin_devicetimeline_check_config($verbose = false)
{
    return true;
}
