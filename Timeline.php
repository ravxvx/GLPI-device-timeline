<?php

namespace GlpiPlugin\Devicetimeline;

use CommonGLPI;
use Computer;
use Dropdown;
use Glpi\Search\SearchOption;
use Html;
use Search;

/**
 * Timeline tab for Computers.
 * Shows: changes of Comments, Status and Location (from GLPI's history, glpi_logs)
 * and Problems / Tickets / Changes linked to the computer.
 */
class Timeline extends CommonGLPI
{
    private const OTHER_LIMIT = 500;

    /** Linked ITIL objects shown in the timeline. */
    private const ITIL = [
        'problem' => [
            'label' => 'Problem', 'prefix' => 'P', 'class' => 'Problem',
            'link_table' => 'glpi_items_problems', 'fk' => 'problems_id', 'table' => 'glpi_problems',
        ],
        'ticket'  => [
            'label' => 'Ticket', 'prefix' => 'T', 'class' => 'Ticket',
            'link_table' => 'glpi_items_tickets', 'fk' => 'tickets_id', 'table' => 'glpi_tickets',
        ],
        'change'  => [
            'label' => 'Change', 'prefix' => 'C', 'class' => 'Change',
            'link_table' => 'glpi_changes_items', 'fk' => 'changes_id', 'table' => 'glpi_changes',
        ],
    ];

    private static ?array $fields = null;

