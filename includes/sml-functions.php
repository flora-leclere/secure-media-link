<?php
/**
 * Fonctions utilitaires globales pour Secure Media Link
 * includes/sml-functions.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtenir les paramètres du plugin avec valeurs par défaut
 */
function sml_get_settings($key = null, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = get_option('sml_settings', array());
        
        // Valeurs par défaut
        $defaults = array(
            'default_expiry_years' => 3,
            'custom_domain' => '',
            'auto_blocking_enabled' => false,
            'auto_blocking_threshold' => 10,
            'auto_blocking_time_window' => 24,
            'external_scan_enabled' => false,
            'external_scan_frequency' => 'daily',
            'google_cse_key' => '',
            'google_cse_id' => '',
            'notification_email' => get_option('admin_email'),
            'violation_notifications' => true,
            'expiry_notifications' => true,
            'expiry_notice_days' => array(30, 7, 1),
            'cache_enabled' => true,
            'cache_duration' => 3600,
            'api_enabled' => false,
            'api_rate_limit' => 1000
        );
        
        $settings = wp_parse_args($settings, $defaults);
    }
    
    if ($key === null) {
        return $settings;
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Générer un lien sécurisé pour un média
 */
function sml_generate_secure_link($media_id, $format_id = null, $expiry_date = null) {
    // Si aucun format spécifié, utiliser le format web_medium par défaut
    if (!$format_id) {
        global $wpdb;
        $format_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sml_media_formats WHERE name = 'web_medium' LIMIT 1");
        
        if (!$format_id) {
            // Créer le format par défaut s'il n'existe pas
            $format_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}sml_media_formats ORDER BY id ASC LIMIT 1");
        }
    }
    
    if (!$format_id) {
        return false;
    }
    
    return SML_Crypto::generate_secure_link($media_id, $format_id, $expiry_date);
}

/**
 * Vérifier si un utilisateur peut accéder à un média
 */
function sml_user_can_access_media($media_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $media = get_post($media_id);
    
    if (!$media || $media->post_type !== 'attachment') {
        return false;
    }
    
    // Les administrateurs ont accès à tout
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    
    // Les propriétaires du média ont accès
    if ($media->post_author == $user_id) {
        return true;
    }
    
    // Vérifier les permissions spéciales
    $special_access = get_post_meta($media_id, '_sml_special_access', true);
    if (is_array($special_access) && in_array($user_id, $special_access)) {
        return true;
    }
    
    return false;
}

/**
 * Formater la taille d'un fichier de manière lisible
 */
function sml_format_file_size($bytes, $precision = 2) {
    if ($bytes === 0) {
        return '0 B';
    }
    
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $base = log($bytes, 1024);
    $unit_index = floor($base);
    
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[$unit_index];
}

/**
 * Obtenir le statut d'un lien sécurisé
 */
function sml_get_link_status($link_id) {
    global $wpdb;
    
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sml_secure_links WHERE id = %d",
        $link_id
    ));
    
    if (!$link) {
        return 'not_found';
    }
    
    if (!$link->is_active) {
        return 'inactive';
    }
    
    if (strtotime($link->expires_at) < time()) {
        return 'expired';
    }
    
    return 'active';
}

/**
 * Obtenir l'URL de téléchargement pour un lien
 */
function sml_get_download_url($link_id) {
    return SML_Rewrite::get_endpoint_url('direct_download', array('link_id' => $link_id));
}

/**
 * Vérifier si une IP est dans une plage CIDR
 */
function sml_ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($subnet, $bits) = explode('/', $range);
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
        !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    
    return ($ip_long & $mask) === ($subnet_long & $mask);
}

/**
 * Valider une adresse email
 */
function sml_is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider une URL
 */
function sml_is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Nettoyer et valider un nom de domaine
 */
function sml_clean_domain($domain) {
    // Supprimer le protocole
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    
    // Supprimer www.
    $domain = preg_replace('/^www\./', '', $domain);
    
    // Supprimer le chemin et les paramètres
    $domain = explode('/', $domain)[0];
    $domain = explode('?', $domain)[0];
    $domain = explode('#', $domain)[0];
    
    return strtolower(trim($domain));
}

/**
 * Obtenir les informations de géolocalisation d'une IP
 */
function sml_get_ip_location($ip_address) {
    static $cache = array();
    
    if (isset($cache[$ip_address])) {
        return $cache[$ip_address];
    }
    
    // Vérifier d'abord le cache WordPress
    $cache_key = 'sml_ip_location_' . md5($ip_address);
    $location = wp_cache_get($cache_key);
    
    if ($location !== false) {
        $cache[$ip_address] = $location;
        return $location;
    }
    
    // Utiliser un service de géolocalisation
    $location = SML_Permissions::get_ip_geolocation($ip_address);
    
    if ($location) {
        // Mettre en cache pendant 24 heures
        wp_cache_set($cache_key, $location, '', DAY_IN_SECONDS);
        $cache[$ip_address] = $location;
    }
    
    return $location;
}

/**
 * Générer un hash unique pour les liens
 */
function sml_generate_hash($data = null) {
    if (!$data) {
        $data = wp_generate_password(32, false);
    }
    
    return hash('sha256', $data . time() . wp_generate_password(16, false));
}

