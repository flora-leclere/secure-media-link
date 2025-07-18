<?php
/**
 * Classe pour la gestion des formats de média
 * includes/class-sml-media-formats.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Media_Formats {
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_ajax_sml_create_format', array(__CLASS__, 'ajax_create_format'));
        add_action('wp_ajax_sml_update_format', array(__CLASS__, 'ajax_update_format'));
        add_action('wp_ajax_sml_delete_format', array(__CLASS__, 'ajax_delete_format'));
        add_action('wp_ajax_sml_generate_media_format', array(__CLASS__, 'ajax_generate_media_format'));
    }
    
    /**
     * Obtenir tous les formats
     */
    public static function get_all_formats() {
        global $wpdb;
        
        $cache_key = 'sml_media_formats_all';
        $formats = wp_cache_get($cache_key);
        
        if ($formats === false) {
            $formats = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}sml_media_formats ORDER BY name ASC"
            );
            wp_cache_set($cache_key, $formats, '', 3600);
        }
        
        return $formats;
    }
    
    /**
     * Obtenir un format par ID
     */
    public static function get_format($format_id) {
        global $wpdb;
        
        $cache_key = "sml_media_format_{$format_id}";
        $format = wp_cache_get($cache_key);
        
        if ($format === false) {
            $format = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sml_media_formats WHERE id = %d",
                $format_id
            ));
            
            if ($format) {
                wp_cache_set($cache_key, $format, '', 3600);
            }
        }
        
        return $format;
    }
    
    /**
     * Créer un nouveau format
     */
    public static function create_format($data) {
        global $wpdb;
        
        // Validation des données
        $errors = self::validate_format_data($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        // Préparer les données
        $format_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'width' => !empty($data['width']) ? intval($data['width']) : null,
            'height' => !empty($data['height']) ? intval($data['height']) : null,
            'quality' => intval($data['quality']),
            'format' => sanitize_text_field($data['format']),
            'crop_mode' => sanitize_text_field($data['crop_mode']),
            'crop_position' => sanitize_text_field($data['crop_position']),
            'type' => sanitize_text_field($data['type'])
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'sml_media_formats', $format_data);
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de la création du format', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        wp_cache_delete('sml_media_formats_all');
        
        return array('success' => true, 'format_id' => $wpdb->insert_id);
    }
    
    /**
     * Mettre à jour un format
     */
    public static function update_format($format_id, $data) {
        global $wpdb;
        
        // Validation des données
        $errors = self::validate_format_data($data, $format_id);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        // Préparer les données
        $format_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'width' => !empty($data['width']) ? intval($data['width']) : null,
            'height' => !empty($data['height']) ? intval($data['height']) : null,
            'quality' => intval($data['quality']),
            'format' => sanitize_text_field($data['format']),
            'crop_mode' => sanitize_text_field($data['crop_mode']),
            'crop_position' => sanitize_text_field($data['crop_position']),
            'type' => sanitize_text_field($data['type'])
        );
        
        $result = $wpdb->update(
            $wpdb->prefix . 'sml_media_formats',
            $format_data,
            array('id' => $format_id)
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de la mise à jour du format', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        wp_cache_delete('sml_media_formats_all');
        wp_cache_delete("sml_media_format_{$format_id}");
        
        return array('success' => true);
    }
    
    /**
     * Supprimer un format
     */
    public static function delete_format($format_id) {
        global $wpdb;
        
        // Vérifier si le format est utilisé
        $links_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE format_id = %d",
            $format_id
        ));
        
        if ($links_count > 0) {
            return array(
                'success' => false, 
                'message' => sprintf(
                    __('Ce format ne peut pas être supprimé car il est utilisé par %d lien(s)', 'secure-media-link'),
                    $links_count
                )
            );
        }
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'sml_media_formats',
            array('id' => $format_id)
        );
        
        if ($result === false) {
            return array('success' => false, 'message' => __('Erreur lors de la suppression du format', 'secure-media-link'));
        }
        
        // Nettoyer le cache
        wp_cache_delete('sml_media_formats_all');
        wp_cache_delete("sml_media_format_{$format_id}");
        
        return array('success' => true);
    }
    
    /**
     * Valider les données d'un format
     */
    private static function validate_format_data($data, $format_id = null) {
        $errors = array();
        
        // Nom requis et unique
        if (empty($data['name'])) {
            $errors['name'] = __('Le nom est requis', 'secure-media-link');
        } else {
            global $wpdb;
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sml_media_formats WHERE name = %s" . 
                ($format_id ? " AND id != %d" : ""),
                $data['name'],
                $format_id
            ));
            
            if ($existing) {
                $errors['name'] = __('Ce nom existe déjà', 'secure-media-link');
            }
        }
        
        // Qualité entre 1 et 100
        if (!isset($data['quality']) || $data['quality'] < 1 || $data['quality'] > 100) {
            $errors['quality'] = __('La qualité doit être entre 1 et 100', 'secure-media-link');
        }
        
        // Format valide
        $valid_formats = array('jpg', 'jpeg', 'png', 'webp');
        if (empty($data['format']) || !in_array($data['format'], $valid_formats)) {
            $errors['format'] = __('Format invalide', 'secure-media-link');
        }
        
        // Mode de crop valide
        $valid_crop_modes = array('resize', 'crop', 'fit');
        if (empty($data['crop_mode']) || !in_array($data['crop_mode'], $valid_crop_modes)) {
            $errors['crop_mode'] = __('Mode de recadrage invalide', 'secure-media-link');
        }
        
        // Position de crop valide
        $valid_crop_positions = array('center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right');
        if (empty($data['crop_position']) || !in_array($data['crop_position'], $valid_crop_positions)) {
            $errors['crop_position'] = __('Position de recadrage invalide', 'secure-media-link');
        }
        
        // Type valide
        $valid_types = array('web', 'print', 'social');
        if (empty($data['type']) || !in_array($data['type'], $valid_types)) {
            $errors['type'] = __('Type invalide', 'secure-media-link');
        }
        
        // Dimensions valides
        if (!empty($data['width']) && $data['width'] < 1) {
            $errors['width'] = __('La largeur doit être positive', 'secure-media-link');
        }
        
        if (!empty($data['height']) && $data['height'] < 1) {
            $errors['height'] = __('La hauteur doit être positive', 'secure-media-link');
        }
        
        return $errors;
    }
    
    /**
     * Générer une version formatée d'un média
     */
    public static function generate_media_format($media_id, $format_id) {
        $format = self::get_format($format_id);
        if (!$format) {
            return false;
        }
        
        $media_path = get_attached_file($media_id);
        if (!$media_path || !file_exists($media_path)) {
            return false;
        }
        
        // Créer le répertoire de destination si nécessaire
        $upload_dir = wp_upload_dir();
        $sml_dir = $upload_dir['basedir'] . '/sml-formats';
        
        if (!file_exists($sml_dir)) {
            wp_mkdir_p($sml_dir);
        }
        
        // Générer le nom du fichier de sortie
        $media_info = pathinfo($media_path);
        $output_filename = $media_id . '_' . $format->name . '.' . $format->format;
        $output_path = $sml_dir . '/' . $output_filename;
        
        // Vérifier si le fichier existe déjà et est plus récent
        if (file_exists($output_path) && filemtime($output_path) > filemtime($media_path)) {
            return $output_path;
        }
        
        // Charger l'image
        $image = wp_get_image_editor($media_path);
        if (is_wp_error($image)) {
            return false;
        }
        
        // Obtenir les dimensions actuelles
        $current_size = $image->get_size();
        
        // Calculer les nouvelles dimensions
        $new_dimensions = self::calculate_dimensions($current_size, $format);
        
        // Redimensionner/recadrer selon le mode
        switch ($format->crop_mode) {
            case 'resize':
                $image->resize($new_dimensions['width'], $new_dimensions['height'], false);
                break;
                
            case 'crop':
                if ($format->width && $format->height) {
                    $image->resize($new_dimensions['width'], $new_dimensions['height'], true);
                } else {
                    $image->resize($new_dimensions['width'], $new_dimensions['height'], false);
                }
                break;
                
            case 'fit':
                // Redimensionner pour s'adapter sans déformation
                $scale = min(
                    $new_dimensions['width'] / $current_size['width'],
                    $new_dimensions['height'] / $current_size['height']
                );
                
                $fit_width = round($current_size['width'] * $scale);
                $fit_height = round($current_size['height'] * $scale);
                
                $image->resize($fit_width, $fit_height, false);
                break;
        }
        
        // Définir la qualité
        $image->set_quality($format->quality);
        
        // Sauvegarder
        $result = $image->save($output_path, $format->format);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        return $output_path;
    }
    
    /**
     * Calculer les dimensions selon le format
     */
    private static function calculate_dimensions($current_size, $format) {
        $current_width = $current_size['width'];
        $current_height = $current_size['height'];
        
        // Si seule la largeur est spécifiée
        if ($format->width && !$format->height) {
            $ratio = $format->width / $current_width;
            return array(
                'width' => $format->width,
                'height' => round($current_height * $ratio)
            );
        }
        
        // Si seule la hauteur est spécifiée
        if (!$format->width && $format->height) {
            $ratio = $format->height / $current_height;
            return array(
                'width' => round($current_width * $ratio),
                'height' => $format->height
            );
        }
        
        // Si les deux dimensions sont spécifiées
        if ($format->width && $format->height) {
            return array(
                'width' => $format->width,
                'height' => $format->height
            );
        }
        
        // Par défaut, garder les dimensions actuelles
        return array(
            'width' => $current_width,
            'height' => $current_height
        );
    }
    
    /**
     * Obtenir l'URL d'un format généré
     */
    public static function get_format_url($media_id, $format_id) {
        $format = self::get_format($format_id);
        if (!$format) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $filename = $media_id . '_' . $format->name . '.' . $format->format;
        $file_path = $upload_dir['basedir'] . '/sml-formats/' . $filename;
        
        if (!file_exists($file_path)) {
            // Générer le format s'il n'existe pas
            $generated_path = self::generate_media_format($media_id, $format_id);
            if (!$generated_path) {
                return false;
            }
        }
        
        return $upload_dir['baseurl'] . '/sml-formats/' . $filename;
    }
    
    /**
     * Nettoyer les anciens formats générés
     */
    public static function cleanup_old_formats($days = 30) {
        $upload_dir = wp_upload_dir();
        $sml_dir = $upload_dir['basedir'] . '/sml-formats';
        
        if (!file_exists($sml_dir)) {
            return;
        }
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $files = glob($sml_dir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * AJAX - Créer un format
     */
    public static function ajax_create_format() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $result = self::create_format($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Mettre à jour un format
     */
    public static function ajax_update_format() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $format_id = intval($_POST['format_id']);
        $result = self::update_format($format_id, $_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Supprimer un format
     */
    public static function ajax_delete_format() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $format_id = intval($_POST['format_id']);
        $result = self::delete_format($format_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX - Générer un format de média
     */
    public static function ajax_generate_media_format() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $media_id = intval($_POST['media_id']);
        $format_id = intval($_POST['format_id']);
        
        $result = self::generate_media_format($media_id, $format_id);
        
        if ($result) {
            $url = self::get_format_url($media_id, $format_id);
            wp_send_json_success(array('file_path' => $result, 'url' => $url));
        } else {
            wp_send_json_error(__('Erreur lors de la génération du format', 'secure-media-link'));
        }
    }
    
    /**
     * Obtenir les statistiques des formats
     */
    public static function get_format_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Nombre total de formats
        $stats['total_formats'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_media_formats"
        );
        
        // Formats les plus utilisés
        $stats['most_used_formats'] = $wpdb->get_results(
            "SELECT mf.name, mf.type, COUNT(sl.id) as usage_count
             FROM {$wpdb->prefix}sml_media_formats mf
             LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON mf.id = sl.format_id
             GROUP BY mf.id
             ORDER BY usage_count DESC
             LIMIT 10"
        );
        
        // Répartition par type
        $stats['formats_by_type'] = $wpdb->get_results(
            "SELECT type, COUNT(*) as count
             FROM {$wpdb->prefix}sml_media_formats
             GROUP BY type"
        );
        
        return $stats;
    }
    
    /**
     * Importer des formats depuis un fichier JSON
     */
    public static function import_formats($json_data) {
        $formats = json_decode($json_data, true);
        
        if (!$formats || !is_array($formats)) {
            return array('success' => false, 'message' => __('Données JSON invalides', 'secure-media-link'));
        }
        
        $imported = 0;
        $errors = array();
        
        foreach ($formats as $format_data) {
            $result = self::create_format($format_data);
            
            if ($result['success']) {
                $imported++;
            } else {
                $errors[] = $format_data['name'] . ': ' . implode(', ', $result['errors']);
            }
        }
        
        return array(
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        );
    }
    
    /**
     * Exporter les formats vers JSON
     */
    public static function export_formats() {
        $formats = self::get_all_formats();
        
        $export_data = array();
        
        foreach ($formats as $format) {
            $export_data[] = array(
                'name' => $format->name,
                'description' => $format->description,
                'width' => $format->width,
                'height' => $format->height,
                'quality' => $format->quality,
                'format' => $format->format,
                'crop_mode' => $format->crop_mode,
                'crop_position' => $format->crop_position,
                'type' => $format->type
            );
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
}