    public static function getTypeName($nb = 0)
    {
        return 'Timeline';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Computer) {
            $count = countElementsInTable('glpi_logs', self::criteria((int) $item->getID()));
            return self::createTabEntry('Timeline', $count);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Computer) {
            self::showForComputer((int) $item->getID());
        }
        return true;
    }

    /**
     * Tracked fields and their search option ids (found dynamically, with fallbacks).
     */
    private static function trackedFields(): array
    {
        if (self::$fields !== null) {
            return self::$fields;
        }

        $found = ['comment' => null, 'status' => null, 'location' => null];
        $options = class_exists(SearchOption::class)
            ? SearchOption::getOptionsForItemtype('Computer')
            : Search::getOptions('Computer');

        foreach ($options as $id => $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $table = $opt['table'] ?? '';
            $field = $opt['field'] ?? '';
            if ($found['comment'] === null && $table === 'glpi_computers' && $field === 'comment') {
                $found['comment'] = (int) $id;
            } elseif ($found['status'] === null && $table === 'glpi_states') {
                $found['status'] = (int) $id;
            } elseif ($found['location'] === null && $table === 'glpi_locations') {
                $found['location'] = (int) $id;
            }
        }

        self::$fields = [
            'comment'  => ['id' => $found['comment'] ?? 16, 'label' => 'Comments'],
            'status'   => ['id' => $found['status'] ?? 31, 'label' => 'Status'],
            'location' => ['id' => $found['location'] ?? 3, 'label' => 'Location'],
        ];
        return self::$fields;
    }

    private static function fieldIds(): array
    {
        return array_values(array_map(static fn($f) => $f['id'], self::trackedFields()));
    }

    private static function criteria(int $computer_id): array
    {
        return [
            'itemtype'         => 'Computer',
            'items_id'         => $computer_id,
            'id_search_option' => self::fieldIds(),
        ];
    }

    private static function esc(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /** Name of a dropdown value ("" when empty). */
    private static function lookup(string $table, int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        return self::clean((string) Dropdown::getDropdownName($table, $id));
    }

    private static function clean(string $v): string
    {
        $v = trim(html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8'));
        return ($v === "\xC2\xA0" || $v === '&nbsp;') ? '' : $v;
    }

    private static function countLinked(string $table, array $where): int
    {
        global $DB;
        try {
            if (!$DB->tableExists($table)) {
                return 0;
            }
            return (int) countElementsInTable($table, $where);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Custom fields from the "Fields" plugin (for example the "IT Info" tab).
     * Password-type fields are never shown.
     */
    private static function customFieldsSections(int $id): string
    {
        global $DB;

        $out = '';
        try {
            if (!$DB->tableExists('glpi_plugin_fields_containers') || !$DB->tableExists('glpi_plugin_fields_fields')) {
                return '';
            }

            foreach ($DB->request([
                'FROM'  => 'glpi_plugin_fields_containers',
                'WHERE' => ['is_active' => 1],
            ]) as $ct) {
                $types = json_decode((string) ($ct['itemtypes'] ?? ''), true);
                if (!is_array($types) || !in_array('Computer', $types, true)) {
                    continue;
                }

                $table = 'glpi_plugin_fields_computer' . strtolower((string) $ct['name']) . 's';
                if (!$DB->tableExists($table)) {
                    continue;
                }

                $data = [];
                foreach ($DB->request(['FROM' => $table, 'WHERE' => ['items_id' => $id], 'LIMIT' => 1]) as $r) {
                    $data = $r;
                }

                $cells = '';
                foreach ($DB->request([
                    'FROM'  => 'glpi_plugin_fields_fields',
                    'WHERE' => ['plugin_fields_containers_id' => (int) $ct['id'], 'is_active' => 1],
                    'ORDER' => 'ranking',
                ]) as $fd) {
                    $name  = (string) $fd['name'];
                    $type  = (string) $fd['type'];
                    $label = (string) (($fd['label'] ?? '') !== '' ? $fd['label'] : $name);

                    if ($type === 'header') {
                        continue;
                    }

                    $col = $type === 'dropdown' ? 'plugin_fields_' . $name . 'dropdowns_id' : $name;
                    $raw = $data[$col] ?? null;

                    if ($type === 'yesno') {
                        $val = ($raw === null || $raw === '') ? '' : ((int) $raw === 1 ? 'Yes' : 'No');
                    } elseif ($type === 'dropdown') {
                        $val = self::lookup('glpi_plugin_fields_' . $name . 'dropdowns', (int) $raw);
                    } else {
                        $val = trim((string) $raw);
                    }

                    if (stripos($name . ' ' . $label, 'password') !== false) {
                        $val = $val !== '' ? "\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022} (hidden)" : '';
                    }

                    $cells .= '<div><div class="dt-k">' . self::attr($label) . '</div><div class="dt-v">'
                        . ($val !== '' ? self::attr($val) : '-') . '</div></div>';
                }

                if ($cells !== '') {
                    $title = (string) (($ct['label'] ?? '') !== '' ? $ct['label'] : $ct['name']);
                    $out .= '<div class="dt-card-section"><div class="dt-section-title">' . self::attr($title)
                        . '</div><div class="dt-card-grid">' . $cells . '</div></div>';
                }
            }
        } catch (\Throwable $e) {
            return $out;
        }

        return $out;
    }

    /** Operating system details of the computer (as on the Operating systems tab). */
    private static function osSection(int $id): string
    {
        global $DB;

        $rows = [];
        try {
            if (!$DB->tableExists('glpi_items_operatingsystems')) {
                return '';
            }
            foreach ($DB->request([
                'FROM'  => 'glpi_items_operatingsystems',
                'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
            ]) as $r) {
                $date = substr((string) ($r['install_date'] ?? ''), 0, 10);
                $rows[] = [
                    'Name'              => self::lookup('glpi_operatingsystems', (int) ($r['operatingsystems_id'] ?? 0)),
                    'Version'           => self::lookup('glpi_operatingsystemversions', (int) ($r['operatingsystemversions_id'] ?? 0)),
                    'Architecture'      => self::lookup('glpi_operatingsystemarchitectures', (int) ($r['operatingsystemarchitectures_id'] ?? 0)),
                    'Kernel'            => self::lookup('glpi_operatingsystemkernelversions', (int) ($r['operatingsystemkernelversions_id'] ?? 0)),
                    'Edition'           => self::lookup('glpi_operatingsystemeditions', (int) ($r['operatingsystemeditions_id'] ?? 0)),
                    'Service pack'      => self::lookup('glpi_operatingsystemservicepacks', (int) ($r['operatingsystemservicepacks_id'] ?? 0)),
                    'Installation date' => strpos($date, '0000') === 0 ? '' : $date,
                ];
            }
        } catch (\Throwable $e) {
            return '';
        }
        if (!$rows) {
            return '';
        }

        $h = '<div class="dt-card-section"><div class="dt-section-title">Operating system</div>';
        foreach ($rows as $parts) {
            $h .= '<div class="dt-card-grid">';
            foreach ($parts as $label => $v) {
                $h .= '<div><div class="dt-k">' . $label . '</div><div class="dt-v">'
                    . ($v !== '' ? self::attr($v) : '-') . '</div></div>';
            }
            $h .= '</div>';
        }
        return $h . '</div>';
    }

    /** Latest tickets linked to the computer. */
    private static function ticketsSection(int $id): string
    {
        global $DB;

        $meta    = self::ITIL['ticket'];
        $class   = $meta['class'];
        $tickets = [];
        try {
            $ids = [];
            foreach ($DB->request([
                'FROM'  => $meta['link_table'],
                'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
            ]) as $r) {
                $ids[] = (int) $r[$meta['fk']];
            }
            if ($ids) {
                foreach ($DB->request([
                    'FROM'  => $meta['table'],
                    'WHERE' => ['id' => $ids, 'is_deleted' => 0],
                    'ORDER' => 'date DESC',
                    'LIMIT' => 10,
                ]) as $o) {
                    $tickets[] = $o;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        $h = '<div class="dt-card-section"><div class="dt-section-title">Tickets</div>';
        if (!$tickets) {
            return $h . '<div class="dt-v">No tickets</div></div>';
        }
        $h .= '<ul class="dt-list-plain">';
        foreach ($tickets as $o) {
            $status = (class_exists($class) && method_exists($class, 'getStatus'))
                ? (string) $class::getStatus((int) $o['status']) : '';
            $h .= '<li><a href="' . self::attr($class::getFormURLWithID((int) $o['id'])) . '">#' . (int) $o['id'] . ' '
                . self::attr((string) $o['name']) . '</a> '
                . ($status !== '' ? '<span class="badge bg-orange-lt">' . self::attr($status) . '</span> ' : '')
                . '<span class="dt-k">' . date('d.m.Y', (int) strtotime((string) $o['date'])) . '</span></li>';
        }
        return $h . '</ul></div>';
    }

    /**
     * Links added directly to this computer with the "Add" button of the Links tab.
     * GLPI keeps them in glpi_manuallinks (name, url, comment, items_id, itemtype, date_creation).
     */
    private static function itemLinks(Computer $c): array
    {
        global $DB;

        $rows = [];
        try {
            if (!$DB->tableExists('glpi_manuallinks')) {
                return [];
            }
            foreach ($DB->request([
                'FROM'  => 'glpi_manuallinks',
                'WHERE' => ['itemtype' => 'Computer', 'items_id' => (int) $c->getID()],
            ]) as $l) {
                $rows[] = $l;
            }
        } catch (\Throwable $e) {
            return [];
        }

        usort($rows, static function ($x, $y) {
            return strcmp((string) ($y['date_creation'] ?? ''), (string) ($x['date_creation'] ?? ''));
        });

        $out = [];
        foreach ($rows as $l) {
            $name = trim((string) ($l['name'] ?? ''));
            $name = $name !== '' ? $name : 'Link';
            $url  = trim((string) ($l['url'] ?? ''));

            $html = preg_match('#^https?://#i', $url)
                ? '<a href="' . self::attr($url) . '" target="_blank" rel="noopener">' . self::attr($name) . '</a>'
                : self::attr($name);

            if (!empty($l['date_creation'])) {
                $html .= ' <span class="dt-k">added ' . date('d.m.Y', (int) strtotime((string) $l['date_creation'])) . '</span>';
            }
            $comment = trim((string) ($l['comment'] ?? ''));
            if ($comment !== '') {
                $html .= ' <span class="dt-k">&middot; ' . self::attr($comment) . '</span>';
            }
            $out[] = $html;
        }

        return $out;
    }

    /** Links of the Links tab: added to the device, or configured for computers. */
    private static function linksSection(Computer $c): string
    {
        global $DB;

        $items = [];
        try {
            if (class_exists(\Link::class) && method_exists(\Link::class, 'getAllLinksFor')) {
                $items = array_values((array) \Link::getAllLinksFor($c));
            }
        } catch (\Throwable $e) {
            $items = [];
        }

        foreach (self::itemLinks($c) as $extra) {
            $items[] = $extra;
        }
        if (!$items) {
            try {
                if ($DB->tableExists('glpi_links_itemtypes') && $DB->tableExists('glpi_links')) {
                    $link_ids = [];
                    foreach ($DB->request([
                        'FROM'  => 'glpi_links_itemtypes',
                        'WHERE' => ['itemtype' => 'Computer'],
                    ]) as $r) {
                        $link_ids[] = (int) $r['links_id'];
                    }
                    if ($link_ids) {
                        foreach ($DB->request(['FROM' => 'glpi_links', 'WHERE' => ['id' => $link_ids]]) as $l) {
                            $items[] = self::attr((string) $l['name']);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $items = [];
            }
        }
        if (!$items) {
            return '<div class="dt-card-section"><div class="dt-section-title">Links</div>'
                . '<div class="dt-v">No links</div></div>';
        }

        $h = '<div class="dt-card-section"><div class="dt-section-title">Links</div><ul class="dt-list-plain">';
        foreach ($items as $i) {
            $h .= '<li>' . $i . '</li>';
        }
        return $h . '</ul></div>';
    }

    /** Summary card with the main data of the device and counts of linked items. */
    private static function deviceCard(Computer $c): string
    {
        global $DB;

        $f  = $c->fields;
        $id = (int) $c->getID();

        // Groups (stored in glpi_groups_items: type 1 = group, type 2 = group in charge)
        $groups = ['normal' => [], 'tech' => []];
        try {
            if ($DB->tableExists('glpi_groups_items')) {
                foreach ($DB->request([
                    'FROM'  => 'glpi_groups_items',
                    'WHERE' => ['itemtype' => 'Computer', 'items_id' => $id],
                ]) as $r) {
                    $n = self::lookup('glpi_groups', (int) $r['groups_id']);
                    if ($n !== '') {
                        $groups[(int) $r['type'] === 2 ? 'tech' : 'normal'][] = $n;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore, fall back below
        }
        foreach ([['groups_id', 'normal'], ['groups_id_tech', 'tech']] as [$col, $k]) {
            if (!$groups[$k] && isset($f[$col]) && is_scalar($f[$col])) {
                $n = self::lookup('glpi_groups', (int) $f[$col]);
                if ($n !== '') {
                    $groups[$k][] = $n;
                }
            }
        }

        $user = static function (int $uid): string {
            return $uid > 0 ? self::clean((string) getUserName($uid)) : '';
        };

        $fields = [
            'Name'                      => (string) ($f['name'] ?? ''),
            'Status'                    => self::lookup('glpi_states', (int) ($f['states_id'] ?? 0)),
            'Location'                  => self::lookup('glpi_locations', (int) ($f['locations_id'] ?? 0)),
            'Serial number'             => (string) ($f['serial'] ?? ''),
            'Technician in charge'      => $user((int) ($f['users_id_tech'] ?? 0)),
            'Inventory number'          => (string) ($f['otherserial'] ?? ''),
            'Group in charge'           => implode(', ', $groups['tech']),
            'Manufacturer'              => self::lookup('glpi_manufacturers', (int) ($f['manufacturers_id'] ?? 0)),
            'Alternate username number' => (string) ($f['contact_num'] ?? ''),
            'Model'                     => self::lookup('glpi_computermodels', (int) ($f['computermodels_id'] ?? 0)),
            'Alternate username'        => (string) ($f['contact'] ?? ''),
            'Type'                      => self::lookup('glpi_computertypes', (int) ($f['computertypes_id'] ?? 0)),
            'User'                      => $user((int) ($f['users_id'] ?? 0)),
            'Group'                     => implode(', ', $groups['normal']),
        ];

        $link = [['itemtype' => 'Computer', 'items_id' => $id]][0];
        $counts = [
            'Operating systems' => self::countLinked('glpi_items_operatingsystems', $link),
            'Software'          => self::countLinked('glpi_items_softwareversions', $link),
            'Volumes'           => self::countLinked('glpi_items_disks', $link),
            'Network ports'     => self::countLinked('glpi_networkports', $link),
            'Connections'       => self::countLinked('glpi_computers_items', ['computers_id' => $id, 'is_deleted' => 0]),
            'Documents'         => self::countLinked('glpi_documents_items', $link),
            'Contracts'         => self::countLinked('glpi_contracts_items', $link),
            'Tickets'           => self::countLinked('glpi_items_tickets', $link),
            'Problems'          => self::countLinked('glpi_items_problems', $link),
            'Changes'           => self::countLinked('glpi_changes_items', $link),
        ];

        $h  = '<details class="dt-card" open><summary><strong>Device details</strong> &middot; '
            . self::attr((string) ($f['name'] ?? '')) . '</summary>';
        $h .= '<div class="dt-card-grid">';
        foreach ($fields as $label => $value) {
            $h .= '<div><div class="dt-k">' . $label . '</div><div class="dt-v">'
                . ($value !== '' ? self::attr($value) : '-') . '</div></div>';
        }
        $h .= '</div>';

        $comment = trim((string) ($f['comment'] ?? ''));
        $h .= '<div class="dt-card-comment"><div class="dt-k">Comments</div><div class="dt-v">'
            . ($comment !== '' ? self::esc($comment) : '-') . '</div></div>';

        $badges = '';
        foreach ($counts as $label => $n) {
            if ($n > 0) {
                $badges .= '<span class="badge bg-secondary-lt">' . $label . ' ' . $n . '</span>';
            }
        }
        if ($badges !== '') {
            $h .= '<div class="dt-card-counts">' . $badges . '</div>';
        }
        $h .= self::customFieldsSections($id);
        $h .= self::osSection($id);
        $h .= self::ticketsSection($id);
        $h .= self::linksSection($c);
        $h .= '<div class="dt-card-counts"><a href="' . self::attr(Computer::getFormURLWithID($id))
            . '">Open full device form</a></div>';

        return $h . '</details>';
    }

    /**
     * Linked Problems / Tickets / Changes as normalized events.
     * Link rows: this computer (all) + other computers (latest OTHER_LIMIT).
     */
    private static function itilEvents(int $computer_id): array
    {
        global $DB;

        $events = [];

        foreach (self::ITIL as $key => $meta) {
            $links = [];

            foreach ($DB->request([
                'FROM'  => $meta['link_table'],
                'WHERE' => ['itemtype' => 'Computer', 'items_id' => $computer_id],
            ]) as $r) {
                $links[] = ['device' => (int) $r['items_id'], 'itil' => (int) $r[$meta['fk']]];
            }

            foreach ($DB->request([
                'FROM'  => $meta['link_table'],
                'WHERE' => ['itemtype' => 'Computer', 'NOT' => ['items_id' => $computer_id]],
                'ORDER' => 'id DESC',
                'LIMIT' => self::OTHER_LIMIT,
            ]) as $r) {
                $links[] = ['device' => (int) $r['items_id'], 'itil' => (int) $r[$meta['fk']]];
            }

            if (!$links) {
                continue;
            }

            $itil_ids = array_values(array_unique(array_map(static fn($l) => $l['itil'], $links)));
            $objects  = [];
            foreach ($DB->request([
                'FROM'  => $meta['table'],
                'WHERE' => ['id' => $itil_ids, 'is_deleted' => 0],
            ]) as $o) {
                $objects[(int) $o['id']] = $o;
            }

            $class = $meta['class'];
            foreach ($links as $l) {
                if (!isset($objects[$l['itil']])) {
                    continue;
                }
                $o = $objects[$l['itil']];

                $status = '';
                if (class_exists($class) && method_exists($class, 'getStatus')) {
                    $status = (string) $class::getStatus((int) $o['status']);
                }

                $user = '-';
                if ((int) ($o['users_id_recipient'] ?? 0) > 0) {
                    $user = (string) getUserName((int) $o['users_id_recipient']);
                }

                $events[] = [
                    'date'     => (string) ($o['date'] ?? $o['date_creation'] ?? ''),
                    'sid'      => 0,
                    'key'      => $key,
                    'items_id' => $l['device'],
                    'user'     => $user,
                    'ref'      => $meta['prefix'] . $o['id'],
                    'number'   => (int) $o['id'],
                    'title'    => (string) $o['name'],
                    'status'   => $status,
                    'url'      => $class::getFormURLWithID((int) $o['id']),
                ];
            }
        }

        return $events;
    }

    public static function showForComputer(int $computer_id): void
    {
        global $DB;

        $fields = self::trackedFields();
        $id_to_key = [];
        foreach ($fields as $key => $f) {
            $id_to_key[$f['id']] = $key;
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Filters (key => label)
        $filter_labels = [
            'comment'  => 'Comments',
            'status'   => 'Status',
            'location' => 'Location',
            'problem'  => 'Problems',
            'ticket'   => 'Tickets',
            'change'   => 'Changes',
            'link'     => 'Links',
        ];
        $kind_labels = [
            'comment'  => 'Comments',
            'status'   => 'Status',
            'location' => 'Location',
            'problem'  => 'Problem',
            'ticket'   => 'Ticket',
            'change'   => 'Change',
            'link'     => 'Link',
        ];

        // Current computer and its model
        $computer = new Computer();
        $computer->getFromDB($computer_id);
        $model_id   = (int) ($computer->fields['computermodels_id'] ?? 0);
        $model_name = $model_id > 0 ? Dropdown::getDropdownName('glpi_computermodels', $model_id) : '';

        // Ids of all computers with the same model (including this one)
        $model_ids = [$computer_id];
        if ($model_id > 0) {
            $model_ids = [];
            foreach ($DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_computers',
                'WHERE'  => ['computermodels_id' => $model_id, 'is_deleted' => 0],
            ]) as $r) {
                $model_ids[] = (int) $r['id'];
            }
            if (!in_array($computer_id, $model_ids, true)) {
                $model_ids[] = $computer_id;
            }
        }

        // ---- Log rows (comments, status, location) ----
        $base = [
            'itemtype' => 'Computer',
            ['OR' => ['id_search_option' => self::fieldIds(), 'itemtype_link' => 'ManualLink']],
        ];
        $logs = [];

        foreach ($DB->request([
            'FROM'  => 'glpi_logs',
            'WHERE' => $base + ['items_id' => $computer_id],
        ]) as $r) {
            $logs[(int) $r['id']] = $r;
        }

        $other_model = array_values(array_diff($model_ids, [$computer_id]));
        if ($other_model) {
            foreach ($DB->request([
                'FROM'  => 'glpi_logs',
                'WHERE' => $base + ['items_id' => $other_model],
                'ORDER' => ['date_mod DESC', 'id DESC'],
                'LIMIT' => self::OTHER_LIMIT,
            ]) as $r) {
                $logs[(int) $r['id']] = $r;
            }
        }

        $n_other = 0;
        foreach ($DB->request([
            'FROM'  => 'glpi_logs',
            'WHERE' => $base + ['NOT' => ['items_id' => $computer_id]],
            'ORDER' => ['date_mod DESC', 'id DESC'],
            'LIMIT' => self::OTHER_LIMIT,
        ]) as $r) {
            $logs[(int) $r['id']] = $r;
            $n_other++;
        }
        $truncated = ($n_other >= self::OTHER_LIMIT);

        // Dropdown values are stored by GLPI as "Name (id)"; show only the name.
        $clean = static function (string $v, string $key): string {
            $v = trim($v);
            return $key === 'comment' ? $v : trim((string) preg_replace('/\s*\(\d+\)$/', '', $v));
        };

        // ---- Normalize everything into one event list ----
        $events = [];
        foreach ($logs as $r) {
            $is_link = ((string) ($r['itemtype_link'] ?? '') === 'ManualLink');
            $key     = $is_link ? 'link' : ($id_to_key[(int) $r['id_search_option']] ?? 'comment');
            $ev = [
                'date'     => (string) $r['date_mod'],
                'sid'      => (int) $r['id'],
                'key'      => $key,
                'items_id' => (int) $r['items_id'],
                'user'     => ($r['user_name'] ?? '') !== '' ? (string) $r['user_name'] : '-',
                'old'      => $clean((string) $r['old_value'], $key),
                'new'      => $clean((string) $r['new_value'], $key),
                'ref'      => (string) (int) $r['id'],
            ];
            if ($is_link) {
                $raw = trim((string) $r['new_value']) !== '' ? (string) $r['new_value'] : (string) $r['old_value'];
                $ev['action']  = (int) $r['linked_action'];
                $ev['link_id'] = preg_match('/\((\d+)\)\s*$/', trim($raw), $m) ? (int) $m[1] : 0;
            }
            $events[] = $ev;
        }
        foreach (self::itilEvents($computer_id) as $e) {
            $events[] = $e;
        }

        // URLs of the links (to make the names clickable)
        $link_urls = [];
        $link_ids  = array_values(array_unique(array_filter(
            array_map(static fn($e) => (int) ($e['link_id'] ?? 0), $events)
        )));
        if ($link_ids) {
            try {
                if ($DB->tableExists('glpi_manuallinks')) {
                    foreach ($DB->request(['FROM' => 'glpi_manuallinks', 'WHERE' => ['id' => $link_ids]]) as $m) {
                        $link_urls[(int) $m['id']] = (string) ($m['url'] ?? '');
                    }
                }
            } catch (\Throwable $ex) {
                // names are shown without links
            }
        }

        usort($events, static function ($a, $b) {
            return [$b['date'], $b['sid']] <=> [$a['date'], $a['sid']];
        });

        // Device names
        $names   = [];
        $serials = [];
        $ids     = array_values(array_unique(array_map(static fn($e) => (int) $e['items_id'], $events)));
        if ($ids) {
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'serial'],
                'FROM'   => 'glpi_computers',
                'WHERE'  => ['id' => $ids],
            ]) as $r) {
                $names[(int) $r['id']]   = $r['name'];
                $serials[(int) $r['id']] = (string) ($r['serial'] ?? '');
            }
        }

        // Counts per scope
        $n_device = 0;
        $n_model  = 0;
        $n_all    = count($events);
        foreach ($events as $e) {
            if ($e['items_id'] === $computer_id) {
                $n_device++;
            }
            if (in_array($e['items_id'], $model_ids, true)) {
                $n_model++;
            }
        }

        $u = 'dt' . $computer_id;

        // ---- Build timeline items and table rows ----
        $timeline_html = '';
        $table_html    = '';

        foreach ($events as $e) {
            $key      = $e['key'];
            $items_id = $e['items_id'];
            $label    = $kind_labels[$key];
            $ts       = strtotime($e['date']);
            $date     = date('d.m.Y', $ts);
            $day      = $days[(int) date('w', $ts)];
            $time     = date('H:i:s', $ts);
            $user     = $e['user'];
            $name     = self::attr((string) ($names[$items_id] ?? ('ID ' . $items_id)));
            $link     = self::attr(Computer::getFormURLWithID($items_id));
            $serial   = (string) ($serials[$items_id] ?? '');
            $serial_html = $serial !== '' ? ' &middot; S/N: ' . self::attr($serial) : '';

            $classes = ' f-' . $key;
            if ($items_id === $computer_id) {
                $classes .= ' s-device';
            }
            if (in_array($items_id, $model_ids, true)) {
                $classes .= ' s-model';
            }

            $head = '<div class="dt-head">' . $date . ' (' . $day . '), ' . $time
                . ' <span class="badge dt-badge">' . $label . '</span></div>';

            if (isset(self::ITIL[$key])) {
                // Linked Problem / Ticket / Change
                $url   = self::attr($e['url']);
                $title = self::attr($e['title']);
                $status_badge = $e['status'] !== ''
                    ? ' <span class="badge bg-orange-lt">' . self::attr($e['status']) . '</span>'
                    : '';
                $plain = '#' . $e['number'] . ' ' . $e['title'] . ($e['status'] !== '' ? ' (' . $e['status'] . ')' : '');

                $timeline_html .= '<div class="dt-item' . $classes . '">' . $head;
                $timeline_html .= '<div class="dt-meta">Device: <a href="' . $link . '">' . $name . '</a>' . $serial_html
                    . ' &middot; Opened by: ' . self::esc($user) . '</div>';
                $timeline_html .= '<div class="dt-text"><strong>' . $label . ' #' . $e['number'] . ':</strong> '
                    . '<a href="' . $url . '">' . $title . '</a>' . $status_badge . '</div>';
                $timeline_html .= '</div>';

                $table_html .= '<tr class="dt-row' . $classes . '">'
                    . '<td>' . self::attr($e['ref']) . '</td>'
                    . '<td>' . $date . '</td><td>' . $day . '</td><td>' . $time . '</td>'
                    . '<td><a href="' . $link . '">' . $name . '</a></td>'
                    . '<td>' . self::attr($serial) . '</td>'
                    . '<td>' . self::esc($user) . '</td>'
                    . '<td>' . $label . '</td>'
                    . '<td data-csv="' . self::attr($plain) . '"><a href="' . $url . '">#' . $e['number'] . ' '
                    . $title . '</a>' . $status_badge . '</td>'
                    . '<td data-csv=""></td>'
                    . '</tr>';
                continue;
            }

            if ($key === 'link') {
                $verbs     = [17 => 'added', 18 => 'updated', 19 => 'removed'];
                $verb      = $verbs[(int) ($e['action'] ?? 0)] ?? 'changed';
                $url       = $link_urls[(int) ($e['link_id'] ?? 0)] ?? '';
                $shown     = $e['new'] !== '' ? $e['new'] : $e['old'];
                $name_html = preg_match('#^https?://#i', $url)
                    ? '<a href="' . self::attr($url) . '" target="_blank" rel="noopener">' . self::attr($shown) . '</a>'
                    : self::attr($shown);

                $icon_val = $e['new'] !== '' ? $e['new'] : $e['old'];
                if ($verb === 'updated' && preg_match('/^ti-[a-z0-9-]+$/i', $icon_val)) {
                    $name_html = self::attr($icon_val);
                    $text = '<strong>Link icon changed:</strong> '
                        . ($e['old'] !== '' ? '<del>' . self::esc($e['old']) . '</del> &rarr; ' : '')
                        . '<ins>' . self::esc($e['new']) . '</ins>';
                } else {
                    $text = '<strong>Link ' . $verb . ':</strong> ';
                }
                if (strpos($text, 'icon changed') === false) {
                    if ($verb === 'updated' && $e['old'] !== '' && $e['old'] !== $e['new']) {
                        $text .= '<del>' . self::esc($e['old']) . '</del> &rarr; ';
                    }
                    $text .= $name_html;
                }

                $timeline_html .= '<div class="dt-item' . $classes . '">' . $head;
                $timeline_html .= '<div class="dt-meta">Device: <a href="' . $link . '">' . $name . '</a>' . $serial_html
                    . ' &middot; Changed by: ' . self::esc($user) . '</div>';
                $timeline_html .= '<div class="dt-text">' . $text . '</div></div>';

                $table_html .= '<tr class="dt-row' . $classes . '">'
                    . '<td>' . self::attr($e['ref']) . '</td>'
                    . '<td>' . $date . '</td><td>' . $day . '</td><td>' . $time . '</td>'
                    . '<td><a href="' . $link . '">' . $name . '</a></td>'
                    . '<td>' . self::attr($serial) . '</td>'
                    . '<td>' . self::esc($user) . '</td>'
                    . '<td>Link ' . $verb . '</td>'
                    . '<td data-csv="' . self::attr($shown . ($url !== '' ? ' (' . $url . ')' : '')) . '">' . $name_html . '</td>'
                    . '<td data-csv="' . self::attr($e['old']) . '">'
                    . ($verb === 'updated' && $e['old'] !== '' ? self::esc($e['old']) : '') . '</td>'
                    . '</tr>';
                continue;
            }

            // Field change from the history log
            $new = $e['new'];
            $old = $e['old'];
            $empty_new = $key === 'comment' ? '<em>(comment removed)</em>' : '<em>(empty)</em>';
            $empty_old = '<em>(empty)</em>';

            $timeline_html .= '<div class="dt-item' . $classes . '">' . $head;
            $timeline_html .= '<div class="dt-meta">Device: <a href="' . $link . '">' . $name . '</a>' . $serial_html
                . ' &middot; Changed by: ' . self::esc($user) . '</div>';
            if ($key === 'comment') {
                $timeline_html .= '<div class="dt-text">' . ($new !== '' ? self::esc($new) : $empty_new) . '</div>';
                if ($old !== '') {
                    $timeline_html .= '<details class="dt-old"><summary>Previous value</summary>'
                        . self::esc($old) . '</details>';
                }
            } else {
                $timeline_html .= '<div class="dt-text"><strong>' . $label . ':</strong> '
                    . ($old !== '' ? '<del>' . self::esc($old) . '</del>' : $empty_old)
                    . ' &rarr; '
                    . ($new !== '' ? '<ins>' . self::esc($new) . '</ins>' : $empty_new) . '</div>';
            }
            $timeline_html .= '</div>';

            $table_html .= '<tr class="dt-row' . $classes . '">'
                . '<td>' . self::attr($e['ref']) . '</td>'
                . '<td>' . $date . '</td><td>' . $day . '</td><td>' . $time . '</td>'
                . '<td><a href="' . $link . '">' . $name . '</a></td>'
                    . '<td>' . self::attr($serial) . '</td>'
                . '<td>' . self::esc($user) . '</td>'
                . '<td>' . $label . '</td>'
                . '<td data-csv="' . self::attr($new) . '">' . ($new !== '' ? self::esc($new) : $empty_new) . '</td>'
                . '<td data-csv="' . self::attr($old) . '">' . ($old !== '' ? self::esc($old) : '') . '</td>'
                . '</tr>';
        }

        // ---- Styles (selectors for the field filter are generated) ----
        $hl = [
            '#__U__-device:checked ~ .dt-switch label[for="__U__-device"]',
            '#__U__-model:checked  ~ .dt-switch label[for="__U__-model"]',
            '#__U__-all:checked    ~ .dt-switch label[for="__U__-all"]',
            '#__U__-vt:checked ~ .dt-switch label[for="__U__-vt"]',
            '#__U__-vb:checked ~ .dt-switch label[for="__U__-vb"]',
            '#__U__-fall:checked ~ .dt-switch label[for="__U__-fall"]',
        ];
        $hide = [
            '#__U__-device:checked ~ .dt-list .dt-item:not(.s-device)',
            '#__U__-device:checked ~ .dt-tablewrap tr.dt-row:not(.s-device)',
            '#__U__-model:checked ~ .dt-list .dt-item:not(.s-model)',
            '#__U__-model:checked ~ .dt-tablewrap tr.dt-row:not(.s-model)',
        ];
        foreach (array_keys($filter_labels) as $k) {
            $hl[]   = '#__U__-f' . $k . ':checked ~ .dt-switch label[for="__U__-f' . $k . '"]';
            $hide[] = '#__U__-f' . $k . ':checked ~ .dt-list .dt-item:not(.f-' . $k . ')';
            $hide[] = '#__U__-f' . $k . ':checked ~ .dt-tablewrap tr.dt-row:not(.f-' . $k . ')';
        }

        // One color per event type: [main color, light background]
        $colors = [
            'comment'  => ['#206bc4', '#e6f0fb'],
            'status'   => ['#f76707', '#fdebd9'],
            'location' => ['#2fb344', '#e0f5e4'],
            'problem'  => ['#d63939', '#fbe6e6'],
            'ticket'   => ['#f59f00', '#fff3d6'],
            'change'   => ['#0ca678', '#dcf5ee'],
            'link'     => ['#ae3ec9', '#f5e6fa'],
        ];
        $color_css = '.dt-item { border-left-color: var(--dt-c, var(--tblr-primary, #206bc4)); }'
            . '.dt-item::before { background: var(--dt-c, var(--tblr-primary, #206bc4)); }'
            . '.dt-item .dt-badge { background: var(--dt-bg, #e6f0fb); color: var(--dt-c, #206bc4); }'
            . 'tr.dt-row td:first-child { border-left: 4px solid var(--dt-c, transparent); }'
            . 'tr.dt-row td:nth-child(8) { color: var(--dt-c); font-weight: 600; }';
        foreach ($colors as $k => $pair) {
            $color_css .= '.dt-item.f-' . $k . ', tr.dt-row.f-' . $k . ' { --dt-c: ' . $pair[0] . '; --dt-bg: ' . $pair[1] . '; }'
                . '.dt-wrap .dt-switch label[for="__U__-f' . $k . '"]::before { content: ""; display: inline-block;'
                . ' width: .6em; height: .6em; border-radius: 50%; margin-right: .45rem; background: ' . $pair[0] . '; }';
        }

        $css = '<style>
            .dt-wrap { max-width: 1000px; margin: 1rem auto; position: relative; }
            .dt-wrap input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
            .dt-switch { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
            .dt-switch label { cursor: pointer; padding: .35rem .8rem; border-radius: 6px;
                       border: 1px solid var(--tblr-border-color, #ccc); }
            ' . implode(",\n            ", $hl) . ' {
                       background: var(--tblr-primary, #206bc4); color: #fff; border-color: transparent; }
            #__U__-vt:checked ~ .dt-tablewrap { display: none; }
            #__U__-vb:checked ~ .dt-list { display: none; }
            ' . implode(",\n            ", $hide) . ' { display: none; }
            .dt-empty { display: none; }
            #__U__-device:checked ~ .dt-list .e-device,
            #__U__-model:checked  ~ .dt-list .e-model,
            #__U__-all:checked    ~ .dt-list .e-all { display: block; }
            .dt-item { position: relative; margin: 0 0 1.25rem 1.5rem; padding-left: 1.25rem;
                       border-left: 3px solid var(--tblr-primary, #206bc4); }
            .dt-item::before { content: ""; position: absolute; left: -9px; top: 4px; width: 15px; height: 15px;
                       border-radius: 50%; background: var(--tblr-primary, #206bc4); }
            .dt-head { font-weight: 600; }
            .dt-badge { margin-left: .4rem; font-weight: 500; }
            .dt-card { border: 1px solid var(--tblr-border-color, #ddd); border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; }
            .dt-card summary { cursor: pointer; font-size: 1.05em; }
            .dt-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: .6rem 1.5rem; margin-top: .75rem; }
            .dt-k { font-size: .8em; opacity: .65; }
            .dt-v { font-weight: 500; word-break: break-word; }
            .dt-card-comment { margin-top: .75rem; }
            .dt-card-counts { margin-top: .75rem; display: flex; flex-wrap: wrap; gap: .4rem; }
            .dt-card-section { margin-top: 1rem; padding-top: .75rem; border-top: 1px solid var(--tblr-border-color, #ddd); }
            .dt-section-title { font-weight: 600; margin-bottom: .4rem; }
            .dt-list-plain { margin: 0; padding-left: 1.1rem; }
            .dt-meta { opacity: .7; font-size: .9em; margin-bottom: .35rem; }
            .dt-text { padding: .6rem .8rem; border-radius: 6px; border: 1px solid var(--tblr-border-color, #ddd); }
            .dt-old  { margin-top: .4rem; font-size: .9em; opacity: .75; }
            .dt-controls { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-bottom: .75rem; }
            .dt-controls .form-control, .dt-controls .form-select { width: auto; }
            .dt-tablewrap { overflow-x: auto; }
            .dt-tablewrap td { vertical-align: top; }
            .dt-pager { display: flex; gap: .35rem; align-items: center; margin: .75rem 0 1.25rem; }
            ' . $color_css . '
        </style>';
        echo str_replace('__U__', $u, $css);

        // ---- Markup ----
        echo '<div class="dt-wrap">';
        echo self::deviceCard($computer);
        echo '<input type="radio" name="' . $u . '" id="' . $u . '-device" checked>';
        echo '<input type="radio" name="' . $u . '" id="' . $u . '-model"' . ($model_id > 0 ? '' : ' disabled') . '>';
        echo '<input type="radio" name="' . $u . '" id="' . $u . '-all">';
        echo '<input type="radio" name="' . $u . '-v" id="' . $u . '-vt" checked>';
        echo '<input type="radio" name="' . $u . '-v" id="' . $u . '-vb">';
        echo '<input type="radio" name="' . $u . '-f" id="' . $u . '-fall" checked>';
        foreach (array_keys($filter_labels) as $k) {
            echo '<input type="radio" name="' . $u . '-f" id="' . $u . '-f' . $k . '">';
        }

        $model_label = $model_id > 0
            ? 'Same model (' . self::attr($model_name) . ') &middot; ' . $n_model
            : 'Same model (not set)';

        echo '<div class="dt-switch">';
        echo '<label for="' . $u . '-device">This device &middot; ' . $n_device . '</label>';
        echo '<label for="' . $u . '-model">' . $model_label . '</label>';
        echo '<label for="' . $u . '-all">All devices &middot; ' . $n_all . '</label>';
        echo '</div>';

        echo '<div class="dt-switch">';
        echo '<label for="' . $u . '-fall">All changes</label>';
        foreach ($filter_labels as $k => $lbl) {
            echo '<label for="' . $u . '-f' . $k . '">' . $lbl . '</label>';
        }
        echo '</div>';

        echo '<div class="dt-switch">';
        echo '<label for="' . $u . '-vt">Timeline</label>';
        echo '<label for="' . $u . '-vb">Table</label>';
        echo '</div>';

        // Shared controls: search, rows per page, export, pager
        echo '<div class="dt-controls">';
        echo '<input type="search" class="form-control" id="' . $u . '-q" placeholder="Search...">';
        echo '<select class="form-select" id="' . $u . '-ps">'
            . '<option value="10">10 rows / page</option>'
            . '<option value="20" selected>20 rows / page</option>'
            . '<option value="50">50 rows / page</option>'
            . '<option value="100">100 rows / page</option>'
            . '</select>';
        echo '<button type="button" class="btn btn-outline-secondary" id="' . $u . '-exp">Export CSV</button>';
        echo '<button type="button" class="btn btn-outline-secondary" id="' . $u . '-pdf">Export PDF</button>';
        echo '<span class="text-muted" id="' . $u . '-info"></span>';
        echo '</div>';
        echo '<div class="dt-pager" id="' . $u . '-pg1"></div>';
        echo '<div class="alert alert-info" id="' . $u . '-none" style="display:none">No rows to show.</div>';

        // Timeline view
        echo '<div class="dt-list">';
        if ($n_device === 0) {
            echo '<div class="alert alert-info dt-empty e-device">No changes recorded for this device.</div>';
        }
        if ($n_model === 0) {
            echo '<div class="alert alert-info dt-empty e-model">No changes recorded for devices of this model.</div>';
        }
        if ($n_all === 0) {
            echo '<div class="alert alert-info dt-empty e-all">No changes found.</div>';
        }
        echo $timeline_html;
        if ($truncated) {
            echo '<div class="text-muted small">Other devices: showing only the latest '
                . self::OTHER_LIMIT . ' changes.</div>';
        }
        echo '</div>';

        // Table view
        echo '<div class="dt-tablewrap">';
        echo '<table class="table table-hover card-table">';
        echo '<thead><tr><th>ID</th><th>Date</th><th>Day</th><th>Time</th><th>Device</th><th>Serial number</th><th>User</th>'
            . '<th>Field</th><th>New value</th><th>Previous value</th></tr></thead>';
        echo '<tbody id="' . $u . '-tb">' . $table_html . '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '<div class="dt-pager" id="' . $u . '-pg2"></div>';

        echo '</div>';

        // ---- Table behaviour: search, paging, CSV export ----
        $js = <<<'JS'
(function () {
    var u = '__U__';
    var keys = __KEYS__;
    var devName = __DEVNAME__;
    var tb = document.getElementById(u + '-tb');
    if (!tb) { return; }
    var wrap = tb.closest('.dt-wrap');
    var rows = Array.prototype.slice.call(tb.querySelectorAll('tr.dt-row'));
    var items = Array.prototype.slice.call(wrap.querySelectorAll('.dt-list .dt-item'));
    var q = document.getElementById(u + '-q');
    var ps = document.getElementById(u + '-ps');
    var info = document.getElementById(u + '-info');
    var pagers = [document.getElementById(u + '-pg1'), document.getElementById(u + '-pg2')];
    var none = document.getElementById(u + '-none');
    var page = 1;

    function scope() {
        if (document.getElementById(u + '-model').checked) { return 's-model'; }
        if (document.getElementById(u + '-all').checked) { return ''; }
        return 's-device';
    }

    function fieldClass() {
        for (var i = 0; i < keys.length; i++) {
            if (document.getElementById(u + '-f' + keys[i]).checked) { return 'f-' + keys[i]; }
        }
        return '';
    }

    // Indexes of rows that match scope, field filter and search text.
    function matching() {
        var s = scope();
        var f = fieldClass();
        var t = q.value.toLowerCase().trim();
        var out = [];
        rows.forEach(function (r, i) {
            if (s && !r.classList.contains(s)) { return; }
            if (f && !r.classList.contains(f)) { return; }
            if (t && r.textContent.toLowerCase().indexOf(t) === -1) { return; }
            out.push(i);
        });
        return out;
    }

    function button(label, target, disabled, active) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary');
        b.textContent = label;
        b.disabled = disabled;
        b.addEventListener('click', function () { page = target; render(); });
        return b;
    }

    function buildPager(el, pages) {
        el.innerHTML = '';
        if (pages <= 1) { return; }
        el.appendChild(button('\u00ab', 1, page === 1, false));
        el.appendChild(button('\u2039', page - 1, page === 1, false));
        var start = Math.max(1, page - 2);
        var end = Math.min(pages, start + 4);
        start = Math.max(1, end - 4);
        for (var p = start; p <= end; p++) {
            el.appendChild(button(String(p), p, false, p === page));
        }
        el.appendChild(button('\u203a', page + 1, page === pages, false));
        el.appendChild(button('\u00bb', pages, page === pages, false));
    }

    function render() {
        var m = matching();
        var size = parseInt(ps.value, 10);
        var pages = Math.max(1, Math.ceil(m.length / size));
        if (page > pages) { page = pages; }
        if (page < 1) { page = 1; }
        var from = (page - 1) * size;
        var to = from + size;

        rows.forEach(function (r, i) {
            r.style.display = 'none';
            if (items[i]) { items[i].style.display = 'none'; }
        });
        m.slice(from, to).forEach(function (i) {
            rows[i].style.display = '';
            if (items[i]) { items[i].style.display = ''; }
        });

        none.style.display = m.length ? 'none' : '';
        info.textContent = m.length
            ? 'Showing ' + (from + 1) + ' to ' + Math.min(to, m.length) + ' of ' + m.length + ' rows'
            : '';
        pagers.forEach(function (el) { if (el) { buildPager(el, pages); } });
    }

    // Header + all matching rows (every page), as plain text.
    function collect() {
        var lines = [['ID', 'Date', 'Day', 'Time', 'Device', 'Serial number', 'User', 'Field', 'New value', 'Previous value']];
        matching().forEach(function (i) {
            lines.push(Array.prototype.map.call(rows[i].cells, function (c) {
                return c.hasAttribute('data-csv') ? c.getAttribute('data-csv') : c.textContent.trim();
            }));
        });
        return lines;
    }

    function labelOf(suffix) {
        var l = wrap.querySelector('label[for="' + u + suffix + '"]');
        return l ? l.textContent.replace(/\s*\u00b7\s*\d+\s*$/, '') : '';
    }

    function chosen(list) {
        for (var i = 0; i < list.length; i++) {
            if (document.getElementById(u + list[i]).checked) { return labelOf(list[i]); }
        }
        return '';
    }

    function csvCell(v) {
        return '"' + String(v).replace(/"/g, '""') + '"';
    }

    function htmlEsc(v) {
        return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.getElementById(u + '-exp').addEventListener('click', function () {
        var csv = collect().map(function (l) { return l.map(csvCell).join(','); }).join('\r\n');
        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'device-timeline.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    // PDF: opens the browser print dialog with a clean A4 table; choose "Save as PDF".
    document.getElementById(u + '-pdf').addEventListener('click', function () {
        var lines = collect();
        var head = lines.shift();
        var heading = 'Device Timeline - ' + devName;
        var sub = chosen(['-device', '-model', '-all']) + ' / ' + chosen(['-fall'].concat(keys.map(function (k) { return '-f' + k; })))
            + ' / generated ' + new Date().toLocaleString();
        var html = '<!doctype html><html><head><meta charset="utf-8"><title>' + htmlEsc(heading) + '</title>'
            + '<style>@page{size:A4 landscape;margin:12mm}body{font-family:Arial,sans-serif;font-size:10px;color:#000}'
            + 'h1{font-size:15px;margin:0 0 4px}p{margin:0 0 10px;color:#444}'
            + 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:3px 5px;text-align:left;vertical-align:top}'
            + 'th{background:#eee}tr{page-break-inside:avoid}</style></head><body>'
            + '<h1>' + htmlEsc(heading) + '</h1><p>' + htmlEsc(sub) + ' (' + lines.length + ' rows)</p><table><thead><tr>'
            + head.map(function (h) { return '<th>' + htmlEsc(h) + '</th>'; }).join('') + '</tr></thead><tbody>'
            + lines.map(function (l) {
                return '<tr>' + l.map(function (c) { return '<td>' + htmlEsc(c).replace(/\n/g, '<br>') + '</td>'; }).join('') + '</tr>';
            }).join('') + '</tbody></table></body></html>';

        var f = document.createElement('iframe');
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
        document.body.appendChild(f);
        f.contentDocument.open();
        f.contentDocument.write(html);
        f.contentDocument.close();
        setTimeout(function () {
            f.contentWindow.focus();
            f.contentWindow.print();
            setTimeout(function () { document.body.removeChild(f); }, 2000);
        }, 300);
    });

    q.addEventListener('input', function () { page = 1; render(); });
    ps.addEventListener('change', function () { page = 1; render(); });
    ['-device', '-model', '-all', '-fall'].concat(keys.map(function (k) { return '-f' + k; })).forEach(function (suffix) {
        document.getElementById(u + suffix).addEventListener('change', function () { page = 1; render(); });
    });

    render();
})();
JS;
        $js = str_replace(
            ['__U__', '__KEYS__', '__DEVNAME__'],
            [$u, json_encode(array_keys($filter_labels)), json_encode((string) ($computer->fields['name'] ?? ''))],
            $js
        );

        if (method_exists(Html::class, 'scriptBlock')) {
            echo Html::scriptBlock($js);
        } else {
            echo '<script>' . $js . '</script>';
        }
    }
}