/**
 * Vérifier si le plugin est configuré correctement
 */
function sml_is_properly_configured() {
    // Vérifier la paire de clés
    $key_pair = get_option('sml_key_pair');
    if (empty($key_pair['private_key']) || empty($key_pair['public_key'])) {
        return false;
    }
    
    // Vérifier les tables de base de données
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'sml_media_formats',
        $wpdb->prefix . 'sml_secure_links',
        $wpdb->prefix . 'sml_tracking',
        $wpdb->prefix . 'sml_permissions'
    );
    
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }
    }
    
    return true;
}

/**
 * Obtenir les statistiques rapides du plugin
 */
function sml_get_quick_stats() {
    global $wpdb;
    
    static $stats = null;
    
    if ($stats === null) {
        $stats = array(
            'total_media' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"),
            'total_links' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links"),
            'active_links' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE is_active = 1 AND expires_at > NOW()"),
            'total_downloads' => $wpdb->get_var("SELECT SUM(download_count) FROM {$wpdb->prefix}sml_secure_links"),
            'violations_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking WHERE is_authorized = 0 AND DATE(created_at) = CURDATE()")
        );
        
        // Calculer les pourcentages
        if ($stats['total_links'] > 0) {
            $stats['active_percentage'] = round(($stats['active_links'] / $stats['total_links']) * 100, 1);
        } else {
            $stats['active_percentage'] = 0;
        }
    }
    
    return $stats;
}

/**
 * Logger les erreurs du plugin
 */
function sml_log_error($message, $context = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('c'),
        'message' => $message,
        'context' => $context,
        'backtrace' => wp_debug_backtrace_summary()
    );
    
    error_log('[SML] ' . json_encode($log_entry));
}

/**
 * Envoyer une notification par email
 */
function sml_send_notification_email($to, $subject, $message, $headers = array()) {
    $settings = sml_get_settings();
    
    if (!$settings['violation_notifications']) {
        return false;
    }
    
    $default_headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    $headers = array_merge($default_headers, $headers);
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Obtenir l'agent utilisateur simplifié
 */
function sml_get_simplified_user_agent($user_agent = null) {
    if (!$user_agent) {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
    
    if (empty($user_agent)) {
        return 'Unknown';
    }
    
    // Détecter les navigateurs principaux
    $browsers = array(
        'Chrome' => '/Chrome\/([0-9\.]+)/',
        'Firefox' => '/Firefox\/([0-9\.]+)/',
        'Safari' => '/Version\/([0-9\.]+).*Safari/',
        'Edge' => '/Edge\/([0-9\.]+)/',
        'Opera' => '/Opera\/([0-9\.]+)/',
        'Internet Explorer' => '/MSIE ([0-9\.]+)/'
    );
    
    foreach ($browsers as $browser => $pattern) {
        if (preg_match($pattern, $user_agent, $matches)) {
            return $browser . ' ' . $matches[1];
        }
    }
    
    // Détecter les bots
    $bots = array('bot', 'crawler', 'spider', 'scraper');
    foreach ($bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return 'Bot/Crawler';
        }
    }
    
    return 'Other';
}

/**
 * Formater une date de manière relative
 */
function sml_time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return __('Il y a quelques secondes', 'secure-media-link');
    }
    
    $time_chunks = array(
        array(60 * 60 * 24 * 365, __('an', 'secure-media-link'), __('ans', 'secure-media-link')),
        array(60 * 60 * 24 * 30, __('mois', 'secure-media-link'), __('mois', 'secure-media-link')),
        array(60 * 60 * 24 * 7, __('semaine', 'secure-media-link'), __('semaines', 'secure-media-link')),
        array(60 * 60 * 24, __('jour', 'secure-media-link'), __('jours', 'secure-media-link')),
        array(60 * 60, __('heure', 'secure-media-link'), __('heures', 'secure-media-link')),
        array(60, __('minute', 'secure-media-link'), __('minutes', 'secure-media-link'))
    );
    
    for ($i = 0; $i < count($time_chunks); $i++) {
        $seconds = $time_chunks[$i][0];
        $name_singular = $time_chunks[$i][1];
        $name_plural = $time_chunks[$i][2];
        
        if (($count = floor($time / $seconds)) != 0) {
            break;
        }
    }
    
    $name = $count == 1 ? $name_singular : $name_plural;
    return sprintf(__('Il y a %d %s', 'secure-media-link'), $count, $name);
}

/**
 * Générer un token CSRF
 */
function sml_generate_csrf_token($action = 'default') {
    $user_id = get_current_user_id();
    $session_token = wp_get_session_token();
    
    return wp_hash($action . $user_id . $session_token . wp_nonce_tick(), 'nonce');
}

/**
 * Vérifier un token CSRF
 */
function sml_verify_csrf_token($token, $action = 'default') {
    $expected = sml_generate_csrf_token($action);
    return hash_equals($expected, $token);
}

/**
 * Obtenir les pays les plus fréquents dans les statistiques
 */
function sml_get_top_countries($limit = 10) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT country, COUNT(*) as count
         FROM {$wpdb->prefix}sml_tracking 
         WHERE country IS NOT NULL 
         AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY country
         ORDER BY count DESC
         LIMIT %d",
        $limit
    ));
}

