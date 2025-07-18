<?php
/**
 * Script de désinstallation du plugin Secure Media Link
 * Supprime toutes les données du plugin de façon propre et sécurisée
 * 
 * @package SecureMediaLink
 * @since 1.0.0
 */

// Sécurité : vérifier que la désinstallation est bien appelée par WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Charger les dépendances nécessaires
require_once plugin_dir_path(__FILE__) . 'includes/class-sml-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-sml-cache.php';

/**
 * Classe pour gérer la désinstallation propre du plugin
 */
class SML_Uninstaller {
    
    /**
     * Exécuter la désinstallation complète
     */
    public static function uninstall() {
        global $wpdb;
        
        // Vérifier les permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Log de début de désinstallation
        error_log('Secure Media Link: Début de la désinstallation');
        
        try {
            // 1. Supprimer les tâches cron
            self::cleanup_cron_jobs();
            
            // 2. Nettoyer le cache
            self::cleanup_cache();
            
            // 3. Supprimer les fichiers générés
            self::cleanup_generated_files();
            
            // 4. Supprimer les métadonnées des posts
            self::cleanup_post_meta();
            
            // 5. Supprimer les options WordPress
            self::cleanup_options();
            
            // 6. Supprimer les tables de base de données
            self::cleanup_database_tables();
            
            // 7. Nettoyer les règles de réécriture
            self::cleanup_rewrite_rules();
            
            // 8. Supprimer les capacités utilisateur personnalisées
            self::cleanup_user_capabilities();
            
            // 9. Nettoyer les transients
            self::cleanup_transients();
            
            // Log de fin de désinstallation
            error_log('Secure Media Link: Désinstallation terminée avec succès');
            
        } catch (Exception $e) {
            error_log('Secure Media Link: Erreur lors de la désinstallation - ' . $e->getMessage());
        }
    }
    
    /**
     * Supprimer toutes les tâches cron programmées
     */
    private static function cleanup_cron_jobs() {
        $cron_hooks = array(
            'sml_check_expiring_links',
            'sml_cleanup_cache',
            'sml_scan_external_usage',
            'sml_generate_statistics',
            'sml_cleanup_old_data',
            'sml_optimize_database',
            'sml_send_notifications'
        );
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            
            // Supprimer tous les événements programmés pour ce hook
            wp_clear_scheduled_hook($hook);
        }
        
