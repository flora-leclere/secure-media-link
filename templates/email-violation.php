<?php
/**
 * Template email pour les notifications de violation
 * templates/email-violation.php
 * 
 * Variables disponibles :
 * $title - Titre de la notification
 * $message - Message principal
 * $data - Données de la violation (tracking_data, permission_check)
 * $site_name - Nom du site
 * $site_url - URL du site
 * $admin_url - URL du tableau de bord admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$tracking = isset($data['tracking_data']) ? $data['tracking_data'] : array();
$permission_check = isset($data['permission_check']) ? $data['permission_check'] : array();
$ip_location = !empty($tracking['ip_address']) ? sml_get_ip_location($tracking['ip_address']) : null;
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
            background: linear-gradient(135deg, #dc3545, #c82333);
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
        .security-icon {
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
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-title {
            font-weight: 600;
            color: #721c24;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .alert-message {
            color: #721c24;
            margin: 0;
        }
        .violation-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .violation-details h3 {
            margin-top: 0;
            color: #495057;
            font-size: 16px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
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
            word-break: break-all;
        }
        .severity-high {
            color: #dc3545;
            font-weight: 600;
        }
        .severity-medium {
            color: #fd7e14;
            font-weight: 600;
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
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .recommendations {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-left: 4px solid #17a2b8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .recommendations h4 {
            color: #0c5460;
            margin-top: 0;
        }
        .recommendations ul {
            color: #0c5460;
            margin-bottom: 0;
        }
        .location-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .location-info strong {
            color: #856404;
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
        .timestamp {
            color: #6c757d;
            font-size: 14px;
            margin-top: 20px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 0;
            }
            .email-content {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
            }
            .detail-label, .detail-value {
                width: 100%;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="security-icon">
                🛡️
            </div>
            <h1><?php _e('Violation de Sécurité Détectée', 'secure-media-link'); ?></h1>
            <div class="site-name"><?php echo esc_html($site_name); ?></div>
        </div>

        <!-- Content -->
        <div class="email-content">
            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-title"><?php echo esc_html($title); ?></div>
                <p class="alert-message"><?php echo esc_html($message); ?></p>
            </div>

            <!-- Violation Details -->
            <?php if (!empty($tracking)): ?>
            <div class="violation-details">
                <h3><?php _e('Détails de la Violation', 'secure-media-link'); ?></h3>
                
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Adresse IP:', 'secure-media-link'); ?></span>
                    <span class="detail-value">
                        <code><?php echo esc_html($tracking['ip_address']); ?></code>
                        <?php if ($ip_location): ?>
                            <span style="color: #6c757d; font-size: 12px;">
                                (<?php echo esc_html($ip_location['city'] . ', ' . sml_get_country_name($ip_location['country'])); ?>)
                            </span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (!empty($tracking['domain'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Domaine:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html($tracking['domain']); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label"><?php _e('Action tentée:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html(ucfirst($tracking['action_type'])); ?></span>
                </div>

                <?php if (!empty($tracking['violation_type'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Type de violation:', 'secure-media-link'); ?></span>
                    <span class="detail-value severity-high"><?php echo esc_html($tracking['violation_type']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($tracking['referer_url'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Page de référence:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html($tracking['referer_url']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($tracking['user_agent'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Navigateur:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html(sml_get_simplified_user_agent($tracking['user_agent'])); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label"><?php _e('Horodatage:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Location Info -->
            <?php if ($ip_location): ?>
            <div class="location-info">
                <strong><?php _e('Géolocalisation:', 'secure-media-link'); ?></strong>
                <?php echo sprintf(
                    __('Cette tentative d\'accès provient de %s, %s', 'secure-media-link'),
                    esc_html($ip_location['city']),
                    esc_html(sml_get_country_name($ip_location['country']))
                ); ?>
            </div>
            <?php endif; ?>

            <!-- Recommendations -->
            <div class="recommendations">
                <h4><?php _e('Actions Recommandées', 'secure-media-link'); ?></h4>
                <ul>
                    <li><?php _e('Vérifiez les logs de sécurité pour d\'autres tentatives suspectes', 'secure-media-link'); ?></li>
                    <li><?php _e('Considérez bloquer cette IP si les violations persistent', 'secure-media-link'); ?></li>
                    <li><?php _e('Examinez les permissions d\'accès pour ce domaine', 'secure-media-link'); ?></li>
                    <li><?php _e('Activez le blocage automatique si nécessaire', 'secure-media-link'); ?></li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="<?php echo esc_url($admin_url . '?page=sml-tracking'); ?>" class="btn btn-primary">
                    <?php _e('Voir les Logs de Sécurité', 'secure-media-link'); ?>
                </a>
                <a href="<?php echo esc_url($admin_url . '?page=sml-permissions'); ?>" class="btn btn-secondary">
                    <?php _e('Gérer les Permissions', 'secure-media-link'); ?>
                </a>
            </div>

            <!-- Additional Info -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h4 style="margin-top: 0; color: #495057;"><?php _e('Que signifie cette violation ?', 'secure-media-link'); ?></h4>
                <?php
                $violation_explanations = array(
                    'blacklisted' => __('L\'IP ou le domaine est dans votre liste noire et n\'est pas autorisé à accéder aux médias.', 'secure-media-link'),
                    'not_whitelisted_ip' => __('L\'IP ne figure pas dans votre liste blanche alors qu\'une restriction IP est active.', 'secure-media-link'),
                    'not_whitelisted_domain' => __('Le domaine ne figure pas dans votre liste blanche alors qu\'une restriction de domaine est active.', 'secure-media-link'),
                    'expired' => __('Tentative d\'accès avec un lien expiré.', 'secure-media-link'),
                    'invalid_signature' => __('La signature du lien n\'est pas valide, possibilité de tentative de contournement.', 'secure-media-link')
                );
                
                $violation_type = isset($tracking['violation_type']) ? $tracking['violation_type'] : '';
                $explanation = isset($violation_explanations[$violation_type]) ? $violation_explanations[$violation_type] : __('Type de violation non reconnu.', 'secure-media-link');
                ?>
                <p style="margin-bottom: 0; color: #6c757d;"><?php echo esc_html($explanation); ?></p>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><?php _e('Cette notification a été générée automatiquement par Secure Media Link.', 'secure-media-link'); ?></p>
            <p>
                <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a> | 
                <a href="<?php echo esc_url($admin_url); ?>"><?php _e('Tableau de bord', 'secure-media-link'); ?></a>
            </p>
            <div class="timestamp">
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