/**
 * Convertir les codes pays en noms complets
 */
function sml_get_country_name($country_code) {
    static $countries = null;
    
    if ($countries === null) {
        $countries = array(
            'AD' => __('Andorre', 'secure-media-link'),
            'AE' => __('Émirats arabes unis', 'secure-media-link'),
            'AF' => __('Afghanistan', 'secure-media-link'),
            'AG' => __('Antigua-et-Barbuda', 'secure-media-link'),
            'AI' => __('Anguilla', 'secure-media-link'),
            'AL' => __('Albanie', 'secure-media-link'),
            'AM' => __('Arménie', 'secure-media-link'),
            'AO' => __('Angola', 'secure-media-link'),
            'AR' => __('Argentine', 'secure-media-link'),
            'AS' => __('Samoa américaines', 'secure-media-link'),
            'AT' => __('Autriche', 'secure-media-link'),
            'AU' => __('Australie', 'secure-media-link'),
            'AW' => __('Aruba', 'secure-media-link'),
            'AZ' => __('Azerbaïdjan', 'secure-media-link'),
            'BA' => __('Bosnie-Herzégovine', 'secure-media-link'),
            'BB' => __('Barbade', 'secure-media-link'),
            'BD' => __('Bangladesh', 'secure-media-link'),
            'BE' => __('Belgique', 'secure-media-link'),
            'BF' => __('Burkina Faso', 'secure-media-link'),
            'BG' => __('Bulgarie', 'secure-media-link'),
            'BH' => __('Bahreïn', 'secure-media-link'),
            'BI' => __('Burundi', 'secure-media-link'),
            'BJ' => __('Bénin', 'secure-media-link'),
            'BM' => __('Bermudes', 'secure-media-link'),
            'BN' => __('Brunei', 'secure-media-link'),
            'BO' => __('Bolivie', 'secure-media-link'),
            'BR' => __('Brésil', 'secure-media-link'),
            'BS' => __('Bahamas', 'secure-media-link'),
            'BT' => __('Bhoutan', 'secure-media-link'),
            'BW' => __('Botswana', 'secure-media-link'),
            'BY' => __('Biélorussie', 'secure-media-link'),
            'BZ' => __('Belize', 'secure-media-link'),
            'CA' => __('Canada', 'secure-media-link'),
            'CD' => __('République démocratique du Congo', 'secure-media-link'),
            'CF' => __('République centrafricaine', 'secure-media-link'),
            'CG' => __('République du Congo', 'secure-media-link'),
            'CH' => __('Suisse', 'secure-media-link'),
            'CI' => __('Côte d\'Ivoire', 'secure-media-link'),
            'CK' => __('Îles Cook', 'secure-media-link'),
            'CL' => __('Chili', 'secure-media-link'),
            'CM' => __('Cameroun', 'secure-media-link'),
            'CN' => __('Chine', 'secure-media-link'),
            'CO' => __('Colombie', 'secure-media-link'),
            'CR' => __('Costa Rica', 'secure-media-link'),
            'CU' => __('Cuba', 'secure-media-link'),
            'CV' => __('Cap-Vert', 'secure-media-link'),
            'CY' => __('Chypre', 'secure-media-link'),
            'CZ' => __('République tchèque', 'secure-media-link'),
            'DE' => __('Allemagne', 'secure-media-link'),
            'DJ' => __('Djibouti', 'secure-media-link'),
            'DK' => __('Danemark', 'secure-media-link'),
            'DM' => __('Dominique', 'secure-media-link'),
            'DO' => __('République dominicaine', 'secure-media-link'),
            'DZ' => __('Algérie', 'secure-media-link'),
            'EC' => __('Équateur', 'secure-media-link'),
            'EE' => __('Estonie', 'secure-media-link'),
            'EG' => __('Égypte', 'secure-media-link'),
            'ER' => __('Érythrée', 'secure-media-link'),
            'ES' => __('Espagne', 'secure-media-link'),
            'ET' => __('Éthiopie', 'secure-media-link'),
            'FI' => __('Finlande', 'secure-media-link'),
            'FJ' => __('Fidji', 'secure-media-link'),
            'FK' => __('Îles Malouines', 'secure-media-link'),
            'FM' => __('Micronésie', 'secure-media-link'),
            'FO' => __('Îles Féroé', 'secure-media-link'),
            'FR' => __('France', 'secure-media-link'),
            'GA' => __('Gabon', 'secure-media-link'),
            'GB' => __('Royaume-Uni', 'secure-media-link'),
            'GD' => __('Grenade', 'secure-media-link'),
            'GE' => __('Géorgie', 'secure-media-link'),
            'GF' => __('Guyane française', 'secure-media-link'),
            'GH' => __('Ghana', 'secure-media-link'),
            'GI' => __('Gibraltar', 'secure-media-link'),
            'GL' => __('Groenland', 'secure-media-link'),
            'GM' => __('Gambie', 'secure-media-link'),
            'GN' => __('Guinée', 'secure-media-link'),
            'GP' => __('Guadeloupe', 'secure-media-link'),
            'GQ' => __('Guinée équatoriale', 'secure-media-link'),
            'GR' => __('Grèce', 'secure-media-link'),
            'GT' => __('Guatemala', 'secure-media-link'),
            'GU' => __('Guam', 'secure-media-link'),
            'GW' => __('Guinée-Bissau', 'secure-media-link'),
            'GY' => __('Guyana', 'secure-media-link'),
            'HK' => __('Hong Kong', 'secure-media-link'),
            'HN' => __('Honduras', 'secure-media-link'),
            'HR' => __('Croatie', 'secure-media-link'),
            'HT' => __('Haïti', 'secure-media-link'),
            'HU' => __('Hongrie', 'secure-media-link'),
            'ID' => __('Indonésie', 'secure-media-link'),
            'IE' => __('Irlande', 'secure-media-link'),
            'IL' => __('Israël', 'secure-media-link'),
            'IM' => __('Île de Man', 'secure-media-link'),
            'IN' => __('Inde', 'secure-media-link'),
            'IO' => __('Territoire britannique de l\'océan Indien', 'secure-media-link'),
            'IQ' => __('Irak', 'secure-media-link'),
            'IR' => __('Iran', 'secure-media-link'),
            'IS' => __('Islande', 'secure-media-link'),
            'IT' => __('Italie', 'secure-media-link'),
            'JE' => __('Jersey', 'secure-media-link'),
            'JM' => __('Jamaïque', 'secure-media-link'),
            'JO' => __('Jordanie', 'secure-media-link'),
            'JP' => __('Japon', 'secure-media-link'),
            'KE' => __('Kenya', 'secure-media-link'),
            'KG' => __('Kirghizistan', 'secure-media-link'),
            'KH' => __('Cambodge', 'secure-media-link'),
            'KI' => __('Kiribati', 'secure-media-link'),
            'KM' => __('Comores', 'secure-media-link'),
            'KN' => __('Saint-Christophe-et-Niévès', 'secure-media-link'),
            'KP' => __('Corée du Nord', 'secure-media-link'),
            'KR' => __('Corée du Sud', 'secure-media-link'),
            'KW' => __('Koweït', 'secure-media-link'),
            'KY' => __('Îles Caïmans', 'secure-media-link'),
            'KZ' => __('Kazakhstan', 'secure-media-link'),
            'LA' => __('Laos', 'secure-media-link'),
            'LB' => __('Liban', 'secure-media-link'),
            'LC' => __('Sainte-Lucie', 'secure-media-link'),
            'LI' => __('Liechtenstein', 'secure-media-link'),
            'LK' => __('Sri Lanka', 'secure-media-link'),
            'LR' => __('Libéria', 'secure-media-link'),
            'LS' => __('Lesotho', 'secure-media-link'),
            'LT' => __('Lituanie', 'secure-media-link'),
            'LU' => __('Luxembourg', 'secure-media-link'),
            'LV' => __('Lettonie', 'secure-media-link'),
            'LY' => __('Libye', 'secure-media-link'),
            'MA' => __('Maroc', 'secure-media-link'),
            'MC' => __('Monaco', 'secure-media-link'),
            'MD' => __('Moldavie', 'secure-media-link'),
            'ME' => __('Monténégro', 'secure-media-link'),
            'MG' => __('Madagascar', 'secure-media-link'),
            'MH' => __('Îles Marshall', 'secure-media-link'),
            'MK' => __('Macédoine du Nord', 'secure-media-link'),
            'ML' => __('Mali', 'secure-media-link'),
            'MM' => __('Myanmar', 'secure-media-link'),
            'MN' => __('Mongolie', 'secure-media-link'),
            'MO' => __('Macao', 'secure-media-link'),
            'MP' => __('Îles Mariannes du Nord', 'secure-media-link'),
            'MQ' => __('Martinique', 'secure-media-link'),
            'MR' => __('Mauritanie', 'secure-media-link'),
            'MS' => __('Montserrat', 'secure-media-link'),
            'MT' => __('Malte', 'secure-media-link'),
            'MU' => __('Maurice', 'secure-media-link'),
            'MV' => __('Maldives', 'secure-media-link'),
            'MW' => __('Malawi', 'secure-media-link'),
            'MX' => __('Mexique', 'secure-media-link'),
            'MY' => __('Malaisie', 'secure-media-link'),
            'MZ' => __('Mozambique', 'secure-media-link'),
            'NA' => __('Namibie', 'secure-media-link'),
            'NC' => __('Nouvelle-Calédonie', 'secure-media-link'),
            'NE' => __('Niger', 'secure-media-link'),
            'NF' => __('Île Norfolk', 'secure-media-link'),
            'NG' => __('Nigeria', 'secure-media-link'),
            'NI' => __('Nicaragua', 'secure-media-link'),
            'NL' => __('Pays-Bas', 'secure-media-link'),
            'NO' => __('Norvège', 'secure-media-link'),
            'NP' => __('Népal', 'secure-media-link'),
            'NR' => __('Nauru', 'secure-media-link'),
            'NU' => __('Niue', 'secure-media-link'),
            'NZ' => __('Nouvelle-Zélande', 'secure-media-link'),
            'OM' => __('Oman', 'secure-media-link'),
            'PA' => __('Panama', 'secure-media-link'),
            'PE' => __('Pérou', 'secure-media-link'),
            'PF' => __('Polynésie française', 'secure-media-link'),
            'PG' => __('Papouasie-Nouvelle-Guinée', 'secure-media-link'),
            'PH' => __('Philippines', 'secure-media-link'),
            'PK' => __('Pakistan', 'secure-media-link'),
            'PL' => __('Pologne', 'secure-media-link'),
            'PM' => __('Saint-Pierre-et-Miquelon', 'secure-media-link'),
            'PN' => __('Îles Pitcairn', 'secure-media-link'),
            'PR' => __('Porto Rico', 'secure-media-link'),
            'PS' => __('Palestine', 'secure-media-link'),
            'PT' => __('Portugal', 'secure-media-link'),
            'PW' => __('Palaos', 'secure-media-link'),
            'PY' => __('Paraguay', 'secure-media-link'),
            'QA' => __('Qatar', 'secure-media-link'),
            'RE' => __('La Réunion', 'secure-media-link'),
            'RO' => __('Roumanie', 'secure-media-link'),
            'RS' => __('Serbie', 'secure-media-link'),
            'RU' => __('Russie', 'secure-media-link'),
            'RW' => __('Rwanda', 'secure-media-link'),
            'SA' => __('Arabie saoudite', 'secure-media-link'),
            'SB' => __('Îles Salomon', 'secure-media-link'),
            'SC' => __('Seychelles', 'secure-media-link'),
            'SD' => __('Soudan', 'secure-media-link'),
            'SE' => __('Suède', 'secure-media-link'),
            'SG' => __('Singapour', 'secure-media-link'),
            'SH' => __('Sainte-Hélène', 'secure-media-link'),
            'SI' => __('Slovénie', 'secure-media-link'),
            'SJ' => __('Svalbard et Jan Mayen', 'secure-media-link'),
            'SK' => __('Slovaquie', 'secure-media-link'),
            'SL' => __('Sierra Leone', 'secure-media-link'),
            'SM' => __('Saint-Marin', 'secure-media-link'),
            'SN' => __('Sénégal', 'secure-media-link'),
            'SO' => __('Somalie', 'secure-media-link'),
            'SR' => __('Suriname', 'secure-media-link'),
            'SS' => __('Soudan du Sud', 'secure-media-link'),
            'ST' => __('Sao Tomé-et-Principe', 'secure-media-link'),
            'SV' => __('El Salvador', 'secure-media-link'),
            'SX' => __('Saint-Martin', 'secure-media-link'),
            'SY' => __('Syrie', 'secure-media-link'),
            'SZ' => __('Eswatini', 'secure-media-link'),
            'TC' => __('Îles Turques-et-Caïques', 'secure-media-link'),
            'TD' => __('Tchad', 'secure-media-link'),
            'TF' => __('Terres australes et antarctiques françaises', 'secure-media-link'),
            'TG' => __('Togo', 'secure-media-link'),
            'TH' => __('Thaïlande', 'secure-media-link'),
            'TJ' => __('Tadjikistan', 'secure-media-link'),
            'TK' => __('Tokelau', 'secure-media-link'),
            'TL' => __('Timor oriental', 'secure-media-link'),
            'TM' => __('Turkménistan', 'secure-media-link'),
            'TN' => __('Tunisie', 'secure-media-link'),
            'TO' => __('Tonga', 'secure-media-link'),
            'TR' => __('Turquie', 'secure-media-link'),
            'TT' => __('Trinité-et-Tobago', 'secure-media-link'),
            'TV' => __('Tuvalu', 'secure-media-link'),
            'TW' => __('Taïwan', 'secure-media-link'),
            'TZ' => __('Tanzanie', 'secure-media-link'),
            'UA' => __('Ukraine', 'secure-media-link'),
            'UG' => __('Ouganda', 'secure-media-link'),
            'UM' => __('Îles mineures éloignées des États-Unis', 'secure-media-link'),
            'US' => __('États-Unis', 'secure-media-link'),
            'UY' => __('Uruguay', 'secure-media-link'),
            'UZ' => __('Ouzbékistan', 'secure-media-link'),
            'VA' => __('Vatican', 'secure-media-link'),
            'VC' => __('Saint-Vincent-et-les-Grenadines', 'secure-media-link'),
            'VE' => __('Venezuela', 'secure-media-link'),
            'VG' => __('Îles Vierges britanniques', 'secure-media-link'),
            'VI' => __('Îles Vierges des États-Unis', 'secure-media-link'),
            'VN' => __('Vietnam', 'secure-media-link'),
            'VU' => __('Vanuatu', 'secure-media-link'),
            'WF' => __('Wallis-et-Futuna', 'secure-media-link'),
            'WS' => __('Samoa', 'secure-media-link'),
            'YE' => __('Yémen', 'secure-media-link'),
            'YT' => __('Mayotte', 'secure-media-link'),
            'ZA' => __('Afrique du Sud', 'secure-media-link'),
            'ZM' => __('Zambie', 'secure-media-link'),
            'ZW' => __('Zimbabwe', 'secure-media-link')
        );
    }
    
    return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
}

