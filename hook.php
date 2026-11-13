<?php

/**
 * Device Timeline - install / update / uninstall.
 * Comments, Status, Location, Links come from GLPI's built-in history (glpi_logs).
 * The "Email" type (Fields plugin text field) uses two small tables, because the Fields plugin
 * does not write its changes to the GLPI history:
 *  - glpi_plugin_devicetimeline_emailsnap   last known value per computer
 *  - glpi_plugin_devicetimeline_emailevents detected changes
 * Uninstalling keeps these tables (history is never deleted).
 */

use GlpiPlugin\Devicetimeline\Timeline;

function plugin_devicetimeline_create_tables(): void
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_devicetimeline_emailsnap')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_devicetimeline_emailsnap` (
            `computers_id` int {$sign} NOT NULL,
            `value` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`computers_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }
    if (!$DB->tableExists('glpi_plugin_devicetimeline_emailevents')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_devicetimeline_emailevents` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `computers_id` int {$sign} NOT NULL DEFAULT 0,
            `date_mod` timestamp NULL DEFAULT NULL,
            `old_value` varchar(255) NOT NULL DEFAULT '',
            `new_value` varchar(255) NOT NULL DEFAULT '',
            `user_name` varchar(255) NOT NULL DEFAULT '-',
            PRIMARY KEY (`id`),
            KEY `computers_id` (`computers_id`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
    }
}

function plugin_devicetimeline_install()
{
    plugin_devicetimeline_create_tables();
    CronTask::register(Timeline::class, 'SyncEmails', 300, [
        'comment' => 'Records changes of the Student Email field (Device Timeline)',
        'mode'    => CronTask::MODE_INTERNAL,
        'state'   => CronTask::STATE_WAITING,
    ]);
    Timeline::syncEmails('-');
    return true;
}

function plugin_devicetimeline_update($from_version)
{
    return plugin_devicetimeline_install();
}

function plugin_devicetimeline_uninstall()
{
    CronTask::unregister('devicetimeline');
    return true;
}
