<?php
/**
 * Template pour la modal des détails d'un média
 * templates/admin-media-modal.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables disponibles : $media, $links, $stats, $usage_stats
?>

<div class="sml-media-modal-header">
    <div class="sml-media-thumbnail">
        <?php if (wp_attachment_is_image($media->ID)): ?>
            <?php echo wp_get_attachment_image($media->ID, 'medium', false, array('class' => 'sml-modal-image')); ?>
        <?php else: ?>
            <div class="sml-file-icon">
                <span class="dashicons dashicons-media-<?php echo strpos($media->post_mime_type, 'video') !== false ? 'video' : 'document'; ?>"></span>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="sml-media-info">
        <h2><?php echo esc_html($media->post_title); ?></h2>
        
        <div class="sml-media-metadata">
            <div class="sml-meta-item">
                <strong><?php _e('Type:', 'secure-media-link'); ?></strong>
                <span><?php echo esc_html($media->post_mime_type); ?></span>
            </div>
            
            <div class="sml-meta-item">
                <strong><?php _e('Taille:', 'secure-media-link'); ?></strong>
                <span><?php echo size_format(filesize(get_attached_file($media->ID))); ?></span>
            </div>
            
            <?php
            $image_meta = wp_get_attachment_metadata($media->ID);
            if ($image_meta && isset($image_meta['width'])):
            ?>
                <div class="sml-meta-item">
                    <strong><?php _e('Dimensions:', 'secure-media-link'); ?></strong>
                    <span><?php echo $image_meta['width']; ?> × <?php echo $image_meta['height']; ?> px</span>
                </div>
            <?php endif; ?>
            
            <div class="sml-meta-item">
                <strong><?php _e('Ajouté le:', 'secure-media-link'); ?></strong>
                <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($media->post_date)); ?></span>
            </div>
            
            <div class="sml-meta-item">
                <strong><?php _e('Auteur:', 'secure-media-link'); ?></strong>
                <span><?php echo get_userdata($media->post_author)->display_name; ?></span>
            </div>
            
            <?php
            $copyright = get_post_meta($media->ID, '_sml_copyright', true);
            if ($copyright):
            ?>
                <div class="sml-meta-item">
                    <strong><?php _e('Copyright:', 'secure-media-link'); ?></strong>
                    <span><?php echo esc_html($copyright); ?></span>
                </div>
            <?php endif; ?>
            
            <?php
            $expiry_date = get_post_meta($media->ID, '_sml_expiry_date', true);
            if ($expiry_date):
            ?>
                <div class="sml-meta-item">
                    <strong><?php _e('Expire le:', 'secure-media-link'); ?></strong>
                    <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($expiry_date)); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($media->post_content)): ?>
            <div class="sml-media-description">
                <strong><?php _e('Description:', 'secure-media-link'); ?></strong>
                <p><?php echo nl2br(esc_html($media->post_content)); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="sml-media-modal-tabs">
    <nav class="nav-tab-wrapper">
        <a href="#sml-links-tab" class="nav-tab nav-tab-active" data-tab="links">
            <?php _e('Liens sécurisés', 'secure-media-link'); ?>
            <span class="sml-tab-count"><?php echo count($links); ?></span>
        </a>
        <a href="#sml-stats-tab" class="nav-tab" data-tab="stats">
            <?php _e('Statistiques', 'secure-media-link'); ?>
        </a>
        <a href="#sml-usage-tab" class="nav-tab" data-tab="usage">
            <?php _e('Utilisation', 'secure-media-link'); ?>
        </a>
        <a href="#sml-actions-tab" class="nav-tab" data-tab="actions">
            <?php _e('Actions', 'secure-media-link'); ?>
        </a>
    </nav>
</div>

<div class="sml-media-modal-content">
    <!-- Onglet Liens sécurisés -->
    <div id="sml-links-tab" class="sml-tab-content active">
        <div class="sml-links-header">
            <h3><?php _e('Liens sécurisés', 'secure-media-link'); ?></h3>
            <div class="sml-links-actions">
                <button class="button button-primary sml-generate-new-link" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Générer un nouveau lien', 'secure-media-link'); ?>
                </button>
                <button class="button sml-refresh-links" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualiser', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
        
        <div class="sml-links-list">
            <?php if (!empty($links)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-format"><?php _e('Format', 'secure-media-link'); ?></th>
                            <th class="column-link"><?php _e('Lien', 'secure-media-link'); ?></th>
                            <th class="column-expires"><?php _e('Expire le', 'secure-media-link'); ?></th>
                            <th class="column-status"><?php _e('Statut', 'secure-media-link'); ?></th>
                            <th class="column-usage"><?php _e('Utilisation', 'secure-media-link'); ?></th>
                            <th class="column-actions"><?php _e('Actions', 'secure-media-link'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $link): ?>
                            <?php
                            $is_expired = strtotime($link->expires_at) < time();
                            $is_active = $link->is_active && !$is_expired;
                            $secure_url = $is_active ? SML_Crypto::generate_secure_link($media->ID, $link->format_id, $link->expires_at) : '';
                            ?>
                            <tr class="sml-link-row <?php echo $is_active ? 'active' : 'inactive'; ?>" data-link-id="<?php echo $link->id; ?>">
                                <td class="column-format">
                                    <strong><?php echo esc_html($link->format_name); ?></strong>
                                    <div class="sml-format-type"><?php echo esc_html($link->format_type); ?></div>
                                </td>
                                
                                <td class="column-link">
                                    <?php if ($secure_url): ?>
                                        <div class="sml-link-container">
                                            <input type="text" class="sml-link-input" value="<?php echo esc_attr($secure_url); ?>" readonly>
                                            <button class="button button-small sml-copy-link" data-url="<?php echo esc_attr($secure_url); ?>" data-link-id="<?php echo $link->id; ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                                <?php _e('Copier', 'secure-media-link'); ?>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="sml-link-inactive"><?php _e('Lien inactif', 'secure-media-link'); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-expires">
                                    <?php 
                                    $expires_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($link->expires_at));
                                    echo $expires_date;
                                    if ($is_expired): ?>
                                        <br><span class="sml-expired"><?php _e('(Expiré)', 'secure-media-link'); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-status">
                                    <span class="sml-status sml-<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $is_active ? __('Actif', 'secure-media-link') : __('Inactif', 'secure-media-link'); ?>
                                    </span>
                                </td>
                                
                                <td class="column-usage">
                                    <div class="sml-usage-stats">
                                        <div class="sml-usage-item">
                                            <span class="dashicons dashicons-download"></span>
                                            <?php echo number_format($link->download_count); ?>
                                        </div>
                                        <div class="sml-usage-item">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            <?php echo number_format($link->copy_count); ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="column-actions">
                                    <div class="sml-link-actions">
                                        <?php if ($link->is_active): ?>
                                            <button class="button button-small sml-deactivate-link" 
                                                    data-link-id="<?php echo $link->id; ?>"
                                                    title="<?php _e('Désactiver', 'secure-media-link'); ?>">
                                                <span class="dashicons dashicons-pause"></span>
                                            </button>
                                        <?php else: ?>
                                            <button class="button button-small sml-activate-link" 
                                                    data-link-id="<?php echo $link->id; ?>"
                                                    title="<?php _e('Activer', 'secure-media-link'); ?>">
                                                <span class="dashicons dashicons-controls-play"></span>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="button button-small sml-edit-link" 
                                                data-link-id="<?php echo $link->id; ?>"
                                                title="<?php _e('Modifier', 'secure-media-link'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        
                                        <button class="button button-small sml-delete-link" 
                                                data-link-id="<?php echo $link->id; ?>"
                                                title="<?php _e('Supprimer', 'secure-media-link'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="sml-no-links">
                    <div class="sml-empty-state">
                        <span class="dashicons dashicons-admin-links"></span>
                        <h4><?php _e('Aucun lien sécurisé', 'secure-media-link'); ?></h4>
                        <p><?php _e('Ce média n\'a pas encore de liens sécurisés générés.', 'secure-media-link'); ?></p>
                        <button class="button button-primary sml-generate-first-link" data-media-id="<?php echo $media->ID; ?>">
                            <?php _e('Générer le premier lien', 'secure-media-link'); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Onglet Statistiques -->
    <div id="sml-stats-tab" class="sml-tab-content">
        <div class="sml-stats-overview">
            <h3><?php _e('Aperçu des statistiques', 'secure-media-link'); ?></h3>
            
            <div class="sml-stats-cards">
                <div class="sml-stat-card">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-download"></span>
                    </div>
                    <div class="sml-stat-content">
                        <div class="sml-stat-number"><?php echo number_format($stats['total_downloads']); ?></div>
                        <div class="sml-stat-label"><?php _e('Téléchargements', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-card">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-admin-page"></span>
                    </div>
                    <div class="sml-stat-content">
                        <div class="sml-stat-number"><?php echo number_format($stats['total_copies']); ?></div>
                        <div class="sml-stat-label"><?php _e('Copies', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-card">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-visibility"></span>
                    </div>
                    <div class="sml-stat-content">
                        <div class="sml-stat-number"><?php echo number_format($stats['total_views']); ?></div>
                        <div class="sml-stat-label"><?php _e('Vues', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-card sml-stat-danger">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="sml-stat-content">
                        <div class="sml-stat-number"><?php echo number_format($stats['total_blocked']); ?></div>
                        <div class="sml-stat-label"><?php _e('Bloqués', 'secure-media-link'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="sml-stats-charts">
            <div class="sml-chart-container">
                <h4><?php _e('Activité des 30 derniers jours', 'secure-media-link'); ?></h4>
                <canvas id="sml-media-activity-chart" data-media-id="<?php echo $media->ID; ?>"></canvas>
            </div>
        </div>
        
        <div class="sml-stats-details">
            <h4><?php _e('Détails par période', 'secure-media-link'); ?></h4>
            
            <div class="sml-period-selector">
                <button class="button period-btn active" data-period="week" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('7 jours', 'secure-media-link'); ?>
                </button>
                <button class="button period-btn" data-period="month" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('30 jours', 'secure-media-link'); ?>
                </button>
                <button class="button period-btn" data-period="year" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('1 an', 'secure-media-link'); ?>
                </button>
            </div>
            
            <div id="sml-period-stats">
                <!-- Chargé via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Onglet Utilisation -->
    <div id="sml-usage-tab" class="sml-tab-content">
        <div class="sml-usage-header">
            <h3><?php _e('Historique d\'utilisation', 'secure-media-link'); ?></h3>
            
            <div class="sml-usage-filters">
                <select id="sml-usage-filter-action">
                    <option value=""><?php _e('Toutes les actions', 'secure-media-link'); ?></option>
                    <option value="download"><?php _e('Téléchargements', 'secure-media-link'); ?></option>
                    <option value="copy"><?php _e('Copies', 'secure-media-link'); ?></option>
                    <option value="view"><?php _e('Vues', 'secure-media-link'); ?></option>
                </select>
                
                <select id="sml-usage-filter-status">
                    <option value=""><?php _e('Tous les statuts', 'secure-media-link'); ?></option>
                    <option value="1"><?php _e('Autorisés', 'secure-media-link'); ?></option>
                    <option value="0"><?php _e('Bloqués', 'secure-media-link'); ?></option>
                </select>
                
                <input type="date" id="sml-usage-filter-date" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                
                <button class="button sml-apply-usage-filters" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('Filtrer', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
        
        <div id="sml-usage-table-container">
            <!-- Table chargée via AJAX -->
        </div>
        
        <div id="sml-usage-pagination">
            <!-- Pagination chargée via AJAX -->
        </div>
    </div>
    
    <!-- Onglet Actions -->
    <div id="sml-actions-tab" class="sml-tab-content">
        <div class="sml-actions-section">
            <h3><?php _e('Actions rapides', 'secure-media-link'); ?></h3>
            
            <div class="sml-action-buttons">
                <button class="button button-primary sml-regenerate-all-links" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Régénérer tous les liens', 'secure-media-link'); ?>
                </button>
                
                <button class="button sml-export-stats" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Exporter les statistiques', 'secure-media-link'); ?>
                </button>
                
                <button class="button sml-scan-external-usage" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Scanner l\'utilisation externe', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
        
        <div class="sml-actions-section">
            <h3><?php _e('Gestion des formats', 'secure-media-link'); ?></h3>
            
            <div class="sml-formats-management">
                <p><?php _e('Sélectionnez les formats pour lesquels vous souhaitez générer des liens:', 'secure-media-link'); ?></p>
                
                <div class="sml-formats-grid">
                    <?php
                    $available_formats = SML_Media_Formats::get_all_formats();
                    $existing_format_ids = array_column($links, 'format_id');
                    
                    foreach ($available_formats as $format):
                        $has_link = in_array($format->id, $existing_format_ids);
                    ?>
                        <div class="sml-format-item <?php echo $has_link ? 'has-link' : ''; ?>">
                            <label class="sml-format-checkbox">
                                <input type="checkbox" 
                                       name="generate_formats[]" 
                                       value="<?php echo $format->id; ?>"
                                       <?php echo $has_link ? 'checked disabled' : ''; ?>>
                                <div class="sml-format-info">
                                    <strong><?php echo esc_html($format->name); ?></strong>
                                    <div class="sml-format-details">
                                        <?php echo esc_html($format->type); ?>
                                        <?php if ($format->width || $format->height): ?>
                                            - <?php echo $format->width ?: '?'; ?>×<?php echo $format->height ?: '?'; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($has_link): ?>
                                        <span class="sml-format-status"><?php _e('Lien existant', 'secure-media-link'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="sml-generate-options">
                    <label>
                        <strong><?php _e('Date d\'expiration:', 'secure-media-link'); ?></strong>
                        <input type="datetime-local" 
                               id="sml-batch-expiry" 
                               min="<?php echo date('Y-m-d\TH:i'); ?>"
                               value="<?php echo date('Y-m-d\TH:i', strtotime('+3 years')); ?>">
                    </label>
                </div>
                
                <button class="button button-primary sml-generate-selected-formats" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('Générer les liens sélectionnés', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
        
        <div class="sml-actions-section">
            <h3><?php _e('Zone de danger', 'secure-media-link'); ?></h3>
            
            <div class="sml-danger-zone">
                <p class="description">
                    <?php _e('Ces actions sont irréversibles. Utilisez-les avec précaution.', 'secure-media-link'); ?>
                </p>
                
                <button class="button button-secondary sml-deactivate-all-links" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-pause"></span>
                    <?php _e('Désactiver tous les liens', 'secure-media-link'); ?>
                </button>
                
                <button class="button button-link-delete sml-delete-all-links" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Supprimer tous les liens', 'secure-media-link'); ?>
                </button>
                
                <button class="button button-link-delete sml-clear-usage-history" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e('Effacer l\'historique d\'utilisation', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestion des onglets
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Mettre à jour les onglets
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Mettre à jour le contenu
        $('.sml-tab-content').removeClass('active');
        $('#sml-' + targetTab + '-tab').addClass('active');
        
        // Charger le contenu dynamique si nécessaire
        if (targetTab === 'usage' && !$('#sml-usage-table-container').hasClass('loaded')) {
            loadUsageData(<?php echo $media->ID; ?>);
        }
    });
    
    // Charger les données d'utilisation
    function loadUsageData(mediaId, filters = {}) {
        $('#sml-usage-table-container').html('<div class="sml-loading">Chargement...</div>');
        
        $.post(ajaxurl, {
            action: 'sml_get_media_usage',
            nonce: sml_ajax.nonce,
            media_id: mediaId,
            filters: filters
        }, function(response) {
            if (response.success) {
                $('#sml-usage-table-container').html(response.data.table).addClass('loaded');
                $('#sml-usage-pagination').html(response.data.pagination);
            } else {
                $('#sml-usage-table-container').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
            }
        });
    }
    
    // Application des filtres d'utilisation
    $('.sml-apply-usage-filters').on('click', function() {
        var mediaId = $(this).data('media-id');
        var filters = {
            action_type: $('#sml-usage-filter-action').val(),
            is_authorized: $('#sml-usage-filter-status').val(),
            date_from: $('#sml-usage-filter-date').val()
        };
        
        loadUsageData(mediaId, filters);
    });
    
    // Statistiques par période
    $('.period-btn').on('click', function() {
        $('.period-btn').removeClass('active');
        $(this).addClass('active');
        
        var period = $(this).data('period');
        var mediaId = $(this).data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_get_media_period_stats',
            nonce: sml_ajax.nonce,
            media_id: mediaId,
            period: period
        }, function(response) {
            if (response.success) {
                $('#sml-period-stats').html(response.data);
            }
        });
    });
    
    // Initialiser le graphique d'activité
    if (typeof Chart !== 'undefined' && $('#sml-media-activity-chart').length) {
        var mediaId = $('#sml-media-activity-chart').data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_get_media_chart_data',
            nonce: sml_ajax.nonce,
            media_id: mediaId,
            period: 'month'
        }, function(response) {
            if (response.success) {
                var ctx = $('#sml-media-activity-chart')[0].getContext('2d');
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Téléchargements',
                            data: response.data.downloads,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.1,
                            fill: true
                        }, {
                            label: 'Copies',
                            data: response.data.copies,
                            borderColor: 'rgb(255, 205, 86)',
                            backgroundColor: 'rgba(255, 205, 86, 0.1)',
                            tension: 0.1,
                            fill: true
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
    }
    
    // Actions sur les liens
    $('.sml-copy-link').on('click', function() {
        var url = $(this).data('url');
        var linkId = $(this).data('link-id');
        
        navigator.clipboard.writeText(url).then(function() {
            // Feedback visuel
            var $btn = $('[data-link-id="' + linkId + '"].sml-copy-link');
            var originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> Copié !');
            
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
            
            // Tracker la copie
            $.post(ajaxurl, {
                action: 'sml_track_usage',
                nonce: sml_ajax.nonce,
                link_id: linkId,
                action_type: 'copy'
            });
        });
    });
    
    // Activer/Désactiver un lien
    $('.sml-activate-link, .sml-deactivate-link').on('click', function() {
        var linkId = $(this).data('link-id');
        var isActivate = $(this).hasClass('sml-activate-link');
        var action = isActivate ? 'activate' : 'deactivate';
        
        if (!confirm('<?php _e('Confirmer cette action ?', 'secure-media-link'); ?>')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'sml_toggle_link_status',
            nonce: sml_ajax.nonce,
            link_id: linkId,
            toggle_action: action
        }, function(response) {
            if (response.success) {
                // Recharger la modal avec les données mises à jour
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de la mise à jour', 'secure-media-link'); ?>');
            }
            $btn.prop('disabled', false);
        });
    });
    
    // Modifier un lien
    $('.sml-edit-link').on('click', function() {
        var linkId = $(this).data('link-id');
        openEditLinkModal(linkId);
    });
    
    // Supprimer un lien
    $('.sml-delete-link').on('click', function() {
        if (!confirm('<?php _e('Supprimer ce lien définitivement ?', 'secure-media-link'); ?>')) {
            return;
        }
        
        var linkId = $(this).data('link-id');
        var $row = $(this).closest('tr');
        
        $.post(ajaxurl, {
            action: 'sml_delete_link',
            nonce: sml_ajax.nonce,
            link_id: linkId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(function() {
                    $row.remove();
                    // Mettre à jour le compteur d'onglets
                    var remainingLinks = $('.sml-link-row').length - 1;
                    $('.sml-tab-count').text(remainingLinks);
                    
                    if (remainingLinks === 0) {
                        $('.sml-links-list').html($('.sml-no-links').html());
                    }
                });
            } else {
                alert(response.data || '<?php _e('Erreur lors de la suppression', 'secure-media-link'); ?>');
            }
        });
    });
    
    // Générer un nouveau lien
    $('.sml-generate-new-link, .sml-generate-first-link').on('click', function() {
        var mediaId = $(this).data('media-id');
        openGenerateLinksModal(mediaId);
    });
    
    // Actualiser les liens
    $('.sml-refresh-links').on('click', function() {
        var mediaId = $(this).data('media-id');
        var $btn = $(this);
        
        $btn.prop('disabled', true).find('.dashicons').addClass('sml-spin');
        
        $.post(ajaxurl, {
            action: 'sml_refresh_media_links',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de l\'actualisation', 'secure-media-link'); ?>');
            }
            $btn.prop('disabled', false).find('.dashicons').removeClass('sml-spin');
        });
    });
    
    // Actions rapides sur tous les liens
    $('.sml-regenerate-all-links').on('click', function() {
        if (!confirm('<?php _e('Régénérer tous les liens ? Les anciens liens seront invalidés.', 'secure-media-link'); ?>')) {
            return;
        }
        
        var mediaId = $(this).data('media-id');
        var $btn = $(this);
        
        $btn.prop('disabled', true).text('<?php _e('Régénération...', 'secure-media-link'); ?>');
        
        $.post(ajaxurl, {
            action: 'sml_regenerate_all_links',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Liens régénérés avec succès', 'secure-media-link'); ?>');
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de la régénération', 'secure-media-link'); ?>');
            }
            $btn.prop('disabled', false).text('<?php _e('Régénérer tous les liens', 'secure-media-link'); ?>');
        });
    });
    
    // Exporter les statistiques
    $('.sml-export-stats').on('click', function() {
        var mediaId = $(this).data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_export_media_stats',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                // Créer un lien de téléchargement temporaire
                var blob = new Blob([response.data.content], {type: 'text/csv'});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            } else {
                alert(response.data || '<?php _e('Erreur lors de l\'export', 'secure-media-link'); ?>');
            }
        });
    });
    
    // Scanner l'utilisation externe
    $('.sml-scan-external-usage').on('click', function() {
        var mediaId = $(this).data('media-id');
        var $btn = $(this);
        
        $btn.prop('disabled', true).text('<?php _e('Scan en cours...', 'secure-media-link'); ?>');
        
        $.post(ajaxurl, {
            action: 'sml_scan_media_external_usage',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Scan terminé', 'secure-media-link'); ?>');
            } else {
                alert(response.data || '<?php _e('Erreur lors du scan', 'secure-media-link'); ?>');
            }
            $btn.prop('disabled', false).text('<?php _e('Scanner l\'utilisation externe', 'secure-media-link'); ?>');
        });
    });
    
    // Génération de liens pour les formats sélectionnés
    $('.sml-generate-selected-formats').on('click', function() {
        var mediaId = $(this).data('media-id');
        var selectedFormats = [];
        var expiryDate = $('#sml-batch-expiry').val();
        
        $('input[name="generate_formats[]"]:checked:not(:disabled)').each(function() {
            selectedFormats.push($(this).val());
        });
        
        if (selectedFormats.length === 0) {
            alert('<?php _e('Veuillez sélectionner au moins un format', 'secure-media-link'); ?>');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('Génération...', 'secure-media-link'); ?>');
        
        $.post(ajaxurl, {
            action: 'sml_generate_selected_formats',
            nonce: sml_ajax.nonce,
            media_id: mediaId,
            formats: selectedFormats,
            expiry_date: expiryDate
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Liens générés avec succès', 'secure-media-link'); ?>');
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de la génération', 'secure-media-link'); ?>');
            }
            $btn.prop('disabled', false).text('<?php _e('Générer les liens sélectionnés', 'secure-media-link'); ?>');
        });
    });
    
    // Actions de la zone de danger
    $('.sml-deactivate-all-links').on('click', function() {
        if (!confirm('<?php _e('Désactiver tous les liens de ce média ?', 'secure-media-link'); ?>')) {
            return;
        }
        
        var mediaId = $(this).data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_deactivate_all_media_links',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Tous les liens ont été désactivés', 'secure-media-link'); ?>');
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de la désactivation', 'secure-media-link'); ?>');
            }
        });
    });
    
    $('.sml-delete-all-links').on('click', function() {
        if (!confirm('<?php _e('ATTENTION: Supprimer définitivement tous les liens de ce média ? Cette action est irréversible.', 'secure-media-link'); ?>')) {
            return;
        }
        
        var mediaId = $(this).data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_delete_all_media_links',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Tous les liens ont été supprimés', 'secure-media-link'); ?>');
                location.reload();
            } else {
                alert(response.data || '<?php _e('Erreur lors de la suppression', 'secure-media-link'); ?>');
            }
        });
    });
    
    $('.sml-clear-usage-history').on('click', function() {
        if (!confirm('<?php _e('Supprimer tout l\'historique d\'utilisation de ce média ?', 'secure-media-link'); ?>')) {
            return;
        }
        
        var mediaId = $(this).data('media-id');
        
        $.post(ajaxurl, {
            action: 'sml_clear_media_usage_history',
            nonce: sml_ajax.nonce,
            media_id: mediaId
        }, function(response) {
            if (response.success) {
                alert(response.data.message || '<?php _e('Historique effacé avec succès', 'secure-media-link'); ?>');
                // Recharger l'onglet des statistiques
                if ($('#sml-stats-tab').hasClass('active')) {
                    location.reload();
                }
            } else {
                alert(response.data || '<?php _e('Erreur lors de l\'effacement', 'secure-media-link'); ?>');
            }
        });
    });
    
    // Fonctions utilitaires
    function openGenerateLinksModal(mediaId) {
        // Ici on pourrait ouvrir une modal dédiée à la génération de liens
        // Pour simplifier, on utilise prompt() mais idéalement il faudrait une vraie modal
        var formatId = prompt('<?php _e('ID du format à générer:', 'secure-media-link'); ?>');
        
        if (formatId) {
            $.post(ajaxurl, {
                action: 'sml_generate_single_link',
                nonce: sml_ajax.nonce,
                media_id: mediaId,
                format_id: formatId
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Lien généré avec succès', 'secure-media-link'); ?>');
                    location.reload();
                } else {
                    alert(response.data || '<?php _e('Erreur lors de la génération', 'secure-media-link'); ?>');
                }
            });
        }
    }
    
    function openEditLinkModal(linkId) {
        // Récupérer les détails du lien et ouvrir une modal d'édition
        $.post(ajaxurl, {
            action: 'sml_get_link_details',
            nonce: sml_ajax.nonce,
            link_id: linkId
        }, function(response) {
            if (response.success) {
                // Créer une modal d'édition simple
                var newExpiry = prompt('<?php _e('Nouvelle date d\'expiration (YYYY-MM-DD HH:MM:SS):', 'secure-media-link'); ?>', response.data.expires_at);
                
                if (newExpiry && newExpiry !== response.data.expires_at) {
                    $.post(ajaxurl, {
                        action: 'sml_update_link_expiry',
                        nonce: sml_ajax.nonce,
                        link_id: linkId,
                        expires_at: newExpiry
                    }, function(updateResponse) {
                        if (updateResponse.success) {
                            alert('<?php _e('Lien mis à jour avec succès', 'secure-media-link'); ?>');
                            location.reload();
                        } else {
                            alert(updateResponse.data || '<?php _e('Erreur lors de la mise à jour', 'secure-media-link'); ?>');
                        }
                    });
                }
            } else {
                alert(response.data || '<?php _e('Impossible de charger les détails du lien', 'secure-media-link'); ?>');
            }
        });
    }
    
    // Charger les données d'utilisation au premier chargement de l'onglet
    if ($('#sml-usage-tab').hasClass('active')) {
        loadUsageData(<?php echo $media->ID; ?>);
    }
    
    // Charger les statistiques de période par défaut
    $('.period-btn.active').trigger('click');
});
</script>

<style>
.sml-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sml-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.sml-modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    color: #666;
    z-index: 1;
}

.sml-modal-close:hover {
    color: #000;
}

.sml-media-modal-header {
    display: flex;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    gap: 20px;
}

.sml-media-thumbnail {
    flex-shrink: 0;
}

.sml-modal-image {
    max-width: 200px;
    height: auto;
    border-radius: 4px;
}

.sml-file-icon {
    width: 200px;
    height: 150px;
    background: #f1f1f1;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sml-file-icon .dashicons {
    font-size: 48px;
    color: #666;
}

.sml-media-info {
    flex: 1;
}

.sml-media-metadata {
    margin-top: 15px;
}

.sml-meta-item {
    margin-bottom: 8px;
    display: flex;
    gap: 10px;
}

.sml-meta-item strong {
    min-width: 120px;
    color: #555;
}

.sml-media-description {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.sml-media-modal-tabs {
    border-bottom: 1px solid #ccc;
}

.sml-media-modal-content {
    padding: 20px;
}

.sml-tab-content {
    display: none;
}

.sml-tab-content.active {
    display: block;
}

.sml-links-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.sml-links-actions {
    display: flex;
    gap: 10px;
}

.sml-link-container {
    display: flex;
    gap: 5px;
    align-items: center;
}

.sml-link-input {
    width: 300px;
    font-family: monospace;
    font-size: 12px;
}

.sml-status {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.sml-status.sml-active {
    background: #d4edda;
    color: #155724;
}

.sml-status.sml-inactive {
    background: #f8d7da;
    color: #721c24;
}

.sml-expired {
    color: #dc3545;
    font-style: italic;
}

.sml-usage-stats {
    display: flex;
    gap: 15px;
}

.sml-usage-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.sml-link-actions {
    display: flex;
    gap: 3px;
}

.sml-no-links {
    text-align: center;
    padding: 40px 20px;
}

.sml-empty-state {
    color: #666;
}

.sml-empty-state .dashicons {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 10px;
}

.sml-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.sml-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.sml-stat-card.sml-stat-danger {
    border-color: #dc3545;
}

.sml-stat-icon .dashicons {
    font-size: 32px;
    color: #0073aa;
    margin-bottom: 10px;
}

.sml-stat-card.sml-stat-danger .sml-stat-icon .dashicons {
    color: #dc3545;
}

.sml-stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.sml-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 5px;
}

.sml-chart-container {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.sml-chart-container h4 {
    margin-top: 0;
    margin-bottom: 15px;
}

.sml-period-selector {
    margin-bottom: 20px;
}

.period-btn {
    margin-right: 10px;
}

.period-btn.active {
    background: #0073aa;
    color: white;
}

.sml-usage-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.sml-usage-filters select,
.sml-usage-filters input {
    min-width: 150px;
}

.sml-loading {
    text-align: center;
    padding: 20px;
    color: #666;
}

.sml-actions-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.sml-actions-section:last-child {
    border-bottom: none;
}

.sml-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.sml-formats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.sml-format-item {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    transition: all 0.2s;
}

.sml-format-item:hover {
    border-color: #0073aa;
}

.sml-format-item.has-link {
    background: #f8f9fa;
    border-color: #28a745;
}

.sml-format-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
}

.sml-format-checkbox input[type="checkbox"] {
    margin: 0;
}

.sml-format-info {
    flex: 1;
}

.sml-format-details {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.sml-format-status {
    font-size: 11px;
    color: #28a745;
    font-weight: bold;
    margin-top: 5px;
}

.sml-generate-options {
    margin-bottom: 20px;
}

.sml-generate-options label {
    display: block;
    margin-bottom: 10px;
}

.sml-generate-options input {
    margin-left: 10px;
}

.sml-danger-zone {
    background: #fff5f5;
    border: 1px solid #feb2b2;
    border-radius: 6px;
    padding: 20px;
}

.sml-danger-zone .description {
    color: #e53e3e;
    margin-bottom: 15px;
}

.sml-danger-zone .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.button-link-delete {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.button-link-delete:hover {
    background: #dc3545 !important;
    color: white !important;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.sml-spin {
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .sml-media-modal-header {
        flex-direction: column;
    }
    
    .sml-modal-image,
    .sml-file-icon {
        max-width: 100%;
        width: 100%;
    }
    
    .sml-links-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .sml-stats-cards {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .sml-usage-filters {
        flex-direction: column;
    }
    
    .sml-action-buttons {
        flex-direction: column;
    }
    
    .sml-action-buttons .button {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php
// Fin du fichier admin-media-modal.php
?>