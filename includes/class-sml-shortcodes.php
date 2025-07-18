<?php
/**
 * Classe pour les shortcodes frontend
 * includes/class-sml-shortcodes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Shortcodes {
    
    /**
     * Enregistrer tous les shortcodes
     */
    public static function register_all() {
        add_shortcode('sml_upload_form', array(__CLASS__, 'upload_form_shortcode'));
        add_shortcode('sml_user_media', array(__CLASS__, 'user_media_shortcode'));
        add_shortcode('sml_download_button', array(__CLASS__, 'download_button_shortcode'));
        add_shortcode('sml_copy_button', array(__CLASS__, 'copy_button_shortcode'));
        add_shortcode('sml_media_gallery', array(__CLASS__, 'media_gallery_shortcode'));
        add_shortcode('sml_media_stats', array(__CLASS__, 'media_stats_shortcode'));
    }
    
    /**
     * Shortcode pour le formulaire d'upload
     * [sml_upload_form]
     */
    public static function upload_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'multiple' => 'true',
            'allowed_types' => 'image/*',
            'max_files' => '10',
            'max_size' => '10485760', // 10MB
            'show_preview' => 'true',
            'redirect_after' => '',
            'class' => 'sml-upload-form'
        ), $atts);
        
        // Vérifier si l'utilisateur est connecté
        if (!is_user_logged_in()) {
            return '<div class="sml-error">' . __('Vous devez être connecté pour uploader des fichiers.', 'secure-media-link') . '</div>';
        }
        
        $current_user = wp_get_current_user();
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <form id="sml-upload-form" method="post" enctype="multipart/form-data">
                <div class="sml-form-section">
                    <label for="sml-upload-files"><?php _e('Sélectionner les fichiers', 'secure-media-link'); ?></label>
                    <input type="file" 
                           id="sml-upload-files" 
                           name="sml_files[]" 
                           <?php echo $atts['multiple'] === 'true' ? 'multiple' : ''; ?>
                           accept="<?php echo esc_attr($atts['allowed_types']); ?>"
                           required>
                    <p class="sml-help-text">
                        <?php printf(__('Maximum %d fichiers, %s par fichier', 'secure-media-link'), 
                            $atts['max_files'], 
                            size_format($atts['max_size'])
                        ); ?>
                    </p>
                </div>
                
                <div class="sml-form-section">
                    <label for="sml-upload-caption"><?php _e('Légende', 'secure-media-link'); ?></label>
                    <input type="text" id="sml-upload-caption" name="caption" maxlength="200">
                </div>
                
                <div class="sml-form-section">
                    <label for="sml-upload-description"><?php _e('Description', 'secure-media-link'); ?></label>
                    <textarea id="sml-upload-description" name="description" rows="4"></textarea>
                </div>
                
                <div class="sml-form-section">
                    <label for="sml-upload-copyright"><?php _e('Copyright', 'secure-media-link'); ?></label>
                    <input type="text" id="sml-upload-copyright" name="copyright" value="© <?php echo esc_attr($current_user->display_name); ?>">
                </div>
                
                <div class="sml-form-section">
                    <label for="sml-upload-expiry"><?php _e('Date d\'expiration', 'secure-media-link'); ?></label>
                    <input type="datetime-local" id="sml-upload-expiry" name="expiry_date" 
                           min="<?php echo date('Y-m-d\TH:i'); ?>"
                           value="<?php echo date('Y-m-d\TH:i', strtotime('+3 years')); ?>">
                </div>
                
                <?php if ($atts['show_preview'] === 'true'): ?>
                    <div class="sml-form-section">
                        <div id="sml-upload-preview" class="sml-upload-preview"></div>
                    </div>
                <?php endif; ?>
                
                <div class="sml-form-section">
                    <div class="sml-upload-progress" style="display: none;">
                        <div class="sml-progress-bar">
                            <div class="sml-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="sml-progress-text">0%</div>
                    </div>
                </div>
                
                <div class="sml-form-actions">
                    <button type="submit" class="sml-btn sml-btn-primary">
                        <?php _e('Uploader les fichiers', 'secure-media-link'); ?>
                    </button>
                    <button type="reset" class="sml-btn sml-btn-secondary">
                        <?php _e('Réinitialiser', 'secure-media-link'); ?>
                    </button>
                </div>
                
                <input type="hidden" name="action" value="sml_frontend_upload">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sml_upload'); ?>">
                <input type="hidden" name="max_files" value="<?php echo esc_attr($atts['max_files']); ?>">
                <input type="hidden" name="max_size" value="<?php echo esc_attr($atts['max_size']); ?>">
                <input type="hidden" name="redirect_after" value="<?php echo esc_attr($atts['redirect_after']); ?>">
            </form>
            
            <div id="sml-upload-messages"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $form = $('#sml-upload-form');
            var $fileInput = $('#sml-upload-files');
            var $preview = $('#sml-upload-preview');
            var $progress = $('.sml-upload-progress');
            var $progressBar = $('.sml-progress-fill');
            var $progressText = $('.sml-progress-text');
            var $messages = $('#sml-upload-messages');
            
            // Prévisualisation des fichiers
            $fileInput.on('change', function() {
                var files = this.files;
                $preview.empty();
                
                if (files.length > parseInt('<?php echo $atts['max_files']; ?>')) {
                    showMessage('error', '<?php _e('Trop de fichiers sélectionnés', 'secure-media-link'); ?>');
                    return;
                }
                
                Array.from(files).forEach(function(file, index) {
                    if (file.size > parseInt('<?php echo $atts['max_size']; ?>')) {
                        showMessage('error', file.name + ': <?php _e('Fichier trop volumineux', 'secure-media-link'); ?>');
                        return;
                    }
                    
                    var $item = $('<div class="sml-preview-item">');
                    $item.append('<span class="sml-file-name">' + file.name + '</span>');
                    $item.append('<span class="sml-file-size">(' + formatFileSize(file.size) + ')</span>');
                    
                    // Prévisualisation des images
                    if (file.type.startsWith('image/')) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            $item.prepend('<img src="' + e.target.result + '" alt="Preview" class="sml-preview-image">');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        $item.prepend('<div class="sml-file-icon"><span class="dashicons dashicons-media-document"></span></div>');
                    }
                    
                    $preview.append($item);
                });
            });
            
            // Soumission du formulaire
            $form.on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                $progress.show();
                $form.find('button[type="submit"]').prop('disabled', true);
                
                $.ajax({
                    url: sml_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                var percentComplete = (e.loaded / e.total) * 100;
                                updateProgress(percentComplete);
                            }
                        });
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('success', response.data.message);
                            $form[0].reset();
                            $preview.empty();
                            
                            var redirectUrl = '<?php echo $atts['redirect_after']; ?>';
                            if (redirectUrl) {
                                setTimeout(function() {
                                    window.location.href = redirectUrl;
                                }, 2000);
                            }
                        } else {
                            showMessage('error', response.data);
                        }
                    },
                    error: function() {
                        showMessage('error', '<?php _e('Erreur lors de l\'upload', 'secure-media-link'); ?>');
                    },
                    complete: function() {
                        $progress.hide();
                        $form.find('button[type="submit"]').prop('disabled', false);
                        updateProgress(0);
                    }
                });
            });
            
            function updateProgress(percent) {
                $progressBar.css('width', percent + '%');
                $progressText.text(Math.round(percent) + '%');
            }
            
            function showMessage(type, message) {
                var $message = $('<div class="sml-message sml-message-' + type + '">' + message + '</div>');
                $messages.append($message);
                
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $message.remove();
                    });
                }, 5000);
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                var k = 1024;
                var sizes = ['Bytes', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les médias d'un utilisateur
     * [sml_user_media user_id="123" limit="10"]
     */
    public static function user_media_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'limit' => '10',
            'show_pending' => 'false',
            'show_stats' => 'true',
            'class' => 'sml-user-media'
        ), $atts);
        
        if (!$atts['user_id'] || !is_user_logged_in()) {
            return '<div class="sml-error">' . __('Vous devez être connecté pour voir vos médias.', 'secure-media-link') . '</div>';
        }
        
        // Vérifier les permissions
        if ($atts['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            return '<div class="sml-error">' . __('Accès refusé.', 'secure-media-link') . '</div>';
        }
        
        global $wpdb;
        
        $where_conditions = array("p.post_author = %d", "p.post_type = 'attachment'");
        $params = array($atts['user_id']);
        
        if ($atts['show_pending'] === 'true') {
            $where_conditions[] = "fu.status = 'pending'";
        } else {
            $where_conditions[] = "(fu.status IS NULL OR fu.status != 'pending')";
        }
        
        $sql = "SELECT p.*, fu.status as upload_status, fu.created_at as upload_date
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->prefix}sml_frontend_uploads fu ON p.ID = fu.media_id
                WHERE " . implode(' AND ', $where_conditions) . "
                ORDER BY p.post_date DESC
                LIMIT %d";
        
        $params[] = intval($atts['limit']);
        $media_items = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['show_stats'] === 'true'): ?>
                <div class="sml-user-stats">
                    <?php
                    $total_media = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'attachment'",
                        $atts['user_id']
                    ));
                    
                    $total_downloads = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(sl.download_count) 
                         FROM {$wpdb->prefix}sml_secure_links sl
                         LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
                         WHERE p.post_author = %d",
                        $atts['user_id']
                    ));
                    ?>
                    <div class="sml-stat">
                        <span class="sml-stat-label"><?php _e('Total médias:', 'secure-media-link'); ?></span>
                        <span class="sml-stat-value"><?php echo number_format($total_media); ?></span>
                    </div>
                    <div class="sml-stat">
                        <span class="sml-stat-label"><?php _e('Total téléchargements:', 'secure-media-link'); ?></span>
                        <span class="sml-stat-value"><?php echo number_format($total_downloads ?: 0); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="sml-media-grid">
                <?php if (!empty($media_items)): ?>
                    <?php foreach ($media_items as $media): ?>
                        <div class="sml-media-item" data-media-id="<?php echo $media->ID; ?>">
                            <div class="sml-media-thumbnail">
                                <?php echo wp_get_attachment_image($media->ID, 'medium'); ?>
                            </div>
                            
                            <div class="sml-media-info">
                                <h4><?php echo esc_html($media->post_title); ?></h4>
                                
                                <div class="sml-media-meta">
                                    <span class="sml-upload-date">
                                        <?php echo date_i18n(get_option('date_format'), strtotime($media->post_date)); ?>
                                    </span>
                                    
                                    <?php if ($media->upload_status === 'pending'): ?>
                                        <span class="sml-status sml-pending"><?php _e('En attente', 'secure-media-link'); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="sml-media-actions">
                                    <button class="sml-btn sml-btn-small sml-view-links" data-media-id="<?php echo $media->ID; ?>">
                                        <?php _e('Voir les liens', 'secure-media-link'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sml-no-media">
                        <p><?php _e('Aucun média trouvé.', 'secure-media-link'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($media_items) >= $atts['limit']): ?>
                <div class="sml-load-more">
                    <button class="sml-btn sml-btn-secondary" id="sml-load-more-media" 
                            data-user-id="<?php echo $atts['user_id']; ?>" 
                            data-offset="<?php echo $atts['limit']; ?>">
                        <?php _e('Charger plus', 'secure-media-link'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Modal pour afficher les liens -->
        <div id="sml-links-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <div id="sml-links-content"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Voir les liens d'un média
            $('.sml-view-links').on('click', function() {
                var mediaId = $(this).data('media-id');
                loadMediaLinks(mediaId);
            });
            
            // Charger plus de médias
            $('#sml-load-more-media').on('click', function() {
                var $btn = $(this);
                var userId = $btn.data('user-id');
                var offset = $btn.data('offset');
                
                $btn.prop('disabled', true).text('<?php _e('Chargement...', 'secure-media-link'); ?>');
                
                $.post(sml_ajax.ajax_url, {
                    action: 'sml_load_more_user_media',
                    nonce: sml_ajax.nonce,
                    user_id: userId,
                    offset: offset,
                    limit: <?php echo $atts['limit']; ?>
                }, function(response) {
                    if (response.success) {
                        $('.sml-media-grid').append(response.data.content);
                        
                        if (response.data.has_more) {
                            $btn.data('offset', offset + <?php echo $atts['limit']; ?>);
                            $btn.prop('disabled', false).text('<?php _e('Charger plus', 'secure-media-link'); ?>');
                        } else {
                            $btn.hide();
                        }
                    }
                });
            });
            
            // Fermer la modal
            $('.sml-modal-close').on('click', function() {
                $(this).closest('.sml-modal').hide();
            });
            
            function loadMediaLinks(mediaId) {
                $('#sml-links-modal').show();
                $('#sml-links-content').html('<div class="sml-loading"><?php _e('Chargement...', 'secure-media-link'); ?></div>');
                
                $.post(sml_ajax.ajax_url, {
                    action: 'sml_get_media_links',
                    nonce: sml_ajax.nonce,
                    media_id: mediaId
                }, function(response) {
                    if (response.success) {
                        $('#sml-links-content').html(response.data);
                    } else {
                        $('#sml-links-content').html('<div class="sml-error">' + response.data + '</div>');
                    }
                });
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour un bouton de téléchargement
     * [sml_download_button link_id="123" text="Télécharger"]
     */
    public static function download_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'link_id' => '',
            'media_id' => '',
            'format_id' => '',
            'text' => __('Télécharger', 'secure-media-link'),
            'class' => 'sml-download-btn',
            'icon' => 'true',
            'track' => 'true'
        ), $atts);
        
        if (!$atts['link_id'] && (!$atts['media_id'] || !$atts['format_id'])) {
            return '<div class="sml-error">' . __('Paramètres insuffisants pour le bouton de téléchargement', 'secure-media-link') . '</div>';
        }
        
        // Récupérer le lien sécurisé
        if ($atts['link_id']) {
            global $wpdb;
            $link = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sml_secure_links WHERE id = %d AND is_active = 1",
                $atts['link_id']
            ));
            
            if (!$link || strtotime($link->expires_at) < time()) {
                return '<div class="sml-error">' . __('Lien non disponible ou expiré', 'secure-media-link') . '</div>';
            }
            
            $secure_url = SML_Crypto::generate_secure_link($link->media_id, $link->format_id, $link->expires_at);
        } else {
            $secure_url = SML_Crypto::generate_secure_link($atts['media_id'], $atts['format_id']);
            $atts['link_id'] = $wpdb->insert_id; // ID du lien nouvellement créé
        }
        
        if (!$secure_url) {
            return '<div class="sml-error">' . __('Impossible de générer le lien de téléchargement', 'secure-media-link') . '</div>';
        }
        
        $icon_html = $atts['icon'] === 'true' ? '<span class="dashicons dashicons-download"></span> ' : '';
        
        ob_start();
        ?>
        <a href="<?php echo esc_url($secure_url); ?>" 
           class="<?php echo esc_attr($atts['class']); ?> sml-download-link"
           data-link-id="<?php echo esc_attr($atts['link_id']); ?>"
           data-track="<?php echo esc_attr($atts['track']); ?>"
           download>
            <?php echo $icon_html . esc_html($atts['text']); ?>
        </a>
        
        <?php if ($atts['track'] === 'true'): ?>
            <script>
            jQuery(document).ready(function($) {
                $('.sml-download-link[data-link-id="<?php echo $atts['link_id']; ?>"]').on('click', function() {
                    var linkId = $(this).data('link-id');
                    
                    $.post(sml_ajax.ajax_url, {
                        action: 'sml_track_usage',
                        nonce: sml_ajax.nonce,
                        link_id: linkId,
                        action_type: 'download'
                    });
                });
            });
            </script>
        <?php endif; ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour un bouton de copie de lien
     * [sml_copy_button link_id="123" text="Copier le lien"]
     */
    public static function copy_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'link_id' => '',
            'media_id' => '',
            'format_id' => '',
            'text' => __('Copier le lien', 'secure-media-link'),
            'class' => 'sml-copy-btn',
            'icon' => 'true',
            'track' => 'true',
            'show_input' => 'false'
        ), $atts);
        
        if (!$atts['link_id'] && (!$atts['media_id'] || !$atts['format_id'])) {
            return '<div class="sml-error">' . __('Paramètres insuffisants pour le bouton de copie', 'secure-media-link') . '</div>';
        }
        
        // Récupérer le lien sécurisé
        if ($atts['link_id']) {
            global $wpdb;
            $link = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sml_secure_links WHERE id = %d AND is_active = 1",
                $atts['link_id']
            ));
            
            if (!$link || strtotime($link->expires_at) < time()) {
                return '<div class="sml-error">' . __('Lien non disponible ou expiré', 'secure-media-link') . '</div>';
            }
            
            $secure_url = SML_Crypto::generate_secure_link($link->media_id, $link->format_id, $link->expires_at);
        } else {
            $secure_url = SML_Crypto::generate_secure_link($atts['media_id'], $atts['format_id']);
            $atts['link_id'] = $wpdb->insert_id; // ID du lien nouvellement créé
        }
        
        if (!$secure_url) {
            return '<div class="sml-error">' . __('Impossible de générer le lien', 'secure-media-link') . '</div>';
        }
        
        $icon_html = $atts['icon'] === 'true' ? '<span class="dashicons dashicons-admin-page"></span> ' : '';
        $unique_id = 'sml-copy-' . uniqid();
        
        ob_start();
        ?>
        <div class="sml-copy-container">
            <?php if ($atts['show_input'] === 'true'): ?>
                <input type="text" id="<?php echo $unique_id; ?>-input" 
                       value="<?php echo esc_attr($secure_url); ?>" 
                       class="sml-link-input" readonly>
            <?php endif; ?>
            
            <button type="button" 
                    class="<?php echo esc_attr($atts['class']); ?> sml-copy-button"
                    data-link-id="<?php echo esc_attr($atts['link_id']); ?>"
                    data-track="<?php echo esc_attr($atts['track']); ?>"
                    data-url="<?php echo esc_attr($secure_url); ?>"
                    data-input-id="<?php echo $unique_id; ?>-input">
                <?php echo $icon_html . esc_html($atts['text']); ?>
            </button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.sml-copy-button[data-link-id="<?php echo $atts['link_id']; ?>"]').on('click', function() {
                var $btn = $(this);
                var url = $btn.data('url');
                var inputId = $btn.data('input-id');
                var linkId = $btn.data('link-id');
                var track = $btn.data('track');
                
                // Méthode de copie
                if (inputId && $('#' + inputId).length) {
                    // Copier depuis l'input
                    var $input = $('#' + inputId);
                    $input.select();
                    document.execCommand('copy');
                } else {
                    // Copier via l'API Clipboard ou fallback
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(url).catch(function() {
                            fallbackCopy(url);
                        });
                    } else {
                        fallbackCopy(url);
                    }
                }
                
                // Feedback visuel
                var originalText = $btn.html();
                $btn.html('<span class="dashicons dashicons-yes"></span> <?php _e('Copié !', 'secure-media-link'); ?>');
                
                setTimeout(function() {
                    $btn.html(originalText);
                }, 2000);
                
                // Tracking
                if (track === 'true') {
                    $.post(sml_ajax.ajax_url, {
                        action: 'sml_track_usage',
                        nonce: sml_ajax.nonce,
                        link_id: linkId,
                        action_type: 'copy'
                    });
                }
            });
            
            function fallbackCopy(text) {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.error('Fallback copy failed', err);
                }
                
                document.body.removeChild(textArea);
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour une galerie de médias sécurisés
     * [sml_media_gallery ids="1,2,3" columns="3"]
     */
    public static function media_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'ids' => '',
            'category' => '',
            'author' => '',
            'limit' => '12',
            'columns' => '3',
            'show_title' => 'true',
            'show_description' => 'false',
            'show_buttons' => 'true',
            'lightbox' => 'true',
            'class' => 'sml-media-gallery'
        ), $atts);
        
        global $wpdb;
        
        $where_conditions = array("p.post_type = 'attachment'");
        $params = array();
        
        if (!empty($atts['ids'])) {
            $ids = array_map('intval', explode(',', $atts['ids']));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where_conditions[] = "p.ID IN ($placeholders)";
            $params = array_merge($params, $ids);
        }
        
        if (!empty($atts['author'])) {
            $where_conditions[] = "p.post_author = %d";
            $params[] = intval($atts['author']);
        }
        
        if (!empty($atts['category'])) {
            // Ici on pourrait ajouter une logique de catégorisation custom
        }
        
        $where_sql = implode(' AND ', $where_conditions);
        
        $sql = "SELECT p.* FROM {$wpdb->posts} p 
                WHERE $where_sql 
                ORDER BY p.post_date DESC 
                LIMIT %d";
        
        $params[] = intval($atts['limit']);
        $media_items = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        if (empty($media_items)) {
            return '<div class="sml-no-media">' . __('Aucun média trouvé.', 'secure-media-link') . '</div>';
        }
        
        $column_class = 'sml-col-' . $atts['columns'];
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <div class="sml-gallery-grid <?php echo $column_class; ?>">
                <?php foreach ($media_items as $media): ?>
                    <div class="sml-gallery-item">
                        <div class="sml-gallery-image">
                            <?php if ($atts['lightbox'] === 'true'): ?>
                                <a href="<?php echo wp_get_attachment_url($media->ID); ?>" 
                                   class="sml-lightbox-trigger"
                                   data-title="<?php echo esc_attr($media->post_title); ?>">
                            <?php endif; ?>
                            
                            <?php echo wp_get_attachment_image($media->ID, 'medium'); ?>
                            
                            <?php if ($atts['lightbox'] === 'true'): ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($atts['show_title'] === 'true' || $atts['show_description'] === 'true' || $atts['show_buttons'] === 'true'): ?>
                            <div class="sml-gallery-content">
                                <?php if ($atts['show_title'] === 'true'): ?>
                                    <h4 class="sml-gallery-title"><?php echo esc_html($media->post_title); ?></h4>
                                <?php endif; ?>
                                
                                <?php if ($atts['show_description'] === 'true' && !empty($media->post_content)): ?>
                                    <p class="sml-gallery-description"><?php echo esc_html(wp_trim_words($media->post_content, 20)); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($atts['show_buttons'] === 'true'): ?>
                                    <div class="sml-gallery-actions">
                                        <?php
                                        // Récupérer les liens disponibles pour ce média
                                        $links = $wpdb->get_results($wpdb->prepare(
                                            "SELECT sl.*, mf.name as format_name 
                                             FROM {$wpdb->prefix}sml_secure_links sl
                                             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
                                             WHERE sl.media_id = %d AND sl.is_active = 1 AND sl.expires_at > NOW()
                                             ORDER BY mf.name",
                                            $media->ID
                                        ));
                                        
                                        if (!empty($links)): ?>
                                            <?php if (count($links) === 1): ?>
                                                <!-- Un seul lien disponible -->
                                                <a href="#" class="sml-btn sml-btn-primary sml-download-single" 
                                                   data-media-id="<?php echo $media->ID; ?>"
                                                   data-link-id="<?php echo $links[0]->id; ?>">
                                                    <span class="dashicons dashicons-download"></span>
                                                    <?php _e('Télécharger', 'secure-media-link'); ?>
                                                </a>
                                            <?php else: ?>
                                                <!-- Plusieurs liens disponibles - menu dropdown -->
                                                <div class="sml-dropdown">
                                                    <button class="sml-btn sml-btn-primary sml-dropdown-toggle">
                                                        <span class="dashicons dashicons-download"></span>
                                                        <?php _e('Télécharger', 'secure-media-link'); ?>
                                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                                    </button>
                                                    
                                                    <ul class="sml-dropdown-menu">
                                                        <?php foreach ($links as $link): ?>
                                                            <li>
                                                                <a href="#" class="sml-download-format" 
                                                                   data-media-id="<?php echo $media->ID; ?>"
                                                                   data-link-id="<?php echo $link->id; ?>"
                                                                   data-format="<?php echo esc_attr($link->format_name); ?>">
                                                                    <?php echo esc_html($link->format_name); ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <button class="sml-btn sml-btn-secondary sml-copy-links" 
                                                    data-media-id="<?php echo $media->ID; ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                                <?php _e('Copier', 'secure-media-link'); ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="sml-no-links"><?php _e('Aucun lien disponible', 'secure-media-link'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($atts['lightbox'] === 'true'): ?>
            <!-- Lightbox simple -->
            <div id="sml-lightbox" class="sml-lightbox" style="display: none;">
                <div class="sml-lightbox-overlay"></div>
                <div class="sml-lightbox-content">
                    <button class="sml-lightbox-close">&times;</button>
                    <img src="" alt="" class="sml-lightbox-image">
                    <div class="sml-lightbox-caption"></div>
                </div>
            </div>
        <?php endif; ?>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des dropdowns
            $('.sml-dropdown-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $dropdown = $(this).closest('.sml-dropdown');
                
                // Fermer tous les autres dropdowns
                $('.sml-dropdown').not($dropdown).removeClass('sml-open');
                
                // Toggle le dropdown actuel
                $dropdown.toggleClass('sml-open');
            });
            
            // Fermer les dropdowns en cliquant ailleurs
            $(document).on('click', function() {
                $('.sml-dropdown').removeClass('sml-open');
            });
            
            // Téléchargement simple
            $('.sml-download-single').on('click', function(e) {
                e.preventDefault();
                var linkId = $(this).data('link-id');
                downloadMedia(linkId);
            });
            
            // Téléchargement par format
            $('.sml-download-format').on('click', function(e) {
                e.preventDefault();
                var linkId = $(this).data('link-id');
                downloadMedia(linkId);
                $(this).closest('.sml-dropdown').removeClass('sml-open');
            });
            
            // Copier les liens
            $('.sml-copy-links').on('click', function() {
                var mediaId = $(this).data('media-id');
                copyMediaLinks(mediaId);
            });
            
            <?php if ($atts['lightbox'] === 'true'): ?>
                // Lightbox
                $('.sml-lightbox-trigger').on('click', function(e) {
                    e.preventDefault();
                    var src = $(this).attr('href');
                    var title = $(this).data('title');
                    
                    $('#sml-lightbox .sml-lightbox-image').attr('src', src).attr('alt', title);
                    $('#sml-lightbox .sml-lightbox-caption').text(title);
                    $('#sml-lightbox').fadeIn();
                });
                
                // Fermer la lightbox
                $('.sml-lightbox-close, .sml-lightbox-overlay').on('click', function() {
                    $('#sml-lightbox').fadeOut();
                });
                
                // Fermer avec échap
                $(document).on('keyup', function(e) {
                    if (e.keyCode === 27) {
                        $('#sml-lightbox').fadeOut();
                    }
                });
            <?php endif; ?>
            
            function downloadMedia(linkId) {
                // Récupérer l'URL sécurisée et déclencher le téléchargement
                $.post(sml_ajax.ajax_url, {
                    action: 'sml_get_download_url',
                    nonce: sml_ajax.nonce,
                    link_id: linkId
                }, function(response) {
                    if (response.success) {
                        // Créer un lien temporaire et déclencher le téléchargement
                        var link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = '';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Tracker le téléchargement
                        $.post(sml_ajax.ajax_url, {
                            action: 'sml_track_usage',
                            nonce: sml_ajax.nonce,
                            link_id: linkId,
                            action_type: 'download'
                        });
                    } else {
                        alert(response.data || '<?php _e('Erreur lors du téléchargement', 'secure-media-link'); ?>');
                    }
                });
            }
            
            function copyMediaLinks(mediaId) {
                $.post(sml_ajax.ajax_url, {
                    action: 'sml_get_media_links_for_copy',
                    nonce: sml_ajax.nonce,
                    media_id: mediaId
                }, function(response) {
                    if (response.success) {
                        var links = response.data.links;
                        var linkText = links.join('\n');
                        
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(linkText).then(function() {
                                showCopyFeedback();
                            });
                        } else {
                            // Fallback
                            var textArea = document.createElement('textarea');
                            textArea.value = linkText;
                            document.body.appendChild(textArea);
                            textArea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            showCopyFeedback();
                        }
                        
                        // Tracker les copies
                        links.forEach(function(link, index) {
                            if (response.data.link_ids[index]) {
                                $.post(sml_ajax.ajax_url, {
                                    action: 'sml_track_usage',
                                    nonce: sml_ajax.nonce,
                                    link_id: response.data.link_ids[index],
                                    action_type: 'copy'
                                });
                            }
                        });
                    }
                });
            }
            
            function showCopyFeedback() {
                // Créer un message de confirmation temporaire
                var $message = $('<div class="sml-copy-feedback"><?php _e('Liens copiés !', 'secure-media-link'); ?></div>');
                $('body').append($message);
                
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $message.remove();
                    });
                }, 2000);
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les statistiques d'un média
     * [sml_media_stats media_id="123"]
     */
    public static function media_stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'media_id' => '',
            'show_downloads' => 'true',
            'show_copies' => 'true',
            'show_views' => 'true',
            'show_chart' => 'false',
            'period' => 'month', // day, week, month, year
            'class' => 'sml-media-stats'
        ), $atts);
        
        if (!$atts['media_id']) {
            return '<div class="sml-error">' . __('ID du média requis', 'secure-media-link') . '</div>';
        }
        
        $media_id = intval($atts['media_id']);
        $media = get_post($media_id);
        
        if (!$media || $media->post_type !== 'attachment') {
            return '<div class="sml-error">' . __('Média introuvable', 'secure-media-link') . '</div>';
        }
        
        global $wpdb;
        
        // Calculer la période
        $date_condition = '';
        switch ($atts['period']) {
            case 'day':
                $date_condition = "AND DATE(t.created_at) = CURDATE()";
                break;
            case 'week':
                $date_condition = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $date_condition = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        // Récupérer les statistiques
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN t.action_type = 'download' AND t.is_authorized = 1 THEN 1 ELSE 0 END) as downloads,
                SUM(CASE WHEN t.action_type = 'copy' AND t.is_authorized = 1 THEN 1 ELSE 0 END) as copies,
                SUM(CASE WHEN t.action_type = 'view' AND t.is_authorized = 1 THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN t.is_authorized = 0 THEN 1 ELSE 0 END) as blocked
            FROM {$wpdb->prefix}sml_tracking t
            LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
            WHERE sl.media_id = %d {$date_condition}
        ", $media_id));
        
        if (!$stats) {
            $stats = (object) array('downloads' => 0, 'copies' => 0, 'views' => 0, 'blocked' => 0);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>" data-media-id="<?php echo $media_id; ?>">
            <div class="sml-stats-header">
                <h4><?php echo esc_html($media->post_title); ?></h4>
                <span class="sml-stats-period"><?php echo esc_html(ucfirst($atts['period'])); ?></span>
            </div>
            
            <div class="sml-stats-grid">
                <?php if ($atts['show_downloads'] === 'true'): ?>
                    <div class="sml-stat-item sml-stat-downloads">
                        <div class="sml-stat-icon">
                            <span class="dashicons dashicons-download"></span>
                        </div>
                        <div class="sml-stat-content">
                            <div class="sml-stat-number"><?php echo number_format($stats->downloads); ?></div>
                            <div class="sml-stat-label"><?php _e('Téléchargements', 'secure-media-link'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_copies'] === 'true'): ?>
                    <div class="sml-stat-item sml-stat-copies">
                        <div class="sml-stat-icon">
                            <span class="dashicons dashicons-admin-page"></span>
                        </div>
                        <div class="sml-stat-content">
                            <div class="sml-stat-number"><?php echo number_format($stats->copies); ?></div>
                            <div class="sml-stat-label"><?php _e('Copies', 'secure-media-link'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_views'] === 'true'): ?>
                    <div class="sml-stat-item sml-stat-views">
                        <div class="sml-stat-icon">
                            <span class="dashicons dashicons-visibility"></span>
                        </div>
                        <div class="sml-stat-content">
                            <div class="sml-stat-number"><?php echo number_format($stats->views); ?></div>
                            <div class="sml-stat-label"><?php _e('Vues', 'secure-media-link'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="sml-stat-item sml-stat-blocked">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="sml-stat-content">
                        <div class="sml-stat-number"><?php echo number_format($stats->blocked); ?></div>
                        <div class="sml-stat-label"><?php _e('Bloquées', 'secure-media-link'); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if ($atts['show_chart'] === 'true'): ?>
                <div class="sml-stats-chart">
                    <canvas id="sml-chart-<?php echo $media_id; ?>" width="400" height="200"></canvas>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Charger les données du graphique
                    $.post(sml_ajax.ajax_url, {
                        action: 'sml_get_media_chart_data',
                        nonce: sml_ajax.nonce,
                        media_id: <?php echo $media_id; ?>,
                        period: '<?php echo $atts['period']; ?>'
                    }, function(response) {
                        if (response.success && window.Chart) {
                            var ctx = document.getElementById('sml-chart-<?php echo $media_id; ?>').getContext('2d');
                            
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: response.data.labels,
                                    datasets: [{
                                        label: '<?php _e('Téléchargements', 'secure-media-link'); ?>',
                                        data: response.data.downloads,
                                        borderColor: 'rgb(75, 192, 192)',
                                        tension: 0.1
                                    }, {
                                        label: '<?php _e('Copies', 'secure-media-link'); ?>',
                                        data: response.data.copies,
                                        borderColor: 'rgb(255, 205, 86)',
                                        tension: 0.1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Traitement AJAX pour l'upload frontend
     */
    public static function ajax_frontend_upload() {
        // Vérifier les permissions
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Vous devez être connecté pour uploader des fichiers.', 'secure-media-link'));
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sml_upload')) {
            wp_send_json_error(__('Nonce invalide.', 'secure-media-link'));
        }
        
        $max_files = intval($_POST['max_files']);
        $max_size = intval($_POST['max_size']);
        
        // Vérifier les fichiers
        if (empty($_FILES['sml_files'])) {
            wp_send_json_error(__('Aucun fichier sélectionné.', 'secure-media-link'));
        }
        
        $files = $_FILES['sml_files'];
        $file_count = is_array($files['name']) ? count($files['name']) : 1;
        
        if ($file_count > $max_files) {
            wp_send_json_error(sprintf(__('Maximum %d fichiers autorisés.', 'secure-media-link'), $max_files));
        }
        
        $uploaded_files = array();
        $errors = array();
        
        // Traiter chaque fichier
        for ($i = 0; $i < $file_count; $i++) {
            $file = array(
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size']
            );
            
            // Vérifier la taille
            if ($file['size'] > $max_size) {
                $errors[] = $file['name'] . ': ' . __('Fichier trop volumineux', 'secure-media-link');
                continue;
            }
            
            // Vérifier le type MIME
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = $file['name'] . ': ' . __('Type de fichier non autorisé', 'secure-media-link');
                continue;
            }
            
            // Upload du fichier
            $upload_result = wp_handle_upload($file, array('test_form' => false));
            
            if (isset($upload_result['error'])) {
                $errors[] = $file['name'] . ': ' . $upload_result['error'];
                continue;
            }
            
            // Créer l'attachment
            $attachment_data = array(
                'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
                'post_content' => sanitize_textarea_field($_POST['description']),
                'post_excerpt' => sanitize_text_field($_POST['caption']),
                'post_status' => 'inherit',
                'post_mime_type' => $file['type']
            );
            
            $attachment_id = wp_insert_attachment($attachment_data, $upload_result['file']);
            
            if (!is_wp_error($attachment_id)) {
                // Générer les métadonnées
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($attachment_id, $upload_result['file']);
                wp_update_attachment_metadata($attachment_id, $metadata);
                
                // Sauvegarder les métadonnées personnalisées
                update_post_meta($attachment_id, '_sml_copyright', sanitize_text_field($_POST['copyright']));
                
                if (!empty($_POST['expiry_date'])) {
                    update_post_meta($attachment_id, '_sml_expiry_date', sanitize_text_field($_POST['expiry_date']));
                }
                
                // Enregistrer dans la table des uploads frontend
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'sml_frontend_uploads',
                    array(
                        'media_id' => $attachment_id,
                        'author_id' => get_current_user_id(),
                        'caption' => sanitize_text_field($_POST['caption']),
                        'description' => sanitize_textarea_field($_POST['description']),
                        'copyright' => sanitize_text_field($_POST['copyright']),
                        'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null,
                        'status' => 'pending'
                    )
                );
                
                $uploaded_files[] = array(
                    'id' => $attachment_id,
                    'url' => $upload_result['url'],
                    'title' => $attachment_data['post_title']
                );
                
                // Notification aux administrateurs
                SML_Notifications::add_notification(
                    'new_upload',
                    __('Nouveau média uploadé', 'secure-media-link'),
                    sprintf(__('Un nouveau média "%s" a été uploadé par %s', 'secure-media-link'), 
                        $attachment_data['post_title'],
                        wp_get_current_user()->display_name
                    ),
                    array('media_id' => $attachment_id)
                );
            } else {
                $errors[] = $file['name'] . ': ' . __('Erreur lors de la création de l\'attachment', 'secure-media-link');
            }
        }
        
        if (!empty($uploaded_files)) {
            $message = sprintf(
                _n('%d fichier uploadé avec succès.', '%d fichiers uploadés avec succès.', count($uploaded_files), 'secure-media-link'),
                count($uploaded_files)
            );
            
            if (!empty($errors)) {
                $message .= ' ' . __('Erreurs:', 'secure-media-link') . ' ' . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'files' => $uploaded_files,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(__('Aucun fichier n\'a pu être uploadé.', 'secure-media-link') . ' ' . implode(', ', $errors));
        }
    }
}

// Enregistrer l'action AJAX pour l'upload
add_action('wp_ajax_sml_frontend_upload', array('SML_Shortcodes', 'ajax_frontend_upload'));
