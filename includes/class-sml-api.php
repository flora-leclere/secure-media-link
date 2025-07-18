<?php
/**
 * Classe pour l'API REST
 * includes/class-sml-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_API {
    
    /**
     * Namespace de l'API
     */
    const API_NAMESPACE = 'sml/v1';
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_filter('rest_authentication_errors', array(__CLASS__, 'authenticate_request'));
        add_action('rest_api_init', array(__CLASS__, 'add_cors_headers'));
    }
    
    /**
     * Enregistrer les routes de l'API
     */
    public static function register_routes() {
        // Route pour obtenir les médias
        register_rest_route(self::API_NAMESPACE, '/media', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_media'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint',
                ),
                'author' => array(
                    'sanitize_callback' => 'absint',
                ),
                'search' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Route pour obtenir un média spécifique
        register_rest_route(self::API_NAMESPACE, '/media/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_media_item'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Route pour générer un lien sécurisé
        register_rest_route(self::API_NAMESPACE, '/links', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_secure_link'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'media_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'format_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'expires_at' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Route pour obtenir les liens d'un média
        register_rest_route(self::API_NAMESPACE, '/media/(?P<id>\d+)/links', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_media_links'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Route pour obtenir un lien spécifique
        register_rest_route(self::API_NAMESPACE, '/links/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_link'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Route pour supprimer un lien
        register_rest_route(self::API_NAMESPACE, '/links/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array(__CLASS__, 'delete_link'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Route pour obtenir les formats
        register_rest_route(self::API_NAMESPACE, '/formats', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_formats'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
        ));
        
        // Route pour les statistiques
        register_rest_route(self::API_NAMESPACE, '/stats', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_statistics'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'period' => array(
                    'default' => 'month',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Route pour le tracking
        register_rest_route(self::API_NAMESPACE, '/track', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'track_usage'),
            'permission_callback' => '__return_true', // Public pour le tracking
            'args' => array(
                'link_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'action_type' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Route pour les permissions
        register_rest_route(self::API_NAMESPACE, '/permissions', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_permissions'),
                'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'create_permission'),
                'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
                'args' => array(
                    'type' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'value' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'permission_type' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'actions' => array(
                        'required' => true,
                        'sanitize_callback' => array(__CLASS__, 'sanitize_actions_array'),
                    ),
                    'description' => array(
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ),
                ),
            ),
        ));
        
        // Route pour vérifier les permissions
        register_rest_route(self::API_NAMESPACE, '/permissions/check', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'check_permissions'),
            'permission_callback' => '__return_true', // Public pour les vérifications
            'args' => array(
                'ip_address' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'domain' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'action' => array(
                    'default' => 'download',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Route pour les notifications
        register_rest_route(self::API_NAMESPACE, '/notifications', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_notifications'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'limit' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                ),
                'unread_only' => array(
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));
        
        // Route pour marquer une notification comme lue
        register_rest_route(self::API_NAMESPACE, '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'mark_notification_read'),
            'permission_callback' => array(__CLASS__, 'check_api_permissions'),
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
    
    /**
     * Ajouter les headers CORS
     */
    public static function add_cors_headers() {
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            $settings = get_option('sml_settings', array());
            
            if (isset($settings['api_enabled']) && $settings['api_enabled']) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
                header('Access-Control-Max-Age: 86400');
            }
            
            return $served;
        }, 10, 4);
    }
    
    /**
     * Vérifier les permissions API
     */
    public static function check_api_permissions($request) {
        $settings = get_option('sml_settings', array());
        
        // Vérifier si l'API est activée
        if (!isset($settings['api_enabled']) || !$settings['api_enabled']) {
            return new WP_Error('api_disabled', __('API désactivée', 'secure-media-link'), array('status' => 403));
        }
        
        // Vérifier le rate limiting
        if (!self::check_rate_limit()) {
            return new WP_Error('rate_limit_exceeded', __('Limite de taux dépassée', 'secure-media-link'), array('status' => 429));
        }
        
        // Pour les requêtes authentifiées, vérifier les permissions
        $user = wp_get_current_user();
        if ($user && $user->ID > 0) {
            return user_can($user, 'edit_posts');
        }
        
        // Pour les requêtes non authentifiées, vérifier les tokens API
        return self::validate_api_token($request);
    }
    
    /**
     * Vérifier les permissions administrateur
     */
    public static function check_admin_permissions($request) {
        if (!self::check_api_permissions($request)) {
            return false;
        }
        
        return current_user_can('manage_options');
    }
    
    /**
     * Authentifier les requêtes
     */
    public static function authenticate_request($result) {
        if (!empty($result)) {
            return $result;
        }
        
        // Vérifier le token dans les headers
        $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        
        if (!$auth_header) {
            return $result;
        }
        
        if (strpos($auth_header, 'Bearer ') === 0) {
            $token = substr($auth_header, 7);
            $token_data = SML_Crypto::verify_api_token($token);
            
            if ($token_data) {
                wp_set_current_user($token_data['user_id']);
                return true;
            }
        }
        
        return $result;
    }
    
    /**
     * Valider le token API
     */
    private static function validate_api_token($request) {
        $token = $request->get_header('authorization');
        
        if (!$token) {
            $token = $request->get_param('token');
        }
        
        if (!$token) {
            return new WP_Error('missing_token', __('Token API requis', 'secure-media-link'), array('status' => 401));
        }
        
        // Supprimer le préfixe "Bearer " si présent
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        $token_data = SML_Crypto::verify_api_token($token);
        
        if (!$token_data) {
            return new WP_Error('invalid_token', __('Token API invalide', 'secure-media-link'), array('status' => 401));
        }
        
        // Définir l'utilisateur actuel
        wp_set_current_user($token_data['user_id']);
        
        return true;
    }
    
    /**
     * Vérifier le rate limiting
     */
    private static function check_rate_limit() {
        $settings = get_option('sml_settings', array());
        $rate_limit = isset($settings['api_rate_limit']) ? $settings['api_rate_limit'] : 1000;
        
        $ip = self::get_client_ip();
        $cache_key = 'sml_api_rate_limit_' . md5($ip);
        
        $current_count = get_transient($cache_key);
        
        if ($current_count === false) {
            set_transient($cache_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($current_count >= $rate_limit) {
            return false;
        }
        
        set_transient($cache_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
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
        
        return '0.0.0.0';
    }
    
    /**
     * Nettoyer un tableau d'actions
     */
    public static function sanitize_actions_array($actions) {
        if (!is_array($actions)) {
            return array();
        }
        
        $valid_actions = array('download', 'copy', 'view');
        return array_intersect($actions, $valid_actions);
    }
    
    /**
     * API - Obtenir les médias
     */
    public static function get_media($request) {
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Limite à 100
        $author = $request->get_param('author');
        $search = $request->get_param('search');
        
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
        );
        
        if ($author) {
            $args['author'] = $author;
        }
        
        if ($search) {
            $args['s'] = $search;
        }
        
        // Si pas admin, limiter aux médias de l'utilisateur actuel
        if (!current_user_can('manage_options')) {
            $args['author'] = get_current_user_id();
        }
        
        $query = new WP_Query($args);
        
        $media_items = array();
        
        foreach ($query->posts as $post) {
            $media_items[] = self::format_media_response($post);
        }
        
        return rest_ensure_response(array(
            'media' => $media_items,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ));
    }
    
    /**
     * API - Obtenir un média spécifique
     */
    public static function get_media_item($request) {
        $media_id = $request->get_param('id');
        $media = get_post($media_id);
        
        if (!$media || $media->post_type !== 'attachment') {
            return new WP_Error('media_not_found', __('Média introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options') && $media->post_author != get_current_user_id()) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
        }
        
        return rest_ensure_response(self::format_media_response($media));
    }
    
    /**
     * API - Créer un lien sécurisé
     */
    public static function create_secure_link($request) {
        $media_id = $request->get_param('media_id');
        $format_id = $request->get_param('format_id');
        $expires_at = $request->get_param('expires_at');
        
        // Vérifier que le média existe
        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            return new WP_Error('media_not_found', __('Média introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options') && $media->post_author != get_current_user_id()) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
        }
        
        // Vérifier que le format existe
        $format = SML_Media_Formats::get_format($format_id);
        if (!$format) {
            return new WP_Error('format_not_found', __('Format introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Générer le lien
        $secure_url = SML_Crypto::generate_secure_link($media_id, $format_id, $expires_at);
        
        if (!$secure_url) {
            return new WP_Error('link_generation_failed', __('Erreur lors de la génération du lien', 'secure-media-link'), array('status' => 500));
        }
        
        // Récupérer les informations du lien créé
        global $wpdb;
        $link_id = $wpdb->insert_id;
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_secure_links WHERE id = %d",
            $link_id
        ));
        
        return rest_ensure_response(array(
            'link_id' => $link_id,
            'secure_url' => $secure_url,
            'expires_at' => $link->expires_at,
            'media_id' => $media_id,
            'format_id' => $format_id,
            'format_name' => $format->name,
        ));
    }
    
    /**
     * API - Obtenir les liens d'un média
     */
    public static function get_media_links($request) {
        $media_id = $request->get_param('id');
        
        // Vérifier que le média existe
        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            return new WP_Error('media_not_found', __('Média introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options') && $media->post_author != get_current_user_id()) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
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
        
        $formatted_links = array();
        
        foreach ($links as $link) {
            $is_expired = strtotime($link->expires_at) < time();
            $is_active = $link->is_active && !$is_expired;
            
            $secure_url = null;
            if ($is_active) {
                $secure_url = SML_Crypto::generate_secure_link($media_id, $link->format_id, $link->expires_at);
            }
            
            $formatted_links[] = array(
                'id' => $link->id,
                'format_id' => $link->format_id,
                'format_name' => $link->format_name,
                'format_type' => $link->format_type,
                'secure_url' => $secure_url,
                'is_active' => $is_active,
                'is_expired' => $is_expired,
                'expires_at' => $link->expires_at,
                'download_count' => $link->download_count,
                'copy_count' => $link->copy_count,
                'created_at' => $link->created_at,
            );
        }
        
        return rest_ensure_response(array(
            'media_id' => $media_id,
            'media_title' => $media->post_title,
            'links' => $formatted_links,
        ));
    }
    
    /**
     * API - Obtenir un lien spécifique
     */
    public static function get_link($request) {
        $link_id = $request->get_param('id');
        
        global $wpdb;
        
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT sl.*, mf.name as format_name, mf.type as format_type, p.post_title as media_title
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             WHERE sl.id = %d",
            $link_id
        ));
        
        if (!$link) {
            return new WP_Error('link_not_found', __('Lien introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Vérifier les permissions
        $media = get_post($link->media_id);
        if (!current_user_can('manage_options') && $media->post_author != get_current_user_id()) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
        }
        
        $is_expired = strtotime($link->expires_at) < time();
        $is_active = $link->is_active && !$is_expired;
        
        $secure_url = null;
        if ($is_active) {
            $secure_url = SML_Crypto::generate_secure_link($link->media_id, $link->format_id, $link->expires_at);
        }
        
        return rest_ensure_response(array(
            'id' => $link->id,
            'media_id' => $link->media_id,
            'media_title' => $link->media_title,
            'format_id' => $link->format_id,
            'format_name' => $link->format_name,
            'format_type' => $link->format_type,
            'secure_url' => $secure_url,
            'is_active' => $is_active,
            'is_expired' => $is_expired,
            'expires_at' => $link->expires_at,
            'download_count' => $link->download_count,
            'copy_count' => $link->copy_count,
            'created_at' => $link->created_at,
        ));
    }
    
    /**
     * API - Supprimer un lien
     */
    public static function delete_link($request) {
        $link_id = $request->get_param('id');
        
        global $wpdb;
        
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT sl.*, p.post_author
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             WHERE sl.id = %d",
            $link_id
        ));
        
        if (!$link) {
            return new WP_Error('link_not_found', __('Lien introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options') && $link->post_author != get_current_user_id()) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'sml_secure_links',
            array('id' => $link_id)
        );
        
        if ($result === false) {
            return new WP_Error('deletion_failed', __('Erreur lors de la suppression', 'secure-media-link'), array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'deleted' => true,
            'id' => $link_id,
        ));
    }
    
    /**
     * API - Obtenir les formats
     */
    public static function get_formats($request) {
        $formats = SML_Media_Formats::get_all_formats();
        
        $formatted_formats = array();
        
        foreach ($formats as $format) {
            $formatted_formats[] = array(
                'id' => $format->id,
                'name' => $format->name,
                'description' => $format->description,
                'type' => $format->type,
                'width' => $format->width,
                'height' => $format->height,
                'quality' => $format->quality,
                'format' => $format->format,
                'crop_mode' => $format->crop_mode,
                'crop_position' => $format->crop_position,
            );
        }
        
        return rest_ensure_response($formatted_formats);
    }
    
    /**
     * API - Obtenir les statistiques
     */
    public static function get_statistics($request) {
        $period = $request->get_param('period');
        
        $stats = SML_Tracking::get_global_statistics($period);
        
        return rest_ensure_response($stats);
    }
    
    /**
     * API - Tracker l'utilisation
     */
    public static function track_usage($request) {
        $link_id = $request->get_param('link_id');
        $action_type = $request->get_param('action_type');
        
        $tracking_id = SML_Tracking::track_usage($link_id, $action_type);
        
        if ($tracking_id) {
            return rest_ensure_response(array(
                'tracked' => true,
                'tracking_id' => $tracking_id,
            ));
        } else {
            return new WP_Error('tracking_failed', __('Erreur lors du tracking', 'secure-media-link'), array('status' => 500));
        }
    }
    
    /**
     * API - Obtenir les permissions
     */
    public static function get_permissions($request) {
        $permissions = SML_Permissions::get_all_permissions();
        
        $formatted_permissions = array();
        
        foreach ($permissions as $permission) {
            $formatted_permissions[] = array(
                'id' => $permission->id,
                'type' => $permission->type,
                'value' => $permission->value,
                'permission_type' => $permission->permission_type,
                'actions' => json_decode($permission->actions, true),
                'description' => $permission->description,
                'is_active' => (bool) $permission->is_active,
                'created_at' => $permission->created_at,
            );
        }
        
        return rest_ensure_response($formatted_permissions);
    }
    
    /**
     * API - Créer une permission
     */
    public static function create_permission($request) {
        $data = array(
            'type' => $request->get_param('type'),
            'value' => $request->get_param('value'),
            'permission_type' => $request->get_param('permission_type'),
            'actions' => $request->get_param('actions'),
            'description' => $request->get_param('description'),
            'is_active' => 1,
        );
        
        $result = SML_Permissions::add_permission($data);
        
        if ($result['success']) {
            return rest_ensure_response(array(
                'created' => true,
                'permission_id' => $result['permission_id'],
            ));
        } else {
            return new WP_Error('creation_failed', $result['message'], array('status' => 400));
        }
    }
    
    /**
     * API - Vérifier les permissions
     */
    public static function check_permissions($request) {
        $ip_address = $request->get_param('ip_address');
        $domain = $request->get_param('domain');
        $action = $request->get_param('action');
        
        // Utiliser l'IP de la requête si non fournie
        if (!$ip_address) {
            $ip_address = self::get_client_ip();
        }
        
        // Utiliser le referer si domaine non fourni
        if (!$domain) {
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $domain = $referer ? parse_url($referer, PHP_URL_HOST) : '';
        }
        
        $permission_check = SML_Permissions::check_permissions($ip_address, $domain, $action);
        
        return rest_ensure_response(array(
            'ip_address' => $ip_address,
            'domain' => $domain,
            'action' => $action,
            'authorized' => $permission_check['authorized'],
            'violation_type' => $permission_check['violation_type'],
            'ip_check' => $permission_check['ip_check'],
            'domain_check' => $permission_check['domain_check'],
        ));
    }
    
    /**
     * API - Obtenir les notifications
     */
    public static function get_notifications($request) {
        $limit = $request->get_param('limit');
        $unread_only = $request->get_param('unread_only');
        
        $notifications = SML_Notifications::get_notifications(null, $limit, $unread_only);
        $unread_count = SML_Notifications::get_unread_count();
        
        $formatted_notifications = array();
        
        foreach ($notifications as $notification) {
            $formatted_notifications[] = array(
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => json_decode($notification->data, true),
                'is_read' => (bool) $notification->is_read,
                'priority' => $notification->priority,
                'created_at' => $notification->created_at,
                'formatted_date' => date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($notification->created_at)
                ),
            );
        }
        
        return rest_ensure_response(array(
            'notifications' => $formatted_notifications,
            'unread_count' => $unread_count,
            'total' => count($formatted_notifications),
        ));
    }
    
    /**
     * API - Marquer une notification comme lue
     */
    public static function mark_notification_read($request) {
        $notification_id = $request->get_param('id');
        
        $result = SML_Notifications::mark_as_read($notification_id);
        
        if ($result) {
            return rest_ensure_response(array(
                'marked_read' => true,
                'notification_id' => $notification_id,
            ));
        } else {
            return new WP_Error('mark_read_failed', __('Erreur lors de la mise à jour', 'secure-media-link'), array('status' => 500));
        }
    }
    
    /**
     * Formater la réponse d'un média
     */
    private static function format_media_response($media) {
        $file_url = wp_get_attachment_url($media->ID);
        $file_path = get_attached_file($media->ID);
        $file_size = $file_path ? filesize($file_path) : 0;
        $metadata = wp_get_attachment_metadata($media->ID);
        
        // Compter les liens actifs
        global $wpdb;
        $active_links = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links 
             WHERE media_id = %d AND is_active = 1 AND expires_at > NOW()",
            $media->ID
        ));
        
        $total_downloads = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(download_count) FROM {$wpdb->prefix}sml_secure_links WHERE media_id = %d",
            $media->ID
        ));
        
        $response = array(
            'id' => $media->ID,
            'title' => $media->post_title,
            'description' => $media->post_content,
            'caption' => $media->post_excerpt,
            'mime_type' => $media->post_mime_type,
            'file_url' => $file_url,
            'file_size' => $file_size,
            'file_size_formatted' => size_format($file_size),
            'upload_date' => $media->post_date,
            'author_id' => $media->post_author,
            'active_links_count' => (int) $active_links,
            'total_downloads' => (int) $total_downloads,
        );
        
        // Ajouter les métadonnées d'image si disponibles
        if ($metadata && isset($metadata['width'])) {
            $response['width'] = $metadata['width'];
            $response['height'] = $metadata['height'];
            
            if (isset($metadata['sizes'])) {
                $response['thumbnails'] = array();
                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    $response['thumbnails'][$size_name] = array(
                        'url' => wp_get_attachment_image_url($media->ID, $size_name),
                        'width' => $size_data['width'],
                        'height' => $size_data['height'],
                    );
                }
            }
        }
        
        // Ajouter les métadonnées personnalisées
        $copyright = get_post_meta($media->ID, '_sml_copyright', true);
        if ($copyright) {
            $response['copyright'] = $copyright;
        }
        
        $expiry_date = get_post_meta($media->ID, '_sml_expiry_date', true);
        if ($expiry_date) {
            $response['expiry_date'] = $expiry_date;
        }
        
        return $response;
    }
    
    /**
     * Générer un token API pour un utilisateur
     */
    public static function generate_user_token($user_id, $permissions = array()) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('access_denied', __('Accès refusé', 'secure-media-link'), array('status' => 403));
        }
        
        $token = SML_Crypto::generate_api_token($user_id, $permissions);
        
        if ($token) {
            return rest_ensure_response(array(
                'token' => $token,
                'user_id' => $user_id,
                'permissions' => $permissions,
                'expires_at' => date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)), // 30 jours
            ));
        } else {
            return new WP_Error('token_generation_failed', __('Erreur lors de la génération du token', 'secure-media-link'), array('status' => 500));
        }
    }
    
    /**
     * Route pour générer un token
     */
    public static function register_token_route() {
        register_rest_route(self::API_NAMESPACE, '/token', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'create_api_token'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
            'args' => array(
                'user_id' => array(
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ),
                'permissions' => array(
                    'default' => array(),
                    'sanitize_callback' => array(__CLASS__, 'sanitize_permissions_array'),
                ),
            ),
        ));
    }
    
    /**
     * API - Créer un token API
     */
    public static function create_api_token($request) {
        $user_id = $request->get_param('user_id');
        $permissions = $request->get_param('permissions');
        
        // Vérifier que l'utilisateur existe
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('user_not_found', __('Utilisateur introuvable', 'secure-media-link'), array('status' => 404));
        }
        
        return self::generate_user_token($user_id, $permissions);
    }
    
    /**
     * Nettoyer un tableau de permissions
     */
    public static function sanitize_permissions_array($permissions) {
        if (!is_array($permissions)) {
            return array();
        }
        
        $valid_permissions = array('read', 'write', 'delete', 'admin');
        return array_intersect($permissions, $valid_permissions);
    }
    
    /**
     * Obtenir les informations de l'API
     */
    public static function get_api_info() {
        return array(
            'namespace' => self::API_NAMESPACE,
            'version' => '1.0',
            'endpoints' => array(
                'GET /media' => __('Obtenir la liste des médias', 'secure-media-link'),
                'GET /media/{id}' => __('Obtenir un média spécifique', 'secure-media-link'),
                'POST /links' => __('Créer un lien sécurisé', 'secure-media-link'),
                'GET /media/{id}/links' => __('Obtenir les liens d\'un média', 'secure-media-link'),
                'GET /links/{id}' => __('Obtenir un lien spécifique', 'secure-media-link'),
                'DELETE /links/{id}' => __('Supprimer un lien', 'secure-media-link'),
                'GET /formats' => __('Obtenir les formats disponibles', 'secure-media-link'),
                'GET /stats' => __('Obtenir les statistiques', 'secure-media-link'),
                'POST /track' => __('Tracker une utilisation', 'secure-media-link'),
                'GET /permissions' => __('Obtenir les permissions', 'secure-media-link'),
                'POST /permissions' => __('Créer une permission', 'secure-media-link'),
                'POST /permissions/check' => __('Vérifier les permissions', 'secure-media-link'),
                'GET /notifications' => __('Obtenir les notifications', 'secure-media-link'),
                'POST /notifications/{id}/read' => __('Marquer une notification comme lue', 'secure-media-link'),
                'POST /token' => __('Générer un token API', 'secure-media-link'),
            ),
            'authentication' => array(
                'methods' => array('Bearer Token', 'API Token'),
                'header' => 'Authorization: Bearer {token}',
                'parameter' => 'token={token}',
            ),
            'rate_limiting' => array(
                'enabled' => true,
                'limit' => get_option('sml_settings')['api_rate_limit'] ?? 1000,
                'window' => '1 hour',
            ),
        );
    }
    
    /**
     * Route pour les informations de l'API
     */
    public static function register_info_route() {
        register_rest_route(self::API_NAMESPACE, '/info', array(
            'methods' => 'GET',
            'callback' => function() {
                return rest_ensure_response(self::get_api_info());
            },
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Initialisation complète des routes
     */
    public static function init_all_routes() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('rest_api_init', array(__CLASS__, 'register_token_route'));
        add_action('rest_api_init', array(__CLASS__, 'register_info_route'));
    }
}