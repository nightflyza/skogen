<?php


/**
 * Manages state alert updates based on channel messages.
 */
class StatesUpdater {
    /**
     * Storage file name for previous states data.
     *
     * @var string
     */
    protected $previousStatesFile = __DIR__ . '/../data/morkstates.json';

    /**
     * Collection of normalized state entries.
     *
     * @var array
     */
    protected $actualStates = array();

    /**
     * Strings used to detect alert status.
     *
     * @var array
     */
    protected $parserStrings = array();

    /**
     * Index of locations keyed by normalized names.
     *
     * @var array
     */
    protected $locationIndex = array();

    /**
     * Strings that cause message lines to be ignored.
     *
     * @var array
     */
    protected $ignoreStrings = array();

    /**
     * Debug logging flag.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Initializes the updater and loads baseline data.
     *
     * @param string|null $previousStatesFile
     *
     * @return void
     */
    public function __construct($previousStatesFile = null) {
        if ($previousStatesFile !== null) {
            $this->previousStatesFile = $previousStatesFile;
        }

        $this->loadDefaults();
        $this->loadPreviousStates();
        $this->buildIndex();
    }

    /**
     * Toggles debug logging.
     *
     * @param bool $flag
     *
     * @return void
     */
    public function setDebug($flag) {
        $this->debug = $flag ? true : false;
    }

    /**
     * Returns current states data.
     *
     * @return array
     */
    public function getStates() {
        return ($this->actualStates);
    }

    /**
     * Parses an incoming message and updates states.
     *
     * @param mixed $message
     *
     * @return bool
     */
    public function parseMessage($message) {
        $lines = $this->normalizeLines($message);
        $updated = false;

        foreach ($lines as $line) {
            if (!is_array($line) or !isset($line['text'])) {
                continue;
            }

            list($timestamp, $reason) = $this->formatReason($line);

            if ($this->shouldIgnoreLine($line['text'])) {
                $this->logSkipped($reason);

                continue;
            }

            $status = $this->detectStatus($line['text']);
            $matches = $this->matchLocations($line['text']);

            $missingStatus = ($status === null);
            $missingLocation = empty($matches);

            if ($missingStatus or $missingLocation) {
                if ($missingStatus) {
                    $this->logWarning('Unable to detect alert status: ' . $reason);
                }

                if ($missingLocation) {
                    $this->logWarning('Unable to detect alert location: ' . $reason);
                }

                continue;
            }

            foreach ($matches as $match) {
                if ($this->applyStatus($match, $status, $timestamp, $reason)) {
                    $updated = true;
                }
            }
        }

        if ($updated) {
            $this->persistStates();
            $this->buildIndex();
        }

        return ($updated);
    }

    /**
     * Loads default states and parser configuration.
     *
     * @return void
     */
    protected function loadDefaults() {
        if (!isset($GLOBALS['defaultStates']) or !is_array($GLOBALS['defaultStates'])) {
            throw new RuntimeException('Default states data is not available.');
        }

        if (!isset($GLOBALS['parserStrings']) or !is_array($GLOBALS['parserStrings'])) {
            throw new RuntimeException('Parser strings data is not available.');
        }

        $this->parserStrings = $GLOBALS['parserStrings'];
        if (isset($GLOBALS['ignoreStrings']) and is_array($GLOBALS['ignoreStrings'])) {
            $this->ignoreStrings = $GLOBALS['ignoreStrings'];
        }
        $this->actualStates = $this->normalizeStates($GLOBALS['defaultStates']);
    }

