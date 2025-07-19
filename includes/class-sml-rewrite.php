<?php
/**
 * Classe pour la gestion des URLs personnalisées et des rewrites
 * includes/class-sml-rewrite.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Rewrite {
    
    /**
     * Patterns d'URL pour les liens sécurisés
     */
    const SECURE_LINK_PATTERN = 'sml/media/([0-9]+)/([0-9]+)/([a-f0-9]{64})';
    const HEALTH_CHECK_PATTERN = 'sml/health';
    const API_PATTERN = 'sml-api/(.*)';
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_action('init', array(__CLASS__, 'add_rewrite_endpoints'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_custom_endpoints'));
        add_filter('rewrite_rules_array', array(__CLASS__, 'insert_rewrite_rules'));
        
        // Actions pour la gestion des requêtes
        add_action('wp_loaded', array(__CLASS__, 'maybe_flush_rewrite_rules'));
        add_action('admin_init', array(__CLASS__, 'check_rewrite_rules'));
    }
    
    /**
     * Ajouter les règles de réécriture
     */
    public static function add_rewrite_rules() {
        // Règle principale pour les liens sécurisés
        add_rewrite_rule(
            '^' . self::SECURE_LINK_PATTERN . '/?$',
            'index.php?sml_action=secure_media&media_id=$matches[1]&format_id=$matches[2]&link_hash=$matches[3]',
            'top'
        );
        
        // Règle pour le health check
        add_rewrite_rule(
            '^' . self::HEALTH_CHECK_PATTERN . '/?$',
            'index.php?sml_action=health_check',
            'top'
        );
        
        // Règle pour l'API personnalisée
        add_rewrite_rule(
            '^' . self::API_PATTERN . '/?$',
            'index.php?sml_action=api&sml_api_path=$matches[1]',
            'top'
        );
        
        // Règle pour les domaines personnalisés
        $settings = get_option('sml_settings', array());
        if (!empty($settings['custom_domain'])) {
            $custom_domain_host = parse_url($settings['custom_domain'], PHP_URL_HOST);
            if ($custom_domain_host && $_SERVER['HTTP_HOST'] === $custom_domain_host) {
                add_rewrite_rule(
                    '^media/([0-9]+)/([0-9]+)/([a-f0-9]{64})/?$',
                    'index.php?sml_action=secure_media&media_id=$matches[1]&format_id=$matches[2]&link_hash=$matches[3]',
                    'top'
                );
            }
        }
        
        // Règle pour les uploads frontend
        add_rewrite_rule(
            '^sml/upload/?$',
            'index.php?sml_action=frontend_upload',
            'top'
        );
        
        // Règle pour les téléchargements directs
        add_rewrite_rule(
            '^sml/download/([0-9]+)/?$',
            'index.php?sml_action=direct_download&link_id=$matches[1]',
            'top'
        );
        
        // Règle pour les statistiques publiques
        add_rewrite_rule(
            '^sml/stats/([0-9]+)/?$',
            'index.php?sml_action=public_stats&media_id=$matches[1]',
            'top'
        );
    }
    
    /**
     * Ajouter les endpoints personnalisés
     */
    public static function add_rewrite_endpoints() {
        add_rewrite_endpoint('sml_action', EP_ROOT);
        add_rewrite_endpoint('media_id', EP_ROOT);
        add_rewrite_endpoint('format_id', EP_ROOT);
        add_rewrite_endpoint('link_hash', EP_ROOT);
        add_rewrite_endpoint('link_id', EP_ROOT);
        add_rewrite_endpoint('sml_api_path', EP_ROOT);
    }
    
    /**
     * Ajouter les variables de requête
     */
    public static function add_query_vars($vars) {
        $vars[] = 'sml_action';
        $vars[] = 'media_id';
        $vars[] = 'format_id';
        $vars[] = 'link_hash';
        $vars[] = 'link_id';
        $vars[] = 'sml_api_path';
        
        return $vars;
    }
    
    /**
     * Gérer les endpoints personnalisés
     */
    public static function handle_custom_endpoints() {
        $action = get_query_var('sml_action');
        
        if (empty($action)) {
            return;
        }
        
        switch ($action) {
            case 'secure_media':
                self::handle_secure_media_request();
                break;
                
            case 'health_check':
                self::handle_health_check();
                break;
                
            case 'api':
                self::handle_api_request();
                break;
                
            case 'frontend_upload':
                self::handle_frontend_upload();
                break;
                
            case 'direct_download':
                self::handle_direct_download();
                break;
                
            case 'public_stats':
                self::handle_public_stats();
                break;
                
            default:
                wp_die(__('Action non reconnue', 'secure-media-link'), 404);
        }
        
        exit;
    }
    
    /**
     * Gérer les requêtes de médias sécurisés
     */
    private static function handle_secure_media_request() {
        $media_id = intval(get_query_var('media_id'));
        $format_id = intval(get_query_var('format_id'));
        $link_hash = get_query_var('link_hash');
        
        // Récupérer les paramètres de la signature depuis l'URL
        $expires = isset($_GET['Expires']) ? intval($_GET['Expires']) : 0;
        $signature = isset($_GET['Signature']) ? sanitize_text_field($_GET['Signature']) : '';
        $key_pair_id = isset($_GET['Key-Pair-Id']) ? sanitize_text_field($_GET['Key-Pair-Id']) : '';
        
        if (!$media_id || !$format_id || !$link_hash || !$expires || !$signature || !$key_pair_id) {
            self::send_error_response(400, __('Paramètres manquants', 'secure-media-link'));
            return;
        }
        
        // Vérifier la signature
        $verification = SML_Crypto::verify_secure_link($link_hash, $signature, $expires, $key_pair_id);
        
        if (!$verification['valid']) {
            // Enregistrer la tentative d'accès non autorisé
            if (isset($verification['link']['id'])) {
                SML_Tracking::track_usage($verification['link']['id'], 'download', array(
                    'error' => $verification['error']
                ));
            }
            
            self::send_error_response(403, __('Accès refusé: ', 'secure-media-link') . $verification['error']);
            return;
        }
        
        // Obtenir le média et le format
        $media = get_post($media_id);
        $format = SML_Media_Formats::get_format($format_id);
        
        if (!$media || $media->post_type !== 'attachment' || !$format) {
            self::send_error_response(404, __('Média ou format introuvable', 'secure-media-link'));
            return;
        }
        
        // Vérifier les permissions
        $ip_address = SML_Tracking::get_client_ip();
        $domain = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';
        $permission_check = SML_Permissions::check_permissions($ip_address, $domain, 'download');
        
        if (!$permission_check['authorized']) {
            // Enregistrer la violation
            SML_Tracking::track_usage($verification['link']['id'], 'download');
            
            self::send_error_response(403, __('Accès refusé par les règles de permissions', 'secure-media-link'));
            return;
        }
        
        // Tracker l'utilisation autorisée
        SML_Tracking::track_usage($verification['link']['id'], 'download');
        
        // Générer le fichier dans le format demandé
        $formatted_file_path = SML_Media_Formats::generate_media_format($media_id, $format_id);
        
        if (!$formatted_file_path || !file_exists($formatted_file_path)) {
            self::send_error_response(500, __('Erreur lors de la génération du format', 'secure-media-link'));
            return;
        }
        
        // Servir le fichier
        self::serve_file($formatted_file_path, $media->post_title, $format->format);
    }
    
    /**
     * Gérer le health check
     */
    private static function handle_health_check() {
        global $wpdb;
        
        $health_data = array(
            'status' => 'ok',
            'timestamp' => current_time('timestamp'),
            'version' => SML_PLUGIN_VERSION,
            'checks' => array()
        );
        
        // Vérifier la base de données
        try {
            $wpdb->get_var("SELECT 1");
            $health_data['checks']['database'] = 'ok';
        } catch (Exception $e) {
            $health_data['checks']['database'] = 'error';
            $health_data['status'] = 'error';
        }
        
        // Vérifier la cryptographie
        try {
            $test_key = SML_Crypto::get_key_pair_id();
            $health_data['checks']['crypto'] = $test_key ? 'ok' : 'warning';
        } catch (Exception $e) {
            $health_data['checks']['crypto'] = 'error';
            $health_data['status'] = 'error';
        }
        
        // Vérifier les permissions de fichiers
        $upload_dir = wp_upload_dir();
        $sml_dir = $upload_dir['basedir'] . '/sml-formats';
        
        if (is_writable($sml_dir) || wp_mkdir_p($sml_dir)) {
            $health_data['checks']['file_permissions'] = 'ok';
        } else {
            $health_data['checks']['file_permissions'] = 'error';
            $health_data['status'] = 'error';
        }
        
        // Vérifier le cache
        $health_data['checks']['cache'] = SML_Cache::is_enabled() ? 'ok' : 'disabled';
        
        // Statistiques rapides
        $health_data['stats'] = array(
            'total_links' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links"),
            'active_links' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE is_active = 1 AND expires_at > NOW()"),
            'total_requests_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking WHERE DATE(created_at) = CURDATE()")
        );
        
        // Retourner la réponse JSON
        wp_send_json($health_data, $health_data['status'] === 'ok' ? 200 : 503);
    }
    
    /**
     * Gérer les requêtes API personnalisées
     */
    private static function handle_api_request() {
        $api_path = get_query_var('sml_api_path');
        
        if (empty($api_path)) {
            wp_send_json_error(__('Chemin API manquant', 'secure-media-link'), 400);
            return;
        }
        
        // Rediriger vers l'API REST WordPress
        $rest_url = rest_url('sml/v1/' . $api_path);
        wp_redirect($rest_url, 301);
        exit;
    }
    
    /**
     * Gérer l'upload frontend
     */
    private static function handle_frontend_upload() {
        // Vérifier si c'est une requête POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::send_error_response(405, __('Méthode non autorisée', 'secure-media-link'));
            return;
        }
        
        // Rediriger vers le handler AJAX
        SML_Shortcodes::ajax_frontend_upload();
    }
    
    /**
     * Gérer les téléchargements directs
     */
    private static function handle_direct_download() {
        $link_id = intval(get_query_var('link_id'));
        
        if (!$link_id) {
            self::send_error_response(400, __('ID de lien manquant', 'secure-media-link'));
            return;
        }
        
        global $wpdb;
        
        // Récupérer le lien
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT sl.*, p.post_title, mf.format
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             WHERE sl.id = %d AND sl.is_active = 1 AND sl.expires_at > NOW()",
            $link_id
        ));
        
        if (!$link) {
            self::send_error_response(404, __('Lien introuvable ou expiré', 'secure-media-link'));
            return;
        }
        
        // Vérifier les permissions
        $ip_address = SML_Tracking::get_client_ip();
        $domain = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';
        $permission_check = SML_Permissions::check_permissions($ip_address, $domain, 'download');
        
        if (!$permission_check['authorized']) {
            SML_Tracking::track_usage($link_id, 'download');
            self::send_error_response(403, __('Accès refusé', 'secure-media-link'));
            return;
        }
        
        // Tracker l'utilisation
        SML_Tracking::track_usage($link_id, 'download');
        
        // Générer et servir le fichier
        $formatted_file_path = SML_Media_Formats::generate_media_format($link->media_id, $link->format_id);
        
        if (!$formatted_file_path || !file_exists($formatted_file_path)) {
            self::send_error_response(500, __('Erreur lors de la génération du fichier', 'secure-media-link'));
            return;
        }
        
        self::serve_file($formatted_file_path, $link->post_title, $link->format);
    }
    
    /**
     * Gérer les statistiques publiques
     */
    private static function handle_public_stats() {
        $media_id = intval(get_query_var('media_id'));
        
        if (!$media_id) {
            self::send_error_response(400, __('ID de média manquant', 'secure-media-link'));
            return;
        }
        
        // Vérifier que le média existe
        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            self::send_error_response(404, __('Média introuvable', 'secure-media-link'));
            return;
        }
        
        global $wpdb;
        
        // Récupérer les statistiques publiques
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN action_type = 'download' AND is_authorized = 1 THEN 1 ELSE 0 END) as downloads,
                SUM(CASE WHEN action_type = 'copy' AND is_authorized = 1 THEN 1 ELSE 0 END) as copies,
                SUM(CASE WHEN is_authorized = 0 THEN 1 ELSE 0 END) as blocked
             FROM {$wpdb->prefix}sml_tracking t
             LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
             WHERE sl.media_id = %d",
            $media_id
        ));
        
        $public_stats = array(
            'media_id' => $media_id,
            'media_title' => $media->post_title,
            'total_requests' => intval($stats->total_requests),
            'downloads' => intval($stats->downloads),
            'copies' => intval($stats->copies),
            'blocked' => intval($stats->blocked),
            'generated_at' => current_time('c')
        );
        
        // Ajouter les headers de cache
        header('Cache-Control: public, max-age=300'); // 5 minutes
        header('Content-Type: application/json');
        
        echo json_encode($public_stats);
    }
    
    /**
     * Servir un fichier avec les headers appropriés
     */
    private static function serve_file($file_path, $filename, $format) {
        if (!file_exists($file_path)) {
            self::send_error_response(404, __('Fichier introuvable', 'secure-media-link'));
            return;
        }
        
        // Nettoyer le nom de fichier
        $clean_filename = sanitize_file_name($filename);
        if (empty($clean_filename)) {
            $clean_filename = 'download';
        }
        
        // Ajouter l'extension si nécessaire
        $pathinfo = pathinfo($clean_filename);
        if (empty($pathinfo['extension'])) {
            $clean_filename .= '.' . $format;
        }
        
        // Déterminer le type MIME
        $mime_type = self::get_mime_type($format);
        
        // Headers de sécurité
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Headers de cache (privé)
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Headers du fichier
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: attachment; filename="' . $clean_filename . '"');
        
        // Envoyer le fichier
        readfile($file_path);
    }
    
    /**
     * Obtenir le type MIME selon l'extension
     */
    private static function get_mime_type($extension) {
        $mime_types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip'
        );
        
        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }
    
    /**
     * Envoyer une réponse d'erreur
     */
    private static function send_error_response($code, $message) {
        http_response_code($code);
        
        // Vérifier si c'est une requête AJAX ou API
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        $accept_header = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
        $wants_json = strpos($accept_header, 'application/json') !== false;
        
        if ($is_ajax || $wants_json) {
            header('Content-Type: application/json');
            echo json_encode(array(
                'error' => true,
                'code' => $code,
                'message' => $message
            ));
        } else {
            // Charger une page d'erreur personnalisée si elle existe
            $template_path = SML_PLUGIN_DIR . 'templates/error-' . $code . '.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                wp_die($message, $code);
            }
        }
    }
    
    /**
     * Insérer les règles de réécriture dans le tableau global
     */
    public static function insert_rewrite_rules($rules) {
        $new_rules = array();
        
        // Règles pour les liens sécurisés
        $new_rules['^' . self::SECURE_LINK_PATTERN . '/?$'] = 
            'index.php?sml_action=secure_media&media_id=$matches[1]&format_id=$matches[2]&link_hash=$matches[3]';
        
        // Règles pour le health check
        $new_rules['^' . self::HEALTH_CHECK_PATTERN . '/?$'] = 
            'index.php?sml_action=health_check';
        
        // Règles pour l'API
        $new_rules['^' . self::API_PATTERN . '/?$'] = 
            'index.php?sml_action=api&sml_api_path=$matches[1]';
        
        return $new_rules + $rules;
    }
    
    /**
     * Vérifier si les règles de réécriture doivent être rechargées
     */
    public static function maybe_flush_rewrite_rules() {
        $plugin_version = get_option('sml_rewrite_version');
        
        if ($plugin_version !== SML_PLUGIN_VERSION) {
            flush_rewrite_rules();
            update_option('sml_rewrite_version', SML_PLUGIN_VERSION);
        }
    }
    
    /**
     * Vérifier les règles de réécriture en admin
     */
    public static function check_rewrite_rules() {
        if (!is_admin()) {
            return;
        }
        
        $rules = get_option('rewrite_rules');
        $expected_rule = '^' . self::SECURE_LINK_PATTERN . '/?$';
        
        if (!isset($rules[$expected_rule])) {
            add_action('admin_notices', array(__CLASS__, 'rewrite_rules_notice'));
        }
    }
    
    /**
     * Notice admin pour les règles de réécriture
     */
    public static function rewrite_rules_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Les règles de réécriture de Secure Media Link doivent être actualisées.', 'secure-media-link'); ?>
                <a href="<?php echo admin_url('options-permalink.php'); ?>">
                    <?php _e('Aller aux permaliens', 'secure-media-link'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Obtenir l'URL d'un endpoint personnalisé
     */
    public static function get_endpoint_url($endpoint, $params = array()) {
        $base_url = get_site_url();
        
        switch ($endpoint) {
            case 'secure_media':
                if (isset($params['media_id'], $params['format_id'], $params['link_hash'])) {
                    return sprintf(
                        '%s/sml/media/%d/%d/%s',
                        $base_url,
                        $params['media_id'],
                        $params['format_id'],
                        $params['link_hash']
                    );
                }
                break;
                
            case 'health_check':
                return $base_url . '/sml/health';
                
            case 'direct_download':
                if (isset($params['link_id'])) {
                    return sprintf('%s/sml/download/%d', $base_url, $params['link_id']);
                }
                break;
                
            case 'public_stats':
                if (isset($params['media_id'])) {
                    return sprintf('%s/sml/stats/%d', $base_url, $params['media_id']);
                }
                break;
        }
        
        return false;
    }
    
    /**
     * Générer une URL de lien sécurisé complète avec signature
     */
    public static function generate_signed_url($media_id, $format_id, $link_hash, $expires, $signature, $key_pair_id) {
        $base_url = self::get_endpoint_url('secure_media', array(
            'media_id' => $media_id,
            'format_id' => $format_id,
            'link_hash' => $link_hash
        ));
        
        if (!$base_url) {
            return false;
        }
        
        $query_params = array(
            'Expires' => $expires,
            'Signature' => $signature,
            'Key-Pair-Id' => $key_pair_id
        );
        
        return $base_url . '?' . http_build_query($query_params);
    }
    
    /**
     * Tester la connectivité des endpoints
     */
    public static function test_endpoints() {
        $results = array();
        
        // Tester le health check
        $health_url = self::get_endpoint_url('health_check');
        $response = wp_remote_get($health_url, array('timeout' => 10));
        
        $results['health_check'] = array(
            'url' => $health_url,
            'status' => !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 'error',
            'working' => !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
        );
        
        // Tester l'API REST
        $api_url = rest_url('sml/v1/info');
        $response = wp_remote_get($api_url, array('timeout' => 10));
        
        $results['api_rest'] = array(
            'url' => $api_url,
            'status' => !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 'error',
            'working' => !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
        );
        
        return $results;
    }
    
    /**
     * Nettoyer les règles de réécriture lors de la désactivation
     */
    public static function cleanup_rewrite_rules() {
        delete_option('sml_rewrite_version');
        flush_rewrite_rules();
    }
}