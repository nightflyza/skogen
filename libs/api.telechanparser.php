<?php

/**
 * Fetches and parses Telegram channel messages using OmaeUrl.
 */
class TeleChanParser {
    /**
     * Telegram channel identifier.
     *
     * @var string
     */
    protected $channel = '';

    /**
     * Maximum HTTP retry attempts.
     *
     * @var int
     */
    protected $maxRetries = 3;

    /**
     * Delay between retries in seconds.
     *
     * @var int
     */
    protected $retryDelaySeconds = 5;

    /**
     * Configured timezone name.
     *
     * @var string
     */
    protected $timezoneName = 'Europe/Kyiv';

    /**
     * Configured timezone instance.
     *
     * @var DateTimeZone|null
     */
    protected $timezone;

    /**
     * Last raw HTTP response body.
     *
     * @var string
     */
    protected $lastRawResponse = '';

    /**
     * Last error message text.
     *
     * @var string
     */
    protected $lastError = '';

    /**
     * Maximum backlog messages to fetch.
     *
     * @var int
     */
    protected $backlogSize = 20;

    /**
     * Last processed Telegram message id.
     *
     * @var int
     */
    protected $lastMessageId = 0;

    /**
     * HTTP user agent string.
     *
     * @var string
     */
    protected $userAgent = 'Mozilla/5.0 (compatible; TeleChanParser/1.0)';

    /**
     * Debug logging flag.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Creates parser instance.
     *
     * @return void
     */
    public function __construct() {
    
    }

    /**
     * Builds timezone instance from name.
     *
     * @param string $name
     *
     * @return DateTimeZone
     */
    protected function createTimezone($name) {
        $result = timezone_open($name);
        if ($result === false) {
            $this->logError('Invalid timezone "' . $name . '". Falling back to UTC.');
            $result = new DateTimeZone('UTC');
        }

        return ($result);
    }

    /**
     * Logs an error message and stores it.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logError($message) {
        $this->lastError = $message;
        print('[error] ' . $message . PHP_EOL);
    }

    /**
     * Logs an info message when debug is enabled.
     *
     * @param string $message
     *
     * @return void
     */
    protected function logInfo($message) {
        if ($this->debug) {
            print('[info] ' . $message . PHP_EOL);
        }
    }

    /**
     * Returns the last error message.
     *
     * @return string
     */
    public function getLastError() {
        $result = $this->lastError;
        return ($result);
    }

    /**
     * Returns the last raw HTTP response body.
     *
     * @return string
     */
    public function getLastRawResponse() {
        $result = $this->lastRawResponse;
        return ($result);
    }

    /**
     * Returns the last processed message id.
     *
     * @return int
     */
    public function getLastMessageId() {
        $result = $this->lastMessageId;
        return ($result);
    }

    /**
     * Sets the Telegram channel identifier.
     *
     * @param string $channel
     *
     * @return void
     */
    public function setChannel($channel) {
        if ($channel !== '') {
            $this->channel = $channel;
        }
    }

    /**
     * Sets maximum retry attempts.
     *
     * @param int $maxRetries
     *
     * @return void
     */
    public function setMaxRetries($maxRetries) {
        $value = (int) $maxRetries;
        if ($value < 1) {
            $value = 1;
        }
        $this->maxRetries = $value;
    }

    /**
     * Sets delay between retries in seconds.
     *
     * @param int $seconds
     *
     * @return void
     */
    public function setRetryDelaySeconds($seconds) {
        $value = (int) $seconds;
        if ($value < 0) {
            $value = 0;
        }
        $this->retryDelaySeconds = $value;
    }

    /**
     * Sets backlog size limit.
     *
     * @param int $backlogSize
     *
     * @return void
     */
    public function setBacklogSize($backlogSize) {
        $value = (int) $backlogSize;
        if ($value < 1) {
            $value = 1;
        }
        $this->backlogSize = $value;
    }

    /**
     * Sets HTTP user agent value.
     *
     * @param string $userAgent
     *
     * @return void
     */
    public function setUserAgent($userAgent) {
        if ($userAgent !== '') {
            $this->userAgent = $userAgent;
        }
    }

    /**
     * Enables or disables debug logging.
     *
     * @param bool $debug
     *
     * @return void
     */
    public function setDebug($debug) {
        $this->debug = $debug ? true : false;
    }

    /**
     * Sets timezone name and updates timezone instance.
     *
     * @param string $timezoneName
     *
     * @return void
     */
    public function setTimezoneName($timezoneName) {
        if ($timezoneName !== '') {
            $timezone = timezone_open($timezoneName);
            if ($timezone === false) {
                $this->logError('Invalid timezone "' . $timezoneName . '". Keeping previous timezone.');
                return;
            }

            $this->timezoneName = $timezoneName;
            $this->timezone = $timezone;
            $this->createTimezone($timezoneName);
        }
    }