    /**
     * Loads previously saved states from storage.
     *
     * @return void
     */
    protected function loadPreviousStates() {
        if (!is_file($this->previousStatesFile)) {
            $this->persistStates();
            $this->logStateSummary('Created new states storage');

            return;
        }

        $content = file_get_contents($this->previousStatesFile);
        if ($content === false) {
            throw new RuntimeException('Unable to read states storage.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $this->persistStates();
            $this->logStateSummary('Reinitialized states storage');

            return;
        }

        $this->actualStates = $this->normalizeStates($decoded);
        $this->logStateSummary('Loaded saved states');
    }

    /**
     * Persists current states to storage.
     *
     * @return void
     */
    protected function persistStates() {
        $encoded = json_encode($this->actualStates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode states for storage.');
        }

        if (file_put_contents($this->previousStatesFile, $encoded) === false) {
            throw new RuntimeException('Unable to write states storage.');
        }
    }

    /**
     * Normalizes a list of state entries.
     *
     * @param array $states
     *
     * @return array
     */
    protected function normalizeStates($states) {
        $normalized = array();

        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }

            $normalized[] = $this->normalizeState($state);
        }

        return ($normalized);
    }

    /**
     * Normalizes a single state entry.
     *
     * @param array $state
     *
     * @return array
     */
    protected function normalizeState($state) {
        $state['alert'] = isset($state['alert']) ? (bool) $state['alert'] : false;
        $state['changed'] = isset($state['changed']) ? (string) $state['changed'] : '1970-01-01 03:00:00';
        $state['reason'] = isset($state['reason']) ? (string) $state['reason'] : '';
        $state['districts'] = (isset($state['districts']) and is_array($state['districts'])) ? $state['districts'] : array();
        $state['community'] = (isset($state['community']) and is_array($state['community'])) ? $state['community'] : array();

        $state['districts'] = $this->normalizeLocations($state['districts']);
        $state['community'] = $this->normalizeLocations($state['community']);

        return ($state);
    }

    /**
     * Normalizes location data within a state.
     *
     * @param array $items
     *
     * @return array
     */
    protected function normalizeLocations($items) {
        $result = array();

        foreach ($items as $item) {
            if (is_array($item) and isset($item['name'])) {
                $result[] = array(
                    'name' => (string) $item['name'],
                    'alert' => isset($item['alert']) ? (bool) $item['alert'] : false,
                    'changed' => isset($item['changed']) ? (string) $item['changed'] : '1970-01-01 03:00:00',
                    'reason' => isset($item['reason']) ? (string) $item['reason'] : '',
                );
            } elseif (is_string($item) and $item !== '') {
                $result[] = array(
                    'name' => $item,
                    'alert' => false,
                    'changed' => '1970-01-01 03:00:00',
                    'reason' => '',
                );
            }
        }

        return ($result);
    }

    /**
     * Builds quick search index for locations.
     *
     * @return void
     */
    protected function buildIndex() {
        $index = array();

        foreach ($this->actualStates as $stateIndex => $state) {
            $this->registerLocation($index, $state['name'], array(
                'type' => 'state',
                'state' => $stateIndex,
            ));

            if (!empty($state['name_en'])) {
                $this->registerLocation($index, $state['name_en'], array(
                    'type' => 'state',
                    'state' => $stateIndex,
                ));
            }

            foreach ($state['districts'] as $districtIndex => $district) {
                $this->registerLocation($index, $district['name'], array(
                    'type' => 'district',
                    'state' => $stateIndex,
                    'district' => $districtIndex,
                ));
            }

            foreach ($state['community'] as $communityIndex => $community) {
                $this->registerLocation($index, $community['name'], array(
                    'type' => 'community',
                    'state' => $stateIndex,
                    'community' => $communityIndex,
                ));
            }
        }

        $this->locationIndex = $index;
    }

    /**
     * Registers a location entry in the index.
     *
     * @param array &$index
     * @param string $name
     * @param array $meta
     *
     * @return void
     */
    protected function registerLocation(&$index, $name, $meta) {
        $key = $this->normalizeKey($name);
        if ($key === '') {
            return;
        }

        if (!isset($index[$key])) {
            $index[$key] = array();
        }

        $index[$key][] = $meta;
    }

    /**
     * Creates a normalized lookup key.
     *
     * @param string $value
     *
     * @return string
     */
    protected function normalizeKey($value) {
        // hack to replace # and _ with spaces to parse locations from hashtags
        $value = str_replace(array('#', '_'), ' ', (string) $value);
        //replace apostrophes with single quote
        $value = str_replace("’", '\'', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim(mb_strtolower($value, 'UTF-8'));

        return ($value);
    }

    /**
     * Splits message text into normalized lines.
     *
     * @param mixed $message
     *
     * @return array
     */
    protected function normalizeLines($message) {
        if (isset($message['text']) and is_array($message['text'])) {
            $lines = array();
            foreach ($message['text'] as $textLine) {
                $textLine = trim($textLine);
                if ($textLine !== '') {
                    $lines[] = array_merge($message, array('text' => $textLine));
                }
            }

            return ($lines);
        }

        $text = '';
        if (is_array($message) and isset($message['text'])) {
            $text = (string) $message['text'];
        } else {
            $text = (string) $message;
        }

        $rawLines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array();

        foreach ($rawLines as $rawLine) {
            $rawLine = trim($rawLine);
            if ($rawLine !== '') {
                $lines[] = array(
                    'id' => isset($message['id']) ? $message['id'] : 0,
                    'author' => isset($message['author']) ? $message['author'] : '',
                    'datetime' => isset($message['datetime']) ? $message['datetime'] : '',
                    'text' => $rawLine,
                );
            }
        }

        return ($lines);
    }

    /**
     * Detects alert status flag within a line.
     *
     * @param string $line
     *
     * @return bool|null
     */
    protected function detectStatus($line) {
        $candidates = array_keys($this->parserStrings);
        usort($candidates, function ($a, $b) {
            return (mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        });

        foreach ($candidates as $needle) {
            if ($needle === '') {
                continue;
            }

            if (mb_stripos($line, $needle, 0, 'UTF-8') !== false) {
                return ((bool) $this->parserStrings[$needle]);
            }
        }

        return (null);
    }

    /**
     * Matches known locations within a line.
     *
     * @param string $line
     *
     * @return array
     */
    protected function matchLocations($line) {
        $lineKey = $this->normalizeKey($line);
        $matches = array();
        if ($lineKey === '') {
            return ($matches);
        }

        $keys = array_keys($this->locationIndex);
        usort($keys, function ($a, $b) {
            return (mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        });

        $occupied = array();

        foreach ($keys as $name) {
            if ($name === '') {
                continue;
            }

            $pattern = '/(?<!\p{L})' . preg_quote($name, '/') . '(?!\p{L})/u';
            if (!preg_match_all($pattern, $lineKey, $found, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($found[0] as $match) {
                $offset = $match[1];
                $length = strlen($match[0]);

                $overlaps = false;
                foreach ($occupied as $span) {
                    if ($offset < $span['end'] and ($offset + $length) > $span['start']) {
                        $overlaps = true;
                        break;
                    }
                }

                if ($overlaps) {
                    continue;
                }

                $entries = $this->locationIndex[$name];
                foreach ($entries as $entry) {
                    $matches[] = $entry;
                }

                $occupied[] = array(
                    'start' => $offset,
                    'end' => $offset + $length,
                );

                break;
            }
        }

        return ($matches);
    }

    /**
     * Checks whether a line must be ignored.
     *
     * @param string $text
     *
     * @return bool
     */
    protected function shouldIgnoreLine($text) {
        foreach ($this->ignoreStrings as $needle) {
            if (!is_string($needle) or $needle === '') {
                continue;
            }

            if (mb_stripos($text, $needle, 0, 'UTF-8') !== false) {
                return (true);
            }
        }

        return (false);
    }

    /**
     * Applies status update to a matched entity.
     *
     * @param array $meta
     * @param bool $status
     * @param string $timestamp
     * @param string $reason
     *
     * @return bool
     */
    protected function applyStatus($meta, $status, $timestamp, $reason) {
        $changed = false;

        if ($meta['type'] === 'state') {
            $changed = $this->updateState($meta['state'], $status, $timestamp, $reason);
        } elseif ($meta['type'] === 'district') {
            $changed = $this->updateDistrict($meta['state'], $meta['district'], $status, $timestamp, $reason);
            $this->syncStateWithChildren($meta['state'], $timestamp, $reason);
        } elseif ($meta['type'] === 'community') {
            $changed = $this->updateCommunity($meta['state'], $meta['community'], $status, $timestamp, $reason);
            $this->syncStateWithChildren($meta['state'], $timestamp, $reason);
        }

        return ($changed);
    }

    /**
     * Updates a state-level alert entry.
     *
     * @param int $stateIndex
     * @param bool $status
     * @param string $timestamp
     * @param string $reason
     *
     * @return bool
     */
    protected function updateState($stateIndex, $status, $timestamp, $reason) {
        if (!isset($this->actualStates[$stateIndex])) {
            return (false);
        }

        $previous = (bool) $this->actualStates[$stateIndex]['alert'];
        $childrenAlert = $this->hasChildrenAlert($stateIndex);
        $newStatus = $status ? true : $childrenAlert;

        $this->actualStates[$stateIndex]['alert'] = $newStatus;

        if ($previous !== $newStatus) {
            $this->actualStates[$stateIndex]['changed'] = $timestamp;
            $this->actualStates[$stateIndex]['reason'] = $reason;
            $this->logStatusUpdate($stateIndex, 'state', $this->actualStates[$stateIndex]['name'], $newStatus, $reason);

            return (true);
        }

        return (false);
    }

    /**
     * Updates a district-level alert entry.
     *
     * @param int $stateIndex
     * @param int $districtIndex
     * @param bool $status
     * @param string $timestamp
     * @param string $reason
     *
     * @return bool
     */
    protected function updateDistrict($stateIndex, $districtIndex, $status, $timestamp, $reason) {
        if (!isset($this->actualStates[$stateIndex]['districts'][$districtIndex])) {
            return (false);
        }

        $previous = (bool) $this->actualStates[$stateIndex]['districts'][$districtIndex]['alert'];

        $this->actualStates[$stateIndex]['districts'][$districtIndex]['alert'] = $status;
        $this->actualStates[$stateIndex]['districts'][$districtIndex]['changed'] = $timestamp;
        $this->actualStates[$stateIndex]['districts'][$districtIndex]['reason'] = $reason;

        if ($previous !== $status) {
            $this->logStatusUpdate(
                $stateIndex,
                'district',
                $this->actualStates[$stateIndex]['districts'][$districtIndex]['name'],
                $status,
                $reason
            );

            return (true);
        }

        return (false);
    }

    /**
     * Updates a community-level alert entry.
     *
     * @param int $stateIndex
     * @param int $communityIndex
     * @param bool $status
     * @param string $timestamp
     * @param string $reason
     *
     * @return bool
     */
    protected function updateCommunity($stateIndex, $communityIndex, $status, $timestamp, $reason) {
        if (!isset($this->actualStates[$stateIndex]['community'][$communityIndex])) {
            return (false);
        }

        $previous = (bool) $this->actualStates[$stateIndex]['community'][$communityIndex]['alert'];

        $this->actualStates[$stateIndex]['community'][$communityIndex]['alert'] = $status;
        $this->actualStates[$stateIndex]['community'][$communityIndex]['changed'] = $timestamp;
        $this->actualStates[$stateIndex]['community'][$communityIndex]['reason'] = $reason;

        if ($previous !== $status) {
            $this->logStatusUpdate(
                $stateIndex,
                'community',
                $this->actualStates[$stateIndex]['community'][$communityIndex]['name'],
                $status,
                $reason
            );

            return (true);
        }

        return (false);
    }

    /**
     * Returns current timestamp in storage format.
     *
     * @return string
     */
    protected function currentTime() {
        $now = new DateTime();

        return ($now->format('Y-m-d H:i:s'));
    }

    /**
     * Formats log reason text.
     *
     * @param array $line
     *
     * @return array
     */
    protected function formatReason($line) {
        $id = isset($line['id']) ? (int) $line['id'] : 0;
        $datetime = (isset($line['datetime']) and $line['datetime'] !== '') ? (string) $line['datetime'] : $this->currentTime();
        $text = isset($line['text']) ? (string) $line['text'] : '';

        $formatted = '[' . $id . ']';

        if ($datetime !== '') {
            $formatted .= ' (' . $datetime . ')';
        }

        $formatted .= ': ' . $text;

        return array($datetime, $formatted);
    }

    /**
     * Outputs info-level log message.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logInfo($message) {
        print('[update] ' . $message . PHP_EOL);
    }

    /**
     * Outputs warning-level log message.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logWarning($message) {
        print('[warn] ' . $message . PHP_EOL);
    }

    /**
     * Outputs skip-level log message.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logSkipped($message) {
        print('[info] skipped: ' . $message . PHP_EOL);
    }

    /**
     * Logs structured alert update message.
     *
     * @param int $stateIndex
     * @param string $type
     * @param string $name
     * @param bool $status
     * @param string $reason
     *
     * @return void
     */
    protected function logStatusUpdate($stateIndex, $type, $name, $status, $reason) {
        $stateName = isset($this->actualStates[$stateIndex]['name']) ? $this->actualStates[$stateIndex]['name'] : 'unknown';
        $segments = array('State "' . $stateName . '"');

        if ($type === 'district') {
            $segments[] = 'District "' . $name . '"';
        } elseif ($type === 'community') {
            $segments[] = 'Community "' . $name . '"';
        }

        $prefix = implode(' ', $segments);
        $this->logInfo($prefix . ' alert => ' . ($status ? 'true' : 'false') . ' because of "' . $reason . '"');
    }

    /**
     * Outputs a summary of current states.
     *
     * @param string $context
     *
     * @return void
     */
    protected function logStateSummary($context) {
        print('[stats] ' . $context . ':' . PHP_EOL);
        print('==================================' . PHP_EOL);
        foreach ($this->actualStates as $state) {
            $name = isset($state['name']) ? $state['name'] : 'unknown';
            $alert = !empty($state['alert']) ? 'true' : 'false';
            print('  ' . $name . ' : ' . $alert . PHP_EOL);
        }
        print('==================================' . PHP_EOL);
    }

    /**
     * Checks if any nested locations are alerting.
     *
     * @param int $stateIndex
     *
     * @return bool
     */
    protected function hasChildrenAlert($stateIndex) {
        if (!isset($this->actualStates[$stateIndex])) {
            return (false);
        }

        foreach ($this->actualStates[$stateIndex]['districts'] as $district) {
            if (!empty($district['alert'])) {
                return (true);
            }
        }

        foreach ($this->actualStates[$stateIndex]['community'] as $community) {
            if (!empty($community['alert'])) {
                return (true);
            }
        }

        return (false);
    }

    /**
     * Synchronizes state alert flag with its children.
     *
     * @param int $stateIndex
     * @param string $timestamp
     * @param string $reason
     *
     * @return void
     */
    protected function syncStateWithChildren($stateIndex, $timestamp, $reason) {
        if (!isset($this->actualStates[$stateIndex])) {
            return;
        }

        $childrenAlert = $this->hasChildrenAlert($stateIndex);
        $previous = (bool) $this->actualStates[$stateIndex]['alert'];

        if ($childrenAlert !== $previous) {
            $this->actualStates[$stateIndex]['alert'] = $childrenAlert;
            $this->actualStates[$stateIndex]['changed'] = $timestamp;
            $this->actualStates[$stateIndex]['reason'] = $reason;
            $this->logStatusUpdate($stateIndex, 'state', $this->actualStates[$stateIndex]['name'], $childrenAlert, $reason);
        }
    }
}