<?php

/**
 * Generates map assets for Ukrainian regions.
 */
class UaMapGen {
    /**
     * Path to SVG template file.
     *
     * @var string
     */
    protected $templatePath = '';

    /**
     * Fill color for alert regions.
     *
     * @var string
     */
    protected $alertColor = '#dd5522';

    /**
     * Fill color for calm regions.
     *
     * @var string
     */
    protected $defaultColor = '#77aa55';

    /**
     * Title text color.
     *
     * @var string
     */
    protected $titleColor = '#000000';

    /**
     * Configures map generator options.
     *
     * @param string|null $templatePath
     * @param string $alertColor
     * @param string $defaultColor
     * @param string $titleColor
     *
     * @return void
     */
    public function __construct($templatePath = null, $alertColor = '#dd5522', $defaultColor = '#77aa55', $titleColor = '#000000') {
        $this->templatePath = $templatePath !== null ? $templatePath : __DIR__ . '/../assets/uamap.svg';
        $this->alertColor = $alertColor;
        $this->defaultColor = $defaultColor;
        $this->titleColor = $titleColor;
    }

    /**
     * Generates map payload with SVG content type.
     *
     * @param array $states
     * @param string $title
     * @param bool $transparent
     *
     * @return array
     */
    public function generate($states, $title = '', $transparent = true) {
        $svg = $this->generateSvg($states, $title, $transparent);

        $result = array(
            'contentType' => 'image/svg+xml',
            'bytes' => $svg,
        );

        return ($result);
    }

    /**
     * Generates SVG string for the map.
     *
     * @param array $states
     * @param string $title
     * @param bool $transparent
     *
     * @return string
     */
    public function generateSvg($states, $title = '', $transparent = true) {
        $template = $this->loadTemplate();
        $alerts = $this->normalizeStates($states);
        $svg = $this->replacePlaceholders($template, $alerts, $title);

        if (!$transparent) {
            $svg = $this->injectBackground($svg);
        }

        return ($svg);
    }

    /**
     * Generates PNG payload for the map.
     *
     * @param array $states
     * @param string $title
     * @param bool $transparent
     *
     * @return array
     */
    public function generatePng($states, $title = '', $transparent = true) {
        $svgClass = '\SVG\SVG';
        if (!class_exists($svgClass)) {
            throw new RuntimeException('php-svg library not found. Include autoloader before using generatePng.');
        }

        $svgContent = $this->generateSvg($states, $title, $transparent);
        $png = $this->convertSvgToPng($svgContent, $transparent);

        $result = array(
            'contentType' => 'image/png',
            'bytes' => $png,
        );

        return ($result);
    }

    /**
     * Loads SVG template from disk.
     *
     * @return string
     */
    protected function loadTemplate() {
        if (!is_file($this->templatePath)) {
            throw new RuntimeException('Map template not found at ' . $this->templatePath);
        }

        $template = file_get_contents($this->templatePath);
        if ($template === false) {
            throw new RuntimeException('Unable to read map template.');
        }

        return ($template);
    }

    /**
     * Normalizes state alert list.
     *
     * @param array $states
     *
     * @return array
     */
    protected function normalizeStates($states) {
        $alerts = array();

        if (!is_array($states)) {
            return ($alerts);
        }

        foreach ($states as $state) {
            $id = null;
            $alert = false;

            if (is_array($state)) {
                $id = isset($state['id']) ? $state['id'] : (isset($state['ID']) ? $state['ID'] : null);
                $alert = isset($state['alert']) ? $state['alert'] : (isset($state['Alert']) ? $state['Alert'] : false);
            } elseif (is_object($state)) {
                if (isset($state->id)) {
                    $id = $state->id;
                } elseif (isset($state->ID)) {
                    $id = $state->ID;
                }

                if (isset($state->alert)) {
                    $alert = $state->alert;
                } elseif (isset($state->Alert)) {
                    $alert = $state->Alert;
                }
            }

            if ($id === null) {
                continue;
            }

            $alerts[(int) $id] = (bool) $alert;
        }

        return ($alerts);
    }

