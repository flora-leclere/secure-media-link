<?php
/**
 * Plugin Name: Secure Media Link
 * Description: Plugin WordPress pour générer des liens signés, sécurisés et temporaires pour les médias avec tracking et gestion des autorisations
 * Version: 1.0.0
 * Author: Votre Nom
 * Text Domain: secure-media-link
 * Domain Path: /languages
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('SML_PLUGIN_FILE', __FILE__);
define('SML_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SML_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SML_PLUGIN_VERSION', '1.0.0');

/**
 * Classe principale du plugin Secure Media Link
 */
class SecureMediaLink {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_sml_generate_link', array($this, 'ajax_generate_link'));
        add_action('wp_ajax_sml_track_usage', array($this, 'ajax_track_usage'));
        add_action('wp_ajax_nopriv_sml_track_usage', array($this, 'ajax_track_usage'));
        
        // Hooks pour la médiathèque
        add_filter('attachment_fields_to_edit', array($this, 'add_media_fields'), 10, 2);
        add_action('edit_attachment', array($this, 'save_media_fields'));
        
        // Hooks pour les shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Hooks pour les tâches cron
        add_action('sml_scan_media_usage', array($this, 'scan_media_usage'));
        add_action('sml_cleanup_expired_links', array($this, 'cleanup_expired_links'));
        
        // Hook pour la désactivation
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Chargement des dépendances
     */
    private function load_dependencies() {
        require_once SML_PLUGIN_DIR . 'includes/class-sml-database.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-crypto.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-media-formats.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-permissions.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-tracking.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-notifications.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-api.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-cache.php';
        require_once SML_PLUGIN_DIR . 'includes/class-sml-shortcodes.php';
        require_once SML_PLUGIN_DIR . 'admin/class-sml-admin.php';
        require_once SML_PLUGIN_DIR . 'frontend/class-sml-frontend.php';
    }
    
