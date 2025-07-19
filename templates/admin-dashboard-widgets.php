<?php
/**
 * Templates pour les widgets du tableau de bord admin
 * templates/admin-dashboard-widgets.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget des statistiques rapides
 */
function sml_render_quick_stats_widget($stats) {
    ?>
    <div class="sml-dashboard-widget sml-quick-stats-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Statistiques rapides', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <button class="sml-refresh-widget" data-widget="quick-stats">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <div class="sml-stats-grid">
                <div class="sml-stat-box sml-stat-primary">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-format-gallery"></span>
                    </div>
                    <div class="sml-stat-data">
                        <div class="sml-stat-number"><?php echo number_format($stats['media']['total_media']); ?></div>
                        <div class="sml-stat-label"><?php _e('Médias totaux', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-box sml-stat-success">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-admin-links"></span>
                    </div>
                    <div class="sml-stat-data">
                        <div class="sml-stat-number"><?php echo number_format($stats['media']['active_links']); ?></div>
                        <div class="sml-stat-label"><?php _e('Liens actifs', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-box sml-stat-info">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-download"></span>
                    </div>
                    <div class="sml-stat-data">
                        <div class="sml-stat-number"><?php echo number_format($stats['usage']['downloads']); ?></div>
                        <div class="sml-stat-label"><?php _e('Téléchargements', 'secure-media-link'); ?></div>
                    </div>
                </div>
                
                <div class="sml-stat-box sml-stat-warning">
                    <div class="sml-stat-icon">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="sml-stat-data">
                        <div class="sml-stat-number"><?php echo number_format($stats['usage']['blocked']); ?></div>
                        <div class="sml-stat-label"><?php _e('Requêtes bloquées', 'secure-media-link'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="sml-stats-summary">
                <div class="sml-stat-row">
                    <span class="sml-stat-description"><?php _e('Taux de réussite:', 'secure-media-link'); ?></span>
                    <span class="sml-stat-value">
                        <?php 
                        $total_requests = $stats['usage']['downloads'] + $stats['usage']['blocked'];
                        if ($total_requests > 0) {
                            echo round(($stats['usage']['downloads'] / $total_requests) * 100, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Widget des violations récentes
 */
function sml_render_recent_violations_widget($violations) {
    ?>
    <div class="sml-dashboard-widget sml-recent-violations-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Violations récentes', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=sml-tracking'); ?>" class="sml-view-all">
                    <?php _e('Voir tout', 'secure-media-link'); ?>
                </a>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <?php if (!empty($violations['data'])): ?>
                <div class="sml-violations-list">
                    <?php foreach (array_slice($violations['data'], 0, 5) as $violation): ?>
                        <div class="sml-violation-item">
                            <div class="sml-violation-icon">
                                <span class="dashicons dashicons-warning sml-text-danger"></span>
                            </div>
                            <div class="sml-violation-details">
                                <div class="sml-violation-primary">
                                    <strong><?php echo esc_html($violation->ip_address); ?></strong>
                                    <?php if ($violation->domain): ?>
                                        <span class="sml-violation-domain">
                                            (<?php echo esc_html($violation->domain); ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="sml-violation-meta">
                                    <span class="sml-violation-type">
                                        <?php echo esc_html(ucfirst($violation->action_type)); ?>
                                    </span>
                                    <span class="sml-violation-time">
                                        <?php echo human_time_diff(strtotime($violation->created_at), current_time('timestamp')); ?>
                                        <?php _e('ago', 'secure-media-link'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="sml-violation-actions">
                                <button class="sml-btn-icon sml-block-ip" 
                                        data-ip="<?php echo esc_attr($violation->ip_address); ?>"
                                        title="<?php _e('Bloquer cette IP', 'secure-media-link'); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sml-empty-state">
                    <div class="sml-empty-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <p><?php _e('Aucune violation récente', 'secure-media-link'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Widget des médias populaires
 */
function sml_render_popular_media_widget($top_media) {
    ?>
    <div class="sml-dashboard-widget sml-popular-media-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Médias populaires', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <select class="sml-period-selector" data-widget="popular-media">
                    <option value="week"><?php _e('7 jours', 'secure-media-link'); ?></option>
                    <option value="month" selected><?php _e('30 jours', 'secure-media-link'); ?></option>
                    <option value="year"><?php _e('1 an', 'secure-media-link'); ?></option>
                </select>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <?php if (!empty($top_media)): ?>
                <div class="sml-media-list">
                    <?php foreach ($top_media as $index => $media): ?>
                        <div class="sml-media-item">
                            <div class="sml-media-rank">
                                <span class="sml-rank-number"><?php echo $index + 1; ?></span>
                            </div>
                            <div class="sml-media-thumbnail">
                                <?php echo wp_get_attachment_image($media->media_id, 'thumbnail'); ?>
                            </div>
                            <div class="sml-media-info">
                                <div class="sml-media-title">
                                    <?php echo esc_html($media->post_title ?: __('Sans titre', 'secure-media-link')); ?>
                                </div>
                                <div class="sml-media-stats">
                                    <span class="sml-usage-count">
                                        <?php printf(_n('%d utilisation', '%d utilisations', $media->usage_count, 'secure-media-link'), $media->usage_count); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="sml-media-actions">
                                <a href="<?php echo admin_url('admin.php?page=sml-media-library&media_id=' . $media->media_id); ?>" 
                                   class="sml-btn-icon" title="<?php _e('Voir les détails', 'secure-media-link'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sml-empty-state">
                    <div class="sml-empty-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <p><?php _e('Aucune donnée disponible', 'secure-media-link'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Widget du système et de la performance
 */
function sml_render_system_status_widget() {
    $cache_stats = SML_Cache::get_cache_stats();
    $db_stats = SML_Database::get_database_stats();
    
    ?>
    <div class="sml-dashboard-widget sml-system-status-widget">
        <div class="sml-widget-header">
            <h3><?php _e('État du système', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <button class="sml-refresh-widget" data-widget="system-status">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <div class="sml-system-metrics">
                <!-- Cache Performance -->
                <div class="sml-metric-row">
                    <div class="sml-metric-label">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Cache', 'secure-media-link'); ?>
                    </div>
                    <div class="sml-metric-value">
                        <div class="sml-progress-bar">
                            <div class="sml-progress-fill" 
                                 style="width: <?php echo $cache_stats['performance']['hit_rate']; ?>%"></div>
                        </div>
                        <span class="sml-metric-text"><?php echo $cache_stats['performance']['hit_rate']; ?>%</span>
                    </div>
                </div>
                
                <!-- Memory Usage -->
                <div class="sml-metric-row">
                    <div class="sml-metric-label">
                        <span class="dashicons dashicons-database"></span>
                        <?php _e('Mémoire cache', 'secure-media-link'); ?>
                    </div>
                    <div class="sml-metric-value">
                        <div class="sml-progress-bar">
                            <div class="sml-progress-fill" 
                                 style="width: <?php echo $cache_stats['memory_cache']['usage_percentage']; ?>%"></div>
                        </div>
                        <span class="sml-metric-text">
                            <?php echo $cache_stats['memory_cache']['size_formatted']; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Database Size -->
                <div class="sml-metric-row">
                    <div class="sml-metric-label">
                        <span class="dashicons dashicons-database-view"></span>
                        <?php _e('Entrées de tracking', 'secure-media-link'); ?>
                    </div>
                    <div class="sml-metric-value">
                        <span class="sml-metric-text">
                            <?php echo number_format($db_stats['tracking']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Active Links -->
                <div class="sml-metric-row">
                    <div class="sml-metric-label">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Liens sécurisés', 'secure-media-link'); ?>
                    </div>
                    <div class="sml-metric-value">
                        <span class="sml-metric-text">
                            <?php echo number_format($db_stats['secure_links']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="sml-system-actions">
                <button class="sml-btn sml-btn-small sml-clear-cache">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Vider le cache', 'secure-media-link'); ?>
                </button>
                
                <button class="sml-btn sml-btn-small sml-optimize-db">
                    <span class="dashicons dashicons-database-import"></span>
                    <?php _e('Optimiser BDD', 'secure-media-link'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Widget graphique des activités
 */
function sml_render_activity_chart_widget() {
    ?>
    <div class="sml-dashboard-widget sml-activity-chart-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Activité des 30 derniers jours', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <select class="sml-chart-period" data-chart="activity">
                    <option value="week"><?php _e('7 jours', 'secure-media-link'); ?></option>
                    <option value="month" selected><?php _e('30 jours', 'secure-media-link'); ?></option>
                    <option value="year"><?php _e('1 an', 'secure-media-link'); ?></option>
                </select>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <div class="sml-chart-container">
                <canvas id="sml-dashboard-activity-chart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Widget des notifications
 */
function sml_render_notifications_widget($notifications) {
    ?>
    <div class="sml-dashboard-widget sml-notifications-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Notifications', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <?php if (!empty($notifications)): ?>
                    <button class="sml-mark-all-read">
                        <?php _e('Tout marquer comme lu', 'secure-media-link'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <?php if (!empty($notifications)): ?>
                <div class="sml-notifications-list">
                    <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                        <div class="sml-notification-item <?php echo $notification->is_read ? 'read' : 'unread'; ?> priority-<?php echo $notification->priority; ?>">
                            <div class="sml-notification-icon">
                                <?php
                                switch ($notification->type) {
                                    case 'security_violation':
                                        echo '<span class="dashicons dashicons-warning sml-text-danger"></span>';
                                        break;
                                    case 'new_upload':
                                        echo '<span class="dashicons dashicons-upload sml-text-info"></span>';
                                        break;
                                    case 'link_expiring':
                                        echo '<span class="dashicons dashicons-clock sml-text-warning"></span>';
                                        break;
                                    default:
                                        echo '<span class="dashicons dashicons-info sml-text-info"></span>';
                                }
                                ?>
                            </div>
                            <div class="sml-notification-content">
                                <div class="sml-notification-title">
                                    <?php echo esc_html($notification->title); ?>
                                </div>
                                <div class="sml-notification-message">
                                    <?php echo esc_html(wp_trim_words($notification->message, 15)); ?>
                                </div>
                                <div class="sml-notification-time">
                                    <?php echo human_time_diff(strtotime($notification->created_at), current_time('timestamp')); ?>
                                    <?php _e('ago', 'secure-media-link'); ?>
                                </div>
                            </div>
                            <div class="sml-notification-actions">
                                <?php if (!$notification->is_read): ?>
                                    <button class="sml-btn-icon sml-mark-notification-read" 
                                            data-notification-id="<?php echo $notification->id; ?>"
                                            title="<?php _e('Marquer comme lu', 'secure-media-link'); ?>">
                                        <span class="dashicons dashicons-yes"></span>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="sml-btn-icon sml-delete-notification" 
                                        data-notification-id="<?php echo $notification->id; ?>"
                                        title="<?php _e('Supprimer', 'secure-media-link'); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($notifications) > 5): ?>
                    <div class="sml-widget-footer">
                        <a href="<?php echo admin_url('admin.php?page=sml-notifications'); ?>" class="sml-view-all">
                            <?php printf(__('Voir toutes les notifications (%d)', 'secure-media-link'), count($notifications)); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="sml-empty-state">
                    <div class="sml-empty-icon">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                    <p><?php _e('Aucune notification', 'secure-media-link'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Widget des suggestions de sécurité
 */
function sml_render_security_suggestions_widget($suggestions) {
    ?>
    <div class="sml-dashboard-widget sml-security-suggestions-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Suggestions de sécurité', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <?php if (!empty($suggestions)): ?>
                    <button class="sml-apply-all-suggestions">
                        <?php _e('Appliquer tout', 'secure-media-link'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <?php if (!empty($suggestions)): ?>
                <div class="sml-suggestions-list">
                    <?php foreach (array_slice($suggestions, 0, 3) as $suggestion): ?>
                        <div class="sml-suggestion-item priority-<?php echo $suggestion['priority']; ?>">
                            <div class="sml-suggestion-icon">
                                <span class="dashicons dashicons-shield"></span>
                            </div>
                            <div class="sml-suggestion-content">
                                <div class="sml-suggestion-title">
                                    <?php echo esc_html($suggestion['value']); ?>
                                </div>
                                <div class="sml-suggestion-reason">
                                    <?php echo esc_html($suggestion['reason']); ?>
                                </div>
                            </div>
                            <div class="sml-suggestion-actions">
                                <button class="sml-btn sml-btn-small sml-apply-suggestion" 
                                        data-suggestion='<?php echo esc_attr(json_encode($suggestion)); ?>'>
                                    <?php _e('Appliquer', 'secure-media-link'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($suggestions) > 3): ?>
                    <div class="sml-widget-footer">
                        <a href="<?php echo admin_url('admin.php?page=sml-permissions&tab=suggestions'); ?>" class="sml-view-all">
                            <?php printf(__('Voir toutes les suggestions (%d)', 'secure-media-link'), count($suggestions)); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="sml-empty-state sml-empty-state-positive">
                    <div class="sml-empty-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <p><?php _e('Aucune suggestion - Configuration optimale', 'secure-media-link'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Widget des liens expirant bientôt
 */
function sml_render_expiring_links_widget() {
    global $wpdb;
    
    $expiring_links = $wpdb->get_results(
        "SELECT sl.*, p.post_title, mf.name as format_name
         FROM {$wpdb->prefix}sml_secure_links sl
         LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
         LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
         WHERE sl.is_active = 1 
         AND sl.expires_at > NOW()
         AND sl.expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
         ORDER BY sl.expires_at ASC
         LIMIT 10"
    );
    
    ?>
    <div class="sml-dashboard-widget sml-expiring-links-widget">
        <div class="sml-widget-header">
            <h3><?php _e('Liens expirant bientôt', 'secure-media-link'); ?></h3>
            <div class="sml-widget-actions">
                <a href="<?php echo admin_url('admin.php?page=sml-media-library&tab=expiring'); ?>" class="sml-view-all">
                    <?php _e('Voir tout', 'secure-media-link'); ?>
                </a>
            </div>
        </div>
        
        <div class="sml-widget-content">
            <?php if (!empty($expiring_links)): ?>
                <div class="sml-expiring-links-list">
                    <?php foreach ($expiring_links as $link): ?>
                        <?php
                        $expires_in = strtotime($link->expires_at) - time();
                        $days_remaining = floor($expires_in / (24 * 60 * 60));
                        ?>
                        <div class="sml-expiring-link-item <?php echo $days_remaining <= 7 ? 'urgent' : 'warning'; ?>">
                            <div class="sml-link-info">
                                <div class="sml-link-title">
                                    <?php echo esc_html($link->post_title ?: __('Sans titre', 'secure-media-link')); ?>
                                </div>
                                <div class="sml-link-format">
                                    <?php echo esc_html($link->format_name); ?>
                                </div>
                            </div>
                            <div class="sml-link-expiry">
                                <?php if ($days_remaining > 0): ?>
                                    <span class="sml-days-remaining">
                                        <?php printf(_n('%d jour', '%d jours', $days_remaining, 'secure-media-link'), $days_remaining); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="sml-expires-today">
                                        <?php _e('Expire aujourd\'hui', 'secure-media-link'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="sml-link-actions">
                                <button class="sml-btn-icon sml-extend-link" 
                                        data-link-id="<?php echo $link->id; ?>"
                                        title="<?php _e('Prolonger', 'secure-media-link'); ?>">
                                    <span class="dashicons dashicons-clock"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="sml-empty-state">
                    <div class="sml-empty-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <p><?php _e('Aucun lien n\'expire dans les 30 prochains jours', 'secure-media-link'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.sml-extend-link').on('click', function() {
            var linkId = $(this).data('link-id');
            var $item = $(this).closest('.sml-expiring-link-item');
            
            if (confirm('<?php _e('Prolonger ce lien de 1 an ?', 'secure-media-link'); ?>')) {
                $.post(ajaxurl, {
                    action: 'sml_extend_link',
                    nonce: sml_ajax.nonce,
                    link_id: linkId,
                    extend_period: '1 year'
                }, function(response) {
                    if (response.success) {
                        $item.fadeOut();
                        SMLAdmin.showNotice('success', '<?php _e('Lien prolongé avec succès', 'secure-media-link'); ?>');
                    } else {
                        SMLAdmin.showNotice('error', response.data);
                    }
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * Fonction pour afficher tous les widgets du dashboard
 */
function sml_render_dashboard_widgets() {
    $stats = SML_Tracking::get_global_statistics();
    $recent_violations = SML_Tracking::get_tracking_data(array('is_authorized' => 0), 1, 10);
    $suggestions = SML_Permissions::analyze_violations_for_suggestions();
    $notifications = SML_Notifications::get_notifications(null, 10, true);
    
    ?>
    <div class="sml-dashboard-widgets-container">
        <div class="sml-widgets-row">
            <div class="sml-widget-col sml-col-6">
                <?php sml_render_quick_stats_widget($stats); ?>
            </div>
            <div class="sml-widget-col sml-col-6">
                <?php sml_render_activity_chart_widget(); ?>
            </div>
        </div>
        
        <div class="sml-widgets-row">
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_recent_violations_widget($recent_violations); ?>
            </div>
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_popular_media_widget($stats['top_media']); ?>
            </div>
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_notifications_widget($notifications); ?>
            </div>
        </div>
        
        <div class="sml-widgets-row">
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_security_suggestions_widget($suggestions); ?>
            </div>
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_expiring_links_widget(); ?>
            </div>
            <div class="sml-widget-col sml-col-4">
                <?php sml_render_system_status_widget(); ?>
            </div>
        </div>
    </div>
    <?php
}