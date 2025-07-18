<?php
/**
 * Classe pour le tracking et les statistiques
 * includes/class-sml-tracking.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Tracking {
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_ajax_sml_track_usage', array(__CLASS__, 'ajax_track_usage'));
        add_action('wp_ajax_nopriv_sml_track_usage', array(__CLASS__, 'ajax_track_usage'));
        add_action('wp_ajax_sml_get_tracking_data', array(__CLASS__, 'ajax_get_tracking_data'));
        add_action('wp_ajax_sml_export_tracking', array(__CLASS__, 'ajax_export_tracking'));
        
        // Hooks pour les tâches automatiques
        add_action('sml_scan_external_usage', array(__CLASS__, 'scan_external_usage'));
        add_action('sml_generate_statistics', array(__CLASS__, 'generate_daily_statistics'));
    }
    
    /**
     * Enregistrer une utilisation
     */
    public static function track_usage($link_id, $action_type, $additional_data = array()) {
        global $wpdb;
        
        // Obtenir les informations de la requête
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referer_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $domain = $referer_url ? parse_url($referer_url, PHP_URL_HOST) : '';
        
        // Vérifier les permissions
        $permission_check = SML_Permissions::check_permissions($ip_address, $domain, $action_type);
        
        // Obtenir la géolocalisation
        $geo_data = SML_Permissions::get_ip_geolocation($ip_address);
        
        // Préparer les données de tracking
        $tracking_data = array(
            'link_id' => $link_id,
            'action_type' => $action_type,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'referer_url' => $referer_url,
            'domain' => SML_Permissions::clean_domain($domain),
            'is_authorized' => $permission_check['authorized'] ? 1 : 0,
            'violation_type' => $permission_check['violation_type']
        );
        
        // Ajouter les données de géolocalisation si disponibles
        if ($geo_data) {
            $tracking_data['country'] = $geo_data['country'];
            $tracking_data['city'] = $geo_data['city'];
            $tracking_data['latitude'] = $geo_data['latitude'];
            $tracking_data['longitude'] = $geo_data['longitude'];
        }
        
        // Fusionner avec les données additionnelles
        $tracking_data = array_merge($tracking_data, $additional_data);
        
        // Insérer en base
        $result = $wpdb->insert($wpdb->prefix . 'sml_tracking', $tracking_data);
        
        if ($result && $permission_check['authorized']) {
            // Mettre à jour les compteurs du lien
            if ($action_type === 'download') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}sml_secure_links 
                     SET download_count = download_count + 1 
                     WHERE id = %d",
                    $link_id
                ));
            } elseif ($action_type === 'copy') {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}sml_secure_links 
                     SET copy_count = copy_count + 1 
                     WHERE id = %d",
                    $link_id
                ));
            }
        }
        
        // Si c'est une violation, envoyer une notification
        if (!$permission_check['authorized']) {
            self::handle_violation($tracking_data, $permission_check);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Obtenir l'IP du client
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                
                // Gérer les IPs multiples (proxy)
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Gérer une violation de sécurité
     */
    private static function handle_violation($tracking_data, $permission_check) {
        // Enregistrer une notification
        $message = sprintf(
            __('Violation détectée: %s depuis %s (%s) - %s', 'secure-media-link'),
            $tracking_data['action_type'],
            $tracking_data['ip_address'],
            $tracking_data['domain'],
            $permission_check['violation_type']
        );
        
        SML_Notifications::add_notification(
            'security_violation',
            __('Violation de sécurité détectée', 'secure-media-link'),
            $message,
            array(
                'tracking_data' => $tracking_data,
                'permission_check' => $permission_check
            )
        );
        
        // Vérifier si des mesures automatiques doivent être prises
        self::check_auto_blocking($tracking_data);
    }
    
    /**
     * Vérifier si un blocage automatique doit être appliqué
     */
    private static function check_auto_blocking($tracking_data) {
        global $wpdb;
        
        $settings = get_option('sml_settings', array());
        $auto_blocking = isset($settings['auto_blocking_enabled']) ? $settings['auto_blocking_enabled'] : false;
        
        if (!$auto_blocking) {
            return;
        }
        
        $violation_threshold = isset($settings['auto_blocking_threshold']) ? $settings['auto_blocking_threshold'] : 10;
        $time_window = isset($settings['auto_blocking_time_window']) ? $settings['auto_blocking_time_window'] : 24;
        
        // Compter les violations récentes pour cette IP
        $recent_violations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
             WHERE ip_address = %s 
             AND is_authorized = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $tracking_data['ip_address'],
            $time_window
        ));
        
        if ($recent_violations >= $violation_threshold) {
            // Ajouter automatiquement à la blacklist
            $permission_data = array(
                'type' => 'ip',
                'value' => $tracking_data['ip_address'],
                'permission_type' => 'blacklist',
                'actions' => array('download', 'copy', 'view'),
                'description' => sprintf(
                    __('Bloqué automatiquement après %d violations en %d heures', 'secure-media-link'),
                    $recent_violations,
                    $time_window
                ),
                'is_active' => 1
            );
            
            SML_Permissions::add_permission($permission_data);
            
            // Notification
            SML_Notifications::add_notification(
                'auto_blocking_applied',
                __('Blocage automatique appliqué', 'secure-media-link'),
                sprintf(
                    __('L\'IP %s a été automatiquement bloquée après %d violations', 'secure-media-link'),
                    $tracking_data['ip_address'],
                    $recent_violations
                )
            );
        }
    }
    
    /**
     * Obtenir les données de tracking avec filtres
     */
    public static function get_tracking_data($filters = array(), $page = 1, $per_page = 50) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        // Filtres par date
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "t.created_at >= %s";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "t.created_at <= %s";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Filtre par type d'action
        if (!empty($filters['action_type'])) {
            $where_clauses[] = "t.action_type = %s";
            $params[] = $filters['action_type'];
        }
        
        // Filtre par statut d'autorisation
        if (isset($filters['is_authorized'])) {
            $where_clauses[] = "t.is_authorized = %d";
            $params[] = $filters['is_authorized'];
        }
        
        // Filtre par domaine
        if (!empty($filters['domain'])) {
            $where_clauses[] = "t.domain LIKE %s";
            $params[] = '%' . $filters['domain'] . '%';
        }
        
        // Filtre par IP
        if (!empty($filters['ip_address'])) {
            $where_clauses[] = "t.ip_address LIKE %s";
            $params[] = '%' . $filters['ip_address'] . '%';
        }
        
        // Filtre par média
        if (!empty($filters['media_id'])) {
            $where_clauses[] = "sl.media_id = %d";
            $params[] = $filters['media_id'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Compter le total
        $count_sql = "SELECT COUNT(*) 
                      FROM {$wpdb->prefix}sml_tracking t
                      LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
                      {$where_sql}";
        
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        
        $total = $wpdb->get_var($count_sql);
        
        // Récupérer les données
        $offset = ($page - 1) * $per_page;
        $data_sql = "SELECT t.*, sl.media_id, sl.format_id, p.post_title as media_title,
                            mf.name as format_name
                     FROM {$wpdb->prefix}sml_tracking t
                     LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
                     LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
                     LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
                     {$where_sql}
                     ORDER BY t.created_at DESC
                     LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $data_sql = $wpdb->prepare($data_sql, $params);
        $data = $wpdb->get_results($data_sql);
        
        return array(
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Générer des statistiques quotidiennes
     */
    public static function generate_daily_statistics($date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = current_time('Y-m-d');
        }
        
        $stats = array();
        
        // Statistiques générales
        $stats['total_requests'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s",
            $date
        ));
        
        $stats['authorized_requests'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s AND is_authorized = 1",
            $date
        ));
        
        $stats['unauthorized_requests'] = $stats['total_requests'] - $stats['authorized_requests'];
        
        // Répartition par action
        $actions_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s
             GROUP BY action_type",
            $date
        ));
        
        foreach ($actions_stats as $action_stat) {
            $stats['actions'][$action_stat->action_type] = $action_stat->count;
        }
        
        // Top domaines
        $top_domains = $wpdb->get_results($wpdb->prepare(
            "SELECT domain, COUNT(*) as count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s AND domain IS NOT NULL
             GROUP BY domain
             ORDER BY count DESC
             LIMIT 10",
            $date
        ));
        
        $stats['top_domains'] = $top_domains;
        
        // Top IPs
        $top_ips = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s
             GROUP BY ip_address
             ORDER BY count DESC
             LIMIT 10",
            $date
        ));
        
        $stats['top_ips'] = $top_ips;
        
        // Top médias
        $top_media = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.media_id, p.post_title, COUNT(*) as count
             FROM {$wpdb->prefix}sml_tracking t
             LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             WHERE DATE(t.created_at) = %s
             GROUP BY sl.media_id
             ORDER BY count DESC
             LIMIT 10",
            $date
        ));
        
        $stats['top_media'] = $top_media;
        
        // Répartition géographique
        $geo_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT country, COUNT(*) as count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE DATE(created_at) = %s AND country IS NOT NULL
             GROUP BY country
             ORDER BY count DESC
             LIMIT 20",
            $date
        ));
        
        $stats['countries'] = $geo_stats;
        
        // Sauvegarder les statistiques
        foreach ($stats as $stat_type => $stat_data) {
            if (is_array($stat_data)) {
                $stat_value = json_encode($stat_data);
            } else {
                $stat_value = $stat_data;
            }
            
            $wpdb->replace(
                $wpdb->prefix . 'sml_statistics',
                array(
                    'stat_type' => 'daily_tracking',
                    'stat_key' => $stat_type,
                    'stat_value' => $stat_value,
                    'period' => 'daily',
                    'date_recorded' => $date
                )
            );
        }
        
        return $stats;
    }
    
    /**
     * Scanner l'utilisation externe des médias
     */
    public static function scan_external_usage() {
        $settings = get_option('sml_settings', array());
        
        if (!isset($settings['external_scan_enabled']) || !$settings['external_scan_enabled']) {
            return;
        }
        
        // Obtenir tous les médias avec des liens actifs
        global $wpdb;
        
        $media_links = $wpdb->get_results(
            "SELECT DISTINCT sl.media_id, p.guid
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             WHERE sl.is_active = 1 
             AND sl.expires_at > NOW()
             AND p.post_type = 'attachment'"
        );
        
        foreach ($media_links as $media) {
            self::scan_media_usage($media->media_id, $media->guid);
        }
    }
    
    /**
     * Scanner l'utilisation d'un média spécifique
     */
    private static function scan_media_usage($media_id, $media_url) {
        // Utiliser Google Custom Search API ou d'autres services
        $search_queries = array(
            $media_url,
            basename($media_url),
            "site:* " . basename($media_url)
        );
        
        foreach ($search_queries as $query) {
            $results = self::search_media_usage($query);
            
            foreach ($results as $result) {
                self::process_external_usage_result($media_id, $result);
            }
        }
    }
    
    /**
     * Rechercher l'utilisation d'un média
     */
    private static function search_media_usage($query) {
        $settings = get_option('sml_settings', array());
        
        // Si Google Custom Search API est configuré
        if (!empty($settings['google_cse_key']) && !empty($settings['google_cse_id'])) {
            return self::google_search($query, $settings['google_cse_key'], $settings['google_cse_id']);
        }
        
        // Sinon, utiliser d'autres méthodes (DuckDuckGo, Bing, etc.)
        return self::alternative_search($query);
    }
    
    /**
     * Recherche via Google Custom Search
     */
    private static function google_search($query, $api_key, $cse_id) {
        $url = "https://www.googleapis.com/customsearch/v1?" . http_build_query(array(
            'key' => $api_key,
            'cx' => $cse_id,
            'q' => $query,
            'searchType' => 'image',
            'num' => 10
        ));
        
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $results = array();
        
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $results[] = array(
                    'url' => $item['link'],
                    'title' => $item['title'],
                    'domain' => parse_url($item['link'], PHP_URL_HOST),
                    'source' => 'google_cse'
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Recherche alternative
     */
    private static function alternative_search($query) {
        // Implémentation basique - peut être étendue
        return array();
    }
    
    /**
     * Traiter un résultat d'utilisation externe
     */
    private static function process_external_usage_result($media_id, $result) {
        global $wpdb;
        
        $domain = $result['domain'];
        $url = $result['url'];
        
        // Vérifier si cette utilisation est déjà enregistrée
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_external_usage 
             WHERE media_id = %d AND external_url = %s",
            $media_id,
            $url
        ));
        
        if ($existing) {
            // Mettre à jour la date de dernière détection
            $wpdb->update(
                $wpdb->prefix . 'sml_external_usage',
                array('last_updated' => current_time('mysql')),
                array('id' => $existing->id)
            );
        } else {
            // Nouvelle utilisation détectée
            $domain_clean = SML_Permissions::clean_domain($domain);
            
            // Vérifier si le domaine est autorisé
            $permission_check = SML_Permissions::check_permissions('0.0.0.0', $domain_clean, 'view');
            
            $usage_data = array(
                'media_id' => $media_id,
                'external_url' => $url,
                'domain' => $domain_clean,
                'scan_method' => $result['source'],
                'is_authorized' => $permission_check['authorized'] ? 1 : 0,
                'violation_score' => $permission_check['authorized'] ? 0 : 50
            );
            
            $wpdb->insert($wpdb->prefix . 'sml_external_usage', $usage_data);
            
            // Si non autorisé, créer une notification
            if (!$permission_check['authorized']) {
                SML_Notifications::add_notification(
                    'unauthorized_external_usage',
                    __('Utilisation externe non autorisée détectée', 'secure-media-link'),
                    sprintf(
                        __('Le média #%d est utilisé sur %s sans autorisation', 'secure-media-link'),
                        $media_id,
                        $domain_clean
                    ),
                    array(
                        'media_id' => $media_id,
                        'domain' => $domain_clean,
                        'url' => $url
                    )
                );
            }
        }
    }
    
    /**
     * Obtenir les statistiques globales
     */
    public static function get_global_statistics($period = 'month') {
        global $wpdb;
        
        $date_condition = '';
        
        switch ($period) {
            case 'day':
                $date_condition = "DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $date_condition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_condition = "1=1";
        }
        
        $stats = array();
        
        // Statistiques des médias
        $stats['media'] = array(
            'total_media' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
            ),
            'total_links' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links"
            ),
            'active_links' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links 
                 WHERE is_active = 1 AND expires_at > NOW()"
            ),
            'expired_links' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links 
                 WHERE expires_at <= NOW()"
            )
        );
        
        // Statistiques d'utilisation
        $stats['usage'] = array(
            'total_requests' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking WHERE {$date_condition}"
            ),
            'downloads' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
                 WHERE action_type = 'download' AND {$date_condition}"
            ),
            'copies' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
                 WHERE action_type = 'copy' AND {$date_condition}"
            ),
            'authorized' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
                 WHERE is_authorized = 1 AND {$date_condition}"
            ),
            'blocked' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
                 WHERE is_authorized = 0 AND {$date_condition}"
            )
        );
        
        // Top 5 médias
        $stats['top_media'] = $wpdb->get_results(
            "SELECT sl.media_id, p.post_title, COUNT(*) as usage_count
             FROM {$wpdb->prefix}sml_tracking t
             JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
             JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             WHERE {$date_condition}
             GROUP BY sl.media_id
             ORDER BY usage_count DESC
             LIMIT 5"
        );
        
        // Top 5 domaines
        $stats['top_domains'] = $wpdb->get_results(
            "SELECT domain, COUNT(*) as request_count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE domain IS NOT NULL AND {$date_condition}
             GROUP BY domain
             ORDER BY request_count DESC
             LIMIT 5"
        );
        
        // Top 5 IPs
        $stats['top_ips'] = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as request_count
             FROM {$wpdb->prefix}sml_tracking 
             WHERE {$date_condition}
             GROUP BY ip_address
             ORDER BY request_count DESC
             LIMIT 5"
        );
        
        // Calculs de pourcentages
        if ($stats['usage']['total_requests'] > 0) {
            $stats['percentages'] = array(
                'downloads' => round(($stats['usage']['downloads'] / $stats['usage']['total_requests']) * 100, 2),
                'copies' => round(($stats['usage']['copies'] / $stats['usage']['total_requests']) * 100, 2),
                'authorized' => round(($stats['usage']['authorized'] / $stats['usage']['total_requests']) * 100, 2),
                'blocked' => round(($stats['usage']['blocked'] / $stats['usage']['total_requests']) * 100, 2)
            );
        } else {
            $stats['percentages'] = array(
                'downloads' => 0,
                'copies' => 0,
                'authorized' => 0,
                'blocked' => 0
            );
        }
        
        return $stats;
    }
    
    /**
     * Obtenir les données pour les graphiques
     */
    public static function get_chart_data($type = 'requests', $period = 'month') {
        global $wpdb;
        
        $data = array();
        
        switch ($period) {
            case 'week':
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $date_format = '%Y-%m';
                $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                break;
            default:
                $date_format = '%Y-%m-%d';
                $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        
        switch ($type) {
            case 'requests':
                $data = $wpdb->get_results(
                    "SELECT DATE_FORMAT(created_at, '{$date_format}') as date,
                            COUNT(*) as total,
                            SUM(is_authorized) as authorized,
                            SUM(CASE WHEN is_authorized = 0 THEN 1 ELSE 0 END) as blocked
                     FROM {$wpdb->prefix}sml_tracking 
                     WHERE {$date_condition}
                     GROUP BY DATE_FORMAT(created_at, '{$date_format}')
                     ORDER BY date"
                );
                break;
                
            case 'actions':
                $data = $wpdb->get_results(
                    "SELECT DATE_FORMAT(created_at, '{$date_format}') as date,
                            action_type,
                            COUNT(*) as count
                     FROM {$wpdb->prefix}sml_tracking 
                     WHERE {$date_condition}
                     GROUP BY DATE_FORMAT(created_at, '{$date_format}'), action_type
                     ORDER BY date, action_type"
                );
                break;
                
            case 'countries':
                $data = $wpdb->get_results(
                    "SELECT country, COUNT(*) as count
                     FROM {$wpdb->prefix}sml_tracking 
                     WHERE country IS NOT NULL AND {$date_condition}
                     GROUP BY country
                     ORDER BY count DESC
                     LIMIT 10"
                );
                break;
        }
        
        return $data;
    }
    
    /**
     * Exporter les données de tracking
     */
    public static function export_tracking_data($filters = array(), $format = 'csv') {
        $tracking_data = self::get_tracking_data($filters, 1, 10000);
        
        if ($format === 'csv') {
            return self::export_to_csv($tracking_data['data']);
        } elseif ($format === 'json') {
            return json_encode($tracking_data['data'], JSON_PRETTY_PRINT);
        }
        
        return false;
    }
    
    /**
     * Exporter vers CSV
     */
    private static function export_to_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = '';
        
        // En-têtes
        $headers = array(
            __('Date', 'secure-media-link'),
            __('Action', 'secure-media-link'),
            __('Média', 'secure-media-link'),
            __('Format', 'secure-media-link'),
            __('IP', 'secure-media-link'),
            __('Domaine', 'secure-media-link'),
            __('Pays', 'secure-media-link'),
            __('Ville', 'secure-media-link'),
            __('Autorisé', 'secure-media-link'),
            __('Type de violation', 'secure-media-link')
        );
        
        $output .= '"' . implode('","', $headers) . '"' . "\n";
        
        // Données
        foreach ($data as $row) {
            $csv_row = array(
                $row->created_at,
                $row->action_type,
                $row->media_title,
                $row->format_name,
                $row->ip_address,
                $row->domain,
                $row->country,
                $row->city,
                $row->is_authorized ? __('Oui', 'secure-media-link') : __('Non', 'secure-media-link'),
                $row->violation_type
            );
            
            $output .= '"' . implode('","', $csv_row) . '"' . "\n";
        }
        
        return $output;
    }
    
    /**
     * Nettoyer les anciennes données de tracking
     */
    public static function cleanup_old_tracking($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_tracking WHERE created_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * AJAX - Tracker l'utilisation
     */
    public static function ajax_track_usage() {
        // Vérifier le nonce mais pas forcément l'authentification pour les utilisations publiques
        if (!wp_verify_nonce($_POST['nonce'], 'sml_nonce')) {
            wp_send_json_error(__('Nonce invalide', 'secure-media-link'));
        }
        
        $link_id = intval($_POST['link_id']);
        $action_type = sanitize_text_field($_POST['action_type']);
        
        if (!$link_id || !$action_type) {
            wp_send_json_error(__('Paramètres invalides', 'secure-media-link'));
        }
        
        $tracking_id = self::track_usage($link_id, $action_type);
        
        if ($tracking_id) {
            wp_send_json_success(array('tracking_id' => $tracking_id));
        } else {
            wp_send_json_error(__('Erreur lors du tracking', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Obtenir les données de tracking
     */
    public static function ajax_get_tracking_data() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $filters = array();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        
        // Construire les filtres depuis $_POST
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        
        if (!empty($_POST['action_type'])) {
            $filters['action_type'] = sanitize_text_field($_POST['action_type']);
        }
        
        if (isset($_POST['is_authorized'])) {
            $filters['is_authorized'] = intval($_POST['is_authorized']);
        }
        
        $data = self::get_tracking_data($filters, $page, $per_page);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX - Exporter les données de tracking
     */
    public static function ajax_export_tracking() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $filters = array();
        $format = sanitize_text_field($_POST['format']);
        
        // Construire les filtres
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        
        $export_data = self::export_tracking_data($filters, $format);
        
        if ($export_data) {
            wp_send_json_success(array(
                'data' => $export_data,
                'filename' => 'sml_tracking_' . date('Y-m-d') . '.' . $format
            ));
        } else {
            wp_send_json_error(__('Erreur lors de l\'export', 'secure-media-link'));
        }
    }
}