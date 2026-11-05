# GLPI Device Timeline

<img width="397" height="152" alt="image" src="https://github.com/user-attachments/assets/e2e9af87-b1b6-4faf-ac2f-1a3379824846" />


<img width="295" height="267" alt="image" src="https://github.com/user-attachments/assets/bee393f6-f1a8-4a33-923e-137564555d00" />


Device Timeline is an open-source plugin for GLPI 11 that provides a chronological history of computer assets.

## Features

- View computer history in a timeline or table.
- Track comment, status, location and link changes.
- Track changes of an e-mail text field of the Fields plugin (shown partly hidden for privacy, e.g. `cb***77@cbcg.ie`).
- View linked tickets, problems and changes.
- Filter events by device, model or all devices.
- Search and paginate through history.
- Export matching records to CSV or print to PDF.
- View key computer information directly in the Timeline tab.

## Requirements

- GLPI 11.0.x
- PHP 8.2 or newer

## Installation

1. Download or clone this repository.
2. Copy the files into the GLPI `plugins/devicetimeline` directory, keeping the layout `setup.php`, `hook.php`, `src/Timeline.php` (the folder name must be exactly `devicetimeline`).
3. Open **Setup → Plugins** in GLPI.
4. Install (or Update) and enable Device Timeline.
5. Open a computer under **Assets → Computers** and select the Timeline tab.

## Data and privacy

Comments, status, location, links, problems, tickets and changes are read from GLPI's existing history and link tables.

The **Email** type needs two small tables (`glpi_plugin_devicetimeline_emailsnap`, `glpi_plugin_devicetimeline_emailevents`) because the Fields plugin does not log its changes. They keep the full address (needed to detect changes); the timeline shows it partly hidden. Set `MASK_EMAILS` to `false` in `src/Timeline.php` to show full addresses. The tables are kept when the plugin is uninstalled.

The Email type has not been tested on many installations; please report problems as issues.

## Documentation and changes

- Full documentation: [`docs/DeviceTimeline-Documentation.docx`](docs/DeviceTimeline-Documentation.docx)
- Version history: [`CHANGELOG.md`](CHANGELOG.md)

## License

See the `LICENSE` file for the applicable license terms.