    /**
     * Replaces placeholders in SVG template.
     *
     * @param string $template
     * @param array $alerts
     * @param string $title
     *
     * @return string
     */
    protected function replacePlaceholders($template, $alerts, $title) {
        $pattern = '/fill="\{\{ if \(index \.alerts (-?\d+)\) \}\}#dd5522\{\{ else \}\}#77aa55\{\{ end \}\}"/';

        $callback = function ($matches) use ($alerts) {
            $id = (int) $matches[1];
            $hasAlert = isset($alerts[$id]) ? (bool) $alerts[$id] : false;
            $color = $hasAlert ? $this->alertColor : $this->defaultColor;

            return ('fill="' . $color . '"');
        };

        $result = preg_replace_callback($pattern, $callback, $template);

        if ($result === null) {
            $result = $template;
        }

        if ($title !== '') {
            $encoded = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $result = str_replace('{{ .title }}', $encoded, $result);
        } else {
            $result = str_replace('{{ .title }}', '', $result);
        }

        return ($result);
    }

    /**
     * Injects title text into SVG.
     *
     * @param string $svg
     * @param string $title
     *
     * @return string
     */
    protected function injectTitle($svg, $title) {
        $encoded = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = '<text x="50" y="620" fill="' . $this->titleColor . '" font-size="48" data-uamapgen="title">' . $encoded . '</text>';

        if (preg_match('/<\/svg>\s*$/i', $svg)) {
            $result = preg_replace('/<\/svg>\s*$/i', $text . '</svg>', $svg, 1);
            if ($result !== null) {
                return ($result);
            }
        }

        return ($svg . $text);
    }

    /**
     * Adds background fill to SVG.
     *
     * @param string $svg
     *
     * @return string
     */
    protected function injectBackground($svg) {
        $background = '<rect width="1000" height="670" fill="#ffffff" data-uamapgen="background"></rect>';

        if (preg_match('/<svg\b[^>]*>/i', $svg, $match)) {
            $tag = $match[0];
            $replacement = $tag . $background;

            $result = preg_replace('/<svg\b[^>]*>/i', $replacement, $svg, 1);
            if ($result !== null) {
                return ($result);
            }
        }

        return ($background . $svg);
    }

    /**
     * Converts SVG to PNG bytes.
     *
     * @param string $svgContent
     * @param bool $transparent
     *
     * @return string
     */
    protected function convertSvgToPng($svgContent, $transparent) {
        $svgClass = '\SVG\SVG';
        if (!class_exists($svgClass)) {
            throw new RuntimeException('php-svg library not found. Include autoloader before using generatePng.');
        }

        $image = call_user_func(array($svgClass, 'fromString'), $svgContent);
        $document = $image->getDocument();

        $width = $this->parsePixelSize($document->getWidth(), 1000);
        $height = $this->parsePixelSize($document->getHeight(), 670);

        $gd = $image->toRasterImage($width, $height);
        imagealphablending($gd, false);
        imagesavealpha($gd, true);
        ob_start();
        imagepng($gd, null, 6);
        imagedestroy($gd);

        return (string) ob_get_clean();
    }

    /**
     * Parses pixel size value from attribute.
     *
     * @param mixed $value
     * @param int $default
     *
     * @return int
     */
    protected function parsePixelSize($value, $default) {
        if (is_numeric($value)) {
            $result = (int) $value;
            return ($result > 0 ? $result : $default);
        }

        if (is_string($value) and $value !== '') {
            $filtered = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if ($filtered > 0) {
                return ($filtered);
            }
        }

        return ($default);
    }

    /**
     * Parses pixel size value from attribute.
     *
     * @param mixed $value
     * @param int $default
     *
     * @return int
     */
}



