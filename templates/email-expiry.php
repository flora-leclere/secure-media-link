<?php
/**
 * Template email pour les notifications d'expiration
 * templates/email-expiry.php
 * 
 * Variables disponibles :
 * $title - Titre de la notification
 * $message - Message principal
 * $data - Données du lien (link_id, media_id, format_id, expires_at, days_remaining)
 * $site_name - Nom du site
 * $site_url - URL du site
 * $admin_url - URL du tableau de bord admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$link_data = isset($data) ? $data : array();
$days_remaining = isset($link_data['days_remaining']) ? intval($link_data['days_remaining']) : 0;
$is_expired = $days_remaining <= 0;

// Récupérer les informations du média et du format
$media = null;
$format = null;
if (!empty($link_data['media_id'])) {
    $media = get_post($link_data['media_id']);
}
if (!empty($link_data['format_id'])) {
    $format = SML_Media_Formats::get_format($link_data['format_id']);
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_locale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: <?php echo $is_expired ? 'linear-gradient(135deg, #dc3545, #c82333)' : 'linear-gradient(135deg, #fd7e14, #e56800)'; ?>;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-header .site-name {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .expiry-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        .email-content {
            padding: 30px;
        }
        .alert-box {
            background: <?php echo $is_expired ? '#f8d7da' : '#fff3cd'; ?>;
            border: 1px solid <?php echo $is_expired ? '#f5c6cb' : '#ffeaa7'; ?>;
            border-left: 4px solid <?php echo $is_expired ? '#dc3545' : '#fd7e14'; ?>;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-title {
            font-weight: 600;
            color: <?php echo $is_expired ? '#721c24' : '#856404'; ?>;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .alert-message {
            color: <?php echo $is_expired ? '#721c24' : '#856404'; ?>;
            margin: 0;
        }
        .countdown {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: <?php echo $is_expired ? '#f8d7da' : '#d1ecf1'; ?>;
            border-radius: 8px;
        }
        .countdown-number {
            font-size: 48px;
            font-weight: 700;
            color: <?php echo $is_expired ? '#dc3545' : '#fd7e14'; ?>;
            display: block;
        }
        .countdown-label {
            font-size: 16px;
            color: <?php echo $is_expired ? '#721c24' : '#0c5460'; ?>;
            margin-top: 5px;
        }
        .media-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .media-info h3 {
            margin-top: 0;
            color: #495057;
            font-size: 16px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .media-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .media-row:last-child {
            border-bottom: none;
        }
        .media-thumbnail {
            width: 60px;
            height: 60px;
            background: #e9ecef;
            border-radius: 4px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            overflow: hidden;
        }
        .media-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        .media-details {
            flex: 1;
        }
        .media-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .media-meta {
            font-size: 14px;
            color: #6c757d;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            width: 40%;
        }
        .detail-value {
            color: #495057;
            width: 58%;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #007cba;
            color: white;
        }
        .btn-primary:hover {
            background-color: #005a87;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-warning {
            background-color: #fd7e14;
            color: white;
        }
        .renewal-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .renewal-info h4 {
            color: #155724;
            margin-top: 0;
        }
        .renewal-steps {
            color: #155724;
            margin-bottom: 0;
        }
        .stats-summary {
            background: #e2e3e5;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }
        .email-footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
        .email-footer a {
            color: #007cba;
            text-decoration: none;
        }
        .urgency-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .urgency-critical {
            background: #dc3545;
            color: white;
        }
        .urgency-high {
            background: #fd7e14;
            color: white;
        }
        .urgency-medium {
            background: #ffc107;
            color: #212529;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            .email-content {
                padding: 20px;
            }
            .detail-row, .stats-row {
                flex-direction: column;
            }
            .detail-label, .detail-value {
                width: 100%;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
            .countdown-number {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="expiry-icon">
                <?php echo $is_expired ? '❌' : '⏰'; ?>
            </div>
            <h1>
                <?php echo $is_expired ? 
                    __('Lien Expiré', 'secure-media-link') : 
                    __('Lien Bientôt Expiré', 'secure-media-link'); ?>
                
                <?php if ($days_remaining > 0): ?>
                    <span class="urgency-indicator urgency-<?php echo $days_remaining <= 1 ? 'critical' : ($days_remaining <= 7 ? 'high' : 'medium'); ?>">
                        <?php echo sprintf(_n('%d jour restant', '%d jours restants', $days_remaining, 'secure-media-link'), $days_remaining); ?>
                    </span>
                <?php endif; ?>
            </h1>
            <div class="site-name"><?php echo esc_html($site_name); ?></div>
        </div>

        <!-- Content -->
        <div class="email-content">
            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-title"><?php echo esc_html($title); ?></div>
                <p class="alert-message"><?php echo esc_html($message); ?></p>
            </div>

            <!-- Countdown -->
            <div class="countdown">
                <span class="countdown-number">
                    <?php echo $is_expired ? '0' : $days_remaining; ?>
                </span>
                <div class="countdown-label">
                    <?php echo $is_expired ? 
                        __('Lien expiré', 'secure-media-link') : 
                        sprintf(_n('jour restant', 'jours restants', $days_remaining, 'secure-media-link')); ?>
                </div>
            </div>

            <!-- Media Information -->
            <?php if ($media): ?>
            <div class="media-info">
                <h3><?php _e('Informations du Média', 'secure-media-link'); ?></h3>
                
                <div class="media-row">
                    <div class="media-thumbnail">
                        <?php if (wp_attachment_is_image($media->ID)): ?>
                            <?php echo wp_get_attachment_image($media->ID, 'thumbnail'); ?>
                        <?php else: ?>
                            <span style="color: #6c757d;"><?php echo sml_get_file_type_icon($media->post_mime_type); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="media-details">
                        <div class="media-title"><?php echo esc_html($media->post_title); ?></div>
                        <div class="media-meta">
                            <?php echo esc_html($media->post_mime_type); ?>
                            <?php if ($format): ?>
                                • <?php echo esc_html($format->name); ?>
                                <?php if ($format->width || $format->height): ?>
                                    (<?php echo $format->width ?: '?'; ?>×<?php echo $format->height ?: '?'; ?>)
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($link_data['expires_at'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Date d\'expiration:', 'secure-media-link'); ?></span>
                    <span class="detail-value">
                        <?php echo date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($link_data['expires_at'])
                        ); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($link_data['link_id'])): ?>
                    <?php
                    // Récupérer les statistiques d'utilisation
                    global $wpdb;
                    $link_stats = $wpdb->get_row($wpdb->prepare(
                        "SELECT download_count, copy_count FROM {$wpdb->prefix}sml_secure_links WHERE id = %d",
                        $link_data['link_id']
                    ));
                    ?>
                    
                    <?php if ($link_stats): ?>
                    <div class="stats-summary">
                        <strong><?php _e('Statistiques d\'utilisation:', 'secure-media-link'); ?></strong>
                        <div class="stats-row">
                            <span><?php _e('Téléchargements:', 'secure-media-link'); ?></span>
                            <span><?php echo number_format($link_stats->download_count); ?></span>
                        </div>
                        <div class="stats-row">
                            <span><?php _e('Copies:', 'secure-media-link'); ?></span>
                            <span><?php echo number_format($link_stats->copy_count); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Renewal Information -->
            <?php if (!$is_expired): ?>
            <div class="renewal-info">
                <h4><?php _e('Comment Renouveler ce Lien', 'secure-media-link'); ?></h4>
                <ol class="renewal-steps">
                    <li><?php _e('Connectez-vous à votre tableau de bord WordPress', 'secure-media-link'); ?></li>
                    <li><?php _e('Accédez à Secure Media > Médiathèque', 'secure-media-link'); ?></li>
                    <li><?php _e('Trouvez le média concerné et cliquez sur "Voir les détails"', 'secure-media-link'); ?></li>
                    <li><?php _e('Modifiez la date d\'expiration ou générez un nouveau lien', 'secure-media-link'); ?></li>
                </ol>
            </div>
            <?php else: ?>
            <div class="alert-box">
                <div class="alert-title"><?php _e('Lien Expiré', 'secure-media-link'); ?></div>
                <p class="alert-message">
                    <?php _e('Ce lien a expiré et n\'est plus accessible. Vous devez générer un nouveau lien pour permettre l\'accès à ce média.', 'secure-media-link'); ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($media): ?>
                <a href="<?php echo esc_url($admin_url . '?page=sml-media-library&media_id=' . $media->ID); ?>" 
                   class="btn <?php echo $is_expired ? 'btn-warning' : 'btn-primary'; ?>">
                    <?php echo $is_expired ? 
                        __('Générer un Nouveau Lien', 'secure-media-link') : 
                        __('Renouveler le Lien', 'secure-media-link'); ?>
                </a>
                <?php endif; ?>
                
                <a href="<?php echo esc_url($admin_url . '?page=sml-media-library'); ?>" class="btn btn-success">
                    <?php _e('Voir la Médiathèque', 'secure-media-link'); ?>
                </a>
            </div>

            <!-- Additional Information -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 30px;">
                <h4 style="margin-top: 0; color: #495057;">
                    <?php _e('Pourquoi les Liens Expirent-ils ?', 'secure-media-link'); ?>
                </h4>
                <p style="margin-bottom: 15px; color: #6c757d; line-height: 1.6;">
                    <?php _e('Les liens sécurisés ont une durée de vie limitée pour protéger vos médias contre l\'accès non autorisé. Cette mesure de sécurité garantit que vos contenus ne restent pas accessibles indéfiniment même si le lien est compromis.', 'secure-media-link'); ?>
                </p>
                
                <h5 style="color: #495057; margin-bottom: 10px;">
                    <?php _e('Avantages des Liens Temporaires:', 'secure-media-link'); ?>
                </h5>
                <ul style="color: #6c757d; margin-bottom: 0; padding-left: 20px;">
                    <li><?php _e('Protection contre le partage non autorisé', 'secure-media-link'); ?></li>
                    <li><?php _e('Contrôle précis de l\'accès aux contenus', 'secure-media-link'); ?></li>
                    <li><?php _e('Traçabilité complète des téléchargements', 'secure-media-link'); ?></li>
                    <li><?php _e('Sécurité renforcée contre le hotlinking', 'secure-media-link'); ?></li>
                </ul>
            </div>

            <!-- Automatic Renewal Option -->
            <?php if (!$is_expired && $days_remaining <= 7): ?>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-left: 4px solid #17a2b8; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h4 style="color: #0c5460; margin-top: 0;">
                    <?php _e('Renouvellement Automatique Disponible', 'secure-media-link'); ?>
                </h4>
                <p style="color: #0c5460; margin-bottom: 15px;">
                    <?php _e('Vous pouvez configurer le renouvellement automatique de ce lien pour éviter les interruptions d\'accès.', 'secure-media-link'); ?>
                </p>
                <a href="<?php echo esc_url($admin_url . '?page=sml-settings#auto-renewal'); ?>" 
                   style="color: #0c5460; font-weight: 600; text-decoration: underline;">
                    <?php _e('Configurer le Renouvellement Automatique', 'secure-media-link'); ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Support Information -->
            <div style="background: #e2e3e5; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h4 style="margin-top: 0; color: #495057; font-size: 14px;">
                    <?php _e('Besoin d\'Aide ?', 'secure-media-link'); ?>
                </h4>
                <p style="margin-bottom: 0; color: #6c757d; font-size: 14px;">
                    <?php _e('Si vous rencontrez des difficultés pour renouveler vos liens ou si vous avez des questions sur la configuration, n\'hésitez pas à consulter la documentation ou à contacter le support technique.', 'secure-media-link'); ?>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><?php _e('Cette notification a été générée automatiquement par Secure Media Link.', 'secure-media-link'); ?></p>
            <p>
                <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a> | 
                <a href="<?php echo esc_url($admin_url); ?>"><?php _e('Tableau de bord', 'secure-media-link'); ?></a> |
                <a href="<?php echo esc_url($admin_url . '?page=sml-settings'); ?>"><?php _e('Paramètres', 'secure-media-link'); ?></a>
            </p>
            <div style="margin-top: 15px; font-size: 12px;">
                <?php echo sprintf(
                    __('Envoyé le %s à %s', 'secure-media-link'),
                    date_i18n(get_option('date_format')),
                    date_i18n(get_option('time_format'))
                ); ?>
            </div>
        </div>
    </div>
</body>
</html>