# GLPI Device Timeline

<img width="397" height="152" alt="image" src="https://github.com/user-attachments/assets/e2e9af87-b1b6-4faf-ac2f-1a3379824846" />


<img width="295" height="267" alt="image" src="https://github.com/user-attachments/assets/bee393f6-f1a8-4a33-923e-137564555d00" />


Device Timeline is an open-source plugin for GLPI 11 and 12 that provides a chronological history of computer assets.

**Download:** [`devicetimeline-2.14.1.tar.gz`](https://github.com/ravxvx/GLPI-device-timeline/raw/main/dist/devicetimeline-2.14.1.tar.gz) – ready to upload in GLPI Plugin Uploader or to extract into the GLPI plugins folder.

## Features

- View computer history in a timeline or table.
- Track comment, status and location changes.
- Track changes of the Student Email field from the Fields plugin (shown partly hidden, e.g. `cb***77@school.ie`; set `MASK_EMAILS = false` in `src/Timeline.php` to show them in full).
- View linked tickets, problems and changes.
- Filter events by device, model or all devices.
- Search and paginate through history.
- Export matching records to CSV or print to PDF.
- View key computer information directly in the Timeline tab.

## Requirements

- GLPI 11.0+ or 12.x (tested on 12.0.1)
- PHP 8.2 or newer

## Installation

**With [GLPI Plugin Uploader](https://github.com/ravxvx/glpi-plugin-uploader):** download this repository as ZIP (**Code → Download ZIP**), upload it in the Uploader, then click **Install / Update** and **Enable**.

**Manually:**

1. Download or clone this repository.
2. Copy the plugin into the GLPI `plugins/devicetimeline` (or `marketplace/devicetimeline`) directory.
3. Open **Setup → Plugins** in GLPI.
4. Install and enable Device Timeline.
5. Open a computer under **Assets → Computers** and select the Timeline tab.

## Data and privacy

Comments, status, location and linked tickets/problems/changes come from GLPI's own history log (`glpi_logs`) and link tables.

The Fields plugin does not write its changes to the GLPI history, so for the Student Email field the plugin keeps two small tables:

- `glpi_plugin_devicetimeline_emailsnap` – last known value per computer
- `glpi_plugin_devicetimeline_emailevents` – detected changes

An automatic action checks the field every 5 minutes. Uninstalling the plugin keeps these tables, so the history is never lost.

## License

See the `LICENSE` file for the applicable license terms.