/**
 * Obtenir l'icône d'un type de fichier
 */
function sml_get_file_type_icon($mime_type) {
    $icon_map = array(
        'image/jpeg' => 'dashicons-format-image',
        'image/jpg' => 'dashicons-format-image',
        'image/png' => 'dashicons-format-image',
        'image/gif' => 'dashicons-format-image',
        'image/webp' => 'dashicons-format-image',
        'application/pdf' => 'dashicons-pdf',
        'application/msword' => 'dashicons-media-document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'dashicons-media-document',
        'application/vnd.ms-excel' => 'dashicons-media-spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'dashicons-media-spreadsheet',
        'application/zip' => 'dashicons-media-archive',
        'application/x-zip-compressed' => 'dashicons-media-archive',
        'video/mp4' => 'dashicons-format-video',
        'video/avi' => 'dashicons-format-video',
        'video/mov' => 'dashicons-format-video',
        'audio/mp3' => 'dashicons-format-audio',
        'audio/wav' => 'dashicons-format-audio',
        'audio/ogg' => 'dashicons-format-audio'
    );
    
    return isset($icon_map[$mime_type]) ? $icon_map[$mime_type] : 'dashicons-media-default';
}

/**
 * Créer un breadcrumb pour l'admin
 */
function sml_get_admin_breadcrumb() {
    $breadcrumb = array();
    
    $breadcrumb[] = array(
        'title' => __('Tableau de bord', 'secure-media-link'),
        'url' => admin_url('admin.php?page=secure-media-link')
    );
    
    $current_page = isset($_GET['page']) ? $_GET['page'] : '';
    
    switch ($current_page) {
        case 'sml-media-library':
            $breadcrumb[] = array(
                'title' => __('Médiathèque sécurisée', 'secure-media-link'),
                'url' => admin_url('admin.php?page=sml-media-library')
            );
            break;
            
        case 'sml-media-formats':
            $breadcrumb[] = array(
                'title' => __('Formats de média', 'secure-media-link'),
                'url' => admin_url('admin.php?page=sml-media-formats')
            );
            break;
            
        case 'sml-tracking':
            $breadcrumb[] = array(
                'title' => __('Tracking & Statistiques', 'secure-media-link'),
                'url' => admin_url('admin.php?page=sml-tracking')
            );
            break;
            
        case 'sml-permissions':
            $breadcrumb[] = array(
                'title' => __('Autorisations', 'secure-media-link'),
                'url' => admin_url('admin.php?page=sml-permissions')
            );
            break;
            
        case 'sml-settings':
            $breadcrumb[] = array(
                'title' => __('Paramètres', 'secure-media-link'),
                'url' => admin_url('admin.php?page=sml-settings')
            );
            break;
    }
    
    return $breadcrumb;
}

