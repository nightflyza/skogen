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
        $this->templatePath = $templatePath !== null ? $templatePath : __DIR__ . '/../assets/uamap.tpl';
        $this->alertColor = $alertColor;
        $this->defaultColor = $defaultColor;
        $this->titleColor = $titleColor;
    }

    /**
     * Generates map payload with SVG content type.
     *
     * @param array $states
     * @param string $title
     *
     * @return array
     */
    public function generate($states, $title = '') {
        $svg = $this->generateSvg($states, $title);

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
     *
     * @return string
     */
    public function generateSvg($states, $title = '') {
        $template = $this->loadTemplate();
        $alerts = $this->normalizeStates($states);
        $svg = $this->replacePlaceholders($template, $alerts, $title);

        return ($svg);
    }

    /**
     * Generates raster payload for the map.
     *
     * @param array $states
     * @param string $title
     * @param string $format
     *
     * @return array
     */
    public function rasterize($states, $title = '', $format = 'png') {
        $normalized = $this->normalizeFormat($format);
        $supported = array('png', 'jpeg', 'gif', 'webp');

        if (!in_array($normalized, $supported, true)) {
            throw new InvalidArgumentException('Unsupported raster format: ' . $format);
        }

        $svgContent = $this->generateSvg($states, $title);
        $bytes = $this->convertSvgToRaster($svgContent, $normalized);

        $result = array(
            'contentType' => 'image/' . $this->contentTypeSuffix($normalized),
            'bytes' => $bytes,
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
     * Converts SVG to raster bytes.
     *
     * @param string $svgContent
     * @param string $format
     *
     * @return string
     */
    protected function convertSvgToRaster($svgContent, $format) {
        $svgClass = '\SVG\SVG';
        if (!class_exists($svgClass)) {
            throw new RuntimeException('php-svg library not found.');
        }

        $svgClass::addFont(__DIR__ . '/../assets/Bebas_Neue_Cyrillic.ttf');

        $image = call_user_func(array($svgClass, 'fromString'), $svgContent);
        $document = $image->getDocument();

        $width = $this->parsePixelSize($document->getWidth(), 1000);
        $height = $this->parsePixelSize($document->getHeight(), 670);

        $gd = $image->toRasterImage($width, $height);
        $isGdObject = class_exists('\GdImage') ? ($gd instanceof \GdImage) : false;

        if (!is_resource($gd) and !$isGdObject) {
            throw new RuntimeException('Unable to render raster image from SVG.');
        }

        if ($format === 'png' or $format === 'webp') {
            imagealphablending($gd, false);
            imagesavealpha($gd, true);
        }

        if ($format === 'gif') {
            $backgroundColor = imagecolorat($gd, 0, 0);
            $backgroundAlpha = ($backgroundColor & 0x7F000000) >> 24;
            imagetruecolortopalette($gd, true, 256);
            $transparentIndex = imagecolorclosestalpha(
                $gd,
                ($backgroundColor >> 16) & 0xFF,
                ($backgroundColor >> 8) & 0xFF,
                $backgroundColor & 0xFF,
                $backgroundAlpha
            );
            if ($backgroundAlpha === 127 and $transparentIndex !== -1) {
                imagecolortransparent($gd, $transparentIndex);
            }
        }

        if ($format === 'jpeg') {
            imagealphablending($gd, true);
            imagesavealpha($gd, false);

            $opaque = imagecreatetruecolor($width, $height);
            $background = imagecolorallocate($opaque, 255, 255, 255);
            imagefilledrectangle($opaque, 0, 0, $width, $height, $background);
            imagecopy($opaque, $gd, 0, 0, 0, 0, $width, $height);
            imagedestroy($gd);
            $gd = $opaque;
        }

        if ($format === 'webp' and !function_exists('imagewebp')) {
            imagedestroy($gd);
            throw new RuntimeException('WebP support is not enabled in GD.');
        }

        ob_start();
        $success = false;
        if ($format === 'png') {
            $success = imagepng($gd, null, 6);
        } elseif ($format === 'jpeg') {
            $success = imagejpeg($gd, null, 80);
        } elseif ($format === 'gif') {
            $success = imagegif($gd);
        } elseif ($format === 'webp') {
            $success = imagewebp($gd, null, 90);
        }

        if ($success === false) {
            ob_end_clean();
            imagedestroy($gd);
            throw new RuntimeException('Failed to encode ' . $format . ' image.');
        }

        $binary = (string) ob_get_clean();
        imagedestroy($gd);

        return ($binary);
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
     * Normalizes raster format name.
     *
     * @param string $format
     *
     * @return string
     */
    protected function normalizeFormat($format) {
        $lower = strtolower((string) $format);

        if ($lower === 'jpg') {
            return ('jpeg');
        }

        return ($lower);
    }

    /**
     * Maps normalized format to content type suffix.
     *
     * @param string $format
     *
     * @return string
     */
    protected function contentTypeSuffix($format) {
        if ($format === 'jpeg') {
            return ('jpeg');
        }

        return ($format);
    }
 
}