    /**
     * Comparison helper for sorting by message id.
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    protected function compareMessageById($a, $b) {
        $result = 0;
        if (isset($a['id'], $b['id'])) {
            if ($a['id'] < $b['id']) {
                $result = -1;
            } elseif ($a['id'] > $b['id']) {
                $result = 1;
            }
        }

        return ($result);
    }

    /**
     * Removes duplicate messages by id.
     *
     * @param array $messages
     *
     * @return array
     */
    protected function deduplicateMessages($messages) {
        $result = array();
        $seen = array();

        foreach ($messages as $message) {
            if (!isset($message['id'])) {
                continue;
            }

            $key = (string) $message['id'];
            if (isset($seen[$key])) {
                continue;
            }

            $result[] = $message;
            $seen[$key] = true;
        }

        if (!empty($result)) {
            usort($result, array($this, 'compareMessageById'));
        }

        return ($result);
    }

    /**
     * Limits message count to the specified limit.
     *
     * @param array $messages
     * @param int|null $limit
     *
     * @return array
     */
    protected function limitMessages($messages, $limit) {
        $result = array();
        $total = count($messages);
        $startIndex = 0;

        if ($limit !== null) {
            $limit = (int) $limit;
            if ($limit > 0 and $total > $limit) {
                $startIndex = $total - $limit;
            }
        }

        for ($index = $startIndex; $index < $total; $index++) {
            $result[] = $messages[$index];
        }

        return ($result);
    }

    /**
     * Updates last message id from list.
     *
     * @param array $messages
     *
     * @return void
     */
    protected function updateLastMessageIdFromList($messages) {
        if (empty($messages)) {
            return;
        }

        $lastIndex = count($messages) - 1;
        if (isset($messages[$lastIndex]['id'])) {
            $this->lastMessageId = (int) $messages[$lastIndex]['id'];
        }
    }

    /**
     * Creates preconfigured OmaeUrl client for Telegram.
     *
     * @return OmaeUrl
     */
    protected function buildTelegramClient() {
        $result = new OmaeUrl();
        $result->setHeadersReturn(false);
        $result->setOpt(CURLOPT_POST, true);
        $result->setOpt(CURLOPT_POSTFIELDS, '');
        $result->setOpt(CURLOPT_ENCODING, '');
        $result->setUserAgent($this->userAgent);
        $result->setReferrer(sprintf('https://t.me/s/%s', $this->channel));
        $result->dataHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $result->dataHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        $result->dataHeader('X-Requested-With', 'XMLHttpRequest');

        return ($result);
    }

    /**
     * Returns descriptive JSON error message.
     *
     * @return string
     */
    protected function jsonErrorMessage() {
        $result = '';
        if (function_exists('json_last_error_msg')) {
            $result = json_last_error_msg();
        } else {
            $errors = array(
                JSON_ERROR_NONE => 'No error',
                JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
                JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
                JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
                JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
                JSON_ERROR_UTF8 => 'Malformed UTF-8 characters'
            );

            $code = json_last_error();
            $result = isset($errors[$code]) ? $errors[$code] : 'Unknown JSON error';
        }

        return ($result);
    }

    /**
     * Decodes Telegram JSON payload containing HTML.
     *
     * @param string $json
     *
     * @return string|null
     */
    protected function decodeTelegramPayload($json) {
        $result = null;
        if ($json === '') {
            $this->logError('Telegram responded with an empty body');
        } else {
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Telegram payload is not valid JSON: ' . $this->jsonErrorMessage());
            } elseif (!is_string($decoded)) {
                $this->logError('Telegram payload was not the expected JSON string');
            } else {
                $result = $decoded;
            }
        }

