<?php
/**
 * Classe pour la gestion du cache et optimisation
 * includes/class-sml-cache.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Cache {
    
    /**
     * Préfixes pour les clés de cache
     */
    const PREFIX_MEDIA_FORMAT = 'sml_media_format_';
    const PREFIX_SECURE_LINK = 'sml_secure_link_';
    const PREFIX_PERMISSION_CHECK = 'sml_permission_check_';
    const PREFIX_MEDIA_STATS = 'sml_media_stats_';
    const PREFIX_TRACKING_DATA = 'sml_tracking_data_';
    const PREFIX_USER_MEDIA = 'sml_user_media_';
    const PREFIX_GLOBAL_STATS = 'sml_global_stats_';
    const PREFIX_CHART_DATA = 'sml_chart_data_';
    
    /**
     * Durées de cache par défaut (en secondes)
     */
    const CACHE_DURATION_SHORT = 300;      // 5 minutes
    const CACHE_DURATION_MEDIUM = 1800;    // 30 minutes
    const CACHE_DURATION_LONG = 3600;      // 1 heure
    const CACHE_DURATION_EXTENDED = 7200;  // 2 heures
    const CACHE_DURATION_DAILY = 86400;    // 24 heures
    
    /**
     * Taille maximale du cache en mémoire (en bytes)
     */
    const MAX_MEMORY_CACHE_SIZE = 10485760; // 10MB
    
    /**
     * Instance du cache en mémoire
     */
    private static $memory_cache = array();
    private static $memory_cache_size = 0;
    private static $cache_hits = 0;
    private static $cache_misses = 0;
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_ajax_sml_clear_cache', array(__CLASS__, 'ajax_clear_cache'));
        add_action('wp_ajax_sml_get_cache_stats', array(__CLASS__, 'ajax_get_cache_stats'));
        
        // Hooks pour l'invalidation automatique du cache
        add_action('sml_link_created', array(__CLASS__, 'invalidate_media_cache'));
        add_action('sml_link_updated', array(__CLASS__, 'invalidate_media_cache'));
        add_action('sml_link_deleted', array(__CLASS__, 'invalidate_media_cache'));
        add_action('sml_permission_updated', array(__CLASS__, 'invalidate_permission_cache'));
        add_action('sml_tracking_added', array(__CLASS__, 'invalidate_stats_cache'));
        
        // Nettoyage automatique du cache
        add_action('sml_cleanup_cache', array(__CLASS__, 'cleanup_expired_cache'));
        
        // Programmer le nettoyage automatique
        if (!wp_next_scheduled('sml_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'sml_cleanup_cache');
        }
    }
    
    /**
     * Obtenir une valeur du cache
     */
    public static function get($key, $default = null) {
        $full_key = self::get_full_key($key);
        
        // Vérifier d'abord le cache en mémoire
        if (isset(self::$memory_cache[$full_key])) {
            $cached_item = self::$memory_cache[$full_key];
            
            // Vérifier l'expiration
            if ($cached_item['expires'] > time()) {
                self::$cache_hits++;
                return $cached_item['data'];
            } else {
                // Supprimer l'élément expiré
                self::remove_from_memory_cache($full_key);
            }
        }
        
        // Vérifier le cache WordPress
        $value = wp_cache_get($full_key, 'sml');
        
        if ($value !== false) {
            self::$cache_hits++;
            
            // Ajouter au cache en mémoire si possible
            self::add_to_memory_cache($full_key, $value, self::CACHE_DURATION_MEDIUM);
            
            return $value;
        }
        
        // Vérifier les transients WordPress
        $value = get_transient($full_key);
        
        if ($value !== false) {
            self::$cache_hits++;
            
            // Ajouter aux autres caches
            wp_cache_set($full_key, $value, 'sml', self::CACHE_DURATION_MEDIUM);
            self::add_to_memory_cache($full_key, $value, self::CACHE_DURATION_MEDIUM);
            
            return $value;
        }
        
        self::$cache_misses++;
        return $default;
    }
    
    /**
     * Définir une valeur dans le cache
     */
    public static function set($key, $value, $duration = null) {
        if ($duration === null) {
            $settings = get_option('sml_settings', array());
            $duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : self::CACHE_DURATION_LONG;
        }
        
        $full_key = self::get_full_key($key);
        
        // Stocker dans tous les niveaux de cache
        wp_cache_set($full_key, $value, 'sml', $duration);
        set_transient($full_key, $value, $duration);
        self::add_to_memory_cache($full_key, $value, $duration);
        
        return true;
    }
    
    /**
     * Supprimer une valeur du cache
     */
    public static function delete($key) {
        $full_key = self::get_full_key($key);
        
        // Supprimer de tous les niveaux
        wp_cache_delete($full_key, 'sml');
        delete_transient($full_key);
        self::remove_from_memory_cache($full_key);
        
        return true;
    }
    
    /**
     * Vider tout le cache
     */
    public static function flush_all() {
        // Vider le cache en mémoire
        self::$memory_cache = array();
        self::$memory_cache_size = 0;
        
        // Vider le cache WordPress
        wp_cache_flush();
        
        // Supprimer tous les transients SML
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sml_%' 
             OR option_name LIKE '_transient_timeout_sml_%'"
        );
        
        // Déclencher une action pour d'autres plugins
        do_action('sml_cache_flushed');
        
        return true;
    }
    
    /**
     * Obtenir ou définir avec callback
     */
    public static function remember($key, $callback, $duration = null) {
        $value = self::get($key);
        
        if ($value === null && is_callable($callback)) {
            $value = call_user_func($callback);
            
            if ($value !== null) {
                self::set($key, $value, $duration);
            }
        }
        
        return $value;
    }
    
    /**
     * Cache conditionnel avec vérification de fraîcheur
     */
    public static function get_fresh($key, $callback, $max_age = null, $default = null) {
        if ($max_age === null) {
            $max_age = self::CACHE_DURATION_MEDIUM;
        }
        
        $cache_key = $key . '_timestamp';
        $timestamp = self::get($cache_key);
        
        // Vérifier si le cache est encore frais
        if ($timestamp && (time() - $timestamp) < $max_age) {
            $value = self::get($key);
            if ($value !== null) {
                return $value;
            }
        }
        
        // Régénérer les données
        if (is_callable($callback)) {
            $value = call_user_func($callback);
            
            if ($value !== null) {
                self::set($key, $value, $max_age * 2); // Cache plus longtemps
                self::set($cache_key, time(), $max_age * 2);
                return $value;
            }
        }
        
        return $default;
    }
    
    /**
     * Cache par lots pour optimiser les requêtes multiples
     */
    public static function get_multiple($keys) {
        $results = array();
        $missing_keys = array();
        
        // Vérifier quelles clés sont en cache
        foreach ($keys as $key) {
            $value = self::get($key);
            if ($value !== null) {
                $results[$key] = $value;
            } else {
                $missing_keys[] = $key;
            }
        }
        
        return array(
            'found' => $results,
            'missing' => $missing_keys
        );
    }
    
    /**
     * Définir plusieurs valeurs en une fois
     */
    public static function set_multiple($data, $duration = null) {
        $success = true;
        
        foreach ($data as $key => $value) {
            if (!self::set($key, $value, $duration)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Invalider le cache des médias
     */
    public static function invalidate_media_cache($media_id = null) {
        if ($media_id) {
            // Invalider le cache spécifique au média
            self::delete(self::PREFIX_MEDIA_STATS . $media_id);
            self::delete(self::PREFIX_USER_MEDIA . get_post_field('post_author', $media_id));
            
            // Invalider les liens sécurisés de ce média
            global $wpdb;
            $links = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sml_secure_links WHERE media_id = %d",
                $media_id
            ));
            
            foreach ($links as $link_id) {
                self::delete(self::PREFIX_SECURE_LINK . $link_id);
            }
        }
        
        // Invalider les statistiques globales
        self::invalidate_stats_cache();
    }
    
    /**
     * Invalider le cache des permissions
     */
    public static function invalidate_permission_cache() {
        // Supprimer tous les caches de vérification de permissions
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_" . self::PREFIX_PERMISSION_CHECK . "%'"
        );
        
        // Vider le cache en mémoire des permissions
        foreach (self::$memory_cache as $key => $value) {
            if (strpos($key, self::PREFIX_PERMISSION_CHECK) === 0) {
                self::remove_from_memory_cache($key);
            }
        }
    }
    
    /**
     * Invalider le cache des statistiques
     */
    public static function invalidate_stats_cache() {
        // Supprimer les statistiques globales
        self::delete(self::PREFIX_GLOBAL_STATS . 'day');
        self::delete(self::PREFIX_GLOBAL_STATS . 'week');
        self::delete(self::PREFIX_GLOBAL_STATS . 'month');
        self::delete(self::PREFIX_GLOBAL_STATS . 'year');
        
        // Supprimer les données de graphiques
        self::delete(self::PREFIX_CHART_DATA . 'requests_day');
        self::delete(self::PREFIX_CHART_DATA . 'requests_week');
        self::delete(self::PREFIX_CHART_DATA . 'requests_month');
        self::delete(self::PREFIX_CHART_DATA . 'actions_day');
        self::delete(self::PREFIX_CHART_DATA . 'actions_week');
        self::delete(self::PREFIX_CHART_DATA . 'actions_month');
        
        // Supprimer les tracking data
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_" . self::PREFIX_TRACKING_DATA . "%'"
        );
    }
    
    /**
     * Nettoyage automatique du cache expiré
     */
    public static function cleanup_expired_cache() {
        global $wpdb;
        
        // Nettoyer les transients expirés
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
        );
        
        // Nettoyer le cache en mémoire expiré
        $current_time = time();
        $expired_keys = array();
        
        foreach (self::$memory_cache as $key => $item) {
            if ($item['expires'] < $current_time) {
                $expired_keys[] = $key;
            }
        }
        
        foreach ($expired_keys as $key) {
            self::remove_from_memory_cache($key);
        }
        
        // Optimiser les tables de cache si elles existent
        $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
    }
    
    /**
     * Nettoyer les liens expirés du cache
     */
    public static function cleanup_expired_links() {
        global $wpdb;
        
        // Obtenir tous les liens expirés
        $expired_links = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}sml_secure_links 
             WHERE expires_at <= NOW()"
        );
        
        // Supprimer du cache
        foreach ($expired_links as $link_id) {
            self::delete(self::PREFIX_SECURE_LINK . $link_id);
        }
        
        // Marquer les liens comme inactifs
        $wpdb->update(
            $wpdb->prefix . 'sml_secure_links',
            array('is_active' => 0),
            array('expires_at <=' => current_time('mysql'))
        );
    }
    
    /**
     * Précharger le cache pour les données fréquemment utilisées
     */
    public static function preload_cache() {
        // Précharger les formats de médias
        $formats = SML_Media_Formats::get_all_formats();
        foreach ($formats as $format) {
            self::set(self::PREFIX_MEDIA_FORMAT . $format->id, $format, self::CACHE_DURATION_DAILY);
        }
        
        // Précharger les statistiques globales
        $stats = SML_Tracking::get_global_statistics('month');
        self::set(self::PREFIX_GLOBAL_STATS . 'month', $stats, self::CACHE_DURATION_LONG);
        
        // Précharger les permissions actives
        $permissions = SML_Permissions::get_all_permissions();
        foreach ($permissions as $permission) {
            if ($permission->is_active) {
                $cache_key = 'permission_' . $permission->type . '_' . md5($permission->value);
                self::set($cache_key, $permission, self::CACHE_DURATION_EXTENDED);
            }
        }
    }
    
    /**
     * Obtenir les statistiques du cache
     */
    public static function get_cache_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Statistiques du cache en mémoire
        $stats['memory_cache'] = array(
            'items' => count(self::$memory_cache),
            'size_bytes' => self::$memory_cache_size,
            'size_formatted' => size_format(self::$memory_cache_size),
            'max_size_bytes' => self::MAX_MEMORY_CACHE_SIZE,
            'max_size_formatted' => size_format(self::MAX_MEMORY_CACHE_SIZE),
            'usage_percentage' => round((self::$memory_cache_size / self::MAX_MEMORY_CACHE_SIZE) * 100, 2)
        );
        
        // Statistiques des hits/misses
        $total_requests = self::$cache_hits + self::$cache_misses;
        $hit_rate = $total_requests > 0 ? round((self::$cache_hits / $total_requests) * 100, 2) : 0;
        
        $stats['performance'] = array(
            'hits' => self::$cache_hits,
            'misses' => self::$cache_misses,
            'total_requests' => $total_requests,
            'hit_rate' => $hit_rate
        );
        
        // Statistiques des transients
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sml_%'"
        );
        
        $stats['transients'] = array(
            'count' => intval($transient_count),
            'expired_count' => self::count_expired_transients()
        );
        
        // Taille approximative du cache
        $cache_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_sml_%'"
        );
        
        $stats['disk_cache'] = array(
            'size_bytes' => intval($cache_size),
            'size_formatted' => size_format($cache_size ?: 0)
        );
        
        return $stats;
    }
    
    /**
     * Compter les transients expirés
     */
    private static function count_expired_transients() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_sml_%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
        );
    }
    
    /**
     * Ajouter au cache en mémoire
     */
    private static function add_to_memory_cache($key, $data, $duration) {
        $settings = get_option('sml_settings', array());
        
        if (!isset($settings['cache_enabled']) || !$settings['cache_enabled']) {
            return;
        }
        
        $serialized_data = maybe_serialize($data);
        $data_size = strlen($serialized_data);
        
        // Vérifier si on peut ajouter sans dépasser la limite
        if ((self::$memory_cache_size + $data_size) > self::MAX_MEMORY_CACHE_SIZE) {
            // Libérer de l'espace en supprimant les éléments les plus anciens
            self::cleanup_memory_cache($data_size);
        }
        
        self::$memory_cache[$key] = array(
            'data' => $data,
            'expires' => time() + $duration,
            'size' => $data_size,
            'accessed' => time()
        );
        
        self::$memory_cache_size += $data_size;
    }
    
    /**
     * Supprimer du cache en mémoire
     */
    private static function remove_from_memory_cache($key) {
        if (isset(self::$memory_cache[$key])) {
            self::$memory_cache_size -= self::$memory_cache[$key]['size'];
            unset(self::$memory_cache[$key]);
        }
    }
    
    /**
     * Nettoyer le cache en mémoire pour faire de la place
     */
    private static function cleanup_memory_cache($space_needed) {
        // Trier par dernier accès (LRU - Least Recently Used)
        uasort(self::$memory_cache, function($a, $b) {
            return $a['accessed'] - $b['accessed'];
        });
        
        $freed_space = 0;
        $keys_to_remove = array();
        
        foreach (self::$memory_cache as $key => $item) {
            $keys_to_remove[] = $key;
            $freed_space += $item['size'];
            
            // Arrêter quand on a libéré assez d'espace
            if ($freed_space >= $space_needed) {
                break;
            }
        }
        
        // Supprimer les éléments sélectionnés
        foreach ($keys_to_remove as $key) {
            self::remove_from_memory_cache($key);
        }
    }
    
    /**
     * Obtenir la clé complète avec préfixe
     */
    private static function get_full_key($key) {
        return 'sml_' . $key;
    }
    
    /**
     * Cache intelligent pour les requêtes de base de données
     */
    public static function cache_query($sql, $callback, $duration = null) {
        $cache_key = 'query_' . md5($sql);
        
        return self::remember($cache_key, $callback, $duration);
    }
    
    /**
     * Cache pour les résultats d'API externe
     */
    public static function cache_external_api($api_endpoint, $params, $callback, $duration = null) {
        if ($duration === null) {
            $duration = self::CACHE_DURATION_EXTENDED;
        }
        
        $cache_key = 'api_' . md5($api_endpoint . serialize($params));
        
        return self::remember($cache_key, $callback, $duration);
    }
    
    /**
     * Cache conditionnel basé sur les permissions utilisateur
     */
    public static function cache_user_data($user_id, $key, $callback, $duration = null) {
        $cache_key = 'user_' . $user_id . '_' . $key;
        
        return self::remember($cache_key, $callback, $duration);
    }
    
    /**
     * Vérifier si le cache est activé
     */
    public static function is_enabled() {
        $settings = get_option('sml_settings', array());
        return isset($settings['cache_enabled']) && $settings['cache_enabled'];
    }
    
    /**
     * Obtenir la configuration du cache
     */
    public static function get_config() {
        $settings = get_option('sml_settings', array());
        
        return array(
            'enabled' => isset($settings['cache_enabled']) && $settings['cache_enabled'],
            'duration' => isset($settings['cache_duration']) ? $settings['cache_duration'] : self::CACHE_DURATION_LONG,
            'max_memory_size' => self::MAX_MEMORY_CACHE_SIZE
        );
    }
    
    /**
     * AJAX - Vider le cache
     */
    public static function ajax_clear_cache() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        if (self::flush_all()) {
            wp_send_json_success(array(
                'message' => __('Cache vidé avec succès', 'secure-media-link')
            ));
        } else {
            wp_send_json_error(__('Erreur lors du nettoyage du cache', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Obtenir les statistiques du cache
     */
    public static function ajax_get_cache_stats() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $stats = self::get_cache_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Diagnostics du cache
     */
    public static function diagnose() {
        $diagnostics = array();
        
        // Vérifier si le cache objet est disponible
        $diagnostics['object_cache'] = wp_using_ext_object_cache() ? 'available' : 'not_available';
        
        // Vérifier les permissions d'écriture
        $diagnostics['write_permissions'] = is_writable(WP_CONTENT_DIR) ? 'ok' : 'error';
        
        // Vérifier la mémoire disponible
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $diagnostics['memory'] = array(
            'limit' => $memory_limit,
            'usage' => size_format($memory_usage),
            'usage_bytes' => $memory_usage
        );
        
        // Vérifier les transients orphelins
        $diagnostics['orphaned_transients'] = self::count_expired_transients();
        
        // Vérifier la configuration
        $config = self::get_config();
        $diagnostics['configuration'] = $config;
        
        return $diagnostics;
    }
    
    /**
     * Réparer le cache (nettoyer les problèmes détectés)
     */
    public static function repair() {
        $repaired = array();
        
        // Nettoyer les transients expirés
        $cleaned_transients = self::cleanup_expired_cache();
        $repaired['cleaned_transients'] = $cleaned_transients;
        
        // Nettoyer le cache en mémoire
        self::$memory_cache = array();
        self::$memory_cache_size = 0;
        $repaired['cleaned_memory_cache'] = true;
        
        // Précharger le cache essentiel
        self::preload_cache();
        $repaired['preloaded_cache'] = true;
        
        return $repaired;
    }
}