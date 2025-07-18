/**
 * Scripts d'administration pour Secure Media Link
 * assets/js/admin.js
 */

(function($) {
    'use strict';
    
    /**
     * Objet principal SML Admin
     */
    const SMLAdmin = {
        
        // Configuration
        config: {
            ajaxUrl: sml_ajax.ajax_url,
            nonce: sml_ajax.nonce,
            strings: sml_ajax.strings,
            charts: {},
            modals: {},
            currentPage: 1,
            itemsPerPage: 20
        },
        
        /**
         * Initialisation
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.loadNotifications();
            
            // Auto-refresh pour les données en temps réel
            this.startAutoRefresh();
            
            console.log('SML Admin initialized');
        },
        
        /**
         * Liaison des événements
         */
        bindEvents: function() {
            // Navigation et onglets
            $(document).on('click', '.nav-tab', this.handleTabSwitch);
            $(document).on('click', '.sml-view-btn', this.handleViewSwitch);
            
            // Modales
            $(document).on('click', '.sml-modal-close', this.closeModal);
            $(document).on('click', '.sml-modal', this.closeModalOnOverlay);
            $(document).on('keyup', this.handleEscapeKey);
            
            // Formulaires
            $(document).on('submit', '.sml-ajax-form', this.handleAjaxForm);
            $(document).on('click', '.sml-copy-link', this.copyToClipboard);
            
            // Actions de médias
            $(document).on('click', '.sml-view-media', this.viewMediaDetails);
            $(document).on('click', '.sml-generate-links', this.generateMediaLinks);
            $(document).on('click', '.sml-delete-media-link', this.deleteMediaLink);
            
            // Permissions
            $(document).on('click', '.sml-block-ip', this.blockIP);
            $(document).on('click', '.sml-block-domain', this.blockDomain);
            $(document).on('click', '.apply-suggestion', this.applySuggestion);
            
            // Filtres et recherche
            $(document).on('input', '.sml-search-input', this.debounce(this.handleSearch, 500));
            $(document).on('change', '.sml-filter-select', this.handleFilter);
            $(document).on('click', '.sml-apply-filters', this.applyFilters);
            $(document).on('click', '.sml-reset-filters', this.resetFilters);
            
            // Pagination
            $(document).on('click', '.sml-pagination-link', this.handlePagination);
            
            // Sélection multiple
            $(document).on('change', '#select-all-media', this.selectAllMedia);
            $(document).on('change', '.sml-media-checkbox', this.updateSelectionCount);
            $(document).on('click', '.sml-bulk-action-btn', this.handleBulkAction);
            
            // Notifications
            $(document).on('click', '.sml-notification-mark-read', this.markNotificationRead);
            $(document).on('click', '.sml-notification-delete', this.deleteNotification);
            
            // Actions rapides
            $(document).on('click', '.sml-quick-action', this.handleQuickAction);
            
            // Cache et maintenance
            $(document).on('click', '#sml-clear-cache', this.clearCache);
            $(document).on('click', '#sml-optimize-db', this.optimizeDatabase);
            $(document).on('click', '#sml-manual-scan', this.manualScan);
        },
        
        /**
         * Initialisation des composants
         */
        initComponents: function() {
            // Initialiser les tooltips
            this.initTooltips();
            
            // Initialiser les graphiques si Chart.js est disponible
            if (typeof Chart !== 'undefined') {
                this.initCharts();
            }
            
            // Initialiser les widgets du dashboard
            this.initDashboardWidgets();
            
            // Initialiser les sliders de qualité
            this.initQualitySliders();
            
            // Initialiser les date pickers
            this.initDatePickers();
            
            // Initialiser les dropdowns
            this.initDropdowns();
        },
        
        /**
         * Gestion des onglets
         */
        handleTabSwitch: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.attr('href') || $tab.data('target');
            
            // Mettre à jour l'état actif
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Afficher le contenu correspondant
            $('.tab-pane').removeClass('active');
            $(target).addClass('active');
            
            // Charger le contenu dynamique si nécessaire
            SMLAdmin.loadTabContent(target);
        },
        
        /**
         * Charger le contenu d'un onglet
         */
        loadTabContent: function(target) {
            const $content = $(target);
            
            if ($content.hasClass('sml-lazy-load') && !$content.hasClass('loaded')) {
                const action = $content.data('action');
                
                if (action) {
                    $content.html('<div class="sml-loading"><span class="spinner is-active"></span> Chargement...</div>');
                    
                    this.ajaxRequest(action, {}, function(response) {
                        if (response.success) {
                            $content.html(response.data).addClass('loaded');
                        } else {
                            $content.html('<div class="notice notice-error"><p>Erreur lors du chargement.</p></div>');
                        }
                    });
                }
            }
        },
        
        /**
         * Gestion du changement de vue (grille/liste)
         */
        handleViewSwitch: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const view = $btn.data('view');
            
            $('.sml-view-btn').removeClass('active');
            $btn.addClass('active');
            
            // Recharger le contenu avec la nouvelle vue
            SMLAdmin.loadMediaLibrary({ view: view });
        },
        
        /**
         * Charger la médiathèque
         */
        loadMediaLibrary: function(params = {}) {
            const defaultParams = {
                action: 'sml_load_media_library',
                nonce: this.config.nonce,
                tab: 'all',
                view: 'grid',
                page: this.config.currentPage,
                filters: this.getActiveFilters()
            };
            
            const requestParams = $.extend(defaultParams, params);
            
            $('#sml-media-content').html('<div class="sml-loading"><span class="spinner is-active"></span> Chargement...</div>');
            
            this.ajaxRequest('sml_load_media_library', requestParams, function(response) {
                if (response.success) {
                    $('#sml-media-content').html(response.data.content);
                    $('#sml-pagination').html(response.data.pagination);
                    SMLAdmin.updateSelectionCount();
                } else {
                    $('#sml-media-content').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            });
        },
        
        /**
         * Voir les détails d'un média
         */
        viewMediaDetails: function(e) {
            e.preventDefault();
            
            const mediaId = $(this).data('media-id');
            
            SMLAdmin.openModal('sml-media-modal');
            $('#sml-media-details').html('<div class="sml-loading"><span class="spinner is-active"></span> Chargement...</div>');
            
            SMLAdmin.ajaxRequest('sml_get_media_details', {
                media_id: mediaId
            }, function(response) {
                if (response.success) {
                    $('#sml-media-details').html(response.data);
                } else {
                    $('#sml-media-details').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            });
        },
        
        /**
         * Générer des liens pour un média
         */
        generateMediaLinks: function(e) {
            e.preventDefault();
            
            const mediaIds = [$(this).data('media-id')];
            SMLAdmin.openGenerateLinksModal(mediaIds);
        },
        
        /**
         * Ouvrir la modal de génération de liens
         */
        openGenerateLinksModal: function(mediaIds) {
            this.openModal('sml-generate-links-modal');
            $('#sml-generate-links-form').data('media-ids', mediaIds);
            
            // Réinitialiser le formulaire
            $('#sml-generate-links-form')[0].reset();
            $('input[name="formats[]"]').prop('checked', false);
            
            // Cocher les formats web par défaut
            $('input[name="formats[]"][value*="web"]').prop('checked', true);
        },
        
        /**
         * Gestion des formulaires AJAX
         */
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const action = $form.data('action') || $form.find('input[name="action"]').val();
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Désactiver le bouton
            $submitBtn.prop('disabled', true);
            const originalText = $submitBtn.text();
            $submitBtn.text('Traitement...');
            
            // Collecter les données
            const formData = $form.serialize();
            
            SMLAdmin.ajaxRequest(action, formData, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', response.data.message || 'Opération réussie');
                    
                    // Fermer la modal si applicable
                    $('.sml-modal').hide();
                    
                    // Recharger les données si nécessaire
                    if ($form.hasClass('sml-reload-after')) {
                        location.reload();
                    }
                } else {
                    const message = response.data.message || response.data || 'Une erreur est survenue';
                    SMLAdmin.showNotice('error', message);
                }
            }, function() {
                // Réactiver le bouton en cas d'erreur
                $submitBtn.prop('disabled', false).text(originalText);
            });
        },
        
        /**
         * Copier vers le presse-papiers
         */
        copyToClipboard: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const text = $btn.data('text') || $btn.siblings('input').val();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    SMLAdmin.showCopyFeedback($btn);
                });
            } else {
                // Fallback pour les navigateurs plus anciens
                const $temp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                SMLAdmin.showCopyFeedback($btn);
            }
        },
        
        /**
         * Afficher le feedback de copie
         */
        showCopyFeedback: function($btn) {
            const originalText = $btn.text();
            $btn.text(this.config.strings.link_copied || 'Copié !');
            
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        },
        
        /**
         * Bloquer une IP
         */
        blockIP: function(e) {
            e.preventDefault();
            
            const ip = $(this).data('ip');
            
            if (!confirm('Bloquer cette adresse IP : ' + ip + ' ?')) {
                return;
            }
            
            SMLAdmin.ajaxRequest('sml_add_permission', {
                type: 'ip',
                value: ip,
                permission_type: 'blacklist',
                actions: ['download', 'copy', 'view'],
                description: 'Bloqué depuis le tableau de bord',
                is_active: 1
            }, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', 'IP bloquée avec succès');
                    location.reload();
                } else {
                    SMLAdmin.showNotice('error', response.data.message);
                }
            });
        },
        
        /**
         * Bloquer un domaine
         */
        blockDomain: function(e) {
            e.preventDefault();
            
            const domain = $(this).data('domain');
            
            if (!confirm('Bloquer ce domaine : ' + domain + ' ?')) {
                return;
            }
            
            SMLAdmin.ajaxRequest('sml_add_permission', {
                type: 'domain',
                value: domain,
                permission_type: 'blacklist',
                actions: ['download', 'copy', 'view'],
                description: 'Bloqué depuis le tableau de bord',
                is_active: 1
            }, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', 'Domaine bloqué avec succès');
                    location.reload();
                } else {
                    SMLAdmin.showNotice('error', response.data.message);
                }
            });
        },
        
        /**
         * Appliquer une suggestion de sécurité
         */
        applySuggestion: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const suggestion = $btn.data('suggestion');
            
            $btn.prop('disabled', true).text('Application...');
            
            SMLAdmin.ajaxRequest('sml_apply_suggestion', {
                suggestion: suggestion
            }, function(response) {
                if (response.success) {
                    $btn.closest('.sml-suggestion-item').fadeOut();
                    SMLAdmin.showNotice('success', 'Suggestion appliquée avec succès');
                } else {
                    SMLAdmin.showNotice('error', response.data);
                    $btn.prop('disabled', false).text('Appliquer');
                }
            });
        },
        
        /**
         * Gestion de la recherche avec debounce
         */
        handleSearch: function() {
            const query = $(this).val();
            SMLAdmin.config.currentPage = 1;
            SMLAdmin.loadMediaLibrary({ search: query });
        },
        
        /**
         * Gestion des filtres
         */
        handleFilter: function() {
            SMLAdmin.config.currentPage = 1;
            SMLAdmin.loadMediaLibrary();
        },
        
        /**
         * Appliquer les filtres
         */
        applyFilters: function(e) {
            e.preventDefault();
            SMLAdmin.config.currentPage = 1;
            SMLAdmin.loadMediaLibrary();
        },
        
        /**
         * Réinitialiser les filtres
         */
        resetFilters: function(e) {
            e.preventDefault();
            $('.sml-filter-select').val('');
            $('.sml-search-input').val('');
            SMLAdmin.config.currentPage = 1;
            SMLAdmin.loadMediaLibrary();
        },
        
        /**
         * Obtenir les filtres actifs
         */
        getActiveFilters: function() {
            const filters = {};
            
            $('.sml-filter-select').each(function() {
                const $filter = $(this);
                const name = $filter.attr('name') || $filter.attr('id');
                const value = $filter.val();
                
                if (value) {
                    filters[name] = value;
                }
            });
            
            const searchQuery = $('.sml-search-input').val();
            if (searchQuery) {
                filters.search = searchQuery;
            }
            
            return filters;
        },
        
        /**
         * Gestion de la pagination
         */
        handlePagination: function(e) {
            e.preventDefault();
            
            const page = $(this).data('page');
            SMLAdmin.config.currentPage = page;
            SMLAdmin.loadMediaLibrary({ page: page });
        },
        
        /**
         * Sélectionner tous les médias
         */
        selectAllMedia: function() {
            const isChecked = $(this).prop('checked');
            $('.sml-media-checkbox').prop('checked', isChecked);
            SMLAdmin.updateSelectionCount();
        },
        
        /**
         * Mettre à jour le compteur de sélection
         */
        updateSelectionCount: function() {
            const count = $('.sml-media-checkbox:checked').length;
            const $counter = $('#sml-selection-count');
            
            if (count > 0) {
                $counter.text(count + ' élément(s) sélectionné(s)').show();
                $('.sml-bulk-actions').show();
            } else {
                $counter.hide();
                $('.sml-bulk-actions').hide();
            }
        },
        
        /**
         * Gestion des actions groupées
         */
        handleBulkAction: function(e) {
            e.preventDefault();
            
            const action = $('#sml-bulk-action').val();
            const selected = [];
            
            $('.sml-media-checkbox:checked').each(function() {
                selected.push($(this).val());
            });
            
            if (!action || selected.length === 0) {
                SMLAdmin.showNotice('warning', 'Veuillez sélectionner une action et des éléments');
                return;
            }
            
            if (action === 'generate_links') {
                SMLAdmin.openGenerateLinksModal(selected);
            } else {
                SMLAdmin.performBulkAction(action, selected);
            }
        },
        
        /**
         * Exécuter une action groupée
         */
        performBulkAction: function(action, mediaIds) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action sur ' + mediaIds.length + ' élément(s) ?')) {
                return;
            }
            
            SMLAdmin.ajaxRequest('sml_bulk_media_action', {
                bulk_action: action,
                media_ids: mediaIds
            }, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', response.data.message);
                    SMLAdmin.loadMediaLibrary();
                } else {
                    SMLAdmin.showNotice('error', response.data);
                }
            });
        },
        
        /**
         * Marquer une notification comme lue
         */
        markNotificationRead: function(e) {
            e.preventDefault();
            
            const notificationId = $(this).data('notification-id');
            const $notification = $(this).closest('.sml-notification-item');
            
            SMLAdmin.ajaxRequest('sml_mark_notification_read', {
                notification_id: notificationId
            }, function(response) {
                if (response.success) {
                    $notification.addClass('read');
                    SMLAdmin.updateNotificationCount();
                }
            });
        },
        
        /**
         * Supprimer une notification
         */
        deleteNotification: function(e) {
            e.preventDefault();
            
            const notificationId = $(this).data('notification-id');
            const $notification = $(this).closest('.sml-notification-item');
            
            SMLAdmin.ajaxRequest('sml_delete_notification', {
                notification_id: notificationId
            }, function(response) {
                if (response.success) {
                    $notification.fadeOut();
                    SMLAdmin.updateNotificationCount();
                }
            });
        },
        
        /**
         * Charger les notifications
         */
        loadNotifications: function() {
            if ($('#sml-notifications-panel').length === 0) {
                return;
            }
            
            SMLAdmin.ajaxRequest('sml_get_notifications', {
                limit: 10,
                unread_only: false
            }, function(response) {
                if (response.success) {
                    SMLAdmin.renderNotifications(response.data.notifications);
                    SMLAdmin.updateNotificationCount(response.data.unread_count);
                }
            });
        },
        
        /**
         * Afficher les notifications
         */
        renderNotifications: function(notifications) {
            const $container = $('#sml-notifications-list');
            $container.empty();
            
            if (notifications.length === 0) {
                $container.html('<div class="sml-no-notifications">Aucune notification</div>');
                return;
            }
            
            notifications.forEach(function(notification) {
                const $item = $('<div class="sml-notification-item ' + (notification.is_read ? 'read' : 'unread') + '">')
                    .html(`
                        <div class="sml-notification-content">
                            <h4>${notification.title}</h4>
                            <p>${notification.message}</p>
                            <span class="sml-notification-date">${notification.formatted_date}</span>
                        </div>
                        <div class="sml-notification-actions">
                            ${!notification.is_read ? '<button class="sml-notification-mark-read" data-notification-id="' + notification.id + '">Marquer comme lu</button>' : ''}
                            <button class="sml-notification-delete" data-notification-id="${notification.id}">Supprimer</button>
                        </div>
                    `);
                
                $container.append($item);
            });
        },
        
        /**
         * Mettre à jour le compteur de notifications
         */
        updateNotificationCount: function(count) {
            if (count === undefined) {
                // Recompter
                count = $('.sml-notification-item.unread').length;
            }
            
            const $badge = $('.sml-notification-badge');
            
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        },
        
        /**
         * Actions rapides
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            
            const action = $(this).data('action');
            const $btn = $(this);
            
            $btn.prop('disabled', true);
            
            switch (action) {
                case 'clear_cache':
                    SMLAdmin.clearCache();
                    break;
                case 'optimize_db':
                    SMLAdmin.optimizeDatabase();
                    break;
                case 'manual_scan':
                    SMLAdmin.manualScan();
                    break;
                default:
                    $btn.prop('disabled', false);
            }
        },
        
        /**
         * Vider le cache
         */
        clearCache: function() {
            const $btn = $('#sml-clear-cache');
            $btn.prop('disabled', true).text('Nettoyage...');
            
            SMLAdmin.ajaxRequest('sml_clear_cache', {}, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', 'Cache vidé avec succès');
                } else {
                    SMLAdmin.showNotice('error', 'Erreur lors du nettoyage du cache');
                }
                $btn.prop('disabled', false).text('Vider le cache');
            });
        },
        
        /**
         * Optimiser la base de données
         */
        optimizeDatabase: function() {
            if (!confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) {
                $('#sml-optimize-db').prop('disabled', false);
                return;
            }
            
            const $btn = $('#sml-optimize-db');
            $btn.prop('disabled', true).text('Optimisation...');
            
            SMLAdmin.ajaxRequest('sml_optimize_database', {}, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', 'Base de données optimisée avec succès');
                } else {
                    SMLAdmin.showNotice('error', 'Erreur lors de l\'optimisation');
                }
                $btn.prop('disabled', false).text('Optimiser la base de données');
            });
        },
        
        /**
         * Scan manuel
         */
        manualScan: function() {
            const $btn = $('#sml-manual-scan');
            $btn.prop('disabled', true).text('Scan en cours...');
            
            SMLAdmin.ajaxRequest('sml_manual_scan', {}, function(response) {
                if (response.success) {
                    SMLAdmin.showNotice('success', 'Scan terminé avec succès');
                } else {
                    SMLAdmin.showNotice('error', 'Erreur lors du scan');
                }
                $btn.prop('disabled', false).text('Scanner l\'utilisation externe');
            });
        },
        
        /**
         * Initialiser les graphiques
         */
        initCharts: function() {
            // Graphique d'activité du dashboard
            if ($('#sml-activity-chart').length > 0) {
                this.initActivityChart();
            }
            
            // Graphique des actions
            if ($('#sml-actions-chart').length > 0) {
                this.initActionsChart();
            }
            
            // Graphiques de tracking
            if ($('#requests-chart').length > 0) {
                this.initRequestsChart();
            }
            
            if ($('#geo-chart').length > 0) {
                this.initGeoChart();
            }
        },
        
        /**
         * Graphique d'activité
         */
        initActivityChart: function() {
            const ctx = document.getElementById('sml-activity-chart').getContext('2d');
            
            this.config.charts.activity = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Total',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Autorisés',
                        data: [],
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Bloqués',
                        data: [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleColor: 'white',
                            bodyColor: 'white'
                        }
                    }
                }
            });
            
            // Charger les données
            this.loadChartData('activity', 'requests', 'month');
        },
        
        /**
         * Graphique des actions (doughnut)
         */
        initActionsChart: function() {
            const ctx = document.getElementById('sml-actions-chart').getContext('2d');
            
            this.config.charts.actions = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Téléchargements', 'Copies', 'Vues'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleColor: 'white',
                            bodyColor: 'white'
                        }
                    }
                }
            });
            
            // Charger les données
            this.loadChartData('actions', 'actions', 'month');
        },
        
        /**
         * Charger les données des graphiques
         */
        loadChartData: function(chartName, type, period) {
            SMLAdmin.ajaxRequest('sml_get_chart_data', {
                type: type,
                period: period
            }, function(response) {
                if (response.success && SMLAdmin.config.charts[chartName]) {
                    SMLAdmin.updateChartData(chartName, response.data);
                }
            });
        },
        
        /**
         * Mettre à jour les données d'un graphique
         */
        updateChartData: function(chartName, data) {
            const chart = this.config.charts[chartName];
            
            if (!chart) return;
            
            switch (chartName) {
                case 'activity':
                    chart.data.labels = data.map(item => item.date);
                    chart.data.datasets[0].data = data.map(item => item.total || 0);
                    chart.data.datasets[1].data = data.map(item => item.authorized || 0);
                    chart.data.datasets[2].data = data.map(item => item.blocked || 0);
                    break;
                    
                case 'actions':
                    if (data.downloads !== undefined) {
                        chart.data.datasets[0].data = [
                            data.downloads || 0,
                            data.copies || 0,
                            data.views || 0
                        ];
                    }
                    break;
            }
            
            chart.update('active');
        },
        
        /**
         * Initialiser les tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const text = $element.data('tooltip');
                
                $element.attr('title', text).tooltip({
                    position: { my: "left+15 center", at: "right center" },
                    show: { duration: 200 },
                    hide: { duration: 100 }
                });
            });
        },
        
        /**
         * Initialiser les widgets du dashboard
         */
        initDashboardWidgets: function() {
            // Widget de statistiques en temps réel
            if ($('.sml-live-stats').length > 0) {
                this.initLiveStats();
            }
            
            // Widget des violations récentes
            if ($('.sml-recent-violations').length > 0) {
                this.initRecentViolations();
            }
            
            // Widget de monitoring système
            if ($('.sml-system-status').length > 0) {
                this.initSystemStatus();
            }
        },
        
        /**
         * Statistiques en temps réel
         */
        initLiveStats: function() {
            const updateStats = function() {
                SMLAdmin.ajaxRequest('sml_get_live_stats', {}, function(response) {
                    if (response.success) {
                        $('.sml-stat-number').each(function() {
                            const $stat = $(this);
                            const key = $stat.data('stat');
                            
                            if (response.data[key] !== undefined) {
                                SMLAdmin.animateNumber($stat, response.data[key]);
                            }
                        });
                    }
                });
            };
            
            // Mettre à jour toutes les 30 secondes
            setInterval(updateStats, 30000);
        },
        
        /**
         * Animer un nombre
         */
        animateNumber: function($element, newValue) {
            const currentValue = parseInt($element.text().replace(/,/g, '')) || 0;
            
            if (currentValue === newValue) return;
            
            $({ counter: currentValue }).animate({ counter: newValue }, {
                duration: 1000,
                easing: 'swing',
                step: function() {
                    $element.text(Math.floor(this.counter).toLocaleString());
                },
                complete: function() {
                    $element.text(newValue.toLocaleString());
                }
            });
        },
        
        /**
         * Initialiser les sliders de qualité
         */
        initQualitySliders: function() {
            $('.sml-quality-slider').on('input', function() {
                const value = $(this).val();
                $(this).siblings('.sml-quality-value').text(value + '%');
            });
        },
        
        /**
         * Initialiser les date pickers
         */
        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('.sml-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        /**
         * Initialiser les dropdowns
         */
        initDropdowns: function() {
            $(document).on('click', '.sml-dropdown-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $dropdown = $(this).closest('.sml-dropdown');
                
                // Fermer tous les autres dropdowns
                $('.sml-dropdown').not($dropdown).removeClass('sml-open');
                
                // Toggle le dropdown actuel
                $dropdown.toggleClass('sml-open');
            });
            
            // Fermer les dropdowns en cliquant ailleurs
            $(document).on('click', function() {
                $('.sml-dropdown').removeClass('sml-open');
            });
        },
        
        /**
         * Auto-refresh pour les données temps réel
         */
        startAutoRefresh: function() {
            // Actualiser les notifications toutes les 2 minutes
            setInterval(function() {
                if ($('#sml-notifications-panel:visible').length > 0) {
                    SMLAdmin.loadNotifications();
                }
            }, 120000);
            
            // Actualiser les statistiques du dashboard toutes les 5 minutes
            setInterval(function() {
                if ($('.sml-dashboard-stats:visible').length > 0) {
                    SMLAdmin.refreshDashboardStats();
                }
            }, 300000);
        },
        
        /**
         * Actualiser les statistiques du dashboard
         */
        refreshDashboardStats: function() {
            SMLAdmin.ajaxRequest('sml_get_dashboard_stats', {}, function(response) {
                if (response.success) {
                    const stats = response.data;
                    
                    // Mettre à jour les cartes de statistiques
                    Object.keys(stats).forEach(function(key) {
                        const $element = $('[data-stat="' + key + '"]');
                        if ($element.length > 0) {
                            SMLAdmin.animateNumber($element, stats[key]);
                        }
                    });
                }
            });
        },
        
        /**
         * Gestion des modales
         */
        openModal: function(modalId) {
            $('#' + modalId).show().addClass('sml-modal-open');
            $('body').addClass('sml-modal-active');
            
            // Focus sur le premier élément focusable
            setTimeout(function() {
                $('#' + modalId).find('input, select, textarea, button').first().focus();
            }, 100);
        },
        
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            $('.sml-modal').hide().removeClass('sml-modal-open');
            $('body').removeClass('sml-modal-active');
        },
        
        closeModalOnOverlay: function(e) {
            if (e.target === this) {
                SMLAdmin.closeModal();
            }
        },
        
        handleEscapeKey: function(e) {
            if (e.keyCode === 27 && $('.sml-modal-open').length > 0) {
                SMLAdmin.closeModal();
            }
        },
        
        /**
         * Afficher une notice
         */
        showNotice: function(type, message, duration = 5000) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible sml-notice">')
                .html('<p>' + message + '</p>')
                .hide();
            
            // Ajouter le bouton de fermeture
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Insérer dans la page
            if ($('.wrap .notice').length > 0) {
                $('.wrap .notice').first().before($notice);
            } else {
                $('.wrap h1').after($notice);
            }
            
            $notice.slideDown();
            
            // Auto-dismiss
            if (duration > 0) {
                setTimeout(function() {
                    $notice.slideUp(function() {
                        $(this).remove();
                    });
                }, duration);
            }
            
            // Gestion du bouton de fermeture
            $notice.on('click', '.notice-dismiss', function() {
                $notice.slideUp(function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Fonction utilitaire debounce
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = function() {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * Requête AJAX standardisée
         */
        ajaxRequest: function(action, data, successCallback, errorCallback) {
            const requestData = {
                action: action,
                nonce: this.config.nonce
            };
            
            // Merger les données
            if (typeof data === 'string') {
                // Si c'est une chaîne sérialisée, la parser
                const params = new URLSearchParams(data);
                params.forEach((value, key) => {
                    requestData[key] = value;
                });
            } else if (typeof data === 'object') {
                Object.assign(requestData, data);
            }
            
            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    
                    if (typeof errorCallback === 'function') {
                        errorCallback(xhr, status, error);
                    } else {
                        SMLAdmin.showNotice('error', 'Erreur de communication avec le serveur');
                    }
                }
            });
        },
        
        /**
         * Utilitaires de formatage
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        
        /**
         * Validation côté client
         */
        validateForm: function($form) {
            let isValid = true;
            const errors = [];
            
            // Validation des champs requis
            $form.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    $field.addClass('error');
                    errors.push($field.attr('name') + ' est requis');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validation des emails
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (value && !SMLAdmin.isValidEmail(value)) {
                    isValid = false;
                    $field.addClass('error');
                    errors.push('Email invalide');
                }
            });
            
            // Validation des URLs
            $form.find('input[type="url"]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (value && !SMLAdmin.isValidUrl(value)) {
                    isValid = false;
                    $field.addClass('error');
                    errors.push('URL invalide');
                }
            });
            
            return { isValid: isValid, errors: errors };
        },
        
        isValidEmail: function(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        },
        
        /**
         * Gestionnaire d'erreurs global
         */
        handleError: function(error, context = '') {
            console.error('SML Error' + (context ? ' in ' + context : '') + ':', error);
            
            // Log l'erreur côté serveur si possible
            this.ajaxRequest('sml_log_js_error', {
                error: error.toString(),
                context: context,
                url: window.location.href,
                user_agent: navigator.userAgent
            });
        }
    };
    
    /**
     * Extensions jQuery personnalisées
     */
    $.fn.smlLoader = function(show = true) {
        return this.each(function() {
            const $element = $(this);
            
            if (show) {
                $element.addClass('sml-loading').append('<div class="sml-loader"><span class="spinner is-active"></span></div>');
            } else {
                $element.removeClass('sml-loading').find('.sml-loader').remove();
            }
        });
    };
    
    $.fn.smlHighlight = function(duration = 2000) {
        return this.each(function() {
            $(this).addClass('sml-highlight');
            setTimeout(() => {
                $(this).removeClass('sml-highlight');
            }, duration);
        });
    };
    
    /**
     * Gestionnaire d'erreurs JavaScript global
     */
    window.onerror = function(message, source, lineno, colno, error) {
        SMLAdmin.handleError(error || message, 'Global Error Handler');
        return false; // Ne pas empêcher le comportement par défaut
    };
    
    /**
     * Gestionnaire pour les promesses rejetées
     */
    window.addEventListener('unhandledrejection', function(event) {
        SMLAdmin.handleError(event.reason, 'Unhandled Promise Rejection');
    });
    
    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        try {
            SMLAdmin.init();
        } catch (error) {
            SMLAdmin.handleError(error, 'Initialization');
        }
    });
    
    // Exposer SMLAdmin globalement pour le debugging
    window.SMLAdmin = SMLAdmin;
    
})(jQuery);