        return ($result);
    }

    /**
     * Fetches a Telegram page optionally before a specific message id.
     *
     * @param int|null $before
     *
     * @return array
     */
    protected function fetchPage($before = null) {
        $client = $this->buildTelegramClient();

        $url = sprintf('https://t.me/s/%s', $this->channel);
        if ($before !== null) {
            $client->dataGet('before', (string) $before);
        }

        $result = array();
        $success = false;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $body = $client->response($url);
            $this->lastRawResponse = $body;

            if ($client->httpCode() >= 200 and $client->httpCode() < 300) {
                $html = $this->decodeTelegramPayload($body);
                if ($html !== null) {
                    $result = $this->parseMessagesFromHtml($html);
                    $success = true;
                    break;
                }
            } else {
                $curlError = $client->error();
                if ($curlError) {
                    $this->logError('Unexpected HTTP ' . $client->httpCode() . ' (curl ' . $curlError['errorcode'] . ': ' . $curlError['errormessage'] . ')');
                } else {
                    $this->logError('Unexpected HTTP ' . $client->httpCode());
                }
            }

            if ($attempt < $this->maxRetries) {
                sleep($this->retryDelaySeconds);
            }
        }

        if ($success === false) {
            $this->logError('Unable to fetch Telegram messages after retries');
        }

        return ($result);
    }

    /**
     * Fetches backlog messages up to backlog size.
     *
     * @return array
     */
    protected function fetchBacklogMessages() {
        $result = array();
        $before = null;
        $seenBefore = array();

        while (count($result) < $this->backlogSize) {
            $page = $this->fetchPage($before);
            if (empty($page)) {
                break;
            }

            $result = array_merge($page, $result);
            $result = $this->deduplicateMessages($result);

            if (count($result) >= $this->backlogSize) {
                break;
            }

            $pageBeforeId = isset($page[0]['id']) ? $page[0]['id'] : null;
            if ($pageBeforeId === null or isset($seenBefore[$pageBeforeId])) {
                break;
            }

            $seenBefore[$pageBeforeId] = true;
            $before = $pageBeforeId;
        }

        $result = $this->deduplicateMessages($result);
        $result = $this->limitMessages($result, $this->backlogSize);
        $this->updateLastMessageIdFromList($result);

        return ($result);
    }

    /**
     * Fetches new messages after last message id.
     *
     * @return array
     */
    protected function fetchNewMessages() {
        $after = $this->lastMessageId;
        $messages = array();
        $before = null;
        $seenBefore = array();

        while (true) {
            $page = $this->fetchPage($before);
            if (empty($page)) {
                break;
            }

            $messages = array_merge($page, $messages);
            $messages = $this->deduplicateMessages($messages);

            if (empty($messages)) {
                break;
            }

            if ($messages[0]['id'] <= $after) {
                break;
            }

            $pageBeforeId = isset($page[0]['id']) ? $page[0]['id'] : null;
            if ($pageBeforeId === null or isset($seenBefore[$pageBeforeId])) {
                break;
            }

            $seenBefore[$pageBeforeId] = true;
            $before = $pageBeforeId;
        }

        $result = array();
        if (!empty($messages)) {
            $messages = $this->deduplicateMessages($messages);

            foreach ($messages as $message) {
                if ($message['id'] > $after) {
                    $result[] = $message;
                }
            }

            $result = $this->deduplicateMessages($result);

        if (!empty($result)) {
            $this->updateLastMessageIdFromList($result);
        }
        }

        return ($result);
    }

    /**
     * Fetches messages based on backlog or new updates.
     *
     * @return array
     */
    public function fetchMessages() {
        $result = array();
        if ($this->channel === '') {
            $this->logError('Telegram channel name cannot be empty');
        } else {
            if ($this->lastMessageId === 0) {
                $result = $this->fetchBacklogMessages();
            } else {
                $result = $this->fetchNewMessages();
            }
        }

        return ($result);
    }

    /**
     * Parses Telegram messages from HTML fragment.
     *
     * @param string $html
     *
     * @return array
     */
    protected function parseMessagesFromHtml($html) {
        $dom = new DOMDocument();
        $normalizedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $previousLibxmlSetting = libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $normalizedHtml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        $result = array();
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query("//*[contains(@class, 'tgme_widget_message')]") as $messageNode) {
            if (!($messageNode instanceof DOMElement)) {
                continue;
            }

            $dataPost = $messageNode->getAttribute('data-post');
            if (!$dataPost) {
                continue;
            }
            $parts = explode('/', $dataPost);
            $id = isset($parts[count($parts) - 1]) ? (int) $parts[count($parts) - 1] : null;

            $authorNode = $xpath->query(".//*[contains(@class, 'tgme_widget_message_author')]//span", $messageNode)->item(0);
            $author = $authorNode ? trim($authorNode->textContent) : '';

            $textNodes = $xpath->query(".//*[contains(@class, 'tgme_widget_message_text')]", $messageNode);
            $textParts = array();
            foreach ($textNodes as $textNode) {
                if ($textNode instanceof DOMElement) {
                    $brNodes = array();
                    foreach ($textNode->getElementsByTagName('br') as $brNode) {
                        $brNodes[] = $brNode;
                    }
                    foreach ($brNodes as $brNode) {
                        if ($brNode->ownerDocument) {
                            $replacementDoc = $brNode->ownerDocument;
                        } else {
                            $replacementDoc = $dom;
                        }
                        $brNode->parentNode->replaceChild($replacementDoc->createTextNode(' '), $brNode);
                    }
                }
                $rawText = $textNode->textContent;
                $rawText = str_replace(array("\r\n", "\n", "\r"), ' ', $rawText);
                $normalizedText = preg_replace('~\s+~', ' ', trim($rawText));
                if ($normalizedText !== '') {
                    $textParts[] = $normalizedText;
                }
            }
            $text = trim(preg_replace('~\s+~', ' ', implode(' ', $textParts)));

            $timeNode = $xpath->query(".//*[contains(@class, 'tgme_widget_message_footer')]//time[@datetime]", $messageNode)->item(0);
            $datetime = $timeNode instanceof DOMElement ? $timeNode->getAttribute('datetime') : null;
            $datetimeString = null;
            if ($datetime) {
                $timestamp = new DateTime($datetime, new DateTimeZone('UTC'));
                if ($this->timezone instanceof DateTimeZone) {
                    $timestamp->setTimezone($this->timezone);
                }
                $datetimeString = $timestamp->format('Y-m-d H:i:s');
            }

            if ($id !== null and $datetimeString !== null) {
                $result[] = array(
                    'id' => $id,
                    'author' => $author,
                    'text' => $text,
                    'datetime' => $datetimeString,
                );
            }
        }

        return ($result);
    }
}
