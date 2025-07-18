<?php
/**
 * Classe pour la gestion des autorisations (IPs et domaines)
 * includes/class-sml-permissions.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Permissions {
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_ajax_sml_add_permission', array(__CLASS__, 'ajax_add_permission'));
        add_action('wp_ajax_sml_update_permission', array(__CLASS__, 'ajax_update_permission'));
        add_action('wp_ajax_sml_delete_permission', array(__CLASS__, 'ajax_delete_permission'));
        add_action('wp_ajax_sml_check_permission', array(__CLASS__, 'ajax_check_permission'));
    }
    
    /**
     * Vérifier les permissions pour une IP et un domaine
     */
    public static function check_permissions($ip_address, $domain, $action = 'download') {
        global $wpdb;
        
        $cache_key = "sml_permission_check_" . md5($ip_address . $domain . $action);
        $result = wp_cache_get($cache_key);
        
        if ($result !== false) {
            return $result;
        }
        
        // Vérifier les permissions IP
        $ip_permission = self::check_ip_permission($ip_address, $action);
        
        // Vérifier les permissions domaine
        $domain_permission = self::check_domain_permission($domain, $action);
        
        // Logique de décision : blacklist a priorité sur whitelist
        $is_authorized = true;
        $violation_type = null;
        
        // Vérifier les blacklists en premier
        if ($ip_permission['type'] === 'blacklist' || $domain_permission['type'] === 'blacklist') {
            $is_authorized = false;
            $violation_type = 'blacklisted';
        }
        // Ensuite vérifier les whitelists
        elseif ($ip_permission['type'] === 'whitelist' && !$ip_permission['allowed']) {
            $is_authorized = false;
            $violation_type = 'not_whitelisted_ip';
        }
        elseif ($domain_permission['type'] === 'whitelist' && !$domain_permission['allowed']) {
            $is_authorized = false;
            $violation_type = 'not_whitelisted_domain';
        }
        
        $result = array(
            'authorized' => $is_authorized,
            'violation_type' => $violation_type,
            'ip_check' => $ip_permission,
            'domain_check' => $domain_permission
        );
        
        // Cache pendant 5 minutes
        wp_cache_set($cache_key, $result, '', 300);
        
        return $result;
    }
    
    /**
     * Vérifier permission IP
     */
    private static function check_ip_permission($ip_address, $action) {
        global $wpdb;
        
        // Vérifier blacklist IP
        $blacklisted = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'ip' 
             AND permission_type = 'blacklist' 
             AND is_active = 1 
             AND (value = %s OR %s LIKE CONCAT(value, '%%'))",
            $ip_address,
            $ip_address
        ));
        
        if ($blacklisted && self::action_allowed($blacklisted->actions, $action)) {
            return array('type' => 'blacklist', 'allowed' => false, 'rule' => $blacklisted);
        }
        
        // Vérifier whitelist IP
        $whitelisted = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'ip' 
             AND permission_type = 'whitelist' 
             AND is_active = 1 
             AND (value = %s OR %s LIKE CONCAT(value, '%%'))",
            $ip_address,
            $ip_address
        ));
        
        if ($whitelisted && self::action_allowed($whitelisted->actions, $action)) {
            return array('type' => 'whitelist', 'allowed' => true, 'rule' => $whitelisted);
        }
        
        // Vérifier s'il y a des règles whitelist IP actives
        $has_whitelist = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'ip' 
             AND permission_type = 'whitelist' 
             AND is_active = 1"
        );
        
        if ($has_whitelist > 0) {
            return array('type' => 'whitelist', 'allowed' => false, 'rule' => null);
        }
        
        return array('type' => 'none', 'allowed' => true, 'rule' => null);
    }
    
    /**
     * Vérifier permission domaine
     */
    private static function check_domain_permission($domain, $action) {
        global $wpdb;
        
        // Nettoyer le domaine
        $clean_domain = self::clean_domain($domain);
        
        // Vérifier blacklist domaine
        $blacklisted = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'domain' 
             AND permission_type = 'blacklist' 
             AND is_active = 1 
             AND (%s LIKE CONCAT('%%', value, '%%') OR value = %s)",
            $clean_domain,
            $clean_domain
        ));
        
        if ($blacklisted && self::action_allowed($blacklisted->actions, $action)) {
            return array('type' => 'blacklist', 'allowed' => false, 'rule' => $blacklisted);
        }
        
        // Vérifier whitelist domaine
        $whitelisted = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'domain' 
             AND permission_type = 'whitelist' 
             AND is_active = 1 
             AND (%s LIKE CONCAT('%%', value, '%%') OR value = %s)",
            $clean_domain,
            $clean_domain
        ));
        
        if ($whitelisted && self::action_allowed($whitelisted->actions, $action)) {
            return array('type' => 'whitelist', 'allowed' => true, 'rule' => $whitelisted);
        }
        
        // Vérifier s'il y a des règles whitelist domaine actives
        $has_whitelist = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_permissions 
             WHERE type = 'domain' 
             AND permission_type = 'whitelist' 
             AND is_active = 1"
        );
        
        if ($has_whitelist > 0) {
            return array('type' => 'whitelist', 'allowed' => false, 'rule' => null);
        }
        
        return array('type' => 'none', 'allowed' => true, 'rule' => null);
    }
    
    /**
     * Vérifier si une action est autorisée dans la liste des actions
     */
    private static function action_allowed($actions_json, $action) {
        $actions = json_decode($actions_json, true);
        
        if (!is_array($actions)) {
            return true; // Si pas de restriction spécifique, autoriser
        }
        
        return in_array($action, $actions);
    }
    
    /**
     * Nettoyer un nom de domaine
     */
    private static function clean_domain($domain) {
        // Supprimer le protocole
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        
        // Supprimer www.
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Supprimer le chemin et les paramètres
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];
        
        return strtolower(trim($domain));
    }
    
    /**
     * Ajouter une nouvelle permission
     */
    public static function add_permission($data) {
        global $wpdb;
        
        // Validation
        $errors = self::validate_permission_data($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        // Préparer les données
        $permission_data = array(
            'type' => sanitize_text_field($data['type']),
            'value' => sanitize_text_field($data['value']),
            'permission_type' => sanitize_text_field($data['permission_type']),
            'actions' => json_encode($data['actions']),
            'description' => sanitize_textarea_field($data['description']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        // Nettoyer la valeur selon le type
        if ($permission_data['type'] === 'domain') {
            $permission_data['value'] = self::clean_domain($permission_data['value']);
        }
        
        $result = $wpdb->insert($wpdb->prefix . 'sml_permissions', $permission_data);
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de l\'ajout de la permission', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        self::clear_permissions_cache();
        
        return array('success' => true, 'permission_id' => $wpdb->insert_id);
    }
    
    /**
     * Mettre à jour une permission
     */
    public static function update_permission($permission_id, $data) {
        global $wpdb;
        
        // Validation
        $errors = self::validate_permission_data($data, $permission_id);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        // Préparer les données
        $permission_data = array(
            'type' => sanitize_text_field($data['type']),
            'value' => sanitize_text_field($data['value']),
            'permission_type' => sanitize_text_field($data['permission_type']),
            'actions' => json_encode($data['actions']),
            'description' => sanitize_textarea_field($data['description']),
            'is_active' => isset($data['is_active']) ? 1 : 0
        );
        
        // Nettoyer la valeur selon le type
        if ($permission_data['type'] === 'domain') {
            $permission_data['value'] = self::clean_domain($permission_data['value']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'sml_permissions',
            $permission_data,
            array('id' => $permission_id)
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de la mise à jour de la permission', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        self::clear_permissions_cache();
        
        return array('success' => true);
    }
    
    /**
     * Supprimer une permission
     */
    public static function delete_permission($permission_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'sml_permissions',
            array('id' => $permission_id)
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de la suppression de la permission', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        self::clear_permissions_cache();
        
        return array('success' => true);
    }
    
    /**
     * Valider les données d'une permission
     */
    private static function validate_permission_data($data, $permission_id = null) {
        $errors = array();
        
        // Type requis
        if (empty($data['type']) || !in_array($data['type'], array('ip', 'domain'))) {
            $errors['type'] = __('Type invalide', 'secure-media-link');
        }
        
        // Valeur requise
        if (empty($data['value'])) {
            $errors['value'] = __('La valeur est requise', 'secure-media-link');
        } else {
            // Validation spécifique selon le type
            if ($data['type'] === 'ip' && !self::is_valid_ip_pattern($data['value'])) {
                $errors['value'] = __('Format IP invalide', 'secure-media-link');
            }
            
            if ($data['type'] === 'domain' && !self::is_valid_domain($data['value'])) {
                $errors['value'] = __('Format de domaine invalide', 'secure-media-link');
            }
        }
        
        // Type de permission requis
        if (empty($data['permission_type']) || !in_array($data['permission_type'], array('whitelist', 'blacklist'))) {
            $errors['permission_type'] = __('Type de permission invalide', 'secure-media-link');
        }
        
        // Actions requises
        if (empty($data['actions']) || !is_array($data['actions'])) {
            $errors['actions'] = __('Au moins une action doit être sélectionnée', 'secure-media-link');
        } else {
            $valid_actions = array('download', 'copy', 'view');
            foreach ($data['actions'] as $action) {
                if (!in_array($action, $valid_actions)) {
                    $errors['actions'] = __('Action invalide détectée', 'secure-media-link');
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Valider un pattern IP
     */
    private static function is_valid_ip_pattern($ip) {
        // IP complète
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        // Pattern CIDR
        if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/', $ip)) {
            return true;
        }
        
        // Pattern avec wildcards
        if (preg_match('/^([0-9]{1,3}\.){0,3}[0-9]{1,3}\*?$/', $ip)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Valider un domaine
     */
    private static function is_valid_domain($domain) {
        $domain = self::clean_domain($domain);
        
        // Domaine simple
        if (filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        // Pattern avec wildcards
        if (preg_match('/^[a-zA-Z0-9*.-]+\.[a-zA-Z]{2,}$/', $domain)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtenir toutes les permissions
     */
    public static function get_all_permissions($type = null, $permission_type = null) {
        global $wpdb;
        
        $where_clauses = array();
        $params = array();
        
        if ($type) {
            $where_clauses[] = "type = %s";
            $params[] = $type;
        }
        
        if ($permission_type) {
            $where_clauses[] = "permission_type = %s";
            $params[] = $permission_type;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $sql = "SELECT * FROM {$wpdb->prefix}sml_permissions {$where_sql} ORDER BY type, permission_type, value";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Nettoyer le cache des permissions
     */
    private static function clear_permissions_cache() {
        // Nettoyer tout le cache des permissions
        wp_cache_flush();
    }
    
    /**
     * Obtenir les statistiques des permissions
     */
    public static function get_permissions_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Nombre total de permissions
        $stats['total_permissions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_permissions"
        );
        
        // Répartition par type
        $stats['by_type'] = $wpdb->get_results(
            "SELECT type, permission_type, COUNT(*) as count, SUM(is_active) as active_count
             FROM {$wpdb->prefix}sml_permissions
             GROUP BY type, permission_type"
        );
        
        // Violations récentes
        $stats['recent_violations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking 
             WHERE is_authorized = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Top domaines bloqués
        $stats['top_blocked_domains'] = $wpdb->get_results(
            "SELECT domain, COUNT(*) as violation_count
             FROM {$wpdb->prefix}sml_tracking
             WHERE is_authorized = 0 
             AND domain IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY domain
             ORDER BY violation_count DESC
             LIMIT 10"
        );
        
        // Top IPs bloquées
        $stats['top_blocked_ips'] = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as violation_count
             FROM {$wpdb->prefix}sml_tracking
             WHERE is_authorized = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY ip_address
             ORDER BY violation_count DESC
             LIMIT 10"
        );
        
        return $stats;
    }
    
    /**
     * Importer des permissions depuis un fichier
     */
    public static function import_permissions($data) {
        if (!is_array($data)) {
            return array('success' => false, 'message' => __('Données invalides', 'secure-media-link'));
        }
        
        $imported = 0;
        $errors = array();
        
        foreach ($data as $permission_data) {
            $result = self::add_permission($permission_data);
            
            if ($result['success']) {
                $imported++;
            } else {
                $errors[] = $permission_data['value'] . ': ' . 
                    (isset($result['errors']) ? implode(', ', $result['errors']) : $result['message']);
            }
        }
        
        return array(
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        );
    }
    
    /**
     * Exporter les permissions
     */
    public static function export_permissions($type = null) {
        $permissions = self::get_all_permissions($type);
        
        $export_data = array();
        
        foreach ($permissions as $permission) {
            $export_data[] = array(
                'type' => $permission->type,
                'value' => $permission->value,
                'permission_type' => $permission->permission_type,
                'actions' => json_decode($permission->actions, true),
                'description' => $permission->description,
                'is_active' => $permission->is_active
            );
        }
        
        return $export_data;
    }
    
    /**
     * Analyser automatiquement les violations pour suggérer des blocages
     */
    public static function analyze_violations_for_suggestions() {
        global $wpdb;
        
        $suggestions = array();
        
        // Domaines avec beaucoup de violations
        $problematic_domains = $wpdb->get_results(
            "SELECT domain, COUNT(*) as violation_count,
                    COUNT(DISTINCT ip_address) as unique_ips
             FROM {$wpdb->prefix}sml_tracking
             WHERE is_authorized = 0 
             AND domain IS NOT NULL
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY domain
             HAVING violation_count >= 10
             ORDER BY violation_count DESC"
        );
        
        foreach ($problematic_domains as $domain_data) {
            // Vérifier si le domaine n'est pas déjà bloqué
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sml_permissions 
                 WHERE type = 'domain' 
                 AND permission_type = 'blacklist' 
                 AND value = %s",
                $domain_data->domain
            ));
            
            if (!$existing) {
                $suggestions[] = array(
                    'type' => 'domain_blacklist',
                    'value' => $domain_data->domain,
                    'reason' => sprintf(
                        __('%d violations depuis %d IP différentes', 'secure-media-link'),
                        $domain_data->violation_count,
                        $domain_data->unique_ips
                    ),
                    'priority' => 'high'
                );
            }
        }
        
        // IPs avec beaucoup de violations
        $problematic_ips = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as violation_count,
                    COUNT(DISTINCT domain) as unique_domains
             FROM {$wpdb->prefix}sml_tracking
             WHERE is_authorized = 0 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY ip_address
             HAVING violation_count >= 50
             ORDER BY violation_count DESC"
        );
        
        foreach ($problematic_ips as $ip_data) {
            // Vérifier si l'IP n'est pas déjà bloquée
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sml_permissions 
                 WHERE type = 'ip' 
                 AND permission_type = 'blacklist' 
                 AND value = %s",
                $ip_data->ip_address
            ));
            
            if (!$existing) {
                $suggestions[] = array(
                    'type' => 'ip_blacklist',
                    'value' => $ip_data->ip_address,
                    'reason' => sprintf(
                        __('%d violations sur %d domaines différents', 'secure-media-link'),
                        $ip_data->violation_count,
                        $ip_data->unique_domains
                    ),
                    'priority' => 'high'
                );
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Appliquer automatiquement des suggestions de blocage
     */
    public static function apply_auto_blocking($suggestions) {
        $applied = 0;
        
        foreach ($suggestions as $suggestion) {
            if ($suggestion['priority'] === 'high') {
                $permission_data = array(
                    'type' => explode('_', $suggestion['type'])[0],
                    'value' => $suggestion['value'],
                    'permission_type' => 'blacklist',
                    'actions' => array('download', 'copy', 'view'),
                    'description' => __('Bloqué automatiquement: ', 'secure-media-link') . $suggestion['reason'],
                    'is_active' => 1
                );
                
                $result = self::add_permission($permission_data);
                
                if ($result['success']) {
                    $applied++;
                    
                    // Enregistrer une notification
                    SML_Notifications::add_notification(
                        'auto_blocking',
                        __('Blocage automatique appliqué', 'secure-media-link'),
                        sprintf(
                            __('%s %s a été automatiquement bloqué: %s', 'secure-media-link'),
                            ucfirst($permission_data['type']),
                            $permission_data['value'],
                            $suggestion['reason']
                        )
                    );
                }
            }
        }
        
        return $applied;
    }
    
    /**
     * Obtenir les géolocalisation d'une IP
     */
    public static function get_ip_geolocation($ip_address) {
        $cache_key = "sml_geo_" . md5($ip_address);
        $geo_data = wp_cache_get($cache_key);
        
        if ($geo_data !== false) {
            return $geo_data;
        }
        
        // Utiliser un service de géolocalisation gratuit
        $response = wp_remote_get("http://ip-api.com/json/{$ip_address}?fields=status,country,countryCode,city,lat,lon");
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['status'] === 'success') {
            $geo_data = array(
                'country' => $data['countryCode'],
                'city' => $data['city'],
                'latitude' => $data['lat'],
                'longitude' => $data['lon']
            );
            
            // Cache pendant 24h
            wp_cache_set($cache_key, $geo_data, '', 86400);
            
            return $geo_data;
        }
        
        return null;
    }
    
    /**
     * AJAX - Ajouter une permission
     */
    public static function ajax_add_permission() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $result = self::add_permission($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Mettre à jour une permission
     */
    public static function ajax_update_permission() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $permission_id = intval($_POST['permission_id']);
        $result = self::update_permission($permission_id, $_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Supprimer une permission
     */
    public static function ajax_delete_permission() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $permission_id = intval($_POST['permission_id']);
        $result = self::delete_permission($permission_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Vérifier une permission
     */
    public static function ajax_check_permission() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $ip_address = sanitize_text_field($_POST['ip_address']);
        $domain = sanitize_text_field($_POST['domain']);
        $action = sanitize_text_field($_POST['action']);
        
        $result = self::check_permissions($ip_address, $domain, $action);
        
        wp_send_json_success($result);
    }
    
    /**
     * Test de performance des permissions
     */
    public static function performance_test($iterations = 1000) {
        $start_time = microtime(true);
        
        // Test avec des IPs et domaines aléatoires
        for ($i = 0; $i < $iterations; $i++) {
            $test_ip = rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
            $test_domain = 'test' . rand(1, 1000) . '.example.com';
            
            self::check_permissions($test_ip, $test_domain, 'download');
        }
        
        $end_time = microtime(true);
        
        return array(
            'iterations' => $iterations,
            'total_time' => $end_time - $start_time,
            'average_time' => ($end_time - $start_time) / $iterations,
            'requests_per_second' => $iterations / ($end_time - $start_time)
        );
    }
}