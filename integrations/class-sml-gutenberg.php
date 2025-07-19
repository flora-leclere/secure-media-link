<?php
/**
 * Classe pour l'intégration avec Gutenberg
 * integrations/class-sml-gutenberg.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Gutenberg {
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueue_block_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_filter('block_categories_all', array(__CLASS__, 'add_block_category'), 10, 2);
        
        // AJAX pour les blocs
        add_action('wp_ajax_sml_get_media_list', array(__CLASS__, 'ajax_get_media_list'));
        add_action('wp_ajax_sml_get_media_formats', array(__CLASS__, 'ajax_get_media_formats'));
        add_action('wp_ajax_sml_generate_block_link', array(__CLASS__, 'ajax_generate_block_link'));
    }
    
    /**
     * Enregistrer les blocs Gutenberg
     */
    public static function register_blocks() {
        // Vérifier si Gutenberg est disponible
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Bloc de téléchargement de média sécurisé
        register_block_type('sml/secure-download', array(
            'attributes' => array(
                'mediaId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'formatId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'linkId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'buttonText' => array(
                    'type' => 'string',
                    'default' => __('Télécharger', 'secure-media-link')
                ),
                'buttonStyle' => array(
                    'type' => 'string',
                    'default' => 'primary'
                ),
                'showIcon' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'trackDownloads' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => 'left'
                ),
                'customClass' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array(__CLASS__, 'render_download_block'),
            'editor_script' => 'sml-gutenberg-blocks',
            'editor_style' => 'sml-gutenberg-editor',
            'style' => 'sml-gutenberg-frontend'
        ));
        
        // Bloc de galerie de médias sécurisés
        register_block_type('sml/secure-gallery', array(
            'attributes' => array(
                'mediaIds' => array(
                    'type' => 'array',
                    'default' => array()
                ),
                'columns' => array(
                    'type' => 'number',
                    'default' => 3
                ),
                'showTitles' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showDescriptions' => array(
                    'type' => 'boolean',
                    'default' => false
                ),
                'showDownloadButtons' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'lightbox' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'gap' => array(
                    'type' => 'number',
                    'default' => 20
                ),
                'customClass' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array(__CLASS__, 'render_gallery_block'),
            'editor_script' => 'sml-gutenberg-blocks',
            'editor_style' => 'sml-gutenberg-editor',
            'style' => 'sml-gutenberg-frontend'
        ));
        
        // Bloc de formulaire d'upload
        register_block_type('sml/upload-form', array(
            'attributes' => array(
                'allowedTypes' => array(
                    'type' => 'string',
                    'default' => 'image/*'
                ),
                'maxFiles' => array(
                    'type' => 'number',
                    'default' => 5
                ),
                'maxSize' => array(
                    'type' => 'number',
                    'default' => 10485760 // 10MB
                ),
                'showPreview' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'redirectAfter' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'requireLogin' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'customClass' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array(__CLASS__, 'render_upload_block'),
            'editor_script' => 'sml-gutenberg-blocks',
            'editor_style' => 'sml-gutenberg-editor',
            'style' => 'sml-gutenberg-frontend'
        ));
        
        // Bloc de statistiques de média
        register_block_type('sml/media-stats', array(
            'attributes' => array(
                'mediaId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'showDownloads' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showCopies' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showViews' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'showChart' => array(
                    'type' => 'boolean',
                    'default' => false
                ),
                'period' => array(
                    'type' => 'string',
                    'default' => 'month'
                ),
                'displayStyle' => array(
                    'type' => 'string',
                    'default' => 'cards'
                ),
                'customClass' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array(__CLASS__, 'render_stats_block'),
            'editor_script' => 'sml-gutenberg-blocks',
            'editor_style' => 'sml-gutenberg-editor',
            'style' => 'sml-gutenberg-frontend'
        ));
        
        // Bloc de liens de copie
        register_block_type('sml/copy-link', array(
            'attributes' => array(
                'mediaId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'formatId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'linkId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'buttonText' => array(
                    'type' => 'string',
                    'default' => __('Copier le lien', 'secure-media-link')
                ),
                'showInput' => array(
                    'type' => 'boolean',
                    'default' => false
                ),
                'trackCopies' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => 'left'
                ),
                'customClass' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array(__CLASS__, 'render_copy_block'),
            'editor_script' => 'sml-gutenberg-blocks',
            'editor_style' => 'sml-gutenberg-editor',
            'style' => 'sml-gutenberg-frontend'
        ));
    }
    
    /**
     * Enregistrer les assets pour l'éditeur
     */
    public static function enqueue_block_assets() {
        wp_enqueue_script(
            'sml-gutenberg-blocks',
            SML_PLUGIN_URL . 'assets/js/gutenberg-blocks.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor', 'wp-data'),
            SML_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'sml-gutenberg-editor',
            SML_PLUGIN_URL . 'assets/css/gutenberg-editor.css',
            array('wp-edit-blocks'),
            SML_PLUGIN_VERSION
        );
        
        // Localisation pour l'éditeur
        wp_localize_script('sml-gutenberg-blocks', 'smlGutenberg', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sml_gutenberg'),
            'pluginUrl' => SML_PLUGIN_URL,
            'strings' => array(
                'selectMedia' => __('Sélectionner un média', 'secure-media-link'),
                'selectFormat' => __('Sélectionner un format', 'secure-media-link'),
                'noMediaSelected' => __('Aucun média sélectionné', 'secure-media-link'),
                'noFormatSelected' => __('Aucun format sélectionné', 'secure-media-link'),
                'loading' => __('Chargement...', 'secure-media-link'),
                'error' => __('Erreur', 'secure-media-link'),
                'downloadButton' => __('Bouton de téléchargement', 'secure-media-link'),
                'copyButton' => __('Bouton de copie', 'secure-media-link'),
                'mediaGallery' => __('Galerie de médias sécurisés', 'secure-media-link'),
                'uploadForm' => __('Formulaire d\'upload', 'secure-media-link'),
                'mediaStats' => __('Statistiques de média', 'secure-media-link'),
            )
        ));
    }
    
    /**
     * Enregistrer les assets frontend
     */
    public static function enqueue_frontend_assets() {
        if (has_block('sml/secure-download') || 
            has_block('sml/secure-gallery') || 
            has_block('sml/upload-form') || 
            has_block('sml/media-stats') || 
            has_block('sml/copy-link')) {
            
            wp_enqueue_style(
                'sml-gutenberg-frontend',
                SML_PLUGIN_URL . 'assets/css/gutenberg-frontend.css',
                array(),
                SML_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'sml-gutenberg-frontend',
                SML_PLUGIN_URL . 'assets/js/gutenberg-frontend.js',
                array('jquery'),
                SML_PLUGIN_VERSION,
                true
            );
            
            wp_localize_script('sml-gutenberg-frontend', 'smlFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sml_frontend'),
                'strings' => array(
                    'copied' => __('Copié !', 'secure-media-link'),
                    'error' => __('Erreur', 'secure-media-link'),
                    'downloading' => __('Téléchargement...', 'secure-media-link')
                )
            ));
        }
    }
    
    /**
     * Ajouter une catégorie de blocs
     */
    public static function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'secure-media-link',
                    'title' => __('Secure Media Link', 'secure-media-link'),
                    'icon' => 'shield'
                )
            )
        );
    }
    
    /**
     * Rendu du bloc de téléchargement
     */
    public static function render_download_block($attributes) {
        if (empty($attributes['mediaId']) || empty($attributes['formatId'])) {
            if (current_user_can('edit_posts')) {
                return '<div class="sml-block-placeholder">' . 
                       __('Veuillez configurer le média et le format dans l\'éditeur.', 'secure-media-link') . 
                       '</div>';
            }
            return '';
        }
        
        $shortcode_attrs = array(
            'media_id' => $attributes['mediaId'],
            'format_id' => $attributes['formatId'],
            'text' => $attributes['buttonText'],
            'class' => 'sml-download-btn sml-btn-' . $attributes['buttonStyle'] . ' ' . $attributes['customClass'],
            'icon' => $attributes['showIcon'] ? 'true' : 'false',
            'track' => $attributes['trackDownloads'] ? 'true' : 'false'
        );
        
        if (!empty($attributes['linkId'])) {
            $shortcode_attrs['link_id'] = $attributes['linkId'];
            unset($shortcode_attrs['media_id'], $shortcode_attrs['format_id']);
        }
        
        $shortcode = '[sml_download_button';
        foreach ($shortcode_attrs as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        $alignment_class = 'has-text-align-' . $attributes['alignment'];
        
        return '<div class="wp-block-sml-secure-download ' . $alignment_class . '">' . 
               do_shortcode($shortcode) . 
               '</div>';
    }
    
    /**
     * Rendu du bloc de galerie
     */
    public static function render_gallery_block($attributes) {
        if (empty($attributes['mediaIds'])) {
            if (current_user_can('edit_posts')) {
                return '<div class="sml-block-placeholder">' . 
                       __('Veuillez sélectionner des médias dans l\'éditeur.', 'secure-media-link') . 
                       '</div>';
            }
            return '';
        }
        
        $shortcode_attrs = array(
            'ids' => implode(',', $attributes['mediaIds']),
            'columns' => $attributes['columns'],
            'show_title' => $attributes['showTitles'] ? 'true' : 'false',
            'show_description' => $attributes['showDescriptions'] ? 'true' : 'false',
            'show_buttons' => $attributes['showDownloadButtons'] ? 'true' : 'false',
            'lightbox' => $attributes['lightbox'] ? 'true' : 'false',
            'class' => 'sml-media-gallery ' . $attributes['customClass']
        );
        
        $shortcode = '[sml_media_gallery';
        foreach ($shortcode_attrs as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        $style = '--sml-gallery-gap: ' . $attributes['gap'] . 'px;';
        
        return '<div class="wp-block-sml-secure-gallery" style="' . esc_attr($style) . '">' . 
               do_shortcode($shortcode) . 
               '</div>';
    }
    
    /**
     * Rendu du bloc de formulaire d'upload
     */
    public static function render_upload_block($attributes) {
        if ($attributes['requireLogin'] && !is_user_logged_in()) {
            return '<div class="sml-login-required">' . 
                   __('Vous devez être connecté pour uploader des fichiers.', 'secure-media-link') . 
                   '</div>';
        }
        
        $shortcode_attrs = array(
            'allowed_types' => $attributes['allowedTypes'],
            'max_files' => $attributes['maxFiles'],
            'max_size' => $attributes['maxSize'],
            'show_preview' => $attributes['showPreview'] ? 'true' : 'false',
            'redirect_after' => $attributes['redirectAfter'],
            'class' => 'sml-upload-form ' . $attributes['customClass']
        );
        
        $shortcode = '[sml_upload_form';
        foreach ($shortcode_attrs as $key => $value) {
            if (!empty($value)) {
                $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
            }
        }
        $shortcode .= ']';
        
        return '<div class="wp-block-sml-upload-form">' . 
               do_shortcode($shortcode) . 
               '</div>';
    }
    
    /**
     * Rendu du bloc de statistiques
     */
    public static function render_stats_block($attributes) {
        if (empty($attributes['mediaId'])) {
            if (current_user_can('edit_posts')) {
                return '<div class="sml-block-placeholder">' . 
                       __('Veuillez sélectionner un média dans l\'éditeur.', 'secure-media-link') . 
                       '</div>';
            }
            return '';
        }
        
        $shortcode_attrs = array(
            'media_id' => $attributes['mediaId'],
            'show_downloads' => $attributes['showDownloads'] ? 'true' : 'false',
            'show_copies' => $attributes['showCopies'] ? 'true' : 'false',
            'show_views' => $attributes['showViews'] ? 'true' : 'false',
            'show_chart' => $attributes['showChart'] ? 'true' : 'false',
            'period' => $attributes['period'],
            'class' => 'sml-media-stats sml-display-' . $attributes['displayStyle'] . ' ' . $attributes['customClass']
        );
        
        $shortcode = '[sml_media_stats';
        foreach ($shortcode_attrs as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        return '<div class="wp-block-sml-media-stats">' . 
               do_shortcode($shortcode) . 
               '</div>';
    }
    
    /**
     * Rendu du bloc de copie
     */
    public static function render_copy_block($attributes) {
        if (empty($attributes['mediaId']) || empty($attributes['formatId'])) {
            if (current_user_can('edit_posts')) {
                return '<div class="sml-block-placeholder">' . 
                       __('Veuillez configurer le média et le format dans l\'éditeur.', 'secure-media-link') . 
                       '</div>';
            }
            return '';
        }
        
        $shortcode_attrs = array(
            'media_id' => $attributes['mediaId'],
            'format_id' => $attributes['formatId'],
            'text' => $attributes['buttonText'],
            'show_input' => $attributes['showInput'] ? 'true' : 'false',
            'track' => $attributes['trackCopies'] ? 'true' : 'false',
            'class' => 'sml-copy-btn ' . $attributes['customClass']
        );
        
        if (!empty($attributes['linkId'])) {
            $shortcode_attrs['link_id'] = $attributes['linkId'];
            unset($shortcode_attrs['media_id'], $shortcode_attrs['format_id']);
        }
        
        $shortcode = '[sml_copy_button';
        foreach ($shortcode_attrs as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']';
        
        $alignment_class = 'has-text-align-' . $attributes['alignment'];
        
        return '<div class="wp-block-sml-copy-link ' . $alignment_class . '">' . 
               do_shortcode($shortcode) . 
               '</div>';
    }
    
    /**
     * AJAX - Obtenir la liste des médias
     */
    public static function ajax_get_media_list() {
        check_ajax_referer('sml_gutenberg', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_wp_attachment_metadata',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query($args);
        $media_items = array();
        
        foreach ($query->posts as $post) {
            $media_items[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => wp_get_attachment_url($post->ID),
                'thumbnail' => wp_get_attachment_image_url($post->ID, 'thumbnail'),
                'type' => $post->post_mime_type,
                'date' => $post->post_date
            );
        }
        
        wp_send_json_success(array(
            'media' => $media_items,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ));
    }
    
    /**
     * AJAX - Obtenir les formats de média
     */
    public static function ajax_get_media_formats() {
        check_ajax_referer('sml_gutenberg', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $formats = SML_Media_Formats::get_all_formats();
        $formatted_formats = array();
        
        foreach ($formats as $format) {
            $formatted_formats[] = array(
                'id' => $format->id,
                'name' => $format->name,
                'type' => $format->type,
                'description' => $format->description,
                'dimensions' => $format->width && $format->height ? 
                    $format->width . 'x' . $format->height : 
                    ($format->width ? $format->width . 'px' : ($format->height ? $format->height . 'px' : 'Original'))
            );
        }
        
        wp_send_json_success($formatted_formats);
    }
    
    /**
     * AJAX - Générer un lien pour le bloc
     */
    public static function ajax_generate_block_link() {
        check_ajax_referer('sml_gutenberg', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $media_id = intval($_POST['media_id']);
        $format_id = intval($_POST['format_id']);
        
        if (!$media_id || !$format_id) {
            wp_send_json_error(__('Paramètres invalides', 'secure-media-link'));
        }
        
        // Vérifier si un lien existe déjà
        global $wpdb;
        $existing_link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sml_secure_links 
             WHERE media_id = %d AND format_id = %d AND is_active = 1 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            $media_id,
            $format_id
        ));
        
        if ($existing_link) {
            wp_send_json_success(array(
                'link_id' => $existing_link->id,
                'url' => SML_Crypto::generate_secure_link($media_id, $format_id, $existing_link->expires_at),
                'expires_at' => $existing_link->expires_at,
                'existing' => true
            ));
        } else {
            // Générer un nouveau lien
            $secure_url = SML_Crypto::generate_secure_link($media_id, $format_id);
            
            if ($secure_url) {
                $link_id = $wpdb->insert_id;
                wp_send_json_success(array(
                    'link_id' => $link_id,
                    'url' => $secure_url,
                    'existing' => false
                ));
            } else {
                wp_send_json_error(__('Erreur lors de la génération du lien', 'secure-media-link'));
            }
        }
    }
    
    /**
     * Obtenir les options pour les sélecteurs
     */
    public static function get_alignment_options() {
        return array(
            array('label' => __('Gauche', 'secure-media-link'), 'value' => 'left'),
            array('label' => __('Centre', 'secure-media-link'), 'value' => 'center'),
            array('label' => __('Droite', 'secure-media-link'), 'value' => 'right')
        );
    }
    
    public static function get_button_style_options() {
        return array(
            array('label' => __('Principal', 'secure-media-link'), 'value' => 'primary'),
            array('label' => __('Secondaire', 'secure-media-link'), 'value' => 'secondary'),
            array('label' => __('Succès', 'secure-media-link'), 'value' => 'success'),
            array('label' => __('Danger', 'secure-media-link'), 'value' => 'danger'),
            array('label' => __('Lien', 'secure-media-link'), 'value' => 'link')
        );
    }
    
    public static function get_period_options() {
        return array(
            array('label' => __('Aujourd\'hui', 'secure-media-link'), 'value' => 'day'),
            array('label' => __('7 jours', 'secure-media-link'), 'value' => 'week'),
            array('label' => __('30 jours', 'secure-media-link'), 'value' => 'month'),
            array('label' => __('1 an', 'secure-media-link'), 'value' => 'year')
        );
    }
    
    public static function get_display_style_options() {
        return array(
            array('label' => __('Cartes', 'secure-media-link'), 'value' => 'cards'),
            array('label' => __('Liste', 'secure-media-link'), 'value' => 'list'),
            array('label' => __('Compact', 'secure-media-link'), 'value' => 'compact')
        );
    }
    
    /**
     * Vérifier si les médias ont des liens sécurisés
     */
    public static function check_media_links($media_ids) {
        if (empty($media_ids)) {
            return array();
        }
        
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($media_ids), '%d'));
        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT media_id, COUNT(*) as link_count
             FROM {$wpdb->prefix}sml_secure_links 
             WHERE media_id IN ($placeholders) AND is_active = 1 AND expires_at > NOW()
             GROUP BY media_id",
            $media_ids
        ));
        
        $result = array();
        foreach ($links as $link) {
            $result[$link->media_id] = $link->link_count;
        }
        
        return $result;
    }
    
    /**
     * Valider les attributs des blocs
     */
    public static function validate_block_attributes($attributes, $block_name) {
        $errors = array();
        
        switch ($block_name) {
            case 'sml/secure-download':
            case 'sml/copy-link':
                if (empty($attributes['mediaId']) && empty($attributes['linkId'])) {
                    $errors[] = __('Un média ou un lien doit être sélectionné', 'secure-media-link');
                }
                break;
                
            case 'sml/secure-gallery':
                if (empty($attributes['mediaIds'])) {
                    $errors[] = __('Au moins un média doit être sélectionné', 'secure-media-link');
                }
                break;
                
            case 'sml/media-stats':
                if (empty($attributes['mediaId'])) {
                    $errors[] = __('Un média doit être sélectionné', 'secure-media-link');
                }
                break;
                
            case 'sml/upload-form':
                if ($attributes['maxFiles'] < 1) {
                    $errors[] = __('Le nombre maximum de fichiers doit être supérieur à 0', 'secure-media-link');
                }
                if ($attributes['maxSize'] < 1024) {
                    $errors[] = __('La taille maximale doit être supérieure à 1KB', 'secure-media-link');
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * Enregistrer les métadonnées des blocs pour le SEO
     */
    public static function add_block_metadata() {
        return array(
            'sml/secure-download' => array(
                'title' => __('Bouton de téléchargement sécurisé', 'secure-media-link'),
                'description' => __('Ajoute un bouton pour télécharger un média avec un lien sécurisé et temporaire', 'secure-media-link'),
                'keywords' => array('download', 'secure', 'media', 'téléchargement', 'sécurisé'),
                'category' => 'secure-media-link',
                'icon' => 'download',
                'supports' => array(
                    'align' => array('left', 'center', 'right'),
                    'className' => true,
                    'customClassName' => true
                )
            ),
            'sml/secure-gallery' => array(
                'title' => __('Galerie de médias sécurisés', 'secure-media-link'),
                'description' => __('Affiche une galerie de médias avec des liens de téléchargement sécurisés', 'secure-media-link'),
                'keywords' => array('gallery', 'media', 'secure', 'galerie', 'sécurisé'),
                'category' => 'secure-media-link',
                'icon' => 'format-gallery',
                'supports' => array(
                    'align' => array('wide', 'full'),
                    'className' => true,
                    'customClassName' => true
                )
            ),
            'sml/upload-form' => array(
                'title' => __('Formulaire d\'upload sécurisé', 'secure-media-link'),
                'description' => __('Permet aux utilisateurs d\'uploader des fichiers avec validation et sécurité', 'secure-media-link'),
                'keywords' => array('upload', 'form', 'file', 'formulaire', 'fichier'),
                'category' => 'secure-media-link',
                'icon' => 'upload',
                'supports' => array(
                    'className' => true,
                    'customClassName' => true
                )
            ),
            'sml/media-stats' => array(
                'title' => __('Statistiques de média', 'secure-media-link'),
                'description' => __('Affiche les statistiques d\'utilisation d\'un média (téléchargements, vues, etc.)', 'secure-media-link'),
                'keywords' => array('stats', 'statistics', 'analytics', 'statistiques'),
                'category' => 'secure-media-link',
                'icon' => 'chart-bar',
                'supports' => array(
                    'className' => true,
                    'customClassName' => true
                )
            ),
            'sml/copy-link' => array(
                'title' => __('Bouton de copie de lien', 'secure-media-link'),
                'description' => __('Permet de copier un lien sécurisé vers un média dans le presse-papiers', 'secure-media-link'),
                'keywords' => array('copy', 'link', 'clipboard', 'copier', 'lien'),
                'category' => 'secure-media-link',
                'icon' => 'admin-page',
                'supports' => array(
                    'align' => array('left', 'center', 'right'),
                    'className' => true,
                    'customClassName' => true
                )
            )
        );
    }
    
    /**
     * Générer le CSS dynamique pour les blocs
     */
    public static function generate_block_css($attributes, $block_name, $block_id) {
        $css = '';
        
        switch ($block_name) {
            case 'sml/secure-gallery':
                if (!empty($attributes['gap'])) {
                    $css .= ".wp-block-sml-secure-gallery#{$block_id} .sml-gallery-grid { gap: {$attributes['gap']}px; }";
                }
                if (!empty($attributes['columns'])) {
                    $css .= ".wp-block-sml-secure-gallery#{$block_id} .sml-gallery-grid { grid-template-columns: repeat({$attributes['columns']}, 1fr); }";
                }
                break;
                
            case 'sml/media-stats':
                if ($attributes['displayStyle'] === 'compact') {
                    $css .= ".wp-block-sml-media-stats#{$block_id} .sml-stats-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }";
                    $css .= ".wp-block-sml-media-stats#{$block_id} .sml-stat-number { font-size: 18px; }";
                }
                break;
        }
        
        return $css;
    }
    
    /**
     * Ajouter les hooks pour l'édition en temps réel
     */
    public static function add_live_preview_hooks() {
        add_action('wp_ajax_sml_preview_block', array(__CLASS__, 'ajax_preview_block'));
    }
    
    /**
     * AJAX - Prévisualisation en temps réel des blocs
     */
    public static function ajax_preview_block() {
        check_ajax_referer('sml_gutenberg', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $block_name = sanitize_text_field($_POST['block_name']);
        $attributes = $_POST['attributes'];
        
        // Validation des attributs
        $errors = self::validate_block_attributes($attributes, $block_name);
        if (!empty($errors)) {
            wp_send_json_error(implode(', ', $errors));
        }
        
        // Générer la prévisualisation
        $preview_html = '';
        
        switch ($block_name) {
            case 'sml/secure-download':
                $preview_html = self::render_download_block($attributes);
                break;
            case 'sml/secure-gallery':
                $preview_html = self::render_gallery_block($attributes);
                break;
            case 'sml/upload-form':
                $preview_html = self::render_upload_block($attributes);
                break;
            case 'sml/media-stats':
                $preview_html = self::render_stats_block($attributes);
                break;
            case 'sml/copy-link':
                $preview_html = self::render_copy_block($attributes);
                break;
        }
        
        wp_send_json_success(array(
            'html' => $preview_html,
            'css' => self::generate_block_css($attributes, $block_name, 'preview-' . uniqid())
        ));
    }
    
    /**
     * Enregistrer les patterns de blocs
     */
    public static function register_block_patterns() {
        if (function_exists('register_block_pattern')) {
            
            // Pattern : Galerie de téléchargement
            register_block_pattern(
                'sml/download-gallery',
                array(
                    'title' => __('Galerie de téléchargement', 'secure-media-link'),
                    'description' => __('Une galerie avec boutons de téléchargement et statistiques', 'secure-media-link'),
                    'content' => '<!-- wp:heading {"level":2} -->
<h2>' . __('Médias disponibles au téléchargement', 'secure-media-link') . '</h2>
<!-- /wp:heading -->

<!-- wp:sml/secure-gallery {"columns":3,"showTitles":true,"showDownloadButtons":true,"lightbox":true} /-->

<!-- wp:sml/media-stats {"period":"month","showChart":true} /-->',
                    'categories' => array('secure-media-link'),
                    'keywords' => array('download', 'gallery', 'stats')
                )
            );
            
            // Pattern : Page d'upload
            register_block_pattern(
                'sml/upload-page',
                array(
                    'title' => __('Page d\'upload', 'secure-media-link'),
                    'description' => __('Page complète pour l\'upload de fichiers avec instructions', 'secure-media-link'),
                    'content' => '<!-- wp:heading {"level":1} -->
<h1>' . __('Uploader vos fichiers', 'secure-media-link') . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . __('Utilisez le formulaire ci-dessous pour uploader vos fichiers en toute sécurité.', 'secure-media-link') . '</p>
<!-- /wp:paragraph -->

<!-- wp:sml/upload-form {"maxFiles":5,"showPreview":true} /-->

<!-- wp:heading {"level":3} -->
<h3>' . __('Vos médias', 'secure-media-link') . '</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[sml_user_media limit="12" show_stats="true"]
<!-- /wp:shortcode -->',
                    'categories' => array('secure-media-link'),
                    'keywords' => array('upload', 'form', 'user')
                )
            );
            
            // Pattern : Boutons de téléchargement
            register_block_pattern(
                'sml/download-buttons',
                array(
                    'title' => __('Boutons de téléchargement multiples', 'secure-media-link'),
                    'description' => __('Plusieurs boutons pour différents formats d\'un même média', 'secure-media-link'),
                    'content' => '<!-- wp:heading {"level":3} -->
<h3>' . __('Télécharger ce fichier', 'secure-media-link') . '</h3>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group">
<!-- wp:sml/secure-download {"buttonText":"' . __('Télécharger HD', 'secure-media-link') . '","buttonStyle":"primary"} /-->

<!-- wp:sml/secure-download {"buttonText":"' . __('Télécharger Web', 'secure-media-link') . '","buttonStyle":"secondary"} /-->

<!-- wp:sml/copy-link {"buttonText":"' . __('Copier le lien', 'secure-media-link') . '","showInput":true} /-->
</div>
<!-- /wp:group -->',
                    'categories' => array('secure-media-link'),
                    'keywords' => array('download', 'buttons', 'formats')
                )
            );
        }
    }
    
    /**
     * Ajouter des variations de blocs
     */
    public static function register_block_variations() {
        if (function_exists('register_block_variation')) {
            
            // Variation du bloc de téléchargement pour les images
            register_block_variation(
                'sml/secure-download',
                array(
                    'name' => 'image-download',
                    'title' => __('Téléchargement d\'image', 'secure-media-link'),
                    'description' => __('Bouton optimisé pour le téléchargement d\'images', 'secure-media-link'),
                    'icon' => 'format-image',
                    'attributes' => array(
                        'buttonText' => __('Télécharger l\'image', 'secure-media-link'),
                        'buttonStyle' => 'primary',
                        'showIcon' => true
                    ),
                    'scope' => array('inserter')
                )
            );
            
            // Variation du bloc de téléchargement pour les documents
            register_block_variation(
                'sml/secure-download',
                array(
                    'name' => 'document-download',
                    'title' => __('Téléchargement de document', 'secure-media-link'),
                    'description' => __('Bouton optimisé pour le téléchargement de documents', 'secure-media-link'),
                    'icon' => 'media-document',
                    'attributes' => array(
                        'buttonText' => __('Télécharger le document', 'secure-media-link'),
                        'buttonStyle' => 'secondary',
                        'showIcon' => true
                    ),
                    'scope' => array('inserter')
                )
            );
            
            // Variation de galerie pour portfolios
            register_block_variation(
                'sml/secure-gallery',
                array(
                    'name' => 'portfolio-gallery',
                    'title' => __('Galerie portfolio', 'secure-media-link'),
                    'description' => __('Galerie optimisée pour présenter un portfolio', 'secure-media-link'),
                    'icon' => 'portfolio',
                    'attributes' => array(
                        'columns' => 4,
                        'showTitles' => true,
                        'showDescriptions' => true,
                        'lightbox' => true,
                        'gap' => 15
                    ),
                    'scope' => array('inserter')
                )
            );
        }
    }
    
    /**
     * Ajouter le support pour les styles de blocs
     */
    public static function add_block_styles() {
        if (function_exists('register_block_style')) {
            
            // Styles pour le bloc de téléchargement
            register_block_style(
                'sml/secure-download',
                array(
                    'name' => 'outlined',
                    'label' => __('Contouré', 'secure-media-link'),
                    'style_handle' => 'sml-gutenberg-frontend'
                )
            );
            
            register_block_style(
                'sml/secure-download',
                array(
                    'name' => 'minimal',
                    'label' => __('Minimal', 'secure-media-link'),
                    'style_handle' => 'sml-gutenberg-frontend'
                )
            );
            
            // Styles pour la galerie
            register_block_style(
                'sml/secure-gallery',
                array(
                    'name' => 'masonry',
                    'label' => __('Masonry', 'secure-media-link'),
                    'style_handle' => 'sml-gutenberg-frontend'
                )
            );
            
            register_block_style(
                'sml/secure-gallery',
                array(
                    'name' => 'carousel',
                    'label' => __('Carrousel', 'secure-media-link'),
                    'style_handle' => 'sml-gutenberg-frontend'
                )
            );
        }
    }
    
    /**
     * Ajouter les filtres pour personnaliser l'apparence
     */
    public static function add_block_filters() {
        // Filtre pour personnaliser les attributs avant le rendu
        add_filter('sml_block_attributes', array(__CLASS__, 'filter_block_attributes'), 10, 2);
        
        // Filtre pour personnaliser le HTML de sortie
        add_filter('sml_block_output', array(__CLASS__, 'filter_block_output'), 10, 3);
    }
    
    /**
     * Filtre pour les attributs des blocs
     */
    public static function filter_block_attributes($attributes, $block_name) {
        // Permettre la personnalisation des attributs par les thèmes/plugins
        return apply_filters("sml_block_attributes_{$block_name}", $attributes);
    }
    
    /**
     * Filtre pour le HTML de sortie des blocs
     */
    public static function filter_block_output($output, $attributes, $block_name) {
        // Permettre la personnalisation du HTML par les thèmes/plugins
        return apply_filters("sml_block_output_{$block_name}", $output, $attributes);
    }
    
    /**
     * Ajouter les hooks d'initialisation
     */
    public static function init_hooks() {
        add_action('init', array(__CLASS__, 'register_block_patterns'));
        add_action('init', array(__CLASS__, 'register_block_variations'));
        add_action('init', array(__CLASS__, 'add_block_styles'));
        add_action('init', array(__CLASS__, 'add_block_filters'));
        add_action('init', array(__CLASS__, 'add_live_preview_hooks'));
    }
    
    /**
     * Obtenir les informations de compatibilité
     */
    public static function get_compatibility_info() {
        return array(
            'gutenberg_required' => '10.0',
            'wordpress_required' => '5.8',
            'php_required' => '7.4',
            'features' => array(
                'block_api_version' => 2,
                'supports_dynamic_blocks' => true,
                'supports_server_side_render' => true,
                'supports_inner_blocks' => false,
                'supports_variations' => true,
                'supports_patterns' => true,
                'supports_styles' => true
            )
        );
    }
    
    /**
     * Vérifier la compatibilité avec la version de WordPress/Gutenberg
     */
    public static function check_compatibility() {
        $compatibility = self::get_compatibility_info();
        $issues = array();
        
        // Vérifier WordPress
        if (version_compare(get_bloginfo('version'), $compatibility['wordpress_required'], '<')) {
            $issues[] = sprintf(
                __('WordPress %s ou supérieur requis (version actuelle: %s)', 'secure-media-link'),
                $compatibility['wordpress_required'],
                get_bloginfo('version')
            );
        }
        
        // Vérifier PHP
        if (version_compare(PHP_VERSION, $compatibility['php_required'], '<')) {
            $issues[] = sprintf(
                __('PHP %s ou supérieur requis (version actuelle: %s)', 'secure-media-link'),
                $compatibility['php_required'],
                PHP_VERSION
            );
        }
        
        // Vérifier si Gutenberg est disponible
        if (!function_exists('register_block_type')) {
            $issues[] = __('L\'éditeur de blocs (Gutenberg) n\'est pas disponible', 'secure-media-link');
        }
        
        return $issues;
    }
    
    /**
     * Afficher les avertissements de compatibilité
     */
    public static function show_compatibility_warnings() {
        $issues = self::check_compatibility();
        
        if (!empty($issues)) {
            foreach ($issues as $issue) {
                add_action('admin_notices', function() use ($issue) {
                    echo '<div class="notice notice-warning">';
                    echo '<p><strong>Secure Media Link - Gutenberg:</strong> ' . esc_html($issue) . '</p>';
                    echo '</div>';
                });
            }
        }
    }
}

// Initialiser l'intégration Gutenberg
add_action('plugins_loaded', array('SML_Gutenberg', 'init'));
add_action('plugins_loaded', array('SML_Gutenberg', 'init_hooks'));
add_action('admin_init', array('SML_Gutenberg', 'show_compatibility_warnings'));

?>