/**
 * Générer un sélecteur de format pour les formulaires
 */
function sml_format_selector($name = 'format_id', $selected = null, $attributes = array()) {
    $formats = SML_Media_Formats::get_all_formats();
    
    $default_attributes = array(
        'id' => $name,
        'name' => $name,
        'class' => 'sml-format-selector'
    );
    
    $attributes = array_merge($default_attributes, $attributes);
    
    $html = '<select';
    foreach ($attributes as $attr => $value) {
        $html .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
    }
    $html .= '>';
    
    $html .= '<option value="">' . __('Sélectionnez un format', 'secure-media-link') . '</option>';
    
    foreach ($formats as $format) {
        $selected_attr = ($selected == $format->id) ? ' selected' : '';
        $dimensions = '';
        
        if ($format->width || $format->height) {
            $dimensions = ' (' . ($format->width ?: '?') . 'x' . ($format->height ?: '?') . ')';
        }
        
        $html .= sprintf(
            '<option value="%d"%s>%s%s - %s</option>',
            $format->id,
            $selected_attr,
            esc_html($format->name),
            $dimensions,
            esc_html(ucfirst($format->type))
        );
    }
    
    $html .= '</select>';
    
    return $html;
}

/**
 * Obtenir la couleur d'un statut
 */
function sml_get_status_color($status) {
    $colors = array(
        'active' => '#28a745',
        'inactive' => '#6c757d',
        'expired' => '#dc3545',
        'pending' => '#ffc107',
        'blocked' => '#dc3545',
        'authorized' => '#28a745'
    );
    
    return isset($colors[$status]) ? $colors[$status] : '#6c757d';
}

