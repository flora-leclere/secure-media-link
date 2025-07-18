/**
 * Scripts frontend pour Secure Media Link
 * assets/js/frontend.js
 */

(function($) {
    'use strict';
    
    /**
     * Objet principal SML Frontend
     */
    const SMLFrontend = {
        
        // Configuration
        config: {
            ajaxUrl: sml_ajax.ajax_url,
            nonce: sml_ajax.nonce,
            strings: sml_ajax.strings || {},
            currentUploads: [],
            maxRetries: 3,
            retryDelay: 1000,
            chunkSize: 1024 * 1024, // 1MB chunks pour gros fichiers
            allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            maxFileSize: 10 * 1024 * 1024 // 10MB par défaut
        },
        
        /**
         * Initialisation
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.loadStoredData();
            
            console.log('SML Frontend initialized');
        },
        
        /**
         * Liaison des événements
         */
        bindEvents: function() {
            // Upload de fichiers
            $(document).on('change', '#sml-upload-files', this.handleFileSelection);
            $(document).on('submit', '#sml-upload-form', this.handleFormSubmit);
            $(document).on('click', '.sml-remove-file', this.removePreviewFile);
            
            // Actions sur les médias
            $(document).on('click', '.sml-view-links', this.viewMediaLinks);
            $(document).on('click', '.sml-download-single', this.handleSingleDownload);
            $(document).on('click', '.sml-download-format', this.handleFormatDownload);
            $(document).on('click', '.sml-copy-links', this.copyMediaLinks);
            $(document).on('click', '.sml-copy-button', this.copyToClipboard);
            
            // Gestion des dropdowns
            $(document).on('click', '.sml-dropdown-toggle', this.toggleDropdown);
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sml-dropdown').length) {
                    $('.sml-dropdown').removeClass('sml-open');
                }
            });
            
            // Lightbox
            $(document).on('click', '.sml-lightbox-trigger', this.openLightbox);
            $(document).on('click', '.sml-lightbox-close, .sml-lightbox-overlay', this.closeLightbox);
            $(document).on('keyup', this.handleEscapeKey);
            
            // Modales
            $(document).on('click', '.sml-modal-close', this.closeModal);
            $(document).on('click', '.sml-modal', this.closeModalOnOverlay);
            
            // Charger plus
            $(document).on('click', '#sml-load-more-media', this.loadMoreMedia);
            
            // Tracking automatique
            $(document).on('click', 'a[href*="/sml/"]', this.trackLinkClick);
            
            // Gestion du drag & drop
            $(document).on('dragover', '.sml-upload-form', this.handleDragOver);
            $(document).on('dragleave', '.sml-upload-form', this.handleDragLeave);
            $(document).on('drop', '.sml-upload-form', this.handleFileDrop);
            
            // Gestion de la progression
            $(document).on('click', '.sml-cancel-upload', this.cancelUpload);
            
            // Stats en temps réel
            if ($('.sml-media-stats').length > 0) {
                setInterval(this.updateRealTimeStats.bind(this), 30000); // Toutes les 30 secondes
            }
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
            
            // Initialiser la géolocalisation
            this.initGeolocation();
            
            // Initialiser les notifications push
            this.initPushNotifications();
            
            // Initialiser le cache client
            this.initClientCache();
        },
        
        /**
         * Gestion de la sélection de fichiers
         */
        handleFileSelection: function(e) {
            const files = e.target.files;
            const $preview = $('#sml-upload-preview');
            const maxFiles = parseInt($('#sml-upload-form input[name="max_files"]').val()) || 10;
            const maxSize = parseInt($('#sml-upload-form input[name="max_size"]').val()) || SMLFrontend.config.maxFileSize;
            
            // Vider la prévisualisation précédente
            $preview.empty();
            SMLFrontend.config.currentUploads = [];
            
            if (files.length > maxFiles) {
                SMLFrontend.showMessage('error', `Maximum ${maxFiles} fichiers autorisés`);
                return;
            }
            
            // Traiter chaque fichier
            Array.from(files).forEach(function(file, index) {
                if (!SMLFrontend.validateFile(file, maxSize)) {
                    return;
                }
                
                SMLFrontend.config.currentUploads.push(file);
                SMLFrontend.createFilePreview(file, index);
            });
            
            // Mettre à jour l'état du formulaire
            SMLFrontend.updateFormState();
        },
        
        /**
         * Validation d'un fichier
         */
        validateFile: function(file, maxSize) {
            // Vérifier la taille
            if (file.size > maxSize) {
                this.showMessage('error', `${file.name}: Fichier trop volumineux (max: ${this.formatFileSize(maxSize)})`);
                return false;
            }
            
            // Vérifier le type MIME
            if (!this.config.allowedTypes.includes(file.type)) {
                this.showMessage('error', `${file.name}: Type de fichier non autorisé`);
                return false;
            }
            
            // Vérifier si le fichier semble corrompu
            if (file.size === 0) {
                this.showMessage('error', `${file.name}: Fichier vide ou corrompu`);
                return false;
            }
            
            return true;
        },
        
        /**
         * Créer une prévisualisation de fichier
         */
        createFilePreview: function(file, index) {
            const $preview = $('#sml-upload-preview');
            const $item = $('<div class="sml-preview-item" data-index="' + index + '">');
            
            // Icône ou image de prévisualisation
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $item.find('.sml-file-icon').html('<img src="' + e.target.result + '" alt="Preview" class="sml-preview-image">');
                };
                reader.readAsDataURL(file);
                
                $item.append('<div class="sml-file-icon"><div class="sml-loading-spinner"></div></div>');
            } else {
                $item.append('<div class="sml-file-icon"><span class="dashicons dashicons-media-document"></span></div>');
            }
            
            // Informations du fichier
            const $info = $('<div class="sml-file-info">');
            $info.append('<div class="sml-file-name">' + this.escapeHtml(file.name) + '</div>');
            $info.append('<div class="sml-file-size">(' + this.formatFileSize(file.size) + ')</div>');
            $info.append('<div class="sml-file-type">' + file.type + '</div>');
            
            // Barre de progression (cachée initialement)
            const $progress = $('<div class="sml-file-progress" style="display: none;">');
            $progress.append('<div class="sml-progress-bar"><div class="sml-progress-fill" style="width: 0%;"></div></div>');
            $progress.append('<div class="sml-progress-text">0%</div>');
            
            // Bouton de suppression
            const $remove = $('<button type="button" class="sml-remove-file" data-index="' + index + '">');
            $remove.append('<span class="dashicons dashicons-no-alt"></span>');
            
            // État du fichier
            const $status = $('<div class="sml-file-status sml-status-pending">En attente</div>');
            
            $item.append($info);
            $item.append($progress);
            $item.append($status);
            $item.append($remove);
            
            $preview.append($item);
        },
        
        /**
         * Supprimer un fichier de la prévisualisation
         */
        removePreviewFile: function(e) {
            e.preventDefault();
            
            const index = parseInt($(this).data('index'));
            const $item = $(this).closest('.sml-preview-item');
            
            // Supprimer du tableau
            SMLFrontend.config.currentUploads.splice(index, 1);
            
            // Supprimer de l'affichage
            $item.fadeOut(function() {
                $(this).remove();
                SMLFrontend.updateFormState();
            });
        },
        
        /**
         * Gestion de la soumission du formulaire
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            if (SMLFrontend.config.currentUploads.length === 0) {
                SMLFrontend.showMessage('error', 'Veuillez sélectionner au moins un fichier');
                return;
            }
            
            // Désactiver le formulaire
            $submitBtn.prop('disabled', true);
            $form.addClass('sml-uploading');
            
            // Afficher les barres de progression
            $('.sml-file-progress').show();
            
            // Démarrer l'upload
            SMLFrontend.startBatchUpload($form);
        },
        
        /**
         * Démarrer l'upload par lots
         */
        startBatchUpload: function($form) {
            const files = this.config.currentUploads;
            let completedUploads = 0;
            let failedUploads = 0;
            const totalFiles = files.length;
            
            // Traiter chaque fichier
            files.forEach((file, index) => {
                this.uploadSingleFile(file, index, $form)
                    .then(() => {
                        completedUploads++;
                        this.updateFileStatus(index, 'success', 'Uploadé avec succès');
                    })
                    .catch((error) => {
                        failedUploads++;
                        this.updateFileStatus(index, 'error', error.message || 'Erreur d\'upload');
                    })
                    .finally(() => {
                        // Vérifier si tous les uploads sont terminés
                        if (completedUploads + failedUploads === totalFiles) {
                            this.finishBatchUpload($form, completedUploads, failedUploads);
                        }
                    });
            });
        },
        
        /**
         * Upload d'un fichier unique
         */
        uploadSingleFile: function(file, index, $form) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                
                // Ajouter le fichier
                formData.append('sml_files[]', file);
                
                // Ajouter les autres données du formulaire
                $form.find('input, textarea, select').not('[type="file"]').each(function() {
                    if ($(this).attr('name')) {
                        formData.append($(this).attr('name'), $(this).val());
                    }
                });
                
                // Configuration AJAX avec progression
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 300000, // 5 minutes
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        
                        // Gérer la progression
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percentComplete = (e.loaded / e.total) * 100;
                                SMLFrontend.updateFileProgress(index, percentComplete);
                            }
                        });
                        
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data || 'Erreur inconnue'));
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Erreur de communication';
                        
                        if (status === 'timeout') {
                            errorMessage = 'Timeout - le fichier est peut-être trop volumineux';
                        } else if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        }
                        
                        reject(new Error(errorMessage));
                    }
                });
            });
        },
        
        /**
         * Mettre à jour la progression d'un fichier
         */
        updateFileProgress: function(index, percent) {
            const $item = $(`.sml-preview-item[data-index="${index}"]`);
            const $progressBar = $item.find('.sml-progress-fill');
            const $progressText = $item.find('.sml-progress-text');
            
            $progressBar.css('width', percent + '%');
            $progressText.text(Math.round(percent) + '%');
        },
        
        /**
         * Mettre à jour le statut d'un fichier
         */
        updateFileStatus: function(index, status, message) {
            const $item = $(`.sml-preview-item[data-index="${index}"]`);
            const $status = $item.find('.sml-file-status');
            
            $status.removeClass('sml-status-pending sml-status-success sml-status-error')
                   .addClass(`sml-status-${status}`)
                   .text(message);
            
            if (status === 'success') {
                $item.find('.sml-remove-file').hide();
                $item.addClass('sml-upload-complete');
            }
        },
        
        /**
         * Finaliser l'upload par lots
         */
        finishBatchUpload: function($form, completed, failed) {
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Réactiver le formulaire
            $submitBtn.prop('disabled', false);
            $form.removeClass('sml-uploading');
            
            // Message de résultat
            if (completed > 0) {
                let message = `${completed} fichier(s) uploadé(s) avec succès`;
                if (failed > 0) {
                    message += `, ${failed} échec(s)`;
                }
                this.showMessage('success', message);
                
                // Redirection si configurée
                const redirectUrl = $form.find('input[name="redirect_after"]').val();
                if (redirectUrl && failed === 0) {
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 2000);
                }
            } else {
                this.showMessage('error', 'Aucun fichier n\'a pu être uploadé');
            }
        },
        
        /**
         * Voir les liens d'un média
         */
        viewMediaLinks: function(e) {
            e.preventDefault();
            
            const mediaId = $(this).data('media-id');
            
            SMLFrontend.openModal('sml-links-modal');
            $('#sml-links-content').html('<div class="sml-loading">Chargement...</div>');
            
            SMLFrontend.ajaxRequest('sml_get_media_links', {
                media_id: mediaId
            }, function(response) {
                if (response.success) {
                    $('#sml-links-content').html(response.data);
                } else {
                    $('#sml-links-content').html('<div class="sml-error">' + response.data + '</div>');
                }
            });
        },
        
        /**
         * Téléchargement simple
         */
        handleSingleDownload: function(e) {
            e.preventDefault();
            
            const linkId = $(this).data('link-id');
            const mediaId = $(this).data('media-id');
            
            SMLFrontend.downloadMedia(linkId, mediaId);
        },
        
        /**
         * Téléchargement par format
         */
        handleFormatDownload: function(e) {
            e.preventDefault();
            
            const linkId = $(this).data('link-id');
            const mediaId = $(this).data('media-id');
            
            SMLFrontend.downloadMedia(linkId, mediaId);
            
            // Fermer le dropdown
            $(this).closest('.sml-dropdown').removeClass('sml-open');
        },
        
        /**
         * Télécharger un média
         */
        downloadMedia: function(linkId, mediaId) {
            // Afficher un indicateur de chargement
            const $indicator = $('<div class="sml-download-indicator">Préparation du téléchargement...</div>');
            $('body').append($indicator);
            
            this.ajaxRequest('sml_get_download_url', {
                link_id: linkId
            }, function(response) {
                $indicator.remove();
                
                if (response.success) {
                    // Créer un lien temporaire et déclencher le téléchargement
                    const link = document.createElement('a');
                    link.href = response.data.url;
                    link.download = '';
                    link.style.display = 'none';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Tracker le téléchargement
                    SMLFrontend.trackUsage(linkId, 'download');
                    
                    // Feedback utilisateur
                    SMLFrontend.showMessage('success', 'Téléchargement démarré');
                } else {
                    SMLFrontend.showMessage('error', response.data || 'Erreur lors du téléchargement');
                }
            }, function() {
                $indicator.remove();
                SMLFrontend.showMessage('error', 'Erreur de communication');
            });
        },
        
        /**
         * Copier les liens d'un média
         */
        copyMediaLinks: function(e) {
            e.preventDefault();
            
            const mediaId = $(this).data('media-id');
            
            this.ajaxRequest('sml_get_media_links_for_copy', {
                media_id: mediaId
            }, function(response) {
                if (response.success && response.data.links.length > 0) {
                    const linksText = response.data.links.join('\n');
                    
                    SMLFrontend.copyToClipboard(linksText).then(() => {
                        SMLFrontend.showCopyFeedback();
                        
                        // Tracker les copies
                        response.data.link_ids.forEach(linkId => {
                            if (linkId) {
                                SMLFrontend.trackUsage(linkId, 'copy');
                            }
                        });
                    });
                } else {
                    SMLFrontend.showMessage('warning', 'Aucun lien disponible à copier');
                }
            });
        },
        
        /**
         * Copier vers le presse-papiers
         */
        copyToClipboard: function(text) {
            if (typeof text === 'object' && text.preventDefault) {
                // C'est un événement de clic
                const event = text;
                event.preventDefault();
                
                const $btn = $(event.currentTarget);
                const textToCopy = $btn.data('url') || $btn.siblings('input').val();
                
                return this.copyToClipboard(textToCopy).then(() => {
                    const originalText = $btn.html();
                    $btn.html('<span class="dashicons dashicons-yes"></span> Copié !');
                    
                    setTimeout(() => {
                        $btn.html(originalText);
                    }, 2000);
                    
                    // Tracker si applicable
                    const linkId = $btn.data('link-id');
                    if (linkId) {
                        SMLFrontend.trackUsage(linkId, 'copy');
                    }
                });
            }
            
            // Méthode moderne avec l'API Clipboard
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            
            // Fallback pour les navigateurs plus anciens
            return new Promise((resolve, reject) => {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        resolve();
                    } else {
                        reject(new Error('Copy command failed'));
                    }
                } catch (err) {
                    reject(err);
                } finally {
                    document.body.removeChild(textArea);
                }
            });
        },
        
        /**
         * Afficher le feedback de copie
         */
        showCopyFeedback: function() {
            const $feedback = $('<div class="sml-copy-feedback">Liens copiés !</div>');
            $('body').append($feedback);
            
            setTimeout(() => {
                $feedback.fadeOut(() => {
                    $feedback.remove();
                });
            }, 2000);
        },
        
        /**
         * Gestion des dropdowns
         */
        toggleDropdown: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $dropdown = $(this).closest('.sml-dropdown');
            
            // Fermer tous les autres dropdowns
            $('.sml-dropdown').not($dropdown).removeClass('sml-open');
            
            // Toggle le dropdown actuel
            $dropdown.toggleClass('sml-open');
        },
        
        /**
         * Lightbox
         */
        openLightbox: function(e) {
            e.preventDefault();
            
            const src = $(this).attr('href');
            const title = $(this).data('title') || $(this).find('img').attr('alt') || '';
            
            const $lightbox = $('#sml-lightbox');
            $lightbox.find('.sml-lightbox-image').attr('src', src).attr('alt', title);
            $lightbox.find('.sml-lightbox-caption').text(title);
            $lightbox.addClass('sml-active');
        },
        
        /**
         * Fermer la lightbox
         */
        closeLightbox: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            $('#sml-lightbox').removeClass('sml-active');
        },
        
        /**
         * Gestion de la touche Échap
         */
        handleEscapeKey: function(e) {
            if (e.keyCode === 27) {
                SMLFrontend.closeLightbox();
                SMLFrontend.closeModal();
            }
        },
        
        /**
         * Gestion des modales
         */
        openModal: function(modalId) {
            $('#' + modalId).show().addClass('sml-active');
            $('body').addClass('sml-modal-active');
        },
        
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            $('.sml-modal').hide().removeClass('sml-active');
            $('body').removeClass('sml-modal-active');
        },
        
        closeModalOnOverlay: function(e) {
            if (e.target === this) {
                SMLFrontend.closeModal();
            }
        },
        
        /**
         * Charger plus de médias
         */
        loadMoreMedia: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const offset = $btn.data('offset');
            const limit = parseInt($btn.data('limit')) || 10;
            
            $btn.prop('disabled', true).text('Chargement...');
            
            SMLFrontend.ajaxRequest('sml_load_more_user_media', {
                user_id: userId,
                offset: offset,
                limit: limit
            }, function(response) {
                if (response.success) {
                    $('.sml-media-grid').append(response.data.content);
                    
                    if (response.data.has_more) {
                        $btn.data('offset', offset + limit)
                           .prop('disabled', false)
                           .text('Charger plus');
                    } else {
                        $btn.hide();
                    }
                    
                    // Animation d'apparition
                    $('.sml-media-item').slice(-response.data.content.split('sml-media-item').length + 1)
                                        .hide().fadeIn();
                } else {
                    SMLFrontend.showMessage('error', response.data);
                    $btn.prop('disabled', false).text('Charger plus');
                }
            });
        },
        
        /**
         * Tracker un clic sur lien
         */
        trackLinkClick: function(e) {
            const href = $(this).attr('href');
            const matches = href.match(/\/sml\/.*?\/(\d+)\//);
            
            if (matches) {
                const linkId = matches[1];
                SMLFrontend.trackUsage(linkId, 'view');
            }
        },
        
        /**
         * Tracker l'utilisation
         */
        trackUsage: function(linkId, actionType) {
            // Ne pas bloquer l'UI pour le tracking
            this.ajaxRequest('sml_track_usage', {
                link_id: linkId,
                action_type: actionType
            }, null, null, false); // async = false pour ne pas attendre
        },
        
        /**
         * Gestion du drag & drop
         */
        handleDragOver: function(e) {
            e.preventDefault();
            $(this).addClass('sml-drag-over');
        },
        
        handleDragLeave: function(e) {
            e.preventDefault();
            $(this).removeClass('sml-drag-over');
        },
        
        handleFileDrop: function(e) {
            e.preventDefault();
            $(this).removeClass('sml-drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            const $fileInput = $(this).find('#sml-upload-files');
            
            // Simuler la sélection de fichiers
            $fileInput[0].files = files;
            $fileInput.trigger('change');
        },
        
        /**
         * Mettre à jour l'état du formulaire
         */
        updateFormState: function() {
            const $form = $('#sml-upload-form');
            const $submitBtn = $form.find('button[type="submit"]');
            const hasFiles = this.config.currentUploads.length > 0;
            
            $submitBtn.prop('disabled', !hasFiles);
            $form.toggleClass('has-files', hasFiles);
        },
        
        /**
         * Initialiser les tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const text = $element.data('tooltip');
                
                $element.attr('title', text);
                
                // Si jQuery UI est disponible
                if ($.fn.tooltip) {
                    $element.tooltip({
                        position: { my: "left+15 center", at: "right center" },
                        show: { duration: 200 },
                        hide: { duration: 100 }
                    });
                }
            });
        },
        
        /**
         * Initialiser les graphiques
         */
        initCharts: function() {
            $('.sml-stats-chart canvas').each(function() {
                const $canvas = $(this);
                const mediaId = $canvas.closest('.sml-media-stats').data('media-id');
                
                if (mediaId) {
                    SMLFrontend.loadMediaChart($canvas, mediaId);
                }
            });
        },
        
        /**
         * Charger un graphique de média
         */
        loadMediaChart: function($canvas, mediaId) {
            this.ajaxRequest('sml_get_media_chart_data', {
                media_id: mediaId,
                period: 'month'
            }, function(response) {
                if (response.success) {
                    const ctx = $canvas[0].getContext('2d');
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: 'Téléchargements',
                                data: response.data.downloads,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1,
                                fill: true
                            }, {
                                label: 'Copies',
                                data: response.data.copies,
                                borderColor: 'rgb(255, 205, 86)',
                                backgroundColor: 'rgba(255, 205, 86, 0.2)',
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
                            },
                            plugins: {
                                legend: {
                                    position: 'top'
                                }
                            }
                        }
                    });
                }
            });
        },
        
        /**
         * Initialiser la géolocalisation
         */
        initGeolocation: function() {
            if (navigator.geolocation && this.shouldTrackLocation()) {
                navigator.geolocation.getCurrentPosition(
                    this.handleGeolocationSuccess.bind(this),
                    this.handleGeolocationError.bind(this),
                    {
                        enableHighAccuracy: false,
                        timeout: 5000,
                        maximumAge: 300000 // 5 minutes
                    }
                );
            }
        },
        
        /**
         * Vérifier si on doit tracker la localisation
         */
        shouldTrackLocation: function() {
            // Vérifier les préférences utilisateur stockées
            const userPrefs = localStorage.getItem('sml_location_tracking');
            return userPrefs === 'allowed';
        },
        
        /**
         * Succès de géolocalisation
         */
        handleGeolocationSuccess: function(position) {
            const location = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy
            };
            
            // Stocker temporairement pour les requêtes
            sessionStorage.setItem('sml_user_location', JSON.stringify(location));
        },
        
        /**
         * Erreur de géolocalisation
         */
        handleGeolocationError: function(error) {
            console.log('Geolocation error:', error.message);
            // Ne pas afficher d'erreur à l'utilisateur pour la géolocalisation
        },
        
        /**
         * Initialiser les notifications push
         */
        initPushNotifications: function() {
            if ('Notification' in window && 'serviceWorker' in navigator) {
                // Enregistrer le service worker pour les notifications
                this.registerServiceWorker();
            }
        },
        
        /**
         * Enregistrer le service worker
         */
        registerServiceWorker: function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/wp-content/plugins/secure-media-link/assets/js/sw.js')
                    .then(function(registration) {
                        console.log('Service Worker registered successfully');
                    })
                    .catch(function(error) {
                        console.log('Service Worker registration failed');
                    });
            }
        },
        
        /**
         * Initialiser le cache client
         */
        initClientCache: function() {
            // Cache des données utilisateur fréquemment utilisées
            this.cache = new Map();
            this.cacheExpiry = new Map();
            
            // Nettoyer le cache expiré toutes les 5 minutes
            setInterval(this.cleanExpiredCache.bind(this), 300000);
        },
        
        /**
         * Nettoyer le cache expiré
         */
        cleanExpiredCache: function() {
            const now = Date.now();
            
            for (const [key, expiry] of this.cacheExpiry.entries()) {
                if (expiry < now) {
                    this.cache.delete(key);
                    this.cacheExpiry.delete(key);
                }
            }
        },
        
        /**
         * Mettre en cache une valeur
         */
        setCacheValue: function(key, value, ttl = 300000) { // 5 minutes par défaut
            this.cache.set(key, value);
            this.cacheExpiry.set(key, Date.now() + ttl);
        },
        
        /**
         * Récupérer une valeur du cache
         */
        getCacheValue: function(key) {
            const expiry = this.cacheExpiry.get(key);
            
            if (!expiry || expiry < Date.now()) {
                this.cache.delete(key);
                this.cacheExpiry.delete(key);
                return null;
            }
            
            return this.cache.get(key);
        },
        
        /**
         * Mettre à jour les statistiques en temps réel
         */
        updateRealTimeStats: function() {
            $('.sml-media-stats').each(function() {
                const $stats = $(this);
                const mediaId = $stats.data('media-id');
                
                if (mediaId) {
                    SMLFrontend.refreshMediaStats($stats, mediaId);
                }
            });
        },
        
        /**
         * Actualiser les statistiques d'un média
         */
        refreshMediaStats: function($stats, mediaId) {
            const cacheKey = `media_stats_${mediaId}`;
            const cached = this.getCacheValue(cacheKey);
            
            if (cached) {
                this.updateStatsDisplay($stats, cached);
                return;
            }
            
            this.ajaxRequest('sml_get_media_stats', {
                media_id: mediaId
            }, (response) => {
                if (response.success) {
                    this.setCacheValue(cacheKey, response.data, 60000); // 1 minute
                    this.updateStatsDisplay($stats, response.data);
                }
            });
        },
        
        /**
         * Mettre à jour l'affichage des statistiques
         */
        updateStatsDisplay: function($stats, data) {
            $stats.find('.sml-stat-downloads .sml-stat-number').text(this.formatNumber(data.downloads || 0));
            $stats.find('.sml-stat-copies .sml-stat-number').text(this.formatNumber(data.copies || 0));
            $stats.find('.sml-stat-views .sml-stat-number').text(this.formatNumber(data.views || 0));
            $stats.find('.sml-stat-blocked .sml-stat-number').text(this.formatNumber(data.blocked || 0));
        },
        
        /**
         * Charger les données stockées
         */
        loadStoredData: function() {
            // Charger les préférences utilisateur
            const prefs = localStorage.getItem('sml_user_preferences');
            if (prefs) {
                try {
                    this.userPreferences = JSON.parse(prefs);
                } catch (e) {
                    this.userPreferences = {};
                }
            } else {
                this.userPreferences = {};
            }
        },
        
        /**
         * Sauvegarder les préférences utilisateur
         */
        saveUserPreference: function(key, value) {
            this.userPreferences[key] = value;
            localStorage.setItem('sml_user_preferences', JSON.stringify(this.userPreferences));
        },
        
        /**
         * Annuler un upload
         */
        cancelUpload: function(e) {
            e.preventDefault();
            
            const index = $(this).data('index');
            // Ici on pourrait implémenter l'annulation d'upload en cours
            
            SMLFrontend.showMessage('info', 'Upload annulé');
        },
        
        /**
         * Afficher un message
         */
        showMessage: function(type, message, duration = 5000) {
            const $container = $('#sml-upload-messages');
            
            if ($container.length === 0) {
                $('body').append('<div id="sml-upload-messages" class="sml-messages-container"></div>');
            }
            
            const $message = $(`<div class="sml-message sml-message-${type}">`);
            $message.html(message);
            
            // Ajouter un bouton de fermeture
            const $closeBtn = $('<button type="button" class="sml-message-close">&times;</button>');
            $closeBtn.on('click', function() {
                $message.fadeOut(() => $message.remove());
            });
            
            $message.append($closeBtn);
            $('#sml-upload-messages').append($message);
            
            // Auto-dismiss
            if (duration > 0) {
                setTimeout(() => {
                    $message.fadeOut(() => $message.remove());
                }, duration);
            }
        },
        
        /**
         * Formater la taille de fichier
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        /**
         * Formater un nombre
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        /**
         * Échapper le HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
        ajaxRequest: function(action, data, successCallback, errorCallback, async = true) {
            const requestData = {
                action: action,
                nonce: this.config.nonce
            };
            
            // Merger les données
            if (typeof data === 'string') {
                const params = new URLSearchParams(data);
                params.forEach((value, key) => {
                    requestData[key] = value;
                });
            } else if (typeof data === 'object') {
                Object.assign(requestData, data);
            }
            
            // Ajouter la géolocalisation si disponible
            const location = sessionStorage.getItem('sml_user_location');
            if (location) {
                try {
                    requestData.user_location = JSON.parse(location);
                } catch (e) {
                    // Ignorer les erreurs de parsing
                }
            }
            
            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                async: async,
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
                        SMLFrontend.showMessage('error', 'Erreur de communication avec le serveur');
                    }
                }
            });
        },
        
        /**
         * Gestion des erreurs globales
         */
        handleError: function(error, context = '') {
            console.error('SML Frontend Error' + (context ? ' in ' + context : '') + ':', error);
            
            // Log l'erreur côté serveur si possible
            this.ajaxRequest('sml_log_js_error', {
                error: error.toString(),
                context: context,
                url: window.location.href,
                user_agent: navigator.userAgent
            }, null, null, false);
        },
        
        /**
         * Détection des fonctionnalités du navigateur
         */
        detectFeatures: function() {
            return {
                dragDrop: 'draggable' in document.createElement('div'),
                fileAPI: 'FileReader' in window,
                canvas: 'getContext' in document.createElement('canvas'),
                localStorage: 'localStorage' in window,
                geolocation: 'geolocation' in navigator,
                notifications: 'Notification' in window,
                serviceWorker: 'serviceWorker' in navigator,
                clipboard: 'clipboard' in navigator
            };
        },
        
        /**
         * Optimisation des performances
         */
        optimizePerformance: function() {
            // Lazy loading des images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy-load');
                            observer.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            // Préchargement des ressources critiques
            this.preloadCriticalResources();
        },
        
        /**
         * Précharger les ressources critiques
         */
        preloadCriticalResources: function() {
            const criticalCSS = [
                SML_PLUGIN_URL + 'assets/css/frontend.css'
            ];
            
            criticalCSS.forEach(url => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'style';
                link.href = url;
                document.head.appendChild(link);
            });
        },
        
        /**
         * Gestion de la connexion réseau
         */
        handleNetworkStatus: function() {
            window.addEventListener('online', () => {
                this.showMessage('success', 'Connexion rétablie', 3000);
                this.retryFailedRequests();
            });
            
            window.addEventListener('offline', () => {
                this.showMessage('warning', 'Connexion perdue - Mode hors ligne', 0);
            });
        },
        
        /**
         * Réessayer les requêtes échouées
         */
        retryFailedRequests: function() {
            // Implémenter la logique de retry pour les requêtes échouées
            console.log('Retrying failed requests...');
        },
        
        /**
         * Analytics et métriques
         */
        trackAnalytics: function(event, data = {}) {
            // Envoyer des métriques d'utilisation (anonymisées)
            this.ajaxRequest('sml_track_analytics', {
                event: event,
                data: data,
                timestamp: Date.now(),
                user_agent: navigator.userAgent,
                screen_resolution: `${screen.width}x${screen.height}`,
                viewport_size: `${window.innerWidth}x${window.innerHeight}`
            }, null, null, false);
        }
    };
    
    /**
     * Extensions jQuery personnalisées
     */
    $.fn.smlLoader = function(show = true) {
        return this.each(function() {
            const $element = $(this);
            
            if (show) {
                $element.addClass('sml-loading').append('<div class="sml-loader"><div class="sml-spinner"></div></div>');
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
    
    $.fn.smlPulse = function(times = 3) {
        return this.each(function() {
            const $element = $(this);
            let count = 0;
            
            const pulse = () => {
                if (count < times) {
                    $element.addClass('sml-pulse');
                    setTimeout(() => {
                        $element.removeClass('sml-pulse');
                        if (++count < times) {
                            setTimeout(pulse, 200);
                        }
                    }, 300);
                }
            };
            
            pulse();
        });
    };
    
    /**
     * Gestionnaire d'erreurs JavaScript global
     */
    window.onerror = function(message, source, lineno, colno, error) {
        SMLFrontend.handleError(error || message, 'Global Error Handler');
        return false;
    };
    
    /**
     * Gestionnaire pour les promesses rejetées
     */
    window.addEventListener('unhandledrejection', function(event) {
        SMLFrontend.handleError(event.reason, 'Unhandled Promise Rejection');
    });
    
    /**
     * Initialisation au chargement du DOM
     */
    $(document).ready(function() {
        try {
            SMLFrontend.init();
            SMLFrontend.optimizePerformance();
            SMLFrontend.handleNetworkStatus();
            
            // Tracker l'initialisation
            SMLFrontend.trackAnalytics('frontend_initialized', {
                features: SMLFrontend.detectFeatures(),
                page_type: document.body.className
            });
        } catch (error) {
            SMLFrontend.handleError(error, 'Initialization');
        }
    });
    
    // Exposer SMLFrontend globalement pour le debugging
    window.SMLFrontend = SMLFrontend;
    
})(jQuery);