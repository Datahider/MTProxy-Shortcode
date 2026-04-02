<?php
/**
 * Plugin Name: MTProxy QR Shortcode
 * Description: Генерирует и кэширует QR-код для MTProxy ссылки, полученной с удаленного endpoint, и выводит через shortcode.
 * Version: 0.1.0
 * Author: MTProxy Shortcode
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MTProxy_QR_Shortcode_Plugin
{
    private const OPTION_KEY = 'mtproxy_qr_settings';
    private const QR_SHORTCODE = 'mtproxy_qr';
    private const LINK_SHORTCODE = 'mtproxy_link';

    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'register_shortcode']);
        add_action('admin_menu', [self::class, 'register_admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function activate(): void
    {
        $defaults = self::default_settings();
        $current = get_option(self::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }
        update_option(self::OPTION_KEY, array_merge($defaults, $current));
    }

    private static function default_settings(): array
    {
        return [
            'endpoint_url' => '',
            'timeout' => 3,
            'qr_size' => 280,
            'cache_ttl' => 43200,
            'fallback_image_url' => '',
            'show_proxy_link' => 1,
        ];
    }

    public static function register_shortcode(): void
    {
        add_shortcode(self::QR_SHORTCODE, [self::class, 'render_qr_shortcode']);
        add_shortcode(self::LINK_SHORTCODE, [self::class, 'render_link_shortcode']);
    }

    public static function register_admin_menu(): void
    {
        add_options_page(
            'MTProxy QR',
            'MTProxy QR',
            'manage_options',
            'mtproxy-qr-settings',
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting(
            'mtproxy_qr_group',
            self::OPTION_KEY,
            [
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default' => self::default_settings(),
            ]
        );

        add_settings_section(
            'mtproxy_qr_main',
            'Основные настройки',
            '__return_false',
            'mtproxy-qr-settings'
        );

        self::add_settings_field('endpoint_url', 'URL endpoint с прокси-ссылкой', 'render_text_field');
        self::add_settings_field('timeout', 'Таймаут запроса (сек)', 'render_number_field', ['min' => 1, 'max' => 10]);
        self::add_settings_field('qr_size', 'Размер QR (px)', 'render_number_field', ['min' => 120, 'max' => 1200]);
        self::add_settings_field('cache_ttl', 'TTL кэша QR (сек)', 'render_number_field', ['min' => 300, 'max' => 86400 * 7]);
        self::add_settings_field('fallback_image_url', 'URL fallback-картинки "Недоступно" (необязательно)', 'render_text_field');
        self::add_settings_field('show_proxy_link', 'Показывать ссылку под QR', 'render_checkbox_field');
    }

    private static function add_settings_field(string $key, string $label, string $renderer, array $args = []): void
    {
        add_settings_field(
            'mtproxy_qr_' . $key,
            $label,
            [self::class, $renderer],
            'mtproxy-qr-settings',
            'mtproxy_qr_main',
            [
                'key' => $key,
                'attrs' => $args,
            ]
        );
    }

    public static function sanitize_settings(array $raw): array
    {
        $defaults = self::default_settings();
        $result = $defaults;

        $result['endpoint_url'] = isset($raw['endpoint_url']) ? esc_url_raw(trim((string) $raw['endpoint_url'])) : '';
        $result['fallback_image_url'] = isset($raw['fallback_image_url']) ? esc_url_raw(trim((string) $raw['fallback_image_url'])) : '';

        $result['timeout'] = isset($raw['timeout']) ? max(1, min(10, (int) $raw['timeout'])) : $defaults['timeout'];
        $result['qr_size'] = isset($raw['qr_size']) ? max(120, min(1200, (int) $raw['qr_size'])) : $defaults['qr_size'];
        $result['cache_ttl'] = isset($raw['cache_ttl']) ? max(300, min(86400 * 7, (int) $raw['cache_ttl'])) : $defaults['cache_ttl'];
        $result['show_proxy_link'] = empty($raw['show_proxy_link']) ? 0 : 1;

        return $result;
    }

    private static function get_settings(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge(self::default_settings(), $saved);
    }

    public static function render_text_field(array $args): void
    {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';

        printf(
            '<input type="url" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($value)
        );
    }

    public static function render_number_field(array $args): void
    {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (int) $settings[$key] : 0;
        $min = isset($args['attrs']['min']) ? (int) $args['attrs']['min'] : 0;
        $max = isset($args['attrs']['max']) ? (int) $args['attrs']['max'] : 0;

        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            $value,
            $min,
            $max
        );
    }

    public static function render_checkbox_field(array $args): void
    {
        $settings = self::get_settings();
        $key = $args['key'];
        $value = !empty($settings[$key]) ? 1 : 0;

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> Да</label>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            checked(1, $value, false)
        );
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>MTProxy QR Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('mtproxy_qr_group');
                do_settings_sections('mtproxy-qr-settings');
                submit_button();
                ?>
            </form>
            <p>Используйте shortcode: <code>[mtproxy_qr]</code> и <code>[mtproxy_link]</code></p>
        </div>
        <?php
    }

    public static function render_qr_shortcode(array $atts = []): string
    {
        $settings = self::get_settings();
        $atts = shortcode_atts(
            [
                'size' => (string) $settings['qr_size'],
                'class' => '',
                'qr_class' => '',
                'link_container_class' => '',
                'show_link' => (string) (int) $settings['show_proxy_link'],
            ],
            $atts,
            self::QR_SHORTCODE
        );

        $size = max(120, min(1200, (int) $atts['size']));
        $class = self::sanitize_classes((string) $atts['class']);
        $qrClass = self::sanitize_classes((string) $atts['qr_class']);
        $linkContainerClass = self::sanitize_classes((string) $atts['link_container_class']);
        $showLink = ((int) $atts['show_link']) === 1;

        $proxyUrl = self::fetch_proxy_url((string) $settings['endpoint_url'], (int) $settings['timeout']);
        if ($proxyUrl === null) {
            return self::render_unavailable($class);
        }

        $qrUrl = self::get_or_create_qr($proxyUrl, $size, (int) $settings['cache_ttl']);
        if ($qrUrl === null) {
            return self::render_unavailable($class);
        }

        $html = '<div class="' . esc_attr(trim('mtproxy-qr ' . $class)) . '">';
        $html .= '<img class="' . esc_attr(trim('mtproxy-qr-image ' . $qrClass)) . '" src="' . esc_url($qrUrl) . '" width="' . (int) $size . '" height="' . (int) $size . '" alt="MTProxy QR" loading="lazy" decoding="async" />';

        if ($showLink) {
            $html .= self::build_link_html($proxyUrl, $linkContainerClass);
        }

        $html .= '</div>';

        return $html;
    }

    public static function render_link_shortcode(array $atts = []): string
    {
        $settings = self::get_settings();
        $atts = shortcode_atts(
            [
                'class' => '',
            ],
            $atts,
            self::LINK_SHORTCODE
        );

        $class = self::sanitize_classes((string) $atts['class']);
        $proxyUrl = self::fetch_proxy_url((string) $settings['endpoint_url'], (int) $settings['timeout']);
        if ($proxyUrl === null) {
            return '';
        }

        return self::build_link_html($proxyUrl, $class);
    }

    private static function build_link_html(string $proxyUrl, string $class = ''): string
    {
        $html = '<div class="' . esc_attr(trim('mtproxy-qr-link ' . $class)) . '">';
        $html .= '<a class="mtproxy-qr-link-anchor" href="' . esc_url($proxyUrl) . '" target="_blank" rel="noopener noreferrer">Открыть прокси-ссылку</a>';
        $html .= '</div>';

        return $html;
    }

    private static function sanitize_classes(string $classes): string
    {
        $raw = preg_split('/\s+/', trim($classes));
        if (!is_array($raw)) {
            return '';
        }

        $clean = [];
        foreach ($raw as $class) {
            if ($class === '') {
                continue;
            }
            $sanitized = sanitize_html_class($class);
            if ($sanitized !== '') {
                $clean[] = $sanitized;
            }
        }

        return implode(' ', array_unique($clean));
    }

    private static function fetch_proxy_url(string $endpointUrl, int $timeout): ?string
    {
        if ($endpointUrl === '' || !wp_http_validate_url($endpointUrl)) {
            return null;
        }

        $response = wp_remote_get($endpointUrl, [
            'timeout' => $timeout,
            'redirection' => 2,
            'headers' => [
                'Accept' => 'text/plain,application/json;q=0.9,*/*;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = trim((string) wp_remote_retrieve_body($response));
        if ($body === '') {
            return null;
        }

        $url = self::extract_url_from_body($body);
        if ($url === null) {
            return null;
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || !in_array(strtolower($host), ['t.me', 'telegram.me'], true)) {
            return null;
        }

        return $url;
    }

    private static function extract_url_from_body(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['url', 'proxy_url', 'link'] as $key) {
                if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                    $candidate = trim($decoded[$key]);
                    if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                        return $candidate;
                    }
                }
            }
        }

        if (filter_var($body, FILTER_VALIDATE_URL)) {
            return $body;
        }

        if (preg_match('~https?://[^\s"\']+~i', $body, $m) === 1) {
            $candidate = $m[0];
            if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function get_or_create_qr(string $proxyUrl, int $size, int $ttl): ?string
    {
        $hash = hash('sha256', $proxyUrl . '|' . $size);
        $upload = wp_upload_dir();

        if (!empty($upload['error']) || empty($upload['basedir']) || empty($upload['baseurl'])) {
            return null;
        }

        $dir = trailingslashit($upload['basedir']) . 'mtproxy-qr';
        $urlBase = trailingslashit($upload['baseurl']) . 'mtproxy-qr';

        if (!file_exists($dir) && !wp_mkdir_p($dir)) {
            return null;
        }

        $filename = 'qr-' . $hash . '.png';
        $path = trailingslashit($dir) . $filename;
        $publicUrl = trailingslashit($urlBase) . $filename;

        if (file_exists($path) && (time() - filemtime($path) < $ttl)) {
            return $publicUrl;
        }

        $lockKey = 'mtproxy_qr_lock_' . $hash;
        if (get_transient($lockKey)) {
            if (file_exists($path)) {
                return $publicUrl;
            }
            return null;
        }

        set_transient($lockKey, 1, 20);

        $apiUrl = add_query_arg(
            [
                'size' => $size . 'x' . $size,
                'data' => $proxyUrl,
                'format' => 'png',
                'margin' => 0,
            ],
            'https://api.qrserver.com/v1/create-qr-code/'
        );

        $response = wp_remote_get($apiUrl, [
            'timeout' => 8,
            'redirection' => 2,
            'headers' => [
                'Accept' => 'image/png,*/*;q=0.8',
            ],
        ]);

        $ok = false;

        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (is_string($body) && $body !== '') {
                $written = file_put_contents($path, $body);
                if ($written !== false) {
                    $ok = true;
                }
            }
        }

        delete_transient($lockKey);

        return $ok && file_exists($path) ? $publicUrl : null;
    }

    private static function render_unavailable(string $class = ''): string
    {
        $settings = self::get_settings();
        $src = $settings['fallback_image_url'] ?: self::default_unavailable_image();

        $html = '<div class="mtproxy-qr-unavailable ' . esc_attr($class) . '">';
        $html .= '<img src="' . esc_url($src) . '" alt="MTProxy недоступен" loading="lazy" decoding="async" />';
        $html .= '</div>';

        return $html;
    }

    private static function default_unavailable_image(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="140" viewBox="0 0 320 140">
<rect width="320" height="140" fill="#f4f4f5"/>
<rect x="1" y="1" width="318" height="138" fill="none" stroke="#d4d4d8"/>
<text x="160" y="64" text-anchor="middle" font-family="Arial, sans-serif" font-size="18" fill="#3f3f46">MTProxy</text>
<text x="160" y="92" text-anchor="middle" font-family="Arial, sans-serif" font-size="16" fill="#b91c1c">Недоступен</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

register_activation_hook(__FILE__, [MTProxy_QR_Shortcode_Plugin::class, 'activate']);
MTProxy_QR_Shortcode_Plugin::bootstrap();