    /**
     * Chargement des traductions
     */
    public function load_textdomain() {
        load_plugin_textdomain('secure-media-link', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Initialisation du plugin
     */
    public function init() {
        // Créer les tables en base si nécessaire
        SML_Database::create_tables();
        
        // Initialiser les composants
        SML_Crypto::init();
        SML_Media_Formats::init();
        SML_Permissions::init();
        SML_Tracking::init();
        SML_Notifications::init();
        SML_API::init();
        SML_Cache::init();
        
        // Programmer les tâches cron
        $this->schedule_cron_jobs();
    }
    
    /**
     * Ajout du menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Secure Media Link', 'secure-media-link'),
            __('Secure Media', 'secure-media-link'),
            'manage_options',
            'secure-media-link',
            array('SML_Admin', 'dashboard_page'),
            'dashicons-shield',
            30
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Tableau de bord', 'secure-media-link'),
            __('Tableau de bord', 'secure-media-link'),
            'manage_options',
            'secure-media-link',
            array('SML_Admin', 'dashboard_page')
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Médiathèque sécurisée', 'secure-media-link'),
            __('Médiathèque', 'secure-media-link'),
            'manage_options',
            'sml-media-library',
            array('SML_Admin', 'media_library_page')
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Formats de média', 'secure-media-link'),
            __('Formats', 'secure-media-link'),
            'manage_options',
            'sml-media-formats',
            array('SML_Admin', 'media_formats_page')
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Tracking & Statistiques', 'secure-media-link'),
            __('Tracking', 'secure-media-link'),
            'manage_options',
            'sml-tracking',
            array('SML_Admin', 'tracking_page')
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Autorisations', 'secure-media-link'),
            __('Autorisations', 'secure-media-link'),
            'manage_options',
            'sml-permissions',
            array('SML_Admin', 'permissions_page')
        );
        
        add_submenu_page(
            'secure-media-link',
            __('Paramètres', 'secure-media-link'),
            __('Paramètres', 'secure-media-link'),
            'manage_options',
            'sml-settings',
            array('SML_Admin', 'settings_page')
        );
    }
    
    /**
     * Enregistrement des scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'secure-media-link') !== false || strpos($hook, 'sml-') !== false) {
            wp_enqueue_script('sml-admin', SML_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SML_PLUGIN_VERSION, true);
            wp_enqueue_style('sml-admin', SML_PLUGIN_URL . 'assets/css/admin.css', array(), SML_PLUGIN_VERSION);
            
            wp_localize_script('sml-admin', 'sml_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sml_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Êtes-vous sûr de vouloir supprimer cet élément ?', 'secure-media-link'),
                    'link_copied' => __('Lien copié dans le presse-papiers', 'secure-media-link'),
                    'error_occurred' => __('Une erreur est survenue', 'secure-media-link')
                )
            ));
        }
    }
    
    /**
     * Enregistrement des scripts frontend
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('sml-frontend', SML_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SML_PLUGIN_VERSION, true);
        wp_enqueue_style('sml-frontend', SML_PLUGIN_URL . 'assets/css/frontend.css', array(), SML_PLUGIN_VERSION);
        
        wp_localize_script('sml-frontend', 'sml_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sml_nonce')
        ));
    }
    
    /**
     * Enregistrement des shortcodes
     */
    public function register_shortcodes() {
        SML_Shortcodes::register_all();
    }
    
    /**
     * Ajout de champs personnalisés à la médiathèque
     */
    public function add_media_fields($form_fields, $post) {
        $form_fields['sml_copyright'] = array(
            'label' => __('Copyright', 'secure-media-link'),
            'input' => 'text',
            'value' => get_post_meta($post->ID, '_sml_copyright', true)
        );
        
        $form_fields['sml_expiry_date'] = array(
            'label' => __('Date d\'expiration', 'secure-media-link'),
            'input' => 'text',
            'value' => get_post_meta($post->ID, '_sml_expiry_date', true),
            'helps' => __('Format: YYYY-MM-DD HH:MM:SS', 'secure-media-link')
        );
        
        return $form_fields;
    }
    
    /**
     * Sauvegarde des champs personnalisés
     */
    public function save_media_fields($post_id) {
        if (isset($_POST['attachments'][$post_id]['sml_copyright'])) {
            update_post_meta($post_id, '_sml_copyright', sanitize_text_field($_POST['attachments'][$post_id]['sml_copyright']));
        }
        
        if (isset($_POST['attachments'][$post_id]['sml_expiry_date'])) {
            $expiry_date = sanitize_text_field($_POST['attachments'][$post_id]['sml_expiry_date']);
            if (!empty($expiry_date)) {
                update_post_meta($post_id, '_sml_expiry_date', $expiry_date);
            }
        }
    }
    
    /**
     * AJAX pour générer un lien sécurisé
     */
    public function ajax_generate_link() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Accès refusé', 'secure-media-link'));
        }
        
        $media_id = intval($_POST['media_id']);
        $format_id = intval($_POST['format_id']);
        $expiry_date = sanitize_text_field($_POST['expiry_date']);
        
        $link = SML_Crypto::generate_secure_link($media_id, $format_id, $expiry_date);
        
        if ($link) {
            wp_send_json_success(array('link' => $link));
        } else {
            wp_send_json_error(__('Erreur lors de la génération du lien', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX pour tracker l'utilisation
     */
    public function ajax_track_usage() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        $link_id = intval($_POST['link_id']);
        $action = sanitize_text_field($_POST['action_type']);
        
        SML_Tracking::track_usage($link_id, $action);
        
        wp_send_json_success();
    }
    
    /**
     * Programmation des tâches cron
     */
    private function schedule_cron_jobs() {
        if (!wp_next_scheduled('sml_scan_media_usage')) {
            wp_schedule_event(time(), 'daily', 'sml_scan_media_usage');
        }
        
        if (!wp_next_scheduled('sml_cleanup_expired_links')) {
            wp_schedule_event(time(), 'hourly', 'sml_cleanup_expired_links');
        }
    }
    
    /**
     * Scan automatique de l'utilisation des médias
     */
    public function scan_media_usage() {
        SML_Tracking::scan_external_usage();
    }
    
    /**
     * Nettoyage des liens expirés
     */
    public function cleanup_expired_links() {
        SML_Cache::cleanup_expired_links();
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        wp_clear_scheduled_hook('sml_scan_media_usage');
        wp_clear_scheduled_hook('sml_cleanup_expired_links');
        SML_Cache::flush_all();
    }
}

// Initialiser le plugin
add_action('plugins_loaded', array('SecureMediaLink', 'get_instance'));

/**
 * Activation du plugin
 */
function sml_activate() {
    SML_Database::create_tables();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'sml_activate');

/**
 * Désactivation du plugin
 */
function sml_deactivate() {
    wp_clear_scheduled_hook('sml_scan_media_usage');
    wp_clear_scheduled_hook('sml_cleanup_expired_links');
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'sml_deactivate');

/**
 * Désinstallation du plugin
 */
function sml_uninstall() {
    // Supprimer les tables
    SML_Database::drop_tables();
    
    // Supprimer les options
    delete_option('sml_settings');
    delete_option('sml_key_pair');
    
    // Supprimer les métadonnées
    delete_metadata('post', 0, '_sml_copyright', '', true);
    delete_metadata('post', 0, '_sml_expiry_date', '', true);
    
    // Nettoyer le cache
    SML_Cache::flush_all();
}
register_uninstall_hook(__FILE__, 'sml_uninstall');