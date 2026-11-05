# Changelog

## 2.13.2 - 2026-11-05
- Email: the partly hidden address now shows the first two and last two characters of the name and the whole domain (`cb***77@cbcg.ie`). Names of 4 characters or fewer show only the first character.

## 2.13.1 - 2026-11-05
- Email: addresses are partly hidden in the timeline, table, search and CSV/PDF export. Set `MASK_EMAILS` to `false` in `src/Timeline.php` to show them in full.

## 2.13.0 - 2026-11-05
- New **Email** type for the "Student Email" text field of the Fields plugin. The Fields plugin does not write its changes to the GLPI history, so two small tables are used (`glpi_plugin_devicetimeline_emailsnap`, `glpi_plugin_devicetimeline_emailevents`). Changes are detected when the field is saved (update hook), every 5 minutes by the automatic action `SyncEmails`, and when the tab is opened.

## 2.12.0 - 2026-11-05
- First attempt at the Email type (e-mail of the user assigned to the computer). Replaced by 2.13.0.

## 2.11.0
- Comments, Status, Location, Problems, Tickets, Changes and Links; timeline and table view; search, paging, CSV/PDF export; scope switch; device details card.

## 2.0 - 2.10
- Version 2 reads the GLPI history (`glpi_logs`) instead of an own table.

## 1.x
- First version with an own table and `item_update` hook.