/**
 * Formater un nombre pour l'affichage
 */
function sml_format_number($number, $decimals = 0) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    } else {
        return number_format($number, $decimals);
    }
}

/**
 * Générer une palette de couleurs pour les graphiques
 */
function sml_get_chart_colors($count = 10) {
    $base_colors = array(
        '#FF6384',
        '#36A2EB',
        '#FFCE56',
        '#4BC0C0',
        '#9966FF',
        '#FF9F40',
        '#FF6384',
        '#C9CBCF',
        '#4BC0C0',
        '#FF6384'
    );
    
    $colors = array();
    for ($i = 0; $i < $count; $i++) {
        $colors[] = $base_colors[$i % count($base_colors)];
    }
    
    return $colors;
}

/**
 * Convertir une chaîne en slug
 */
function sml_slugify($text) {
    // Remplacer les caractères non-alphabétiques par des tirets
    $text = preg_replace('/[^a-zA-Z0-9]/u', '-', $text);
    
    // Supprimer les tirets multiples
    $text = preg_replace('/-+/', '-', $text);
    
    // Supprimer les tirets en début et fin
    $text = trim($text, '-');
    
    return strtolower($text);
}

/**
 * Vérifier si une URL est sécurisée (HTTPS)
 */
function sml_is_secure_url($url) {
    return strpos($url, 'https://') === 0;
}

