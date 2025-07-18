<?php
/**
 * Classe pour la gestion du frontend
 * frontend/class-sml-frontend.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Frontend {
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('init', array(__CLASS__, 'handle_secure_links'));
        add_action('template_redirect', array(__CLASS__, 'handle_media_requests'));
        
        // AJAX pour les utilisateurs connectés et non connectés
        add_action('wp_ajax_sml_get_media_links', array(__CLASS__, 'ajax_get_media_links'));
        add_action('wp_ajax_sml_load_more_user_media', array(__CLASS__, 'ajax_load_more_user_media'));
        add_action('wp_ajax_sml_get_download_url', array(__CLASS__, 'ajax_get_download_url'));
        add_action('wp_ajax_sml_get_media_links_for_copy', array(__CLASS__, 'ajax_get_media_links_for_copy'));
        add_action('wp_ajax_sml_get_media_chart_data', array(__CLASS__, 'ajax_get_media_chart_data'));
        
        add_action('wp_ajax_nopriv_sml_get_download_url', array(__CLASS__, 'ajax_get_download_url'));
        
        // Shortcodes
        add_action('init', array(__CLASS__, 'init_shortcodes'));
        
        // Rewrite rules pour les liens sécurisés
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        
        // Headers de sécurité
        add_action('send_headers', array(__CLASS__, 'add_security_headers'));
    }
    
    /**
     * Enregistrer les scripts et styles frontend
     */
    public static function enqueue_scripts() {
        wp_enqueue_script('sml-frontend', SML_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SML_PLUGIN_VERSION, true);
        wp_enqueue_style('sml-frontend', SML_PLUGIN_URL . 'assets/css/frontend.css', array(), SML_PLUGIN_VERSION);
        
        // Chart.js pour les graphiques si disponible
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        wp_localize_script('sml-frontend', 'sml_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sml_nonce'),
            'strings' => array(
                'loading' => __('Chargement...', 'secure-media-link'),
                'error' => __('Une erreur est survenue', 'secure-media-link'),
                'copied' => __('Copié !', 'secure-media-link'),
                'download_started' => __('Téléchargement démarré', 'secure-media-link'),
                'link_expired' => __('Ce lien a expiré', 'secure-media-link'),
                'access_denied' => __('Accès refusé', 'secure-media-link')
            )
        ));
    }
    
    /**
     * Initialiser les shortcodes
     */
    public static function init_shortcodes() {
        // Les shortcodes sont déjà définis dans class-sml-shortcodes.php
        // On ajoute ici la logique AJAX pour les supporter
    }
    
    /**
     * Ajouter les règles de réécriture pour les liens sécurisés
     */
    public static function add_rewrite_rules() {
        add_rewrite_rule(
            '^sml/media/([0-9]+)/([0-9]+)/([a-f0-9]+)/?$',
            'index.php?sml_media_id=$matches[1]&sml_format_id=$matches[2]&sml_hash=$matches[3]',
            'top'
        );
        
        add_rewrite_rule(
            '^sml/download/([a-f0-9]+)/?$',
            'index.php?sml_download=$matches[1]',
            'top'
        );
    }
    
    /**
     * Ajouter les variables de requête
     */
    public static function add_query_vars($vars) {
        $vars[] = 'sml_media_id';
        $vars[] = 'sml_format_id';
        $vars[] = 'sml_hash';
        $vars[] = 'sml_download';
        return $vars;
    }
    
    /**
     * Gérer les demandes de liens sécurisés
     */
    public static function handle_secure_links() {
        // Cette fonction sera appelée par handle_media_requests
    }
    
    /**
     * Gérer les requêtes de médias
     */
    public static function handle_media_requests() {
        global $wp_query;
        
        // Vérifier si c'est une requête de lien sécurisé
        if (get_query_var('sml_media_id') || get_query_var('sml_download')) {
            self::serve_secure_media();
            exit;
        }
    }
    
    /**
     * Servir un média sécurisé
     */
    private static function serve_secure_media() {
        $media_id = get_query_var('sml_media_id');
        $format_id = get_query_var('sml_format_id');
        $hash = get_query_var('sml_hash');
        $download_hash = get_query_var('sml_download');
        
        // Gérer les deux types d'URLs
        if ($download_hash) {
            // URL courte de type /sml/download/hash
            $link_data = self::get_link_by_hash($download_hash);
            if (!$link_data) {
                self::send_error_response(404, __('Lien introuvable', 'secure-media-link'));
                return;
            }
            $media_id = $link_data->media_id;
            $format_id = $link_data->format_id;
            $hash = $link_data->link_hash;
        }
        
        if (!$media_id || !$format_id || !$hash) {
            self::send_error_response(400, __('Paramètres manquants', 'secure-media-link'));
            return;
        }
        
        // Vérifier les paramètres de signature dans l'URL
        $signature = isset($_GET['Signature']) ? $_GET['Signature'] : '';
        $expires = isset($_GET['Expires']) ? intval($_GET['Expires']) : 0;
        $key_pair_id = isset($_GET['Key-Pair-Id']) ? $_GET['Key-Pair-Id'] : '';
        
        if (!$signature || !$expires || !$key_pair_id) {
            self::send_error_response(400, __('Paramètres de signature manquants', 'secure-media-link'));
            return;
        }
        
        // Vérifier la signature et les permissions
        $verification = SML_Crypto::verify_secure_link($hash, $signature, $expires, $key_pair_id);
        
        if (!$verification['valid']) {
            // Tracker la tentative non autorisée
            if (isset($verification['link'])) {
                SML_Tracking::track_usage($verification['link']->id, 'download', array(
                    'error' => $verification['error']
                ));
            }
            
            self::send_error_response(403, self::get_error_message($verification['error']));
            return;
        }
        
        $link = $verification['link'];
        
        // Vérifier les permissions IP/domaine
        $ip_address = self::get_client_ip();
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : '';
        
        $permission_check = SML_Permissions::check_permissions($ip_address, $domain, 'download');
        
        if (!$permission_check['authorized']) {
            // Tracker la violation
            SML_Tracking::track_usage($link->id, 'download');
            
            self::send_error_response(403, __('Accès refusé par les règles de sécurité', 'secure-media-link'));
            return;
        }
        
        // Générer le fichier dans le format demandé
        $file_path = self::get_formatted_media_file($media_id, $format_id);
        
        if (!$file_path || !file_exists($file_path)) {
            self::send_error_response(404, __('Fichier introuvable', 'secure-media-link'));
            return;
        }
        
        // Tracker l'utilisation autorisée
        SML_Tracking::track_usage($link->id, 'download');
        
        // Servir le fichier
        self::serve_file($file_path, $media_id, $format_id);
    }
    
    /**
     * Obtenir un lien par son hash court
     */
    private static function get_link_by_hash($hash) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_secure_links 
             WHERE link_hash = %s AND is_active = 1 AND expires_at > NOW()",
            $hash
        ));
    }
    
    /**
     * Obtenir le fichier formaté
     */
    private static function get_formatted_media_file($media_id, $format_id) {
        // Si format_id = 0, utiliser le fichier original
        if ($format_id == 0) {
            return get_attached_file($media_id);
        }
        
        // Sinon, générer ou récupérer le fichier formaté
        return SML_Media_Formats::generate_media_format($media_id, $format_id);
    }
    
    /**
     * Servir un fichier avec les headers appropriés
     */
    private static function serve_file($file_path, $media_id, $format_id) {
        $media = get_post($media_id);
        $format = SML_Media_Formats::get_format($format_id);
        
        $file_size = filesize($file_path);
        $file_name = basename($file_path);
        
        // Générer un nom de fichier convivial
        if ($media && $format) {
            $clean_title = sanitize_file_name($media->post_title);
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);
            $file_name = $clean_title . '_' . $format->name . '.' . $extension;
        }
        
        // Headers de sécurité et de cache
        header('Content-Type: ' . $media->post_mime_type);
        header('Content-Length: ' . $file_size);
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Headers anti-hotlinking
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        
        // Nettoyer le buffer de sortie
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Servir le fichier par chunks pour optimiser la mémoire
        $chunk_size = 8192;
        $handle = fopen($file_path, 'rb');
        
        if ($handle === false) {
            self::send_error_response(500, __('Erreur lors de la lecture du fichier', 'secure-media-link'));
            return;
        }
        
        while (!feof($handle)) {
            echo fread($handle, $chunk_size);
            flush();
        }
        
        fclose($handle);
    }
    
    /**
     * Obtenir l'IP du client
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Envoyer une réponse d'erreur
     */
    private static function send_error_response($code, $message) {
        status_header($code);
        
        // Réponse JSON pour les requêtes AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json_error($message);
            return;
        }
        
        // Page d'erreur simple pour les requêtes directes
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>' . __('Erreur', 'secure-media-link') . '</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { color: #d63384; font-size: 18px; }
                .code { color: #6c757d; font-size: 14px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <h1>' . __('Accès refusé', 'secure-media-link') . '</h1>
            <p class="error">' . esc_html($message) . '</p>
            <p class="code">Code: ' . $code . '</p>
        </body>
        </html>';
        
        echo $html;
    }
    
    /**
     * Obtenir le message d'erreur approprié
     */
    private static function get_error_message($error_code) {
        switch ($error_code) {
            case 'expired':
                return __('Ce lien a expiré', 'secure-media-link');
            case 'invalid_signature':
                return __('Signature invalide', 'secure-media-link');
            case 'invalid_key_pair':
                return __('Clé de signature invalide', 'secure-media-link');
            case 'link_not_found':
                return __('Lien introuvable', 'secure-media-link');
            case 'link_expired':
                return __('Lien expiré', 'secure-media-link');
            default:
                return __('Accès refusé', 'secure-media-link');
        }
    }
    
    /**
     * Ajouter des headers de sécurité
     */
    public static function add_security_headers() {
        if (get_query_var('sml_media_id') || get_query_var('sml_download')) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * AJAX - Obtenir les liens d'un média
     */
    public static function ajax_get_media_links() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Vous devez être connecté', 'secure-media-link'));
        }
        
        $media_id = intval($_POST['media_id']);
        
        if (!$media_id) {
            wp_send_json_error(__('ID média requis', 'secure-media-link'));
        }
        
        // Vérifier que l'utilisateur peut voir ce média
        $media = get_post($media_id);
        if (!$media || ($media->post_author != get_current_user_id() && !current_user_can('manage_options'))) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        global $wpdb;
        
        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.*, mf.name as format_name, mf.type as format_type
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             WHERE sl.media_id = %d
             ORDER BY sl.created_at DESC",
            $media_id
        ));
        
        ob_start();
        ?>
        <div class="sml-media-links-list">
            <h3><?php echo esc_html($media->post_title); ?></h3>
            
            <?php if (!empty($links)): ?>
                <div class="sml-links-table">
                    <?php foreach ($links as $link): ?>
                        <?php
                        $is_expired = strtotime($link->expires_at) < time();
                        $is_active = $link->is_active && !$is_expired;
                        $secure_url = SML_Crypto::generate_secure_link($media_id, $link->format_id, $link->expires_at);
                        ?>
                        <div class="sml-link-item <?php echo $is_active ? 'active' : 'inactive'; ?>">
                            <div class="sml-link-info">
                                <strong><?php echo esc_html($link->format_name); ?></strong>
                                <span class="sml-format-type">(<?php echo esc_html($link->format_type); ?>)</span>
                                <div class="sml-link-meta">
                                    <span class="sml-expiry">
                                        <?php _e('Expire le:', 'secure-media-link'); ?> 
                                        <?php echo date_i18n(get_option('date_format'), strtotime($link->expires_at)); ?>
                                        <?php if ($is_expired): ?>
                                            <span class="sml-expired"><?php _e('(Expiré)', 'secure-media-link'); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="sml-usage">
                                        <?php printf(__('%d téléchargements, %d copies', 'secure-media-link'), $link->download_count, $link->copy_count); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($is_active && $secure_url): ?>
                                <div class="sml-link-actions">
                                    <input type="text" class="sml-link-url" value="<?php echo esc_attr($secure_url); ?>" readonly>
                                    <button class="sml-btn sml-btn-small sml-copy-link" data-url="<?php echo esc_attr($secure_url); ?>">
                                        <?php _e('Copier', 'secure-media-link'); ?>
                                    </button>
                                    <a href="<?php echo esc_url($secure_url); ?>" class="sml-btn sml-btn-small sml-btn-primary" download>
                                        <?php _e('Télécharger', 'secure-media-link'); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="sml-link-actions">
                                    <span class="sml-link-inactive"><?php _e('Lien inactif', 'secure-media-link'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="sml-no-links"><?php _e('Aucun lien généré pour ce média.', 'secure-media-link'); ?></p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.sml-copy-link').on('click', function() {
                var $btn = $(this);
                var url = $btn.data('url');
                var $input = $btn.siblings('.sml-link-url');
                
                $input.select();
                document.execCommand('copy');
                
                var originalText = $btn.text();
                $btn.text('<?php _e('Copié !', 'secure-media-link'); ?>');
                
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            });
        });
        </script>
        <?php
        
        wp_send_json_success(ob_get_clean());
    }
    
    /**
     * AJAX - Charger plus de médias utilisateur
     */
    public static function ajax_load_more_user_media() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Vous devez être connecté', 'secure-media-link'));
        }
        
        $user_id = intval($_POST['user_id']);
        $offset = intval($_POST['offset']);
        $limit = intval($_POST['limit']);
        
        if ($user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        global $wpdb;
        
        $media_items = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, fu.status as upload_status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}sml_frontend_uploads fu ON p.ID = fu.media_id
             WHERE p.post_author = %d AND p.post_type = 'attachment'
             ORDER BY p.post_date DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
        
        ob_start();
        
        foreach ($media_items as $media) {
            ?>
            <div class="sml-media-item" data-media-id="<?php echo $media->ID; ?>">
                <div class="sml-media-thumbnail">
                    <?php echo wp_get_attachment_image($media->ID, 'medium'); ?>
                </div>
                
                <div class="sml-media-info">
                    <h4><?php echo esc_html($media->post_title); ?></h4>
                    
                    <div class="sml-media-meta">
                        <span class="sml-upload-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($media->post_date)); ?>
                        </span>
                        
                        <?php if ($media->upload_status === 'pending'): ?>
                            <span class="sml-status sml-pending"><?php _e('En attente', 'secure-media-link'); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="sml-media-actions">
                        <button class="sml-btn sml-btn-small sml-view-links" data-media-id="<?php echo $media->ID; ?>">
                            <?php _e('Voir les liens', 'secure-media-link'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php
        }
        
        $content = ob_get_clean();
        $has_more = count($media_items) >= $limit;
        
        wp_send_json_success(array(
            'content' => $content,
            'has_more' => $has_more
        ));
    }
    
    /**
     * AJAX - Obtenir l'URL de téléchargement
     */
    public static function ajax_get_download_url() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        $link_id = intval($_POST['link_id']);
        
        if (!$link_id) {
            wp_send_json_error(__('ID lien requis', 'secure-media-link'));
        }
        
        global $wpdb;
        
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_secure_links 
             WHERE id = %d AND is_active = 1 AND expires_at > NOW()",
            $link_id
        ));
        
        if (!$link) {
            wp_send_json_error(__('Lien introuvable ou expiré', 'secure-media-link'));
        }
        
        $secure_url = SML_Crypto::generate_secure_link($link->media_id, $link->format_id, $link->expires_at);
        
        if (!$secure_url) {
            wp_send_json_error(__('Erreur lors de la génération de l\'URL', 'secure-media-link'));
        }
        
        wp_send_json_success(array('url' => $secure_url));
    }
    
    /**
     * AJAX - Obtenir les liens d'un média pour copie
     */
    public static function ajax_get_media_links_for_copy() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        $media_id = intval($_POST['media_id']);
        
        if (!$media_id) {
            wp_send_json_error(__('ID média requis', 'secure-media-link'));
        }
        
        global $wpdb;
        
        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.*, mf.name as format_name
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             WHERE sl.media_id = %d AND sl.is_active = 1 AND sl.expires_at > NOW()
             ORDER BY mf.name",
            $media_id
        ));
        
        $urls = array();
        $link_ids = array();
        
        foreach ($links as $link) {
            $secure_url = SML_Crypto::generate_secure_link($link->media_id, $link->format_id, $link->expires_at);
            if ($secure_url) {
                $urls[] = $link->format_name . ': ' . $secure_url;
                $link_ids[] = $link->id;
            }
        }
        
        wp_send_json_success(array(
            'links' => $urls,
            'link_ids' => $link_ids
        ));
    }
    
    /**
     * AJAX - Obtenir les données de graphique pour un média
     */
    public static function ajax_get_media_chart_data() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        $media_id = intval($_POST['media_id']);
        $period = sanitize_text_field($_POST['period']);
        
        if (!$media_id) {
            wp_send_json_error(__('ID média requis', 'secure-media-link'));
        }
        
        global $wpdb;
        
        // Déterminer la période
        switch ($period) {
            case 'week':
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            default:
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(t.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        $chart_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(t.created_at, '{$date_format}') as date,
                    SUM(CASE WHEN t.action_type = 'download' AND t.is_authorized = 1 THEN 1 ELSE 0 END) as downloads,
                    SUM(CASE WHEN t.action_type = 'copy' AND t.is_authorized = 1 THEN 1 ELSE 0 END) as copies
             FROM {$wpdb->prefix}sml_tracking t
             LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
             WHERE sl.media_id = %d AND {$date_condition}
             GROUP BY DATE_FORMAT(t.created_at, '{$date_format}')
             ORDER BY date",
            $media_id
        ));
        
        $labels = array();
        $downloads = array();
        $copies = array();
        
        foreach ($chart_data as $data) {
            $labels[] = date_i18n(get_option('date_format'), strtotime($data->date));
            $downloads[] = intval($data->downloads);
            $copies[] = intval($data->copies);
        }
        
        wp_send_json_success(array(
            'labels' => $labels,
            'downloads' => $downloads,
            'copies' => $copies
        ));
    }
}