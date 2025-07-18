<?php
/**
 * Classe pour l'interface d'administration
 * admin/class-sml-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Admin {
    
    /**
     * Page du tableau de bord
     */
    public static function dashboard_page() {
        $stats = SML_Tracking::get_global_statistics();
        $recent_violations = SML_Tracking::get_tracking_data(array('is_authorized' => 0), 1, 10);
        $suggestions = SML_Permissions::analyze_violations_for_suggestions();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Secure Media Link - Tableau de bord', 'secure-media-link'); ?></h1>
            
            <!-- Statistiques principales -->
            <div class="sml-dashboard-stats">
                <div class="sml-stat-cards">
                    <div class="sml-stat-card">
                        <h3><?php _e('Total Médias', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['media']['total_media']); ?></span>
                    </div>
                    
                    <div class="sml-stat-card">
                        <h3><?php _e('Liens Actifs', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['media']['active_links']); ?></span>
                    </div>
                    
                    <div class="sml-stat-card">
                        <h3><?php _e('Téléchargements', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['usage']['downloads']); ?></span>
                        <span class="sml-stat-percent"><?php echo $stats['percentages']['downloads']; ?>%</span>
                    </div>
                    
                    <div class="sml-stat-card">
                        <h3><?php _e('Demandes Bloquées', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['usage']['blocked']); ?></span>
                        <span class="sml-stat-percent"><?php echo $stats['percentages']['blocked']; ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="sml-dashboard-charts">
                <div class="sml-chart-container">
                    <h3><?php _e('Activité des 30 derniers jours', 'secure-media-link'); ?></h3>
                    <canvas id="sml-activity-chart"></canvas>
                </div>
                
                <div class="sml-chart-container">
                    <h3><?php _e('Répartition des actions', 'secure-media-link'); ?></h3>
                    <canvas id="sml-actions-chart"></canvas>
                </div>
            </div>
            
            <!-- Violations récentes -->
            <div class="sml-recent-violations">
                <h3><?php _e('Violations Récentes', 'secure-media-link'); ?></h3>
                
                <?php if (!empty($recent_violations['data'])): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'secure-media-link'); ?></th>
                                <th><?php _e('IP', 'secure-media-link'); ?></th>
                                <th><?php _e('Domaine', 'secure-media-link'); ?></th>
                                <th><?php _e('Action', 'secure-media-link'); ?></th>
                                <th><?php _e('Type de Violation', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions', 'secure-media-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_violations['data'] as $violation): ?>
                                <tr>
                                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($violation->created_at)); ?></td>
                                    <td><code><?php echo esc_html($violation->ip_address); ?></code></td>
                                    <td><?php echo esc_html($violation->domain); ?></td>
                                    <td><?php echo esc_html($violation->action_type); ?></td>
                                    <td><?php echo esc_html($violation->violation_type); ?></td>
                                    <td>
                                        <button class="button button-small sml-block-ip" data-ip="<?php echo esc_attr($violation->ip_address); ?>">
                                            <?php _e('Bloquer IP', 'secure-media-link'); ?>
                                        </button>
                                        <button class="button button-small sml-block-domain" data-domain="<?php echo esc_attr($violation->domain); ?>">
                                            <?php _e('Bloquer Domaine', 'secure-media-link'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('Aucune violation récente.', 'secure-media-link'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Suggestions de sécurité -->
            <?php if (!empty($suggestions)): ?>
                <div class="sml-security-suggestions">
                    <h3><?php _e('Suggestions de Sécurité', 'secure-media-link'); ?></h3>
                    
                    <div class="notice notice-warning">
                        <p><?php _e('Les éléments suivants sont recommandés pour améliorer la sécurité:', 'secure-media-link'); ?></p>
                        
                        <ul>
                            <?php foreach ($suggestions as $suggestion): ?>
                                <li>
                                    <strong><?php echo esc_html($suggestion['value']); ?></strong>: 
                                    <?php echo esc_html($suggestion['reason']); ?>
                                    <button class="button button-small sml-apply-suggestion" 
                                            data-type="<?php echo esc_attr($suggestion['type']); ?>"
                                            data-value="<?php echo esc_attr($suggestion['value']); ?>">
                                        <?php _e('Appliquer', 'secure-media-link'); ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Top médias et domaines -->
            <div class="sml-dashboard-tops">
                <div class="sml-top-media">
                    <h3><?php _e('Top 5 Médias', 'secure-media-link'); ?></h3>
                    
                    <?php if (!empty($stats['top_media'])): ?>
                        <ol>
                            <?php foreach ($stats['top_media'] as $media): ?>
                                <li>
                                    <strong><?php echo esc_html($media->post_title); ?></strong>
                                    <span class="sml-usage-count"><?php echo number_format($media->usage_count); ?> <?php _e('utilisations', 'secure-media-link'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p><?php _e('Aucune donnée disponible.', 'secure-media-link'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="sml-top-domains">
                    <h3><?php _e('Top 5 Domaines', 'secure-media-link'); ?></h3>
                    
                    <?php if (!empty($stats['top_domains'])): ?>
                        <ol>
                            <?php foreach ($stats['top_domains'] as $domain): ?>
                                <li>
                                    <strong><?php echo esc_html($domain->domain); ?></strong>
                                    <span class="sml-usage-count"><?php echo number_format($domain->request_count); ?> <?php _e('requêtes', 'secure-media-link'); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p><?php _e('Aucune donnée disponible.', 'secure-media-link'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="sml-quick-actions">
                <h3><?php _e('Actions Rapides', 'secure-media-link'); ?></h3>
                
                <div class="sml-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=sml-media-library'); ?>" class="button button-primary">
                        <?php _e('Médiathèque Sécurisée', 'secure-media-link'); ?>
                    </a>
                    
                    <button class="button sml-scan-external" id="sml-manual-scan">
                        <?php _e('Scanner Utilisation Externe', 'secure-media-link'); ?>
                    </button>
                    
                    <button class="button sml-cleanup-cache" id="sml-cleanup-cache">
                        <?php _e('Nettoyer le Cache', 'secure-media-link'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=sml-settings'); ?>" class="button">
                        <?php _e('Paramètres', 'secure-media-link'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialiser les graphiques
            smlInitializeCharts();
            
            // Actions rapides
            $('#sml-manual-scan').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Scan en cours...', 'secure-media-link'); ?>');
                
                                $.post(ajaxurl, {
                    action: 'sml_manual_scan',
                    nonce: sml_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Scan terminé avec succès', 'secure-media-link'); ?>');
                    } else {
                        alert('<?php _e('Erreur lors du scan', 'secure-media-link'); ?>');
                    }
                    $btn.prop('disabled', false).text('<?php _e('Scanner Utilisation Externe', 'secure-media-link'); ?>');
                });
            });
            
            $('#sml-cleanup-cache').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'sml_cleanup_cache',
                    nonce: sml_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Cache nettoyé avec succès', 'secure-media-link'); ?>');
                    }
                    $btn.prop('disabled', false);
                });
            });
            
            // Bloquer IP/Domaine depuis les violations
            $('.sml-block-ip').on('click', function() {
                var ip = $(this).data('ip');
                if (confirm('<?php _e('Bloquer cette IP ?', 'secure-media-link'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'sml_add_permission',
                        nonce: sml_ajax.nonce,
                        type: 'ip',
                        value: ip,
                        permission_type: 'blacklist',
                        actions: ['download', 'copy', 'view'],
                        description: '<?php _e('Bloqué depuis le tableau de bord', 'secure-media-link'); ?>',
                        is_active: 1
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('IP bloquée avec succès', 'secure-media-link'); ?>');
                            location.reload();
                        }
                    });
                }
            });
            
            $('.sml-block-domain').on('click', function() {
                var domain = $(this).data('domain');
                if (confirm('<?php _e('Bloquer ce domaine ?', 'secure-media-link'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'sml_add_permission',
                        nonce: sml_ajax.nonce,
                        type: 'domain',
                        value: domain,
                        permission_type: 'blacklist',
                        actions: ['download', 'copy', 'view'],
                        description: '<?php _e('Bloqué depuis le tableau de bord', 'secure-media-link'); ?>',
                        is_active: 1
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('Domaine bloqué avec succès', 'secure-media-link'); ?>');
                            location.reload();
                        }
                    });
                }
            });
        });
        
        function smlInitializeCharts() {
            // Graphique d'activité
            var activityCtx = document.getElementById('sml-activity-chart').getContext('2d');
            var activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column(SML_Tracking::get_chart_data('requests', 'month'), 'date')); ?>,
                    datasets: [{
                        label: '<?php _e('Total', 'secure-media-link'); ?>',
                        data: <?php echo json_encode(array_column(SML_Tracking::get_chart_data('requests', 'month'), 'total')); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }, {
                        label: '<?php _e('Autorisés', 'secure-media-link'); ?>',
                        data: <?php echo json_encode(array_column(SML_Tracking::get_chart_data('requests', 'month'), 'authorized')); ?>,
                        borderColor: 'rgb(54, 162, 235)',
                        tension: 0.1
                    }, {
                        label: '<?php _e('Bloqués', 'secure-media-link'); ?>',
                        data: <?php echo json_encode(array_column(SML_Tracking::get_chart_data('requests', 'month'), 'blocked')); ?>,
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Graphique des actions
            var actionsCtx = document.getElementById('sml-actions-chart').getContext('2d');
            var actionsChart = new Chart(actionsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['<?php _e('Téléchargements', 'secure-media-link'); ?>', '<?php _e('Copies', 'secure-media-link'); ?>', '<?php _e('Vues', 'secure-media-link'); ?>'],
                    datasets: [{
                        data: [
                            <?php echo $stats['usage']['downloads']; ?>,
                            <?php echo $stats['usage']['copies']; ?>,
                            <?php echo $stats['usage']['total_requests'] - $stats['usage']['downloads'] - $stats['usage']['copies']; ?>
                        ],
                        backgroundColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Page de la médiathèque sécurisée
     */
    public static function media_library_page() {
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
        $tabs = array(
            'all' => __('Tous', 'secure-media-link'),
            'new' => __('Nouveaux', 'secure-media-link'),
            'active' => __('Actifs', 'secure-media-link'),
            'inactive' => __('Inactifs', 'secure-media-link'),
            'expired' => __('Expirés', 'secure-media-link')
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Médiathèque Sécurisée', 'secure-media-link'); ?></h1>
            
            <!-- Onglets -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_name): ?>
                    <a href="?page=sml-media-library&tab=<?php echo $tab_key; ?>" 
                       class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo $tab_name; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Actions groupées -->
            <div class="sml-bulk-actions">
                <select id="sml-bulk-action">
                    <option value=""><?php _e('Actions groupées', 'secure-media-link'); ?></option>
                    <option value="generate_links"><?php _e('Générer des liens', 'secure-media-link'); ?></option>
                    <option value="activate_links"><?php _e('Activer les liens', 'secure-media-link'); ?></option>
                    <option value="deactivate_links"><?php _e('Désactiver les liens', 'secure-media-link'); ?></option>
                    <option value="delete_links"><?php _e('Supprimer les liens', 'secure-media-link'); ?></option>
                </select>
                
                <button class="button" id="sml-apply-bulk-action"><?php _e('Appliquer', 'secure-media-link'); ?></button>
            </div>
            
            <!-- Filtres -->
            <div class="sml-filters">
                <input type="text" id="sml-search" placeholder="<?php _e('Rechercher...', 'secure-media-link'); ?>">
                
                <select id="sml-filter-format">
                    <option value=""><?php _e('Tous les formats', 'secure-media-link'); ?></option>
                    <?php foreach (SML_Media_Formats::get_all_formats() as $format): ?>
                        <option value="<?php echo $format->id; ?>"><?php echo esc_html($format->name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="sml-filter-author">
                    <option value=""><?php _e('Tous les auteurs', 'secure-media-link'); ?></option>
                    <?php foreach (get_users(array('role' => 'author')) as $user): ?>
                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button class="button" id="sml-apply-filters"><?php _e('Filtrer', 'secure-media-link'); ?></button>
                <button class="button" id="sml-reset-filters"><?php _e('Réinitialiser', 'secure-media-link'); ?></button>
            </div>
            
            <!-- Vue -->
            <div class="sml-view-switcher">
                <button class="button sml-view-btn active" data-view="grid">
                    <span class="dashicons dashicons-grid-view"></span> <?php _e('Grille', 'secure-media-link'); ?>
                </button>
                <button class="button sml-view-btn" data-view="list">
                    <span class="dashicons dashicons-list-view"></span> <?php _e('Liste', 'secure-media-link'); ?>
                </button>
            </div>
            
            <!-- Contenu de la médiathèque -->
            <div id="sml-media-content">
                <!-- Le contenu sera chargé via AJAX -->
            </div>
            
            <!-- Pagination -->
            <div id="sml-pagination"></div>
        </div>
        
        <!-- Modal pour les détails d'un média -->
        <div id="sml-media-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <div id="sml-media-details"></div>
            </div>
        </div>
        
        <!-- Modal pour générer des liens -->
        <div id="sml-generate-links-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <h3><?php _e('Générer des liens sécurisés', 'secure-media-link'); ?></h3>
                
                <form id="sml-generate-links-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="sml-formats"><?php _e('Formats', 'secure-media-link'); ?></label></th>
                            <td>
                                <?php foreach (SML_Media_Formats::get_all_formats() as $format): ?>
                                    <label>
                                        <input type="checkbox" name="formats[]" value="<?php echo $format->id; ?>">
                                        <?php echo esc_html($format->name); ?> (<?php echo esc_html($format->type); ?>)
                                    </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-expiry-date"><?php _e('Date d\'expiration', 'secure-media-link'); ?></label></th>
                            <td>
                                <input type="datetime-local" id="sml-expiry-date" name="expiry_date">
                                <p class="description"><?php _e('Laissez vide pour utiliser la valeur par défaut (3 ans)', 'secure-media-link'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="sml-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Générer les liens', 'secure-media-link'); ?></button>
                        <button type="button" class="button sml-modal-close"><?php _e('Annuler', 'secure-media-link'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var currentView = 'grid';
            var currentTab = '<?php echo $current_tab; ?>';
            var currentPage = 1;
            
            // Charger le contenu initial
            loadMediaContent();
            
            // Gestion des vues
            $('.sml-view-btn').on('click', function() {
                $('.sml-view-btn').removeClass('active');
                $(this).addClass('active');
                currentView = $(this).data('view');
                loadMediaContent();
            });
            
            // Gestion des onglets
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = new URL($(this).attr('href')).searchParams.get('tab');
                currentTab = tab;
                currentPage = 1;
                loadMediaContent();
            });
            
            // Recherche et filtres
            $('#sml-apply-filters').on('click', function() {
                currentPage = 1;
                loadMediaContent();
            });
            
            $('#sml-reset-filters').on('click', function() {
                $('#sml-search').val('');
                $('#sml-filter-format').val('');
                $('#sml-filter-author').val('');
                currentPage = 1;
                loadMediaContent();
            });
            
            // Actions groupées
            $('#sml-apply-bulk-action').on('click', function() {
                var action = $('#sml-bulk-action').val();
                var selected = [];
                
                $('.sml-media-checkbox:checked').each(function() {
                    selected.push($(this).val());
                });
                
                if (!action || selected.length === 0) {
                    alert('<?php _e('Veuillez sélectionner une action et des médias', 'secure-media-link'); ?>');
                    return;
                }
                
                if (action === 'generate_links') {
                    openGenerateLinksModal(selected);
                } else {
                    performBulkAction(action, selected);
                }
            });
            
            // Modal
            $('.sml-modal-close').on('click', function() {
                $(this).closest('.sml-modal').hide();
            });
            
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('sml-modal')) {
                    $('.sml-modal').hide();
                }
            });
            
            function loadMediaContent() {
                $('#sml-media-content').html('<div class="sml-loading"><?php _e('Chargement...', 'secure-media-link'); ?></div>');
                
                var filters = {
                    search: $('#sml-search').val(),
                    format: $('#sml-filter-format').val(),
                    author: $('#sml-filter-author').val()
                };
                
                $.post(ajaxurl, {
                    action: 'sml_load_media_library',
                    nonce: sml_ajax.nonce,
                    tab: currentTab,
                    view: currentView,
                    page: currentPage,
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        $('#sml-media-content').html(response.data.content);
                        $('#sml-pagination').html(response.data.pagination);
                    } else {
                        $('#sml-media-content').html('<div class="error">' + response.data + '</div>');
                    }
                });
            }
            
            function openGenerateLinksModal(mediaIds) {
                $('#sml-generate-links-modal').show();
                $('#sml-generate-links-form').data('media-ids', mediaIds);
            }
            
            function performBulkAction(action, mediaIds) {
                if (!confirm('<?php _e('Êtes-vous sûr de vouloir effectuer cette action ?', 'secure-media-link'); ?>')) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'sml_bulk_media_action',
                    nonce: sml_ajax.nonce,
                    bulk_action: action,
                    media_ids: mediaIds
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        loadMediaContent();
                    } else {
                        alert(response.data);
                    }
                });
            }
            
            // Génération de liens
            $('#sml-generate-links-form').on('submit', function(e) {
                e.preventDefault();
                
                var mediaIds = $(this).data('media-ids');
                var formats = [];
                var expiryDate = $('#sml-expiry-date').val();
                
                $('input[name="formats[]"]:checked').each(function() {
                    formats.push($(this).val());
                });
                
                if (formats.length === 0) {
                    alert('<?php _e('Veuillez sélectionner au moins un format', 'secure-media-link'); ?>');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'sml_generate_bulk_links',
                    nonce: sml_ajax.nonce,
                    media_ids: mediaIds,
                    formats: formats,
                    expiry_date: expiryDate
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('#sml-generate-links-modal').hide();
                        loadMediaContent();
                    } else {
                        alert(response.data);
                    }
                });
            });
            
            // Délégation d'événements pour les éléments chargés dynamiquement
            $(document).on('click', '.sml-media-item', function() {
                var mediaId = $(this).data('media-id');
                openMediaModal(mediaId);
            });
            
            $(document).on('click', '.sml-pagination-link', function(e) {
                e.preventDefault();
                currentPage = parseInt($(this).data('page'));
                loadMediaContent();
            });
            
            function openMediaModal(mediaId) {
                $('#sml-media-modal').show();
                $('#sml-media-details').html('<div class="sml-loading"><?php _e('Chargement...', 'secure-media-link'); ?></div>');
                
                $.post(ajaxurl, {
                    action: 'sml_get_media_details',
                    nonce: sml_ajax.nonce,
                    media_id: mediaId
                }, function(response) {
                    if (response.success) {
                        $('#sml-media-details').html(response.data);
                    } else {
                        $('#sml-media-details').html('<div class="error">' + response.data + '</div>');
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Page des formats de média
     */
    public static function media_formats_page() {
        $formats = SML_Media_Formats::get_all_formats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Formats de Média', 'secure-media-link'); ?></h1>
            
            <div class="sml-formats-actions">
                <button class="button button-primary" id="sml-add-format"><?php _e('Ajouter un Format', 'secure-media-link'); ?></button>
                <button class="button" id="sml-import-formats"><?php _e('Importer des Formats', 'secure-media-link'); ?></button>
                <a href="#" class="button" id="sml-export-formats"><?php _e('Exporter les Formats', 'secure-media-link'); ?></a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Nom', 'secure-media-link'); ?></th>
                        <th><?php _e('Type', 'secure-media-link'); ?></th>
                        <th><?php _e('Dimensions', 'secure-media-link'); ?></th>
                        <th><?php _e('Qualité', 'secure-media-link'); ?></th>
                        <th><?php _e('Format', 'secure-media-link'); ?></th>
                        <th><?php _e('Mode', 'secure-media-link'); ?></th>
                        <th><?php _e('Utilisations', 'secure-media-link'); ?></th>
                        <th><?php _e('Actions', 'secure-media-link'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formats as $format): ?>
                        <tr>
                            <td><strong><?php echo esc_html($format->name); ?></strong></td>
                            <td>
                                <span class="sml-format-type sml-type-<?php echo esc_attr($format->type); ?>">
                                    <?php echo esc_html(ucfirst($format->type)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($format->width && $format->height) {
                                    echo $format->width . ' × ' . $format->height;
                                } elseif ($format->width) {
                                    echo $format->width . ' px';
                                } elseif ($format->height) {
                                    echo $format->height . ' px';
                                } else {
                                    echo __('Original', 'secure-media-link');
                                }
                                ?>
                            </td>
                            <td><?php echo $format->quality; ?>%</td>
                            <td><code><?php echo strtoupper($format->format); ?></code></td>
                            <td><?php echo esc_html(ucfirst($format->crop_mode)); ?></td>
                            <td>
                                <?php
                                global $wpdb;
                                $usage_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE format_id = %d",
                                    $format->id
                                ));
                                echo number_format($usage_count);
                                ?>
                            </td>
                            <td>
                                <button class="button button-small sml-edit-format" data-format-id="<?php echo $format->id; ?>">
                                    <?php _e('Modifier', 'secure-media-link'); ?>
                                </button>
                                <button class="button button-small sml-delete-format" data-format-id="<?php echo $format->id; ?>">
                                    <?php _e('Supprimer', 'secure-media-link'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal pour ajouter/modifier un format -->
        <div id="sml-format-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <h3 id="sml-format-modal-title"><?php _e('Ajouter un Format', 'secure-media-link'); ?></h3>
                
                <form id="sml-format-form">
                    <input type="hidden" id="sml-format-id" name="format_id">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="sml-format-name"><?php _e('Nom', 'secure-media-link'); ?></label></th>
                            <td><input type="text" id="sml-format-name" name="name" required></td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-description"><?php _e('Description', 'secure-media-link'); ?></label></th>
                            <td><textarea id="sml-format-description" name="description"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-type"><?php _e('Type', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-format-type" name="type" required>
                                    <option value="web"><?php _e('Web', 'secure-media-link'); ?></option>
                                    <option value="print"><?php _e('Impression', 'secure-media-link'); ?></option>
                                    <option value="social"><?php _e('Réseaux sociaux', 'secure-media-link'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-width"><?php _e('Largeur', 'secure-media-link'); ?></label></th>
                            <td><input type="number" id="sml-format-width" name="width" min="1"></td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-height"><?php _e('Hauteur', 'secure-media-link'); ?></label></th>
                            <td><input type="number" id="sml-format-height" name="height" min="1"></td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-quality"><?php _e('Qualité', 'secure-media-link'); ?></label></th>
                            <td>
                                <input type="range" id="sml-format-quality" name="quality" min="1" max="100" value="85">
                                <span id="sml-quality-value">85</span>%
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-format"><?php _e('Format de fichier', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-format-format" name="format" required>
                                    <option value="jpg">JPG</option>
                                    <option value="png">PNG</option>
                                    <option value="webp">WebP</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-crop-mode"><?php _e('Mode de redimensionnement', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-format-crop-mode" name="crop_mode" required>
                                    <option value="resize"><?php _e('Redimensionner', 'secure-media-link'); ?></option>
                                    <option value="crop"><?php _e('Recadrer', 'secure-media-link'); ?></option>
                                    <option value="fit"><?php _e('Adapter', 'secure-media-link'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-format-crop-position"><?php _e('Position de recadrage', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-format-crop-position" name="crop_position">
                                    <option value="center"><?php _e('Centre', 'secure-media-link'); ?></option>
                                    <option value="top"><?php _e('Haut', 'secure-media-link'); ?></option>
                                    <option value="bottom"><?php _e('Bas', 'secure-media-link'); ?></option>
                                    <option value="left"><?php _e('Gauche', 'secure-media-link'); ?></option>
                                    <option value="right"><?php _e('Droite', 'secure-media-link'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="sml-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Sauvegarder', 'secure-media-link'); ?></button>
                        <button type="button" class="button sml-modal-close"><?php _e('Annuler', 'secure-media-link'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mise à jour de l'affichage de la qualité
            $('#sml-format-quality').on('input', function() {
                $('#sml-quality-value').text($(this).val());
            });
            
            // Ajouter un format
            $('#sml-add-format').on('click', function() {
                $('#sml-format-modal-title').text('<?php _e('Ajouter un Format', 'secure-media-link'); ?>');
                $('#sml-format-form')[0].reset();
                $('#sml-format-id').val('');
                $('#sml-format-modal').show();
            });
            
            // Modifier un format
            $('.sml-edit-format').on('click', function() {
                var formatId = $(this).data('format-id');
                
                $.post(ajaxurl, {
                    action: 'sml_get_format',
                    nonce: sml_ajax.nonce,
                    format_id: formatId
                            }, function(response) {
                    if (response.success) {
                        var format = response.data;
                        $('#sml-format-modal-title').text('<?php _e('Modifier le Format', 'secure-media-link'); ?>');
                        $('#sml-format-id').val(format.id);
                        $('#sml-format-name').val(format.name);
                        $('#sml-format-description').val(format.description);
                        $('#sml-format-type').val(format.type);
                        $('#sml-format-width').val(format.width);
                        $('#sml-format-height').val(format.height);
                        $('#sml-format-quality').val(format.quality);
                        $('#sml-quality-value').text(format.quality);
                        $('#sml-format-format').val(format.format);
                        $('#sml-format-crop-mode').val(format.crop_mode);
                        $('#sml-format-crop-position').val(format.crop_position);
                        $('#sml-format-modal').show();
                    }
                });
            });
            
            // Supprimer un format
            $('.sml-delete-format').on('click', function() {
                if (!confirm('<?php _e('Êtes-vous sûr de vouloir supprimer ce format ?', 'secure-media-link'); ?>')) {
                    return;
                }
                
                var formatId = $(this).data('format-id');
                
                $.post(ajaxurl, {
                    action: 'sml_delete_format',
                    nonce: sml_ajax.nonce,
                    format_id: formatId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
            
            // Soumettre le formulaire de format
            $('#sml-format-form').on('submit', function(e) {
                e.preventDefault();
                
                var formatId = $('#sml-format-id').val();
                var action = formatId ? 'sml_update_format' : 'sml_create_format';
                var formData = $(this).serialize();
                
                $.post(ajaxurl, {
                    action: action,
                    nonce: sml_ajax.nonce
                } + '&' + formData, function(response) {
                    if (response.success) {
                        $('#sml-format-modal').hide();
                        location.reload();
                    } else {
                        var errors = response.data.errors || {};
                        var errorMsg = Object.values(errors).join('\n') || response.data.message;
                        alert(errorMsg);
                    }
                });
            });
            
            // Export des formats
            $('#sml-export-formats').on('click', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'sml_export_formats',
                    nonce: sml_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        var blob = new Blob([response.data], {type: 'application/json'});
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'sml_formats_' + new Date().toISOString().split('T')[0] + '.json';
                        a.click();
                        window.URL.revokeObjectURL(url);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Page de tracking et statistiques
     */
    public static function tracking_page() {
        $stats = SML_Tracking::get_global_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Tracking & Statistiques', 'secure-media-link'); ?></h1>
            
            <!-- Filtres de période -->
            <div class="sml-period-filters">
                <button class="button period-btn active" data-period="day"><?php _e('Aujourd\'hui', 'secure-media-link'); ?></button>
                <button class="button period-btn" data-period="week"><?php _e('7 jours', 'secure-media-link'); ?></button>
                <button class="button period-btn" data-period="month"><?php _e('30 jours', 'secure-media-link'); ?></button>
                <button class="button period-btn" data-period="year"><?php _e('1 an', 'secure-media-link'); ?></button>
            </div>
            
            <!-- Statistiques principales -->
            <div class="sml-tracking-stats">
                <div class="sml-stat-cards">
                    <div class="sml-stat-card">
                        <h3><?php _e('Total Requêtes', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number" id="total-requests"><?php echo number_format($stats['usage']['total_requests']); ?></span>
                    </div>
                    
                    <div class="sml-stat-card">
                        <h3><?php _e('Téléchargements', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number" id="total-downloads"><?php echo number_format($stats['usage']['downloads']); ?></span>
                        <span class="sml-stat-percent"><?php echo $stats['percentages']['downloads']; ?>%</span>
                    </div>
                    
                    <div class="sml-stat-card">
                        <h3><?php _e('Copies', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number" id="total-copies"><?php echo number_format($stats['usage']['copies']); ?></span>
                        <span class="sml-stat-percent"><?php echo $stats['percentages']['copies']; ?>%</span>
                    </div>
                    
                    <div class="sml-stat-card sml-stat-danger">
                        <h3><?php _e('Bloquées', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number" id="total-blocked"><?php echo number_format($stats['usage']['blocked']); ?></span>
                        <span class="sml-stat-percent"><?php echo $stats['percentages']['blocked']; ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="sml-tracking-charts">
                <div class="sml-chart-row">
                    <div class="sml-chart-container">
                        <h3><?php _e('Évolution des requêtes', 'secure-media-link'); ?></h3>
                        <canvas id="requests-chart"></canvas>
                    </div>
                    
                    <div class="sml-chart-container">
                        <h3><?php _e('Répartition géographique', 'secure-media-link'); ?></h3>
                        <canvas id="geo-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tableau de tracking -->
            <div class="sml-tracking-table">
                <h3><?php _e('Détails du Tracking', 'secure-media-link'); ?></h3>
                
                <!-- Filtres du tableau -->
                <div class="sml-table-filters">
                    <input type="date" id="filter-date-from" placeholder="<?php _e('Date de début', 'secure-media-link'); ?>">
                    <input type="date" id="filter-date-to" placeholder="<?php _e('Date de fin', 'secure-media-link'); ?>">
                    
                    <select id="filter-action">
                        <option value=""><?php _e('Toutes les actions', 'secure-media-link'); ?></option>
                        <option value="download"><?php _e('Téléchargement', 'secure-media-link'); ?></option>
                        <option value="copy"><?php _e('Copie', 'secure-media-link'); ?></option>
                        <option value="view"><?php _e('Vue', 'secure-media-link'); ?></option>
                    </select>
                    
                    <select id="filter-authorized">
                        <option value=""><?php _e('Toutes', 'secure-media-link'); ?></option>
                        <option value="1"><?php _e('Autorisées', 'secure-media-link'); ?></option>
                        <option value="0"><?php _e('Bloquées', 'secure-media-link'); ?></option>
                    </select>
                    
                    <input type="text" id="filter-search" placeholder="<?php _e('Rechercher...', 'secure-media-link'); ?>">
                    
                    <button class="button" id="apply-filters"><?php _e('Filtrer', 'secure-media-link'); ?></button>
                    <button class="button" id="reset-filters"><?php _e('Réinitialiser', 'secure-media-link'); ?></button>
                    <button class="button" id="export-data"><?php _e('Exporter', 'secure-media-link'); ?></button>
                </div>
                
                <!-- Tableau -->
                <div id="tracking-table-container">
                    <!-- Chargé via AJAX -->
                </div>
                
                <!-- Pagination -->
                <div id="tracking-pagination"></div>
            </div>
        </div>
        
        <!-- Modal d'export -->
        <div id="sml-export-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <h3><?php _e('Exporter les données', 'secure-media-link'); ?></h3>
                
                <form id="sml-export-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="export-format"><?php _e('Format', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="export-format" name="format">
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="export-date-from"><?php _e('Période', 'secure-media-link'); ?></label></th>
                            <td>
                                <input type="date" id="export-date-from" name="date_from">
                                <span><?php _e('à', 'secure-media-link'); ?></span>
                                <input type="date" id="export-date-to" name="date_to">
                            </td>
                        </tr>
                    </table>
                    
                    <div class="sml-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Exporter', 'secure-media-link'); ?></button>
                        <button type="button" class="button sml-modal-close"><?php _e('Annuler', 'secure-media-link'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var currentPeriod = 'day';
            var currentPage = 1;
            
            // Chargement initial
            loadTrackingData();
            updateCharts();
            
            // Gestion des périodes
            $('.period-btn').on('click', function() {
                $('.period-btn').removeClass('active');
                $(this).addClass('active');
                currentPeriod = $(this).data('period');
                updateStats();
                updateCharts();
            });
            
            // Filtres du tableau
            $('#apply-filters').on('click', function() {
                currentPage = 1;
                loadTrackingData();
            });
            
            $('#reset-filters').on('click', function() {
                $('#filter-date-from, #filter-date-to, #filter-action, #filter-authorized, #filter-search').val('');
                currentPage = 1;
                loadTrackingData();
            });
            
            // Export
            $('#export-data').on('click', function() {
                $('#sml-export-modal').show();
            });
            
            $('#sml-export-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.post(ajaxurl, {
                    action: 'sml_export_tracking',
                    nonce: sml_ajax.nonce
                } + '&' + formData, function(response) {
                    if (response.success) {
                        var blob = new Blob([response.data.data], {type: 'text/plain'});
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        a.click();
                        window.URL.revokeObjectURL(url);
                        $('#sml-export-modal').hide();
                    }
                });
            });
            
            function updateStats() {
                $.post(ajaxurl, {
                    action: 'sml_get_period_stats',
                    nonce: sml_ajax.nonce,
                    period: currentPeriod
                }, function(response) {
                    if (response.success) {
                        var stats = response.data;
                        $('#total-requests').text(stats.total_requests.toLocaleString());
                        $('#total-downloads').text(stats.downloads.toLocaleString());
                        $('#total-copies').text(stats.copies.toLocaleString());
                        $('#total-blocked').text(stats.blocked.toLocaleString());
                    }
                });
            }
            
            function loadTrackingData() {
                $('#tracking-table-container').html('<div class="sml-loading"><?php _e('Chargement...', 'secure-media-link'); ?></div>');
                
                var filters = {
                    date_from: $('#filter-date-from').val(),
                    date_to: $('#filter-date-to').val(),
                    action_type: $('#filter-action').val(),
                    is_authorized: $('#filter-authorized').val()
                };
                
                $.post(ajaxurl, {
                    action: 'sml_get_tracking_data',
                    nonce: sml_ajax.nonce,
                    page: currentPage,
                    filters: filters
                }, function(response) {
                    if (response.success) {
                        $('#tracking-table-container').html(response.data.table);
                        $('#tracking-pagination').html(response.data.pagination);
                    }
                });
            }
            
            function updateCharts() {
                // Mettre à jour les graphiques avec les nouvelles données
                $.post(ajaxurl, {
                    action: 'sml_get_chart_data',
                    nonce: sml_ajax.nonce,
                    period: currentPeriod
                }, function(response) {
                    if (response.success) {
                        updateRequestsChart(response.data.requests);
                        updateGeoChart(response.data.countries);
                    }
                });
            }
            
            function updateRequestsChart(data) {
                var ctx = document.getElementById('requests-chart').getContext('2d');
                
                if (window.requestsChart) {
                    window.requestsChart.destroy();
                }
                
                window.requestsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(item => item.date),
                        datasets: [{
                            label: '<?php _e('Autorisées', 'secure-media-link'); ?>',
                            data: data.map(item => item.authorized),
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }, {
                            label: '<?php _e('Bloquées', 'secure-media-link'); ?>',
                            data: data.map(item => item.blocked),
                            borderColor: 'rgb(255, 99, 132)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function updateGeoChart(data) {
                var ctx = document.getElementById('geo-chart').getContext('2d');
                
                if (window.geoChart) {
                    window.geoChart.destroy();
                }
                
                window.geoChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(item => item.country),
                        datasets: [{
                            data: data.map(item => item.count),
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                            ]
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            }
            
            // Délégation d'événements pour la pagination
            $(document).on('click', '.tracking-pagination-link', function(e) {
                e.preventDefault();
                currentPage = parseInt($(this).data('page'));
                loadTrackingData();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Page des autorisations
     */
    public static function permissions_page() {
        $permissions = SML_Permissions::get_all_permissions();
        $stats = SML_Permissions::get_permissions_statistics();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Autorisations', 'secure-media-link'); ?></h1>
            
            <!-- Statistiques des permissions -->
            <div class="sml-permissions-stats">
                <div class="sml-stat-cards">
                    <div class="sml-stat-card">
                        <h3><?php _e('Total Permissions', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['total_permissions']); ?></span>
                    </div>
                    
                    <div class="sml-stat-card sml-stat-danger">
                        <h3><?php _e('Violations (7j)', 'secure-media-link'); ?></h3>
                        <span class="sml-stat-number"><?php echo number_format($stats['recent_violations']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Onglets -->
            <nav class="nav-tab-wrapper">
                <a href="#ips" class="nav-tab nav-tab-active"><?php _e('Adresses IP', 'secure-media-link'); ?></a>
                <a href="#domains" class="nav-tab"><?php _e('Domaines', 'secure-media-link'); ?></a>
                <a href="#suggestions" class="nav-tab"><?php _e('Suggestions', 'secure-media-link'); ?></a>
            </nav>
            
            <!-- Contenu des onglets -->
            <div class="tab-content">
                <!-- Onglet IPs -->
                <div id="ips" class="tab-pane active">
                    <div class="sml-permissions-actions">
                        <button class="button button-primary" id="add-ip-permission"><?php _e('Ajouter une IP', 'secure-media-link'); ?></button>
                        <button class="button" id="import-ip-permissions"><?php _e('Importer des IPs', 'secure-media-link'); ?></button>
                        <a href="#" class="button" id="export-ip-permissions"><?php _e('Exporter les IPs', 'secure-media-link'); ?></a>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Adresse IP', 'secure-media-link'); ?></th>
                                <th><?php _e('Type', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions autorisées', 'secure-media-link'); ?></th>
                                <th><?php _e('Description', 'secure-media-link'); ?></th>
                                <th><?php _e('Statut', 'secure-media-link'); ?></th>
                                <th><?php _e('Créé le', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions', 'secure-media-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permission): ?>
                                <?php if ($permission->type === 'ip'): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($permission->value); ?></code></td>
                                        <td>
                                            <span class="sml-permission-type sml-<?php echo esc_attr($permission->permission_type); ?>">
                                                <?php echo $permission->permission_type === 'whitelist' ? __('Whitelist', 'secure-media-link') : __('Blacklist', 'secure-media-link'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $actions = json_decode($permission->actions, true);
                                            echo is_array($actions) ? implode(', ', $actions) : $permission->actions;
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($permission->description); ?></td>
                                        <td>
                                            <span class="sml-status sml-<?php echo $permission->is_active ? 'active' : 'inactive'; ?>">
                                                <?php echo $permission->is_active ? __('Actif', 'secure-media-link') : __('Inactif', 'secure-media-link'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($permission->created_at)); ?></td>
                                        <td>
                                            <button class="button button-small edit-permission" data-permission-id="<?php echo $permission->id; ?>">
                                                <?php _e('Modifier', 'secure-media-link'); ?>
                                            </button>
                                            <button class="button button-small delete-permission" data-permission-id="<?php echo $permission->id; ?>">
                                                <?php _e('Supprimer', 'secure-media-link'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Onglet Domaines -->
                <div id="domains" class="tab-pane">
                    <div class="sml-permissions-actions">
                        <button class="button button-primary" id="add-domain-permission"><?php _e('Ajouter un Domaine', 'secure-media-link'); ?></button>
                        <button class="button" id="import-domain-permissions"><?php _e('Importer des Domaines', 'secure-media-link'); ?></button>
                        <a href="#" class="button" id="export-domain-permissions"><?php _e('Exporter les Domaines', 'secure-media-link'); ?></a>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Domaine', 'secure-media-link'); ?></th>
                                <th><?php _e('Type', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions autorisées', 'secure-media-link'); ?></th>
                                <th><?php _e('Description', 'secure-media-link'); ?></th>
                                <th><?php _e('Statut', 'secure-media-link'); ?></th>
                                <th><?php _e('Créé le', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions', 'secure-media-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissions as $permission): ?>
                                <?php if ($permission->type === 'domain'): ?>
                                    <tr>
                                        <td><?php echo esc_html($permission->value); ?></td>
                                        <td>
                                            <span class="sml-permission-type sml-<?php echo esc_attr($permission->permission_type); ?>">
                                                <?php echo $permission->permission_type === 'whitelist' ? __('Whitelist', 'secure-media-link') : __('Blacklist', 'secure-media-link'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $actions = json_decode($permission->actions, true);
                                            echo is_array($actions) ? implode(', ', $actions) : $permission->actions;
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($permission->description); ?></td>
                                        <td>
                                            <span class="sml-status sml-<?php echo $permission->is_active ? 'active' : 'inactive'; ?>">
                                                <?php echo $permission->is_active ? __('Actif', 'secure-media-link') : __('Inactif', 'secure-media-link'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($permission->created_at)); ?></td>
                                        <td>
                                            <button class="button button-small edit-permission" data-permission-id="<?php echo $permission->id; ?>">
                                                <?php _e('Modifier', 'secure-media-link'); ?>
                                            </button>
                                            <button class="button button-small delete-permission" data-permission-id="<?php echo $permission->id; ?>">
                                                <?php _e('Supprimer', 'secure-media-link'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Onglet Suggestions -->
                <div id="suggestions" class="tab-pane">
                    <div id="sml-suggestions-content">
                        <!-- Chargé via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal pour ajouter/modifier une permission -->
        <div id="sml-permission-modal" class="sml-modal" style="display: none;">
            <div class="sml-modal-content">
                <span class="sml-modal-close">&times;</span>
                <h3 id="sml-permission-modal-title"><?php _e('Ajouter une Permission', 'secure-media-link'); ?></h3>
                
                <form id="sml-permission-form">
                    <input type="hidden" id="sml-permission-id" name="permission_id">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="sml-permission-type"><?php _e('Type', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-permission-type" name="type" required>
                                    <option value="ip"><?php _e('Adresse IP', 'secure-media-link'); ?></option>
                                    <option value="domain"><?php _e('Domaine', 'secure-media-link'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-permission-value"><?php _e('Valeur', 'secure-media-link'); ?></label></th>
                            <td>
                                <input type="text" id="sml-permission-value" name="value" required>
                                <p class="description"><?php _e('IP (ex: 192.168.1.1) ou domaine (ex: example.com)', 'secure-media-link'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-permission-rule-type"><?php _e('Type de règle', 'secure-media-link'); ?></label></th>
                            <td>
                                <select id="sml-permission-rule-type" name="permission_type" required>
                                    <option value="whitelist"><?php _e('Whitelist (Autoriser)', 'secure-media-link'); ?></option>
                                    <option value="blacklist"><?php _e('Blacklist (Bloquer)', 'secure-media-link'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php _e('Actions', 'secure-media-link'); ?></label></th>
                            <td>
                                <label><input type="checkbox" name="actions[]" value="download" checked> <?php _e('Téléchargement', 'secure-media-link'); ?></label><br>
                                <label><input type="checkbox" name="actions[]" value="copy" checked> <?php _e('Copie', 'secure-media-link'); ?></label><br>
                                <label><input type="checkbox" name="actions[]" value="view" checked> <?php _e('Vue', 'secure-media-link'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="sml-permission-description"><?php _e('Description', 'secure-media-link'); ?></label></th>
                            <td><textarea id="sml-permission-description" name="description"></textarea></td>
                        </tr>
                        <tr>
                            <tr>
                            <th><label for="sml-permission-active"><?php _e('Actif', 'secure-media-link'); ?></label></th>
                            <td><input type="checkbox" id="sml-permission-active" name="is_active" checked></td>
                        </tr>
                    </table>
                    
                    <div class="sml-modal-actions">
                        <button type="submit" class="button button-primary"><?php _e('Sauvegarder', 'secure-media-link'); ?></button>
                        <button type="button" class="button sml-modal-close"><?php _e('Annuler', 'secure-media-link'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des onglets
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-pane').removeClass('active');
                $(target).addClass('active');
                
                if (target === '#suggestions') {
                    loadSuggestions();
                }
            });
            
            // Ajouter une permission IP
            $('#add-ip-permission').on('click', function() {
                openPermissionModal('ip');
            });
            
            // Ajouter une permission domaine
            $('#add-domain-permission').on('click', function() {
                openPermissionModal('domain');
            });
            
            // Modifier une permission
            $('.edit-permission').on('click', function() {
                var permissionId = $(this).data('permission-id');
                editPermission(permissionId);
            });
            
            // Supprimer une permission
            $('.delete-permission').on('click', function() {
                if (!confirm('<?php _e('Êtes-vous sûr de vouloir supprimer cette permission ?', 'secure-media-link'); ?>')) {
                    return;
                }
                
                var permissionId = $(this).data('permission-id');
                deletePermission(permissionId);
            });
            
            // Soumettre le formulaire de permission
            $('#sml-permission-form').on('submit', function(e) {
                e.preventDefault();
                savePermission();
            });
            
            function openPermissionModal(type) {
                $('#sml-permission-modal-title').text('<?php _e('Ajouter une Permission', 'secure-media-link'); ?>');
                $('#sml-permission-form')[0].reset();
                $('#sml-permission-id').val('');
                $('#sml-permission-type').val(type);
                $('#sml-permission-active').prop('checked', true);
                $('input[name="actions[]"]').prop('checked', true);
                $('#sml-permission-modal').show();
            }
            
            function editPermission(permissionId) {
                $.post(ajaxurl, {
                    action: 'sml_get_permission',
                    nonce: sml_ajax.nonce,
                    permission_id: permissionId
                }, function(response) {
                    if (response.success) {
                        var permission = response.data;
                        $('#sml-permission-modal-title').text('<?php _e('Modifier la Permission', 'secure-media-link'); ?>');
                        $('#sml-permission-id').val(permission.id);
                        $('#sml-permission-type').val(permission.type);
                        $('#sml-permission-value').val(permission.value);
                        $('#sml-permission-rule-type').val(permission.permission_type);
                        $('#sml-permission-description').val(permission.description);
                        $('#sml-permission-active').prop('checked', permission.is_active == 1);
                        
                        // Actions
                        $('input[name="actions[]"]').prop('checked', false);
                        var actions = JSON.parse(permission.actions);
                        actions.forEach(function(action) {
                            $('input[name="actions[]"][value="' + action + '"]').prop('checked', true);
                        });
                        
                        $('#sml-permission-modal').show();
                    }
                });
            }
            
            function savePermission() {
                var permissionId = $('#sml-permission-id').val();
                var action = permissionId ? 'sml_update_permission' : 'sml_add_permission';
                var formData = $('#sml-permission-form').serialize();
                
                $.post(ajaxurl, {
                    action: action,
                    nonce: sml_ajax.nonce
                } + '&' + formData, function(response) {
                    if (response.success) {
                        $('#sml-permission-modal').hide();
                        location.reload();
                    } else {
                        var errors = response.data.errors || {};
                        var errorMsg = Object.values(errors).join('\n') || response.data.message;
                        alert(errorMsg);
                    }
                });
            }
            
            function deletePermission(permissionId) {
                $.post(ajaxurl, {
                    action: 'sml_delete_permission',
                    nonce: sml_ajax.nonce,
                    permission_id: permissionId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            }
            
            function loadSuggestions() {
                $('#sml-suggestions-content').html('<div class="sml-loading"><?php _e('Chargement des suggestions...', 'secure-media-link'); ?></div>');
                
                $.post(ajaxurl, {
                    action: 'sml_get_security_suggestions',
                    nonce: sml_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        displaySuggestions(response.data);
                    } else {
                        $('#sml-suggestions-content').html('<div class="error"><?php _e('Erreur lors du chargement des suggestions', 'secure-media-link'); ?></div>');
                    }
                });
            }
            
            function displaySuggestions(suggestions) {
                var html = '';
                
                if (suggestions.length === 0) {
                    html = '<div class="notice notice-success"><p><?php _e('Aucune suggestion de sécurité. Votre configuration semble optimale.', 'secure-media-link'); ?></p></div>';
                } else {
                    html += '<div class="sml-suggestions-header">';
                    html += '<h3><?php _e('Suggestions de Sécurité', 'secure-media-link'); ?></h3>';
                    html += '<button class="button button-primary" id="apply-all-suggestions"><?php _e('Appliquer Toutes', 'secure-media-link'); ?></button>';
                    html += '</div>';
                    
                    html += '<div class="sml-suggestions-list">';
                    
                    suggestions.forEach(function(suggestion) {
                        html += '<div class="sml-suggestion-item sml-priority-' + suggestion.priority + '">';
                        html += '<div class="sml-suggestion-content">';
                        html += '<h4>' + suggestion.value + '</h4>';
                        html += '<p>' + suggestion.reason + '</p>';
                        html += '<span class="sml-suggestion-type">' + suggestion.type + '</span>';
                        html += '</div>';
                        html += '<div class="sml-suggestion-actions">';
                        html += '<button class="button apply-suggestion" data-suggestion=\'' + JSON.stringify(suggestion) + '\'><?php _e('Appliquer', 'secure-media-link'); ?></button>';
                        html += '<button class="button ignore-suggestion"><?php _e('Ignorer', 'secure-media-link'); ?></button>';
                        html += '</div>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                }
                
                $('#sml-suggestions-content').html(html);
                
                // Événements pour les suggestions
                $('.apply-suggestion').on('click', function() {
                    var suggestion = JSON.parse($(this).data('suggestion'));
                    applySuggestion(suggestion, $(this).closest('.sml-suggestion-item'));
                });
                
                $('#apply-all-suggestions').on('click', function() {
                    if (confirm('<?php _e('Appliquer toutes les suggestions de sécurité ?', 'secure-media-link'); ?>')) {
                        applyAllSuggestions(suggestions);
                    }
                });
            }
            
            function applySuggestion(suggestion, $element) {
                $.post(ajaxurl, {
                    action: 'sml_apply_suggestion',
                    nonce: sml_ajax.nonce,
                    suggestion: suggestion
                }, function(response) {
                    if (response.success) {
                        $element.fadeOut();
                        alert('<?php _e('Suggestion appliquée avec succès', 'secure-media-link'); ?>');
                    } else {
                        alert(response.data);
                    }
                });
            }
            
            function applyAllSuggestions(suggestions) {
                $.post(ajaxurl, {
                    action: 'sml_apply_all_suggestions',
                    nonce: sml_ajax.nonce,
                    suggestions: suggestions
                }, function(response) {
                    if (response.success) {
                        alert(response.data.applied + ' <?php _e('suggestions appliquées', 'secure-media-link'); ?>');
                        loadSuggestions();
                    } else {
                        alert(response.data);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Page des paramètres
     */
    public static function settings_page() {
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
        
        // Traitement du formulaire
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['sml_settings_nonce'], 'sml_settings')) {
            $new_settings = array();
            
            $new_settings['default_expiry_years'] = intval($_POST['default_expiry_years']);
            $new_settings['custom_domain'] = sanitize_url($_POST['custom_domain']);
            $new_settings['auto_blocking_enabled'] = isset($_POST['auto_blocking_enabled']);
            $new_settings['auto_blocking_threshold'] = intval($_POST['auto_blocking_threshold']);
            $new_settings['auto_blocking_time_window'] = intval($_POST['auto_blocking_time_window']);
            $new_settings['external_scan_enabled'] = isset($_POST['external_scan_enabled']);
            $new_settings['external_scan_frequency'] = sanitize_text_field($_POST['external_scan_frequency']);
            $new_settings['google_cse_key'] = sanitize_text_field($_POST['google_cse_key']);
            $new_settings['google_cse_id'] = sanitize_text_field($_POST['google_cse_id']);
            $new_settings['notification_email'] = sanitize_email($_POST['notification_email']);
            $new_settings['violation_notifications'] = isset($_POST['violation_notifications']);
            $new_settings['expiry_notifications'] = isset($_POST['expiry_notifications']);
            $new_settings['expiry_notice_days'] = array_map('intval', explode(',', $_POST['expiry_notice_days']));
            $new_settings['cache_enabled'] = isset($_POST['cache_enabled']);
            $new_settings['cache_duration'] = intval($_POST['cache_duration']);
            $new_settings['api_enabled'] = isset($_POST['api_enabled']);
            $new_settings['api_rate_limit'] = intval($_POST['api_rate_limit']);
            
            update_option('sml_settings', $new_settings);
            
            echo '<div class="notice notice-success"><p>' . __('Paramètres sauvegardés.', 'secure-media-link') . '</p></div>';
            
            $settings = $new_settings;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Paramètres - Secure Media Link', 'secure-media-link'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('sml_settings', 'sml_settings_nonce'); ?>
                
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('Général', 'secure-media-link'); ?></a>
                    <a href="#security" class="nav-tab"><?php _e('Sécurité', 'secure-media-link'); ?></a>
                    <a href="#scanning" class="nav-tab"><?php _e('Scan Externe', 'secure-media-link'); ?></a>
                    <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'secure-media-link'); ?></a>
                    <a href="#performance" class="nav-tab"><?php _e('Performance', 'secure-media-link'); ?></a>
                    <a href="#api" class="nav-tab"><?php _e('API', 'secure-media-link'); ?></a>
                </nav>
                
                <div class="tab-content">
                    <!-- Onglet Général -->
                    <div id="general" class="tab-pane active">
                        <h2><?php _e('Paramètres Généraux', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="default_expiry_years"><?php _e('Durée d\'expiration par défaut', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="default_expiry_years" name="default_expiry_years" 
                                           value="<?php echo esc_attr($settings['default_expiry_years']); ?>" min="1" max="10">
                                    <span><?php _e('années', 'secure-media-link'); ?></span>
                                    <p class="description"><?php _e('Durée par défaut avant expiration des liens générés', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="custom_domain"><?php _e('Domaine personnalisé', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="custom_domain" name="custom_domain" 
                                           value="<?php echo esc_attr($settings['custom_domain']); ?>" class="regular-text">
                                    <p class="description"><?php _e('Domaine personnalisé pour les liens sécurisés (ex: https://media.example.com)', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Sécurité -->
                    <div id="security" class="tab-pane">
                        <h2><?php _e('Paramètres de Sécurité', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Blocage automatique', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="auto_blocking_enabled" <?php checked($settings['auto_blocking_enabled']); ?>>
                                        <?php _e('Activer le blocage automatique des IPs suspectes', 'secure-media-link'); ?>
                                    </label>
                                    <p class="description"><?php _e('Bloquer automatiquement les IPs qui génèrent trop de violations', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="auto_blocking_threshold"><?php _e('Seuil de violations', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="auto_blocking_threshold" name="auto_blocking_threshold" 
                                           value="<?php echo esc_attr($settings['auto_blocking_threshold']); ?>" min="5" max="100">
                                    <p class="description"><?php _e('Nombre de violations avant blocage automatique', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="auto_blocking_time_window"><?php _e('Fenêtre de temps', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="auto_blocking_time_window" name="auto_blocking_time_window" 
                                           value="<?php echo esc_attr($settings['auto_blocking_time_window']); ?>" min="1" max="168">
                                    <span><?php _e('heures', 'secure-media-link'); ?></span>
                                    <p class="description"><?php _e('Période durant laquelle compter les violations', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Scan Externe -->
                    <div id="scanning" class="tab-pane">
                        <h2><?php _e('Scan d\'Utilisation Externe', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Scan automatique', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="external_scan_enabled" <?php checked($settings['external_scan_enabled']); ?>>
                                        <?php _e('Activer le scan automatique d\'utilisation externe', 'secure-media-link'); ?>
                                    </label>
                                    <p class="description"><?php _e('Rechercher automatiquement l\'utilisation de vos médias sur d\'autres sites', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="external_scan_frequency"><?php _e('Fréquence', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <select id="external_scan_frequency" name="external_scan_frequency">
                                        <option value="daily" <?php selected($settings['external_scan_frequency'], 'daily'); ?>><?php _e('Quotidien', 'secure-media-link'); ?></option>
                                        <option value="weekly" <?php selected($settings['external_scan_frequency'], 'weekly'); ?>><?php _e('Hebdomadaire', 'secure-media-link'); ?></option>
                                        <option value="monthly" <?php selected($settings['external_scan_frequency'], 'monthly'); ?>><?php _e('Mensuel', 'secure-media-link'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="google_cse_key"><?php _e('Clé API Google Custom Search', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="google_cse_key" name="google_cse_key" 
                                           value="<?php echo esc_attr($settings['google_cse_key']); ?>" class="regular-text">
                                    <p class="description"><?php _e('Clé API pour utiliser Google Custom Search (optionnel)', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="google_cse_id"><?php _e('ID Custom Search Engine', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="google_cse_id" name="google_cse_id" 
                                           value="<?php echo esc_attr($settings['google_cse_id']); ?>" class="regular-text">
                                    <p class="description"><?php _e('ID de votre moteur de recherche personnalisé', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Notifications -->
                    <div id="notifications" class="tab-pane">
                        <h2><?php _e('Paramètres de Notifications', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="notification_email"><?php _e('Email de notification', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="email" id="notification_email" name="notification_email" 
                                           value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text">
                                    <p class="description"><?php _e('Adresse email pour recevoir les notifications', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Notifications de violations', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="violation_notifications" <?php checked($settings['violation_notifications']); ?>>
                                        <?php _e('Recevoir des notifications lors de violations de sécurité', 'secure-media-link'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Notifications d\'expiration', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="expiry_notifications" <?php checked($settings['expiry_notifications']); ?>>
                                        <?php _e('Recevoir des notifications avant expiration des liens', 'secure-media-link'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="expiry_notice_days"><?php _e('Délais de notification (jours)', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="expiry_notice_days" name="expiry_notice_days" 
                                           value="<?php echo esc_attr(implode(',', $settings['expiry_notice_days'])); ?>" class="regular-text">
                                    <p class="description"><?php _e('Jours avant expiration pour envoyer les notifications (séparés par des virgules)', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Onglet Performance -->
                    <div id="performance" class="tab-pane">
                        <h2><?php _e('Paramètres de Performance', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Cache', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cache_enabled" <?php checked($settings['cache_enabled']); ?>>
                                        <?php _e('Activer le cache pour améliorer les performances', 'secure-media-link'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cache_duration"><?php _e('Durée du cache', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="cache_duration" name="cache_duration" 
                                           value="<?php echo esc_attr($settings['cache_duration']); ?>" min="300" max="86400">
                                    <span><?php _e('secondes', 'secure-media-link'); ?></span>
                                    <p class="description"><?php _e('Durée de conservation des données en cache', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="sml-maintenance-actions">
                            <h3><?php _e('Maintenance', 'secure-media-link'); ?></h3>
                            
                            <p>
                                <button type="button" class="button" id="clear-cache"><?php _e('Vider le cache', 'secure-media-link'); ?></button>
                                <button type="button" class="button" id="optimize-db"><?php _e('Optimiser la base de données', 'secure-media-link'); ?></button>
                                <button type="button" class="button" id="cleanup-old-data"><?php _e('Nettoyer les anciennes données', 'secure-media-link'); ?></button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Onglet API -->
                    <div id="api" class="tab-pane">
                        <h2><?php _e('Paramètres API', 'secure-media-link'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('API REST', 'secure-media-link'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="api_enabled" <?php checked($settings['api_enabled']); ?>>
                                        <?php _e('Activer l\'API REST pour l\'intégration externe', 'secure-media-link'); ?>
                                    </label>
                                    <p class="description"><?php _e('Permet aux applications externes d\'interagir avec le plugin', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="api_rate_limit"><?php _e('Limite de taux', 'secure-media-link'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="api_rate_limit" name="api_rate_limit" 
                                           value="<?php echo esc_attr($settings['api_rate_limit']); ?>" min="100" max="10000">
                                    <span><?php _e('requêtes par heure', 'secure-media-link'); ?></span>
                                    <p class="description"><?php _e('Nombre maximum de requêtes API par heure et par IP', 'secure-media-link'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="sml-api-info">
                            <h3><?php _e('Informations API', 'secure-media-link'); ?></h3>
                            
                            <p><strong><?php _e('URL de base:', 'secure-media-link'); ?></strong> <code><?php echo rest_url('sml/v1/'); ?></code></p>
                            
                            <p><strong><?php _e('Endpoints disponibles:', 'secure-media-link'); ?></strong></p>
                            <ul>
                                <li><code>GET /media</code> - <?php _e('Lister les médias', 'secure-media-link'); ?></li>
                                <li><code>POST /links</code> - <?php _e('Générer un lien sécurisé', 'secure-media-link'); ?></li>
                                <li><code>GET /links/{id}</code> - <?php _e('Obtenir les détails d\'un lien', 'secure-media-link'); ?></li>
                                <li><code>DELETE /links/{id}</code> - <?php _e('Supprimer un lien', 'secure-media-link'); ?></li>
                            </ul>
                            
                            <p><a href="#" target="_blank"><?php _e('Documentation complète de l\'API', 'secure-media-link'); ?></a></p>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des onglets
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-pane').removeClass('active');
                $(target).addClass('active');
            });
            
            // Actions de maintenance
            $('#clear-cache').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Nettoyage...', 'secure-media-link'); ?>');
                
                $.post(ajaxurl, {
                    action: 'sml_clear_cache',
                    nonce: '<?php echo wp_create_nonce('sml_maintenance'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Cache vidé avec succès', 'secure-media-link'); ?>');
                    } else {
                        alert('<?php _e('Erreur lors du nettoyage du cache', 'secure-media-link'); ?>');
                    }
                    $btn.prop('disabled', false).text('<?php _e('Vider le cache', 'secure-media-link'); ?>');
                });
            });
            
            $('#optimize-db').on('click', function() {
                if (!confirm('<?php _e('Optimiser la base de données ? Cette opération peut prendre quelques minutes.', 'secure-media-link'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Optimisation...', 'secure-media-link'); ?>');
                
                $.post(ajaxurl, {
                    action: 'sml_optimize_database',
                    nonce: '<?php echo wp_create_nonce('sml_maintenance'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php _e('Base de données optimisée avec succès', 'secure-media-link'); ?>');
                    } else {
                        alert('<?php _e('Erreur lors de l\'optimisation', 'secure-media-link'); ?>');
                    }
                    $btn.prop('disabled', false).text('<?php _e('Optimiser la base de données', 'secure-media-link'); ?>');
                });
            });
            
            $('#cleanup-old-data').on('click', function() {
                if (!confirm('<?php _e('Supprimer les anciennes données (plus de 1 an) ? Cette action est irréversible.', 'secure-media-link'); ?>')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e('Nettoyage...', 'secure-media-link'); ?>');
                
                $.post(ajaxurl, {
                    action: 'sml_cleanup_old_data',
                    nonce: '<?php echo wp_create_nonce('sml_maintenance'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert(response.data.deleted + ' <?php _e('entrées supprimées', 'secure-media-link'); ?>');
                    } else {
                        alert('<?php _e('Erreur lors du nettoyage', 'secure-media-link'); ?>');
                    }
                    $btn.prop('disabled', false).text('<?php _e('Nettoyer les anciennes données', 'secure-media-link'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX - Obtenir les détails d'un média
     */
    public static function ajax_get_media_details() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $media_id = intval($_POST['media_id']);
        $media = get_post($media_id);
        
        if (!$media || $media->post_type !== 'attachment') {
            wp_send_json_error(__('Média introuvable', 'secure-media-link'));
        }
        
        // Récupérer les liens existants
        global $wpdb;
        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT sl.*, mf.name as format_name 
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             WHERE sl.media_id = %d
             ORDER BY sl.created_at DESC",
            $media_id
        ));
        
        // Récupérer les métadonnées
        $file_url = wp_get_attachment_url($media_id);
        $file_path = get_attached_file($media_id);
        $file_size = $file_path ? filesize($file_path) : 0;
        $image_meta = wp_get_attachment_metadata($media_id);
        
        ob_start();
        ?>
        <div class="sml-media-details">
            <div class="sml-media-header">
                <div class="sml-media-thumbnail">
                    <?php echo wp_get_attachment_image($media_id, 'medium'); ?>
                </div>
                
                <div class="sml-media-info">
                    <h3><?php echo esc_html($media->post_title); ?></h3>
                    <p><strong><?php _e('Type:', 'secure-media-link'); ?></strong> <?php echo esc_html($media->post_mime_type); ?></p>
                    <p><strong><?php _e('Taille:', 'secure-media-link'); ?></strong> <?php echo size_format($file_size); ?></p>
                    
                    <?php if ($image_meta && isset($image_meta['width'])): ?>
                        <p><strong><?php _e('Dimensions:', 'secure-media-link'); ?></strong> <?php echo $image_meta['width']; ?> × <?php echo $image_meta['height']; ?> px</p>
                    <?php endif; ?>
                    
                    <p><strong><?php _e('Ajouté le:', 'secure-media-link'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($media->post_date)); ?></p>
                    <p><strong><?php _e('Par:', 'secure-media-link'); ?></strong> <?php echo get_userdata($media->post_author)->display_name; ?></p>
                </div>
            </div>
            
            <div class="sml-media-links">
                <h4><?php _e('Liens Sécurisés', 'secure-media-link'); ?></h4>
                
                <div class="sml-generate-new-link">
                    <button class="button button-primary" id="generate-new-link" data-media-id="<?php echo $media_id; ?>">
                        <?php _e('Générer un nouveau lien', 'secure-media-link'); ?>
                    </button>
                </div>
                
                <?php if (!empty($links)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Format', 'secure-media-link'); ?></th>
                                <th><?php _e('Lien', 'secure-media-link'); ?></th>
                                <th><?php _e('Expire le', 'secure-media-link'); ?></th>
                                <th><?php _e('Statut', 'secure-media-link'); ?></th>
                                <th><?php _e('Utilisations', 'secure-media-link'); ?></th>
                                <th><?php _e('Actions', 'secure-media-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?php echo esc_html($link->format_name); ?></td>
                                    <td>
                                        <input type="text" class="sml-link-input" value="<?php echo esc_attr(SML_Crypto::generate_secure_link($media_id, $link->format_id, $link->expires_at)); ?>" readonly>
                                        <button class="button button-small sml-copy-link"><?php _e('Copier', 'secure-media-link'); ?></button>
                                    </td>
                                    <td>
                                        <?php 
                                        $expires = strtotime($link->expires_at);
                                        $is_expired = $expires < time();
                                        echo date_i18n(get_option('date_format'), $expires);
                                        if ($is_expired) {
                                            echo ' <span class="sml-expired">' . __('(Expiré)', 'secure-media-link') . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="sml-status sml-<?php echo $link->is_active && !$is_expired ? 'active' : 'inactive'; ?>">
                                            <?php echo $link->is_active && !$is_expired ? __('Actif', 'secure-media-link') : __('Inactif', 'secure-media-link'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php printf(__('%d téléchargements, %d copies', 'secure-media-link'), $link->download_count, $link->copy_count); ?>
                                    </td>
                                    <td>
                                        <?php if ($link->is_active): ?>
                                            <button class="button button-small sml-deactivate-link" data-link-id="<?php echo $link->id; ?>">
                                                <?php _e('Désactiver', 'secure-media-link'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button class="button button-small sml-activate-link" data-link-id="<?php echo $link->id; ?>">
                                                <?php _e('Activer', 'secure-media-link'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="button button-small sml-delete-link" data-link-id="<?php echo $link->id; ?>">
                                            <?php _e('Supprimer', 'secure-media-link'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('Aucun lien sécurisé généré pour ce média.', 'secure-media-link'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="sml-media-usage">
                <h4><?php _e('Statistiques d\'Utilisation', 'secure-media-link'); ?></h4>
                
                <?php
                // Récupérer les statistiques d'utilisation
                $usage_stats = $wpdb->get_results($wpdb->prepare(
                    "SELECT t.action_type, COUNT(*) as count, t.is_authorized
                     FROM {$wpdb->prefix}sml_tracking t
                     LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON t.link_id = sl.id
                     WHERE sl.media_id = %d
                     GROUP BY t.action_type, t.is_authorized",
                    $media_id
                ));
                
                if (!empty($usage_stats)):
                ?>
                    <table class="wp-list-table widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Action', 'secure-media-link'); ?></th>
                                <th><?php _e('Autorisées', 'secure-media-link'); ?></th>
                                <th><?php _e('Bloquées', 'secure-media-link'); ?></th>
                                <th><?php _e('Total', 'secure-media-link'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stats_by_action = array();
                            foreach ($usage_stats as $stat) {
                                if (!isset($stats_by_action[$stat->action_type])) {
                                    $stats_by_action[$stat->action_type] = array('authorized' => 0, 'blocked' => 0);
                                }
                                
                                if ($stat->is_authorized) {
                                    $stats_by_action[$stat->action_type]['authorized'] = $stat->count;
                                } else {
                                    $stats_by_action[$stat->action_type]['blocked'] = $stat->count;
                                }
                            }
                            
                            foreach ($stats_by_action as $action => $counts):
                                $total = $counts['authorized'] + $counts['blocked'];
                            ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($action)); ?></td>
                                    <td><?php echo number_format($counts['authorized']); ?></td>
                                    <td><?php echo number_format($counts['blocked']); ?></td>
                                    <td><?php echo number_format($total); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('Aucune statistique d\'utilisation disponible.', 'secure-media-link'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Copier un lien
            $('.sml-copy-link').on('click', function() {
                var $input = $(this).siblings('.sml-link-input');
                $input.select();
                document.execCommand('copy');
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('<?php _e('Copié !', 'secure-media-link'); ?>');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            });
            
            // Activer/Désactiver un lien
            $('.sml-activate-link, .sml-deactivate-link').on('click', function() {
                var linkId = $(this).data('link-id');
                var isActivate = $(this).hasClass('sml-activate-link');
                
                $.post(ajaxurl, {
                    action: 'sml_toggle_link_status',
                    nonce: sml_ajax.nonce,
                    link_id: linkId,
                    activate: isActivate
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Supprimer un lien
            $('.sml-delete-link').on('click', function() {
                if (!confirm('<?php _e('Supprimer ce lien définitivement ?', 'secure-media-link'); ?>')) {
                    return;
                }
                
                var linkId = $(this).data('link-id');
                
                $.post(ajaxurl, {
                    action: 'sml_delete_link',
                    nonce: sml_ajax.nonce,
                    link_id: linkId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Générer un nouveau lien
            $('#generate-new-link').on('click', function() {
                var mediaId = $(this).data('media-id');
                openGenerateLinksModal([mediaId]);
            });
        });
        </script>
        <?php
        
        $content = ob_get_clean();
        wp_send_json_success($content);
    }
    
    /**
     * AJAX - Charger la médiathèque
     */
    public static function ajax_load_media_library() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $tab = sanitize_text_field($_POST['tab']);
        $view = sanitize_text_field($_POST['view']);
        $page = intval($_POST['page']);
        $filters = $_POST['filters'];
        
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Construire la requête selon l'onglet
        global $wpdb;
        
        $where_clauses = array("p.post_type = 'attachment'");
        $join_clauses = array();
        $params = array();
        
        switch ($tab) {
            case 'new':
                $join_clauses[] = "LEFT JOIN {$wpdb->prefix}sml_frontend_uploads fu ON p.ID = fu.media_id";
                $where_clauses[] = "fu.status = 'pending'";
                break;
                
            case 'active':
                $join_clauses[] = "INNER JOIN {$wpdb->prefix}sml_secure_links sl ON p.ID = sl.media_id";
                $where_clauses[] = "sl.is_active = 1 AND sl.expires_at > NOW()";
                break;
                
            case 'inactive':
                $join_clauses[] = "LEFT JOIN {$wpdb->prefix}sml_secure_links sl ON p.ID = sl.media_id";
                $where_clauses[] = "sl.id IS NULL";
                break;
                
            case 'expired':
                $join_clauses[] = "INNER JOIN {$wpdb->prefix}sml_secure_links sl ON p.ID = sl.media_id";
                $where_clauses[] = "sl.expires_at <= NOW()";
                break;
        }
        
        // Filtres
        if (!empty($filters['search'])) {
            $where_clauses[] = "p.post_title LIKE %s";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['author'])) {
            $where_clauses[] = "p.post_author = %d";
            $params[] = $filters['author'];
        }
        
        $join_sql = implode(' ', $join_clauses);
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        // Compter le total
        $count_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$join_sql} {$where_sql}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = $wpdb->get_var($count_sql);
        
        // Récupérer les données
        $data_sql = "SELECT DISTINCT p.* FROM {$wpdb->posts} p {$join_sql} {$where_sql} 
                     ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $data_sql = $wpdb->prepare($data_sql, $params);
        $media_items = $wpdb->get_results($data_sql);
        
        // Générer le contenu
        ob_start();
        
        if ($view === 'grid') {
            echo '<div class="sml-media-grid">';
            foreach ($media_items as $media) {
                self::render_media_grid_item($media);
            }
            echo '</div>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th><input type="checkbox" id="select-all-media"></th>';
            echo '<th>' . __('Média', 'secure-media-link') . '</th>';
            echo '<th>' . __('Titre', 'secure-media-link') . '</th>';
            echo '<th>' . __('Statut', 'secure-media-link') . '</th>';
            echo '<th>' . __('Liens', 'secure-media-link') . '</th>';
            echo '<th>' . __('Téléchargements', 'secure-media-link') . '</th>';
            echo '<th>' . __('Date', 'secure-media-link') . '</th>';
            echo '<th>' . __('Actions', 'secure-media-link') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($media_items as $media) {
                self::render_media_list_item($media);
            }
            
            echo '</tbody></table>';
        }
        
        $content = ob_get_clean();
        
        // Générer la pagination
        $total_pages = ceil($total / $per_page);
        ob_start();
        
        if ($total_pages > 1) {
            echo '<div class="sml-pagination">';
            
            for ($i = 1; $i <= $total_pages; $i++) {
                $class = $i === $page ? 'button-primary' : 'button';
                echo '<button class="' . $class . ' sml-pagination-link" data-page="' . $i . '">' . $i . '</button>';
            }
            
            echo '</div>';
        }
        
        $pagination = ob_get_clean();
        
        wp_send_json_success(array(
            'content' => $content,
            'pagination' => $pagination,
            'total' => $total
        ));
    }
    
    /**
     * Rendre un élément de la grille
     */
    private static function render_media_grid_item($media) {
        $thumbnail = wp_get_attachment_image($media->ID, 'medium');
        $links_count = self::get_media_links_count($media->ID);
        $status = self::get_media_status($media->ID);
        
        ?>
        <div class="sml-media-item" data-media-id="<?php echo $media->ID; ?>">
            <div class="sml-media-checkbox-container">
                <input type="checkbox" class="sml-media-checkbox" value="<?php echo $media->ID; ?>">
            </div>
            
            <div class="sml-media-thumbnail">
                <?php echo $thumbnail; ?>
            </div>
            
            <div class="sml-media-info">
                <h4><?php echo esc_html($media->post_title); ?></h4>
                <p class="sml-media-meta">
                    <span class="sml-status sml-<?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                    <span class="sml-links-count"><?php echo $links_count; ?> <?php _e('liens', 'secure-media-link'); ?></span>
                </p>
            </div>
            
            <div class="sml-media-actions">
                <button class="button button-small sml-view-media" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
                <button class="button button-small sml-generate-links" data-media-id="<?php echo $media->ID; ?>">
                    <span class="dashicons dashicons-admin-links"></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Rendre un élément de la liste
     */
    private static function render_media_list_item($media) {
        $thumbnail = wp_get_attachment_image($media->ID, 'thumbnail');
        $links_count = self::get_media_links_count($media->ID);
        $status = self::get_media_status($media->ID);
        $downloads = self::get_media_downloads($media->ID);
        
        ?>
        <tr>
            <td><input type="checkbox" class="sml-media-checkbox" value="<?php echo $media->ID; ?>"></td>
            <td><?php echo $thumbnail; ?></td>
            <td><?php echo esc_html($media->post_title); ?></td>
            <td><span class="sml-status sml-<?php echo $status['class']; ?>"><?php echo $status['label']; ?></span></td>
            <td><?php echo $links_count; ?></td>
            <td><?php echo number_format($downloads); ?></td>
            <td><?php echo date_i18n(get_option('date_format'), strtotime($media->post_date)); ?></td>
            <td>
                <button class="button button-small sml-view-media" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('Voir', 'secure-media-link'); ?>
                </button>
                <button class="button button-small sml-generate-links" data-media-id="<?php echo $media->ID; ?>">
                    <?php _e('Liens', 'secure-media-link'); ?>
                </button>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Obtenir le nombre de liens pour un média
     */
    private static function get_media_links_count($media_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links WHERE media_id = %d",
            $media_id
        ));
    }
    
    /**
     * Obtenir le statut d'un média
     */
    private static function get_media_status($media_id) {
        global $wpdb;
        
        $active_links = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links 
             WHERE media_id = %d AND is_active = 1 AND expires_at > NOW()",
            $media_id
        ));
        
        $expired_links = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_secure_links 
             WHERE media_id = %d AND expires_at <= NOW()",
            $media_id
        ));
        
        if ($active_links > 0) {
            return array('class' => 'active', 'label' => __('Actif', 'secure-media-link'));
        } elseif ($expired_links > 0) {
            return array('class' => 'expired', 'label' => __('Expiré', 'secure-media-link'));
        } else {
            return array('class' => 'inactive', 'label' => __('Inactif', 'secure-media-link'));
        }
    }
    
    /**
     * Obtenir le nombre de téléchargements pour un média
     */
    private static function get_media_downloads($media_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(download_count) FROM {$wpdb->prefix}sml_secure_links WHERE media_id = %d",
            $media_id
        )) ?: 0;
    }
    
    /**
     * AJAX - Actions groupées sur les médias
     */
    public static function ajax_bulk_media_action() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $media_ids = array_map('intval', $_POST['media_ids']);
        
        if (empty($media_ids)) {
            wp_send_json_error(__('Aucun média sélectionné', 'secure-media-link'));
        }
        
        $processed = 0;
        global $wpdb;
        
        switch ($action) {
            case 'activate_links':
                foreach ($media_ids as $media_id) {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'sml_secure_links',
                        array('is_active' => 1),
                        array('media_id' => $media_id)
                    );
                    if ($result !== false) $processed++;
                }
                $message = sprintf(_n('%d lien activé', '%d liens activés', $processed, 'secure-media-link'), $processed);
                break;
                
            case 'deactivate_links':
                foreach ($media_ids as $media_id) {
                    $result = $wpdb->update(
                        $wpdb->prefix . 'sml_secure_links',
                        array('is_active' => 0),
                        array('media_id' => $media_id)
                    );
                    if ($result !== false) $processed++;
                }
                $message = sprintf(_n('%d lien désactivé', '%d liens désactivés', $processed, 'secure-media-link'), $processed);
                break;
                
            case 'delete_links':
                foreach ($media_ids as $media_id) {
                    $result = $wpdb->delete(
                        $wpdb->prefix . 'sml_secure_links',
                        array('media_id' => $media_id)
                    );
                    if ($result !== false) $processed++;
                }
                $message = sprintf(_n('%d lien supprimé', '%d liens supprimés', $processed, 'secure-media-link'), $processed);
                break;
                
            default:
                wp_send_json_error(__('Action non reconnue', 'secure-media-link'));
        }
        
        wp_send_json_success(array('message' => $message, 'processed' => $processed));
    }
    
    /**
     * AJAX - Générer des liens en masse
     */
    public static function ajax_generate_bulk_links() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $media_ids = array_map('intval', $_POST['media_ids']);
        $format_ids = array_map('intval', $_POST['formats']);
        $expiry_date = sanitize_text_field($_POST['expiry_date']);
        
        if (empty($media_ids) || empty($format_ids)) {
            wp_send_json_error(__('Paramètres insuffisants', 'secure-media-link'));
        }
        
        $generated = 0;
        $errors = array();
        
        foreach ($media_ids as $media_id) {
            foreach ($format_ids as $format_id) {
                $link = SML_Crypto::generate_secure_link($media_id, $format_id, $expiry_date);
                
                if ($link) {
                    $generated++;
                } else {
                    $errors[] = sprintf(__('Erreur pour le média %d, format %d', 'secure-media-link'), $media_id, $format_id);
                }
            }
        }
        
        $message = sprintf(_n('%d lien généré', '%d liens générés', $generated, 'secure-media-link'), $generated);
        
        if (!empty($errors)) {
            $message .= ' ' . __('Erreurs:', 'secure-media-link') . ' ' . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= sprintf(__(' et %d autres...', 'secure-media-link'), count($errors) - 3);
            }
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'generated' => $generated,
            'errors' => count($errors)
        ));
    }
    
    /**
     * AJAX - Obtenir un format
     */
    public static function ajax_get_format() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $format_id = intval($_POST['format_id']);
        $format = SML_Media_Formats::get_format($format_id);
        
        if ($format) {
            wp_send_json_success($format);
        } else {
            wp_send_json_error(__('Format introuvable', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Exporter les formats
     */
    public static function ajax_export_formats() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $export_data = SML_Media_Formats::export_formats();
        
        wp_send_json_success($export_data);
    }
    
    /**
     * AJAX - Actions de maintenance
     */
    public static function ajax_clear_cache() {
        check_ajax_referer('sml_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        wp_cache_flush();
        SML_Cache::flush_all();
        
        wp_send_json_success();
    }
    
    public static function ajax_optimize_database() {
        check_ajax_referer('sml_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        SML_Database::optimize_tables();
        
        wp_send_json_success();
    }
    
    public static function ajax_cleanup_old_data() {
        check_ajax_referer('sml_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $deleted = SML_Tracking::cleanup_old_tracking(365);
        SML_Database::cleanup_old_data(365);
        
        wp_send_json_success(array('deleted' => $deleted));
    }
}

// Enregistrer les actions AJAX
add_action('wp_ajax_sml_get_media_details', array('SML_Admin', 'ajax_get_media_details'));
add_action('wp_ajax_sml_load_media_library', array('SML_Admin', 'ajax_load_media_library'));
add_action('wp_ajax_sml_bulk_media_action', array('SML_Admin', 'ajax_bulk_media_action'));
add_action('wp_ajax_sml_generate_bulk_links', array('SML_Admin', 'ajax_generate_bulk_links'));
add_action('wp_ajax_sml_get_format', array('SML_Admin', 'ajax_get_format'));
add_action('wp_ajax_sml_export_formats', array('SML_Admin', 'ajax_export_formats'));
add_action('wp_ajax_sml_clear_cache', array('SML_Admin', 'ajax_clear_cache'));
add_action('wp_ajax_sml_optimize_database', array('SML_Admin', 'ajax_optimize_database'));
add_action('wp_ajax_sml_cleanup_old_data', array('SML_Admin', 'ajax_cleanup_old_data'));