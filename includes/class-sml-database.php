<?php
/**
 * Classe pour la gestion de la base de données
 * includes/class-sml-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Database {
    
    /**
     * Création des tables nécessaires
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table pour les formats de média
        $table_formats = $wpdb->prefix . 'sml_media_formats';
        $sql_formats = "CREATE TABLE $table_formats (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            width int(11) DEFAULT NULL,
            height int(11) DEFAULT NULL,
            quality int(3) DEFAULT 85,
            format varchar(10) DEFAULT 'jpg',
            crop_mode varchar(20) DEFAULT 'resize',
            crop_position varchar(20) DEFAULT 'center',
            type varchar(20) DEFAULT 'web',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Table pour les liens sécurisés
        $table_links = $wpdb->prefix . 'sml_secure_links';
        $sql_links = "CREATE TABLE $table_links (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            media_id bigint(20) NOT NULL,
            format_id mediumint(9) NOT NULL,
            link_hash varchar(255) NOT NULL,
            signature text NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            download_count int(11) DEFAULT 0,
            copy_count int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY link_hash (link_hash),
            KEY media_id (media_id),
            KEY format_id (format_id),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Table pour le tracking des utilisations
        $table_tracking = $wpdb->prefix . 'sml_tracking';
        $sql_tracking = "CREATE TABLE $table_tracking (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            link_id mediumint(9) NOT NULL,
            action_type varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            referer_url text,
            domain varchar(255),
            country varchar(2),
            city varchar(100),
            latitude decimal(10,8),
            longitude decimal(11,8),
            is_authorized tinyint(1) DEFAULT 1,
            violation_type varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY link_id (link_id),
            KEY action_type (action_type),
            KEY ip_address (ip_address),
            KEY domain (domain),
            KEY is_authorized (is_authorized),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table pour les permissions (IPs et domaines)
        $table_permissions = $wpdb->prefix . 'sml_permissions';
        $sql_permissions = "CREATE TABLE $table_permissions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            type varchar(10) NOT NULL,
            value varchar(255) NOT NULL,
            permission_type varchar(10) NOT NULL,
            actions text,
            description text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY permission_type (permission_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Table pour les notifications
        $table_notifications = $wpdb->prefix . 'sml_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data text,
            is_read tinyint(1) DEFAULT 0,
            user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_read (is_read),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table pour les uploads frontend
        $table_uploads = $wpdb->prefix . 'sml_frontend_uploads';
        $sql_uploads = "CREATE TABLE $table_uploads (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            media_id bigint(20) NOT NULL,
            author_id bigint(20) NOT NULL,
            caption text,
            description text,
            copyright varchar(255),
            expiry_date datetime,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            approved_at datetime DEFAULT NULL,
            approved_by bigint(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY media_id (media_id),
            KEY author_id (author_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table pour les scans externes
        $table_external_usage = $wpdb->prefix . 'sml_external_usage';
        $sql_external_usage = "CREATE TABLE $table_external_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            media_id bigint(20) NOT NULL,
            external_url text NOT NULL,
            domain varchar(255) NOT NULL,
            first_detected datetime DEFAULT CURRENT_TIMESTAMP,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            scan_method varchar(50),
            is_authorized tinyint(1) DEFAULT 0,
            violation_score int(3) DEFAULT 0,
            PRIMARY KEY (id),
            KEY media_id (media_id),
            KEY domain (domain),
            KEY status (status),
            KEY is_authorized (is_authorized),
            KEY first_detected (first_detected)
        ) $charset_collate;";
        
        // Table pour les statistiques
        $table_stats = $wpdb->prefix . 'sml_statistics';
        $sql_stats = "CREATE TABLE $table_stats (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            stat_type varchar(50) NOT NULL,
            stat_key varchar(100) NOT NULL,
            stat_value text NOT NULL,
            period varchar(20) NOT NULL,
            date_recorded date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_stat (stat_type, stat_key, period, date_recorded),
            KEY stat_type (stat_type),
            KEY period (period),
            KEY date_recorded (date_recorded)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_formats);
        dbDelta($sql_links);
        dbDelta($sql_tracking);
        dbDelta($sql_permissions);
        dbDelta($sql_notifications);
        dbDelta($sql_uploads);
        dbDelta($sql_external_usage);
        dbDelta($sql_stats);
        
        // Insérer les formats par défaut
        self::insert_default_formats();
        
        // Insérer les permissions par défaut
        self::insert_default_permissions();
    }
    
    /**
     * Insertion des formats par défaut
     */
    private static function insert_default_formats() {
        global $wpdb;
        
        $table_formats = $wpdb->prefix . 'sml_media_formats';
        
        // Vérifier si des formats existent déjà
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_formats");
        if ($existing > 0) {
            return;
        }
        
        $default_formats = array(
            array(
                'name' => 'web_small',
                'description' => 'Format web petit (400px)',
                'width' => 400,
                'height' => null,
                'quality' => 85,
                'format' => 'jpg',
                'crop_mode' => 'resize',
                'type' => 'web'
            ),
            array(
                'name' => 'web_medium',
                'description' => 'Format web moyen (800px)',
                'width' => 800,
                'height' => null,
                'quality' => 85,
                'format' => 'jpg',
                'crop_mode' => 'resize',
                'type' => 'web'
            ),
            array(
                'name' => 'web_large',
                'description' => 'Format web grand (1200px)',
                'width' => 1200,
                'height' => null,
                'quality' => 90,
                'format' => 'jpg',
                'crop_mode' => 'resize',
                'type' => 'web'
            ),
            array(
                'name' => 'print_hd',
                'description' => 'Format impression HD (300dpi)',
                'width' => 2400,
                'height' => null,
                'quality' => 95,
                'format' => 'jpg',
                'crop_mode' => 'resize',
                'type' => 'print'
            ),
            array(
                'name' => 'social_square',
                'description' => 'Format social carré (1080x1080)',
                'width' => 1080,
                'height' => 1080,
                'quality' => 85,
                'format' => 'jpg',
                'crop_mode' => 'crop',
                'crop_position' => 'center',
                'type' => 'social'
            ),
            array(
                'name' => 'social_story',
                'description' => 'Format social story (1080x1920)',
                'width' => 1080,
                'height' => 1920,
                'quality' => 85,
                'format' => 'jpg',
                'crop_mode' => 'crop',
                'crop_position' => 'center',
                'type' => 'social'
            )
        );
        
        foreach ($default_formats as $format) {
            $wpdb->insert($table_formats, $format);
        }
    }
    
    /**
     * Insertion des permissions par défaut
     */
    private static function insert_default_permissions() {
        global $wpdb;
        
        $table_permissions = $wpdb->prefix . 'sml_permissions';
        
        // Vérifier si des permissions existent déjà
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_permissions");
        if ($existing > 0) {
            return;
        }
        
        $default_permissions = array(
            array(
                'type' => 'ip',
                'value' => '127.0.0.1',
                'permission_type' => 'whitelist',
                'actions' => json_encode(array('download', 'copy')),
                'description' => 'Localhost'
            ),
            array(
                'type' => 'domain',
                'value' => get_site_url(),
                'permission_type' => 'whitelist',
                'actions' => json_encode(array('download', 'copy')),
                'description' => 'Site local'
            )
        );
        
        foreach ($default_permissions as $permission) {
            $wpdb->insert($table_permissions, $permission);
        }
    }
    
    /**
     * Suppression des tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'sml_media_formats',
            $wpdb->prefix . 'sml_secure_links',
            $wpdb->prefix . 'sml_tracking',
            $wpdb->prefix . 'sml_permissions',
            $wpdb->prefix . 'sml_notifications',
            $wpdb->prefix . 'sml_frontend_uploads',
            $wpdb->prefix . 'sml_external_usage',
            $wpdb->prefix . 'sml_statistics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Optimisation des tables
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'sml_media_formats',
            $wpdb->prefix . 'sml_secure_links',
            $wpdb->prefix . 'sml_tracking',
            $wpdb->prefix . 'sml_permissions',
            $wpdb->prefix . 'sml_notifications',
            $wpdb->prefix . 'sml_frontend_uploads',
            $wpdb->prefix . 'sml_external_usage',
            $wpdb->prefix . 'sml_statistics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
    
    /**
     * Nettoyage des anciennes données
     */
    public static function cleanup_old_data($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Supprimer les anciens trackings
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_tracking WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Supprimer les anciennes notifications lues
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_notifications WHERE is_read = 1 AND created_at < %s",
            $cutoff_date
        ));
        
        // Supprimer les anciennes statistiques
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_statistics WHERE created_at < %s",
            $cutoff_date
        ));
        
        // Supprimer les liens expirés inactifs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_secure_links WHERE expires_at < %s AND is_active = 0",
            $cutoff_date
        ));
    }
    
    /**
     * Obtenir les statistiques de la base de données
     */
    public static function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        $tables = array(
            'media_formats' => $wpdb->prefix . 'sml_media_formats',
            'secure_links' => $wpdb->prefix . 'sml_secure_links',
            'tracking' => $wpdb->prefix . 'sml_tracking',
            'permissions' => $wpdb->prefix . 'sml_permissions',
            'notifications' => $wpdb->prefix . 'sml_notifications',
            'frontend_uploads' => $wpdb->prefix . 'sml_frontend_uploads',
            'external_usage' => $wpdb->prefix . 'sml_external_usage',
            'statistics' => $wpdb->prefix . 'sml_statistics'
        );
        
        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            $stats[$key] = intval($count);
        }
        
        return $stats;
    }
    
    /**
     * Vérifier l'intégrité de la base de données
     */
    public static function check_database_integrity() {
        global $wpdb;
        
        $issues = array();
        
        // Vérifier les liens orphelins
        $orphaned_links = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}sml_secure_links sl
            LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
            WHERE p.ID IS NULL
        ");
        
        if ($orphaned_links > 0) {
            $issues[] = sprintf(__('%d liens orphelins détectés', 'secure-media-link'), $orphaned_links);
        }
        
        // Vérifier les trackings orphelins
        $orphaned_tracking = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}sml_tracking t
            LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
            WHERE sl.id IS NULL
        ");
        
        if ($orphaned_tracking > 0) {
            $issues[] = sprintf(__('%d entrées de tracking orphelines détectées', 'secure-media-link'), $orphaned_tracking);
        }
        
        // Vérifier les formats orphelins
        $orphaned_formats = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}sml_secure_links sl
            LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
            WHERE mf.id IS NULL
        ");
        
        if ($orphaned_formats > 0) {
            $issues[] = sprintf(__('%d liens avec formats inexistants détectés', 'secure-media-link'), $orphaned_formats);
        }
        
        return $issues;
    }
    
    /**
     * Réparer les problèmes d'intégrité
     */
    public static function repair_database_integrity() {
        global $wpdb;
        
        $repaired = 0;
        
        // Supprimer les liens orphelins
        $result = $wpdb->query("
            DELETE sl FROM {$wpdb->prefix}sml_secure_links sl
            LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
            WHERE p.ID IS NULL
        ");
        $repaired += $result;
        
        // Supprimer les trackings orphelins
        $result = $wpdb->query("
            DELETE t FROM {$wpdb->prefix}sml_tracking t
            LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
            WHERE sl.id IS NULL
        ");
        $repaired += $result;
        
        // Supprimer les liens avec formats inexistants
        $result = $wpdb->query("
            DELETE sl FROM {$wpdb->prefix}sml_secure_links sl
            LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
            WHERE mf.id IS NULL
        ");
        $repaired += $result;
        
        return $repaired;
    }
}