/**
 * Obtenir les métadonnées étendues d'un média
 */
function sml_get_media_metadata($media_id) {
    $media = get_post($media_id);
    
    if (!$media || $media->post_type !== 'attachment') {
        return false;
    }
    
    $metadata = array(
        'id' => $media->ID,
        'title' => $media->post_title,
        'description' => $media->post_content,
        'caption' => $media->post_excerpt,
        'alt_text' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
        'mime_type' => $media->post_mime_type,
        'file_url' => wp_get_attachment_url($media->ID),
        'upload_date' => $media->post_date,
        'author_id' => $media->post_author,
        'file_size' => 0,
        'dimensions' => array('width' => null, 'height' => null)
    );
    
    // Taille du fichier
    $file_path = get_attached_file($media->ID);
    if ($file_path && file_exists($file_path)) {
        $metadata['file_size'] = filesize($file_path);
        $metadata['file_size_formatted'] = sml_format_file_size($metadata['file_size']);
    }
    
    // Métadonnées d'image
    $wp_metadata = wp_get_attachment_metadata($media->ID);
    if ($wp_metadata) {
        if (isset($wp_metadata['width'])) {
            $metadata['dimensions']['width'] = $wp_metadata['width'];
        }
        if (isset($wp_metadata['height'])) {
            $metadata['dimensions']['height'] = $wp_metadata['height'];
        }
    }
    
    // Métadonnées personnalisées SML
    $metadata['copyright'] = get_post_meta($media->ID, '_sml_copyright', true);
    $metadata['expiry_date'] = get_post_meta($media->ID, '_sml_expiry_date', true);
    
    return $metadata;
}

/**
 * Calculer l'intégrité d'un fichier (checksum)
 */
function sml_calculate_file_checksum($file_path, $algorithm = 'sha256') {
    if (!file_exists($file_path)) {
        return false;
    }
    
    return hash_file($algorithm, $file_path);
}

/**
 * Obtenir les informations de performance du plugin
 */
function sml_get_performance_info() {
    $info = array(
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'database_queries' => get_num_queries()
    );
    
    // Informations sur le cache
    $cache_stats = SML_Cache::get_cache_stats();
    $info['cache'] = $cache_stats;
    
    return $info;
}

/**
 * Créer un widget de statistiques pour le tableau de bord
 */
function sml_dashboard_widget($title, $stats, $icon = 'dashicons-chart-bar') {
    $html = '<div class="sml-dashboard-widget">';
    $html .= '<div class="sml-widget-header">';
    $html .= '<span class="dashicons ' . esc_attr($icon) . '"></span>';
    $html .= '<h3>' . esc_html($title) . '</h3>';
    $html .= '</div>';
    $html .= '<div class="sml-widget-content">';
    
    foreach ($stats as $label => $value) {
        $html .= '<div class="sml-stat-row">';
        $html .= '<span class="sml-stat-label">' . esc_html($label) . '</span>';
        $html .= '<span class="sml-stat-value">' . esc_html($value) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Obtenir les recommandations de sécurité
 */
function sml_get_security_recommendations() {
    $recommendations = array();
    
    // Vérifier HTTPS
    if (!is_ssl()) {
        $recommendations[] = array(
            'type' => 'warning',
            'title' => __('HTTPS non activé', 'secure-media-link'),
            'description' => __('Il est recommandé d\'utiliser HTTPS pour sécuriser les échanges.', 'secure-media-link'),
            'action' => __('Configurer HTTPS', 'secure-media-link')
        );
    }
    
    // Vérifier les permissions de fichiers
    $upload_dir = wp_upload_dir();
    if (!is_writable($upload_dir['basedir'])) {
        $recommendations[] = array(
            'type' => 'error',
            'title' => __('Permissions de fichiers', 'secure-media-link'),
            'description' => __('Le répertoire d\'upload n\'est pas accessible en écriture.', 'secure-media-link'),
            'action' => __('Corriger les permissions', 'secure-media-link')
        );
    }
    
    // Vérifier la configuration de sécurité
    if (!defined('DISALLOW_FILE_EDIT') || !DISALLOW_FILE_EDIT) {
        $recommendations[] = array(
            'type' => 'info',
            'title' => __('Édition de fichiers', 'secure-media-link'),
            'description' => __('Il est recommandé de désactiver l\'édition de fichiers depuis l\'admin.', 'secure-media-link'),
            'action' => __('Ajouter DISALLOW_FILE_EDIT dans wp-config.php', 'secure-media-link')
        );
    }
    
    // Vérifier les versions
    global $wp_version;
    $latest_version = get_transient('sml_latest_wp_version');
    
    if ($latest_version && version_compare($wp_version, $latest_version, '<')) {
        $recommendations[] = array(
            'type' => 'warning',
            'title' => __('WordPress obsolète', 'secure-media-link'),
            'description' => sprintf(__('WordPress %s est disponible (actuellement %s).', 'secure-media-link'), $latest_version, $wp_version),
            'action' => __('Mettre à jour WordPress', 'secure-media-link')
        );
    }
    
    return $recommendations;
}

/**
 * Nettoyer les données temporaires
 */
function sml_cleanup_temp_data() {
    global $wpdb;
    
    $cleaned = 0;
    
    // Nettoyer les tracking anciens (plus de 1 an)
    $cleaned += $wpdb->query(
        "DELETE FROM {$wpdb->prefix}sml_tracking 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
    );
    
    // Nettoyer les notifications lues anciennes (plus de 3 mois)
    $cleaned += $wpdb->query(
        "DELETE FROM {$wpdb->prefix}sml_notifications 
         WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)"
    );
    
    // Nettoyer les liens expirés inactifs (plus de 6 mois)
    $cleaned += $wpdb->query(
        "DELETE FROM {$wpdb->prefix}sml_secure_links 
         WHERE is_active = 0 AND expires_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
    );
    
    // Nettoyer les statistiques anciennes (plus de 2 ans)
    $cleaned += $wpdb->query(
        "DELETE FROM {$wpdb->prefix}sml_statistics 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)"
    );
    
    // Nettoyer les fichiers temporaires
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/sml-temp';
    
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '/*');
        $cutoff_time = time() - (24 * 60 * 60); // 24 heures
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
                $cleaned++;
            }
        }
    }
    
    return $cleaned;
}