        error_log('Secure Media Link: Tâches cron supprimées');
    }
    
    /**
     * Nettoyer tous les caches
     */
    private static function cleanup_cache() {
        // Cache WordPress
        wp_cache_flush();
        
        // Cache spécifique SML
        if (class_exists('SML_Cache')) {
            SML_Cache::flush_all();
        }
        
        // Cache objet externe si disponible
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sml');
        }
        
        error_log('Secure Media Link: Cache nettoyé');
    }
    
    /**
     * Supprimer tous les fichiers générés par le plugin
     */
    private static function cleanup_generated_files() {
        $upload_dir = wp_upload_dir();
        
        // Dossiers à supprimer
        $directories_to_remove = array(
            $upload_dir['basedir'] . '/sml-formats',
            $upload_dir['basedir'] . '/sml-temp',
            $upload_dir['basedir'] . '/sml-cache',
            $upload_dir['basedir'] . '/sml-logs',
            $upload_dir['basedir'] . '/sml-exports'
        );
        
        foreach ($directories_to_remove as $directory) {
            if (is_dir($directory)) {
                self::recursive_rmdir($directory);
                error_log("Secure Media Link: Dossier supprimé - {$directory}");
            }
        }
        
        // Fichiers spécifiques à supprimer
        $files_to_remove = array(
            ABSPATH . '.htaccess.sml.backup',
            $upload_dir['basedir'] . '/sml-debug.log'
        );
        
        foreach ($files_to_remove as $file) {
            if (file_exists($file)) {
                unlink($file);
                error_log("Secure Media Link: Fichier supprimé - {$file}");
            }
        }
    }
    
    /**
     * Supprimer récursivement un dossier et son contenu
     */
    private static function recursive_rmdir($directory) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = array_diff(scandir($directory), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($directory);
    }
    
    /**
     * Supprimer toutes les métadonnées des posts liées au plugin
     */
    private static function cleanup_post_meta() {
        global $wpdb;
        
        $meta_keys = array(
            '_sml_copyright',
            '_sml_expiry_date',
            '_sml_secure_links',
            '_sml_download_count',
            '_sml_last_access',
            '_sml_protection_level',
            '_sml_custom_permissions'
        );
        
        foreach ($meta_keys as $meta_key) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            ));
            
            if ($deleted) {
                error_log("Secure Media Link: {$deleted} métadonnées '{$meta_key}' supprimées");
            }
        }
    }
    
    /**
     * Supprimer toutes les options WordPress du plugin
     */
    private static function cleanup_options() {
        $options_to_delete = array(
            // Options principales
            'sml_settings',
            'sml_key_pair',
            'sml_version',
            'sml_db_version',
            'sml_installation_date',
            
            // Options de cache
            'sml_cache_settings',
            'sml_cache_stats',
            
            // Options de notifications
            'sml_notification_settings',
            'sml_last_notification_check',
            
            // Options de performance
            'sml_performance_stats',
            'sml_optimization_settings',
            
            // Options de sécurité
            'sml_security_log',
            'sml_blocked_ips',
            'sml_rate_limits',
            
            // Options de migration
            'sml_migration_status',
            'sml_backup_data',
            
            // Options temporaires
            'sml_temp_data',
            'sml_processing_queue'
        );
        
        foreach ($options_to_delete as $option) {
            if (delete_option($option)) {
                error_log("Secure Media Link: Option '{$option}' supprimée");
            }
        }
        
        // Supprimer les options avec préfixes
        global $wpdb;
        
        $option_prefixes = array(
            'sml_format_',
            'sml_permission_',
            'sml_stats_',
            'sml_cache_',
            'sml_temp_'
        );
        
        foreach ($option_prefixes as $prefix) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            ));
            
            if ($deleted) {
                error_log("Secure Media Link: {$deleted} options avec préfixe '{$prefix}' supprimées");
            }
        }
    }
    
    /**
     * Supprimer toutes les tables de base de données
     */
    private static function cleanup_database_tables() {
        global $wpdb;
        
        // Liste des tables à supprimer
        $tables_to_drop = array(
            $wpdb->prefix . 'sml_media_formats',
            $wpdb->prefix . 'sml_secure_links',
            $wpdb->prefix . 'sml_tracking',
            $wpdb->prefix . 'sml_permissions',
            $wpdb->prefix . 'sml_notifications',
            $wpdb->prefix . 'sml_frontend_uploads',
            $wpdb->prefix . 'sml_external_usage',
            $wpdb->prefix . 'sml_statistics',
            $wpdb->prefix . 'sml_cache_data',
            $wpdb->prefix . 'sml_api_tokens'
        );
        
        foreach ($tables_to_drop as $table) {
            // Vérifier si la table existe
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
            if ($table_exists) {
                $result = $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
                
                if ($result !== false) {
                    error_log("Secure Media Link: Table '{$table}' supprimée");
                } else {
                    error_log("Secure Media Link: Erreur lors de la suppression de la table '{$table}'");
                }
            }
        }
        
        // Optimiser les tables restantes
        $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->postmeta}");
        
        error_log('Secure Media Link: Tables de base de données supprimées');
    }
    
    /**
     * Nettoyer les règles de réécriture
     */
    private static function cleanup_rewrite_rules() {
        // Supprimer les règles personnalisées du .htaccess
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (file_exists($htaccess_file) && is_writable($htaccess_file)) {
            $htaccess_content = file_get_contents($htaccess_file);
            
            // Supprimer les règles SML entre les marqueurs
            $pattern = '/# BEGIN Secure Media Link.*?# END Secure Media Link\s*/s';
            $new_content = preg_replace($pattern, '', $htaccess_content);
            
            if ($new_content !== $htaccess_content) {
                file_put_contents($htaccess_file, $new_content);
                error_log('Secure Media Link: Règles .htaccess supprimées');
            }
        }
        
        // Flush des règles de réécriture WordPress
        flush_rewrite_rules();
    }
    
    /**
     * Supprimer les capacités utilisateur personnalisées
     */
    private static function cleanup_user_capabilities() {
        $custom_capabilities = array(
            'sml_manage_secure_media',
            'sml_upload_media',
            'sml_view_statistics',
            'sml_manage_permissions',
            'sml_access_api',
            'sml_export_data'
        );
        
        // Supprimer des rôles existants
        $roles = array('administrator', 'editor', 'author', 'contributor');
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($custom_capabilities as $capability) {
                    $role->remove_cap($capability);
                }
            }
        }
        
        // Supprimer le rôle personnalisé s'il existe
        remove_role('sml_media_manager');
        
        error_log('Secure Media Link: Capacités utilisateur supprimées');
    }
    
    /**
     * Nettoyer tous les transients liés au plugin
     */
    private static function cleanup_transients() {
        global $wpdb;
        
        // Supprimer les transients avec préfixes SML
        $transient_prefixes = array(
            '_transient_sml_',
            '_transient_timeout_sml_'
        );
        
        foreach ($transient_prefixes as $prefix) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            ));
            
            if ($deleted) {
                error_log("Secure Media Link: {$deleted} transients avec préfixe '{$prefix}' supprimés");
            }
        }
        
        // Supprimer les transients spécifiques
        $specific_transients = array(
            'sml_global_stats',
            'sml_media_formats',
            'sml_permissions_cache',
            'sml_tracking_data',
            'sml_api_rate_limits'
        );
        
        foreach ($specific_transients as $transient) {
            delete_transient($transient);
        }
    }
    
    /**
     * Nettoyer les données utilisateur (RGPD compliant)
     */
    private static function cleanup_user_data() {
        global $wpdb;
        
        // Anonymiser les données de tracking (IP, géolocalisation)
        $wpdb->query(
            "UPDATE {$wpdb->prefix}sml_tracking 
             SET ip_address = '0.0.0.0', 
                 latitude = NULL, 
                 longitude = NULL, 
                 city = NULL,
                 user_agent = 'anonymized'
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        // Supprimer les données personnelles des notifications
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}sml_notifications 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        
        error_log('Secure Media Link: Données utilisateur anonymisées (RGPD)');
    }
    
    /**
     * Créer un rapport de désinstallation
     */
    private static function generate_uninstall_report() {
        global $wpdb;
        
        $report = array(
            'timestamp' => current_time('mysql'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => SML_PLUGIN_VERSION ?? '1.0.0',
            'site_url' => get_site_url(),
            'admin_email' => get_option('admin_email'),
            'multisite' => is_multisite(),
            'data_removed' => array(
                'database_tables' => 0,
                'options' => 0,
                'postmeta' => 0,
                'transients' => 0,
                'files' => 0
            )
        );
        
        // Compter les éléments supprimés
        $tables_count = count(array(
            'sml_media_formats', 'sml_secure_links', 'sml_tracking',
            'sml_permissions', 'sml_notifications', 'sml_frontend_uploads',
            'sml_external_usage', 'sml_statistics'
        ));
        
        $report['data_removed']['database_tables'] = $tables_count;
        
        // Sauvegarder le rapport
        $upload_dir = wp_upload_dir();
        $report_file = $upload_dir['basedir'] . '/sml-uninstall-report-' . date('Y-m-d-H-i-s') . '.json';
        
        if (is_writable($upload_dir['basedir'])) {
            file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));
            error_log("Secure Media Link: Rapport de désinstallation créé - {$report_file}");
        }
        
        return $report;
    }
    
    /**
     * Vérifier l'intégrité avant désinstallation
     */
    private static function pre_uninstall_check() {
        $checks = array(
            'permissions' => current_user_can('activate_plugins'),
            'not_network_admin' => !is_network_admin() || is_main_site(),
            'wp_filesystem' => WP_Filesystem(),
            'database_access' => true
        );
        
        // Test de connexion à la base de données
        global $wpdb;
        $checks['database_access'] = $wpdb->query("SELECT 1") !== false;
        
        foreach ($checks as $check_name => $result) {
            if (!$result) {
                error_log("Secure Media Link: Échec de la vérification '{$check_name}'");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Nettoyer les fichiers de sauvegarde
     */
    private static function cleanup_backup_files() {
        $upload_dir = wp_upload_dir();
        $backup_patterns = array(
            $upload_dir['basedir'] . '/sml-backup-*.json',
            $upload_dir['basedir'] . '/sml-export-*.csv',
            $upload_dir['basedir'] . '/sml-export-*.json',
            ABSPATH . '.htaccess.sml.backup'
        );
        
        foreach ($backup_patterns as $pattern) {
            $files = glob($pattern);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    error_log("Secure Media Link: Fichier de sauvegarde supprimé - {$file}");
                }
            }
        }
    }
    
    /**
     * Effectuer une vérification post-désinstallation
     */
    private static function post_uninstall_verification() {
        global $wpdb;
        
        $verification_results = array(
            'tables_remaining' => 0,
            'options_remaining' => 0,
            'meta_remaining' => 0,
            'files_remaining' => 0
        );
        
        // Vérifier les tables restantes
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}sml_%'");
        $verification_results['tables_remaining'] = count($tables);
        
        // Vérifier les options restantes
        $options = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'sml_%'"
        );
        $verification_results['options_remaining'] = $options;
        
        // Vérifier les métadonnées restantes
        $meta = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sml_%'"
        );
        $verification_results['meta_remaining'] = $meta;
        
        // Vérifier les dossiers restants
        $upload_dir = wp_upload_dir();
        $sml_dirs = glob($upload_dir['basedir'] . '/sml-*', GLOB_ONLYDIR);
        $verification_results['files_remaining'] = count($sml_dirs);
        
        // Logger les résultats
        $total_remaining = array_sum($verification_results);
        
        if ($total_remaining > 0) {
            error_log('Secure Media Link: Vérification post-désinstallation - ' . $total_remaining . ' éléments restants');
            error_log('Secure Media Link: Détails - ' . json_encode($verification_results));
        } else {
            error_log('Secure Media Link: Vérification post-désinstallation - Désinstallation complète réussie');
        }
        
        return $verification_results;
    }
}

