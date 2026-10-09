# GLPI Device Timeline

<img width="397" height="152" alt="image" src="https://github.com/user-attachments/assets/e2e9af87-b1b6-4faf-ac2f-1a3379824846" />


<img width="295" height="267" alt="image" src="https://github.com/user-attachments/assets/bee393f6-f1a8-4a33-923e-137564555d00" />


Device Timeline is an open-source plugin for GLPI 11 that provides a chronological history of computer assets.

## Features

- View computer history in a timeline or table.
- Track comment, status and location changes.
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
2. Copy the plugin into the GLPI `plugins/devicetimeline` directory.
3. Open **Setup → Plugins** in GLPI.
4. Install and enable Device Timeline.
5. Open a computer under **Assets → Computers** and select the Timeline tab.

## Data and privacy

The plugin reads computer history from GLPI's existing history logs and does not create a new database table in version 2.11.0.

## License

See the `LICENSE` file for the applicable license terms.
