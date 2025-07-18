<?php
/**
 * Classe pour la gestion cryptographique et des liens sécurisés
 * includes/class-sml-crypto.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Crypto {
    
    const KEY_SIZE = 2048;
    const SIGNATURE_ALGORITHM = OPENSSL_ALGO_SHA256;
    
    /**
     * Initialisation de la cryptographie
     */
    public static function init() {
        // Générer la paire de clés si elle n'existe pas
        if (!self::key_pair_exists()) {
            self::generate_key_pair();
        }
    }
    
    /**
     * Vérifier si la paire de clés existe
     */
    private static function key_pair_exists() {
        $key_pair = get_option('sml_key_pair');
        return !empty($key_pair['private_key']) && !empty($key_pair['public_key']);
    }
    
    /**
     * Générer une nouvelle paire de clés RSA
     */
    public static function generate_key_pair() {
        $config = array(
            'digest_alg' => 'sha256',
            'private_key_bits' => self::KEY_SIZE,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        );
        
        // Créer la paire de clés
        $resource = openssl_pkey_new($config);
        
        if (!$resource) {
            throw new Exception(__('Erreur lors de la génération de la paire de clés', 'secure-media-link'));
        }
        
        // Extraire la clé privée
        openssl_pkey_export($resource, $private_key);
        
        // Extraire la clé publique
        $key_details = openssl_pkey_get_details($resource);
        $public_key = $key_details['key'];
        
        // Générer un ID unique pour cette paire de clés
        $key_pair_id = 'sml_' . wp_generate_password(16, false);
        
        // Sauvegarder la paire de clés
        $key_pair = array(
            'private_key' => $private_key,
            'public_key' => $public_key,
            'key_pair_id' => $key_pair_id,
            'created_at' => current_time('mysql')
        );
        
        update_option('sml_key_pair', $key_pair);
        
        return $key_pair_id;
    }
    
    /**
     * Obtenir l'ID de la paire de clés
     */
    public static function get_key_pair_id() {
        $key_pair = get_option('sml_key_pair');
        return isset($key_pair['key_pair_id']) ? $key_pair['key_pair_id'] : null;
    }
    
    /**
     * Obtenir la clé publique
     */
    public static function get_public_key() {
        $key_pair = get_option('sml_key_pair');
        return isset($key_pair['public_key']) ? $key_pair['public_key'] : null;
    }
    
    /**
     * Obtenir la clé privée
     */
    private static function get_private_key() {
        $key_pair = get_option('sml_key_pair');
        return isset($key_pair['private_key']) ? $key_pair['private_key'] : null;
    }
    
    /**
     * Générer un lien sécurisé
     */
    public static function generate_secure_link($media_id, $format_id, $expiry_date = null) {
        global $wpdb;
        
        // Valider les paramètres
        if (!$media_id || !$format_id) {
            return false;
        }
        
        // Vérifier que le média existe
        $media = get_post($media_id);
        if (!$media || $media->post_type !== 'attachment') {
            return false;
        }
        
        // Vérifier que le format existe
        $format = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_media_formats WHERE id = %d",
            $format_id
        ));
        
        if (!$format) {
            return false;
        }
        
        // Date d'expiration par défaut (3 ans)
        if (!$expiry_date) {
            $settings = get_option('sml_settings', array());
            $default_expiry = isset($settings['default_expiry_years']) ? $settings['default_expiry_years'] : 3;
            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$default_expiry} years"));
        }
        
        // Générer un hash unique pour le lien
        $link_hash = self::generate_link_hash($media_id, $format_id, $expiry_date);
        
        // Créer l'URL à signer
        $base_url = self::get_base_url();
        $resource_path = "/media/{$media_id}/{$format_id}/{$link_hash}";
        $expires_timestamp = strtotime($expiry_date);
        
        // Créer la chaîne à signer
        $string_to_sign = "GET\n{$resource_path}\n{$expires_timestamp}";
        
        // Signer la chaîne
        $signature = self::sign_string($string_to_sign);
        
        if (!$signature) {
            return false;
        }
        
        // Encoder la signature en base64 URL-safe
        $signature_encoded = self::url_safe_base64_encode($signature);
        
        // Obtenir l'ID de la paire de clés
        $key_pair_id = self::get_key_pair_id();
        
        // Construire l'URL finale
        $secure_url = $base_url . $resource_path . '?' . http_build_query(array(
            'Expires' => $expires_timestamp,
            'Signature' => $signature_encoded,
            'Key-Pair-Id' => $key_pair_id
        ));
        
        // Sauvegarder le lien en base de données
        $link_data = array(
            'media_id' => $media_id,
            'format_id' => $format_id,
            'link_hash' => $link_hash,
            'signature' => $signature_encoded,
            'expires_at' => $expiry_date,
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'sml_secure_links', $link_data);
        
        if ($result === false) {
            return false;
        }
        
        return $secure_url;
    }
    
    /**
     * Vérifier un lien sécurisé
     */
    public static function verify_secure_link($link_hash, $signature, $expires, $key_pair_id) {
        global $wpdb;
        
        // Vérifier l'expiration
        if ($expires < time()) {
            return array('valid' => false, 'error' => 'expired');
        }
        
        // Vérifier l'ID de la paire de clés
        $current_key_pair_id = self::get_key_pair_id();
        if ($key_pair_id !== $current_key_pair_id) {
            return array('valid' => false, 'error' => 'invalid_key_pair');
        }
        
        // Récupérer les informations du lien
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_secure_links WHERE link_hash = %s AND is_active = 1",
            $link_hash
        ));
        
        if (!$link) {
            return array('valid' => false, 'error' => 'link_not_found');
        }
        
        // Vérifier que le lien n'est pas expiré en base
        if (strtotime($link->expires_at) < time()) {
            return array('valid' => false, 'error' => 'link_expired');
        }
        
        // Reconstruire la chaîne à vérifier
        $resource_path = "/media/{$link->media_id}/{$link->format_id}/{$link_hash}";
        $string_to_verify = "GET\n{$resource_path}\n{$expires}";
        
        // Vérifier la signature
        $signature_decoded = self::url_safe_base64_decode($signature);
        $is_valid = self::verify_signature($string_to_verify, $signature_decoded);
        
        if (!$is_valid) {
            return array('valid' => false, 'error' => 'invalid_signature');
        }
        
        return array(
            'valid' => true, 
            'link' => $link,
            'media_id' => $link->media_id,
            'format_id' => $link->format_id
        );
    }
    
    /**
     * Générer un hash unique pour le lien
     */
    private static function generate_link_hash($media_id, $format_id, $expiry_date) {
        $data = $media_id . '|' . $format_id . '|' . $expiry_date . '|' . wp_generate_password(32, false);
        return hash('sha256', $data);
    }
    
    /**
     * Signer une chaîne avec la clé privée
     */
    private static function sign_string($string) {
        $private_key = self::get_private_key();
        if (!$private_key) {
            return false;
        }
        
        $signature = '';
        $result = openssl_sign($string, $signature, $private_key, self::SIGNATURE_ALGORITHM);
        
        return $result ? $signature : false;
    }
    
    /**
     * Vérifier une signature avec la clé publique
     */
    private static function verify_signature($string, $signature) {
        $public_key = self::get_public_key();
        if (!$public_key) {
            return false;
        }
        
        $result = openssl_verify($string, $signature, $public_key, self::SIGNATURE_ALGORITHM);
        
        return $result === 1;
    }
    
    /**
     * Encoder en base64 URL-safe
     */
    private static function url_safe_base64_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Décoder depuis base64 URL-safe
     */
    private static function url_safe_base64_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Obtenir l'URL de base pour les liens
     */
    private static function get_base_url() {
        $settings = get_option('sml_settings', array());
        
        if (!empty($settings['custom_domain'])) {
            return rtrim($settings['custom_domain'], '/');
        }
        
        return rtrim(get_site_url(), '/') . '/sml';
    }
    
    /**
     * Générer un token API
     */
    public static function generate_api_token($user_id, $permissions = array()) {
        $payload = array(
            'user_id' => $user_id,
            'permissions' => $permissions,
            'issued_at' => time(),
            'expires_at' => time() + (30 * 24 * 60 * 60) // 30 jours
        );
        
        $token_data = json_encode($payload);
        $signature = self::sign_string($token_data);
        
        if (!$signature) {
            return false;
        }
        
        $token = array(
            'data' => self::url_safe_base64_encode($token_data),
            'signature' => self::url_safe_base64_encode($signature)
        );
        
        return implode('.', $token);
    }
    
    /**
     * Vérifier un token API
     */
    public static function verify_api_token($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $token_data = self::url_safe_base64_decode($parts[0]);
        $signature = self::url_safe_base64_decode($parts[1]);
        
        // Vérifier la signature
        if (!self::verify_signature($token_data, $signature)) {
            return false;
        }
        
        $payload = json_decode($token_data, true);
        
        if (!$payload) {
            return false;
        }
        
        // Vérifier l'expiration
        if ($payload['expires_at'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Chiffrer des données sensibles
     */
    public static function encrypt_data($data, $key = null) {
        if (!$key) {
            $key = AUTH_KEY;
        }
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Déchiffrer des données
     */
    public static function decrypt_data($encrypted_data, $key = null) {
        if (!$key) {
            $key = AUTH_KEY;
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Générer un hash sécurisé pour les mots de passe
     */
    public static function hash_password($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Vérifier un mot de passe
     */
    public static function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Nettoyer les anciennes clés
     */
    public static function cleanup_old_keys() {
        // Cette fonction peut être utilisée pour faire une rotation des clés
        // si nécessaire pour la sécurité
        
        // Pour l'instant, on garde la même paire de clés
        // mais on pourrait implémenter une rotation automatique
    }
    
    /**
     * Exporter la clé publique pour vérification externe
     */
    public static function export_public_key() {
        $public_key = self::get_public_key();
        
        if (!$public_key) {
            return false;
        }
        
        return array(
            'key' => $public_key,
            'key_pair_id' => self::get_key_pair_id(),
            'algorithm' => 'RS256',
            'created_at' => get_option('sml_key_pair')['created_at']
        );
    }
}