// Exécuter la désinstallation si le script est appelé directement
if (defined('WP_UNINSTALL_PLUGIN')) {
    // Vérifications de sécurité
    if (!SML_Uninstaller::pre_uninstall_check()) {
        error_log('Secure Media Link: Échec des vérifications pré-désinstallation');
        return;
    }
    
    // Créer un point de sauvegarde des données critiques avant suppression
    $backup_data = array(
        'settings' => get_option('sml_settings', array()),
        'key_pair_exists' => !empty(get_option('sml_key_pair')),
        'formats_count' => 0,
        'links_count' => 0
    );
    
    global $wpdb;
    
    // Compter les éléments avant suppression
    $backup_data['formats_count'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sml_media_formats"
    ) ?: 0;
    
    $backup_data['links_count'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links"
    ) ?: 0;
    
    // Sauvegarder temporairement pour le rapport
    set_transient('sml_uninstall_backup', $backup_data, HOUR_IN_SECONDS);
    
    // Exécuter la désinstallation
    SML_Uninstaller::uninstall();
    
    // Générer le rapport final
    SML_Uninstaller::generate_uninstall_report();
    
    // Vérification finale
    $verification = SML_Uninstaller::post_uninstall_verification();
    
    // Nettoyer le transient de sauvegarde
    delete_transient('sml_uninstall_backup');
    
    // Log final
    error_log('Secure Media Link: Processus de désinstallation terminé');
}