/**
 * Obtenir les informations système pour le support
 */
function sml_get_system_info() {
    global $wp_version, $wpdb;
    
    $info = array(
        'wordpress' => array(
            'version' => $wp_version,
            'multisite' => is_multisite(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ),
        'server' => array(
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'mysql_version' => $wpdb->db_version(),
            'ssl_support' => extension_loaded('openssl'),
            'curl_support' => extension_loaded('curl'),
            'gd_support' => extension_loaded('gd')
        ),
        'plugin' => array(
            'version' => SML_PLUGIN_VERSION,
            'database_version' => get_option('sml_db_version', '1.0.0'),
            'active_links' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE is_active = 1"),
            'total_tracking' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sml_tracking"),
            'cache_enabled' => SML_Cache::is_enabled()
        )
    );
    
    return $info;
}

/**
 * Débugger une requête SQL
 */
function sml_debug_query($query, $params = array()) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    global $wpdb;
    
    if (!empty($params)) {
        $query = $wpdb->prepare($query, $params);
    }
    
    $start_time = microtime(true);
    $result = $wpdb->get_results($query);
    $execution_time = microtime(true) - $start_time;
    
    error_log(sprintf(
        '[SML Query Debug] Time: %s ms | Query: %s | Results: %d',
        round($execution_time * 1000, 2),
        $query,
        is_array($result) ? count($result) : 0
    ));
    
    return $result;
}

/**
 * Créer un backup des paramètres
 */
function sml_backup_settings() {
    $settings = get_option('sml_settings', array());
    $backup = array(
        'settings' => $settings,
        'version' => SML_PLUGIN_VERSION,
        'timestamp' => current_time('timestamp'),
        'site_url' => get_site_url()
    );
    
    $backup_key = 'sml_settings_backup_' . date('Y_m_d_H_i_s');
    update_option($backup_key, $backup);
    
    // Nettoyer les anciens backups (garder seulement les 10 derniers)
    $all_options = wp_load_alloptions();
    $backup_options = array();
    
    foreach ($all_options as $option_name => $option_value) {
        if (strpos($option_name, 'sml_settings_backup_') === 0) {
            $backup_options[$option_name] = $option_value;
        }
    }
    
    if (count($backup_options) > 10) {
        ksort($backup_options);
        $to_delete = array_slice(array_keys($backup_options), 0, count($backup_options) - 10);
        
        foreach ($to_delete as $option_name) {
            delete_option($option_name);
        }
    }
    
    return $backup_key;
}

/**
 * Restaurer un backup des paramètres
 */
function sml_restore_settings($backup_key) {
    $backup = get_option($backup_key);
    
    if (!$backup || !isset($backup['settings'])) {
        return false;
    }
    
    update_option('sml_settings', $backup['settings']);
    
    return true;
}

/**
 * Obtenir la liste des backups disponibles
 */
function sml_get_available_backups() {
    $all_options = wp_load_alloptions();
    $backups = array();
    
    foreach ($all_options as $option_name => $option_value) {
        if (strpos($option_name, 'sml_settings_backup_') === 0) {
            $backup_data = maybe_unserialize($option_value);
            if (is_array($backup_data) && isset($backup_data['timestamp'])) {
                $backups[$option_name] = array(
                    'key' => $option_name,
                    'timestamp' => $backup_data['timestamp'],
                    'version' => $backup_data['version'] ?? 'Unknown',
                    'date_formatted' => date_i18n(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        $backup_data['timestamp']
                    )
                );
            }
        }
    }
    
    // Trier par timestamp décroissant
    uasort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $backups;
}