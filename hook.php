<?php

/**
 * Device Timeline - install / update / uninstall.
 * This version needs no tables and no item_update hook:
 * it reads comment changes from GLPI's built-in history (glpi_logs).
 * The old table glpi_plugin_devicetimeline_events is left untouched.
 */

function plugin_devicetimeline_install()
{
    return true;
}

function plugin_devicetimeline_update($from_version)
{
    return true;
}

function plugin_devicetimeline_uninstall()
{
    return true;
}
