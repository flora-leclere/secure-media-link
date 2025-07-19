<?php
/**
 * Template email pour les notifications de nouvel upload
 * templates/email-upload.php
 * 
 * Variables disponibles :
 * $title - Titre de la notification
 * $message - Message principal
 * $data - Données du média (media_id)
 * $site_name - Nom du site
 * $site_url - URL du site
 * $admin_url - URL du tableau de bord admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$media_id = isset($data['media_id']) ? intval($data['media_id']) : 0;
$media = $media_id ? get_post($media_id) : null;
$upload_data = null;

// Récupérer les données d'upload frontend si disponibles
if ($media_id) {
    global $wpdb;
    $upload_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sml_frontend_uploads WHERE media_id = %d ORDER BY created_at DESC LIMIT 1",
        $media_id
    ));
}

$author = $media ? get_userdata($media->post_author) : null;
$file_size = 0;
$file_path = $media ? get_attached_file($media->ID) : '';
if ($file_path && file_exists($file_path)) {
    $file_size = filesize($file_path);
}

$metadata = $media ? wp_get_attachment_metadata($media->ID) : null;
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
            background: linear-gradient(135deg, #28a745, #20c997);
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
        .upload-icon {
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
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-title {
            font-weight: 600;
            color: #155724;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .alert-message {
            color: #155724;
            margin: 0;
        }
        .media-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .media-thumbnail {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
            border-radius: 8px;
            overflow: hidden;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .media-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-thumbnail .file-icon {
            font-size: 48px;
            color: #6c757d;
        }
        .media-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
        }
        .media-meta {
            color: #6c757d;
            font-size: 14px;
        }
        .media-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .media-details h3 {
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
        .author-info {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
        }
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
        }
        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .author-details h4 {
            margin: 0 0 5px 0;
            color: #0056b3;
        }
        .author-details .author-role {
            color: #6c757d;
            font-size: 14px;
        }
        .approval-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .approval-section h4 {
            color: #856404;
            margin-top: 0;
        }
        .approval-section p {
            color: #856404;
            margin-bottom: 15px;
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
            background-color: #ffc107;
            color: #212529;
        }
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .quick-actions .btn {
            flex: 1;
            margin: 0;
            padding: 10px;
            font-size: 14px;
        }
        .file-specs {
            background: #e2e3e5;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .spec-item {
            text-align: center;
        }
        .spec-value {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            display: block;
        }
        .spec-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .next-steps {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-left: 4px solid #17a2b8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .next-steps h4 {
            color: #0c5460;
            margin-top: 0;
        }
        .next-steps ol {
            color: #0c5460;
            margin-bottom: 0;
            padding-left: 20px;
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
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #ffc107;
            color: #212529;
        }
        .status-approved {
            background: #28a745;
            color: white;
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
            .specs-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .quick-actions {
                flex-direction: column;
            }
            .author-info {
                flex-direction: column;
                text-align: center;
            }
            .author-avatar {
                margin: 0 auto 10px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="upload-icon">
                📤
            </div>
            <h1><?php _e('Nouveau Média Uploadé', 'secure-media-link'); ?></h1>
            <div class="site-name"><?php echo esc_html($site_name); ?></div>
        </div>

        <!-- Content -->
        <div class="email-content">
            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-title"><?php echo esc_html($title); ?></div>
                <p class="alert-message"><?php echo esc_html($message); ?></p>
            </div>

            <!-- Media Preview -->
            <?php if ($media): ?>
            <div class="media-preview">
                <div class="media-thumbnail">
                    <?php if (wp_attachment_is_image($media->ID)): ?>
                        <?php echo wp_get_attachment_image($media->ID, 'thumbnail'); ?>
                    <?php else: ?>
                        <div class="file-icon">
                            <?php 
                            $icon_map = array(
                                'application/pdf' => '📄',
                                'application/msword' => '📝',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📝',
                                'application/zip' => '📦',
                                'video/' => '🎥',
                                'audio/' => '🎵'
                            );
                            
                            $icon = '📁'; // Default
                            foreach ($icon_map as $mime => $emoji) {
                                if (strpos($media->post_mime_type, $mime) === 0) {
                                    $icon = $emoji;
                                    break;
                                }
                            }
                            echo $icon;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="media-title"><?php echo esc_html($media->post_title); ?></div>
                <div class="media-meta">
                    <?php echo esc_html($media->post_mime_type); ?>
                    <?php if ($file_size > 0): ?>
                        • <?php echo sml_format_file_size($file_size); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Author Information -->
            <?php if ($author): ?>
            <div class="author-info">
                <div class="author-avatar">
                    <?php echo get_avatar($author->ID, 50); ?>
                </div>
                <div class="author-details">
                    <h4><?php echo esc_html($author->display_name); ?></h4>
                    <div class="author-role">
                        <?php 
                        $user_roles = $author->roles;
                        echo esc_html(ucfirst($user_roles[0] ?? 'Utilisateur'));
                        ?>
                        • <?php echo esc_html($author->user_email); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Media Details -->
            <?php if ($media): ?>
            <div class="media-details">
                <h3><?php _e('Détails du Fichier', 'secure-media-link'); ?></h3>
                
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Nom du fichier:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html(basename(get_attached_file($media->ID))); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><?php _e('Type MIME:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo esc_html($media->post_mime_type); ?></span>
                </div>

                <?php if ($file_size > 0): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Taille:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo sml_format_file_size($file_size); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($metadata && isset($metadata['width'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><?php _e('Dimensions:', 'secure-media-link'); ?></span>
                    <span class="detail-value"><?php echo $metadata['width']; ?> × <?php echo $metadata['height']; ?> pixels</span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label"><?php _e('Date d\'upload:', 'secure-media-link'); ?></span>
                    <span class="detail-value">
                        <?php echo date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            strtotime($media->post_date)
                        ); ?>
                    </span>
                </div>

                <?php if ($upload_data): ?>
                    <?php if (!empty($upload_data->caption)): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Légende:', 'secure-media-link'); ?></span>
                        <span class="detail-value"><?php echo esc_html($upload_data->caption); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($upload_data->description)): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Description:', 'secure-media-link'); ?></span>
                        <span class="detail-value"><?php echo esc_html($upload_data->description); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($upload_data->copyright)): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Copyright:', 'secure-media-link'); ?></span>
                        <span class="detail-value"><?php echo esc_html($upload_data->copyright); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Statut:', 'secure-media-link'); ?></span>
                        <span class="detail-value">
                            <span class="status-badge status-<?php echo esc_attr($upload_data->status); ?>">
                                <?php echo esc_html(ucfirst($upload_data->status)); ?>
                            </span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- File Specifications -->
            <?php if ($metadata && wp_attachment_is_image($media->ID)): ?>
            <div class="file-specs">
                <h4 style="margin-top: 0; text-align: center; color: #495057;">
                    <?php _e('Spécifications Techniques', 'secure-media-link'); ?>
                </h4>
                <div class="specs-grid">
                    <div class="spec-item">
                        <span class="spec-value"><?php echo number_format($metadata['width']); ?></span>
                        <span class="spec-label"><?php _e('Largeur (px)', 'secure-media-link'); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-value"><?php echo number_format($metadata['height']); ?></span>
                        <span class="spec-label"><?php _e('Hauteur (px)', 'secure-media-link'); ?></span>
                    </div>
                    <?php if (isset($metadata['image_meta']['aperture'])): ?>
                    <div class="spec-item">
                        <span class="spec-value">f/<?php echo $metadata['image_meta']['aperture']; ?></span>
                        <span class="spec-label"><?php _e('Ouverture', 'secure-media-link'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($metadata['image_meta']['iso'])): ?>
                    <div class="spec-item">
                        <span class="spec-value">ISO <?php echo $metadata['image_meta']['iso']; ?></span>
                        <span class="spec-label"><?php _e('Sensibilité', 'secure-media-link'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approval Section -->
            <?php if ($upload_data && $upload_data->status === 'pending'): ?>
            <div class="approval-section">
                <h4><?php _e('Action Requise : Approbation', 'secure-media-link'); ?></h4>
                <p><?php _e('Ce média attend votre approbation avant d\'être accessible. Vous pouvez l\'approuver, le modifier ou le rejeter depuis votre tableau de bord.', 'secure-media-link'); ?></p>
                
                <div class="quick-actions">
                    <a href="<?php echo esc_url($admin_url . '?page=sml-media-library&action=approve&media_id=' . $media_id); ?>" 
                       class="btn btn-success">
                        ✓ <?php _e('Approuver', 'secure-media-link'); ?>
                    </a>
                    <a href="<?php echo esc_url($admin_url . '?page=sml-media-library&action=review&media_id=' . $media_id); ?>" 
                       class="btn btn-warning">
                        👁 <?php _e('Examiner', 'secure-media-link'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Next Steps -->
            <div class="next-steps">
                <h4><?php _e('Prochaines Étapes', 'secure-media-link'); ?></h4>
                <ol>
                    <?php if ($upload_data && $upload_data->status === 'pending'): ?>
                        <li><?php _e('Examinez le média uploadé et ses métadonnées', 'secure-media-link'); ?></li>
                        <li><?php _e('Approuvez ou rejetez le média selon vos critères', 'secure-media-link'); ?></li>
                        <li><?php _e('Configurez les permissions d\'accès si nécessaire', 'secure-media-link'); ?></li>
                        <li><?php _e('Générez des liens sécurisés pour les formats requis', 'secure-media-link'); ?></li>
                    <?php else: ?>
                        <li><?php _e('Le média est maintenant disponible dans votre médiathèque', 'secure-media-link'); ?></li>
                        <li><?php _e('Générez des liens sécurisés selon vos besoins', 'secure-media-link'); ?></li>
                        <li><?php _e('Configurez les permissions d\'accès si nécessaire', 'secure-media-link'); ?></li>
                        <li><?php _e('Surveillez l\'utilisation via les statistiques', 'secure-media-link'); ?></li>
                    <?php endif; ?>
                </ol>
            </div>

            <!-- Security Recommendations -->
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h4 style="color: #856404; margin-top: 0;">
                    <?php _e('Recommandations de Sécurité', 'secure-media-link'); ?>
                </h4>
                <ul style="color: #856404; margin-bottom: 0;">
                    <li><?php _e('Vérifiez toujours le contenu des fichiers uploadés avant approbation', 'secure-media-link'); ?></li>
                    <li><?php _e('Configurez des restrictions d\'accès appropriées selon la sensibilité du contenu', 'secure-media-link'); ?></li>
                    <li><?php _e('Définissez des dates d\'expiration raisonnables pour les liens', 'secure-media-link'); ?></li>
                    <li><?php _e('Surveillez régulièrement les logs d\'accès pour détecter les anomalies', 'secure-media-link'); ?></li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="<?php echo esc_url($admin_url . '?page=sml-media-library' . ($media_id ? '&media_id=' . $media_id : '')); ?>" 
                   class="btn btn-primary">
                    <?php _e('Voir dans la Médiathèque', 'secure-media-link'); ?>
                </a>
                
                <?php if ($media_id): ?>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $media_id . '&action=edit')); ?>" 
                   class="btn btn-success">
                    <?php _e('Modifier le Média', 'secure-media-link'); ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Upload Statistics -->
            <?php if ($author): ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 30px;">
                <h4 style="margin-top: 0; color: #495057;">
                    <?php _e('Statistiques de l\'Utilisateur', 'secure-media-link'); ?>
                </h4>
                
                <?php
                // Récupérer les statistiques de l'utilisateur
                global $wpdb;
                $user_stats = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        COUNT(*) as total_uploads,
                        SUM(CASE WHEN fu.status = 'approved' THEN 1 ELSE 0 END) as approved_uploads,
                        SUM(CASE WHEN fu.status = 'pending' THEN 1 ELSE 0 END) as pending_uploads
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->prefix}sml_frontend_uploads fu ON p.ID = fu.media_id
                     WHERE p.post_author = %d AND p.post_type = 'attachment'",
                    $author->ID
                ));
                ?>
                
                <div class="specs-grid">
                    <div class="spec-item">
                        <span class="spec-value"><?php echo number_format($user_stats->total_uploads); ?></span>
                        <span class="spec-label"><?php _e('Total Uploads', 'secure-media-link'); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-value"><?php echo number_format($user_stats->approved_uploads); ?></span>
                        <span class="spec-label"><?php _e('Approuvés', 'secure-media-link'); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-value"><?php echo number_format($user_stats->pending_uploads); ?></span>
                        <span class="spec-label"><?php _e('En Attente', 'secure-media-link'); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-value">
                            <?php echo $user_stats->total_uploads > 0 ? round(($user_stats->approved_uploads / $user_stats->total_uploads) * 100) : 0; ?>%
                        </span>
                        <span class="spec-label"><?php _e('Taux Approbation', 'secure-media-link'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Auto-moderation Info -->
            <?php if ($upload_data && $upload_data->status === 'pending'): ?>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-left: 4px solid #17a2b8; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h4 style="color: #0c5460; margin-top: 0;">
                    <?php _e('Modération Automatique', 'secure-media-link'); ?>
                </h4>
                <p style="color: #0c5460; margin-bottom: 15px;">
                    <?php _e('Vous pouvez configurer des règles de modération automatique pour accélérer le processus d\'approbation des utilisateurs de confiance.', 'secure-media-link'); ?>
                </p>
                <a href="<?php echo esc_url($admin_url . '?page=sml-settings#moderation'); ?>" 
                   style="color: #0c5460; font-weight: 600; text-decoration: underline;">
                    <?php _e('Configurer la Modération Auto', 'secure-media-link'); ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Related Actions -->
            <div style="background: #e2e3e5; padding: 20px; border-radius: 6px; margin-top: 30px;">
                <h4 style="margin-top: 0; color: #495057;">
                    <?php _e('Actions Connexes', 'secure-media-link'); ?>
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong style="color: #495057; font-size: 14px;">
                            <?php _e('Gestion des Médias', 'secure-media-link'); ?>
                        </strong>
                        <div style="margin-top: 8px;">
                            <a href="<?php echo esc_url($admin_url . '?page=sml-media-library'); ?>" 
                               style="color: #007cba; text-decoration: none; font-size: 13px; display: block;">
                                → <?php _e('Médiathèque sécurisée', 'secure-media-link'); ?>
                            </a>
                            <a href="<?php echo esc_url($admin_url . '?page=sml-media-formats'); ?>" 
                               style="color: #007cba; text-decoration: none; font-size: 13px; display: block;">
                                → <?php _e('Formats disponibles', 'secure-media-link'); ?>
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <strong style="color: #495057; font-size: 14px;">
                            <?php _e('Sécurité & Permissions', 'secure-media-link'); ?>
                        </strong>
                        <div style="margin-top: 8px;">
                            <a href="<?php echo esc_url($admin_url . '?page=sml-permissions'); ?>" 
                               style="color: #007cba; text-decoration: none; font-size: 13px; display: block;">
                                → <?php _e('Gérer les permissions', 'secure-media-link'); ?>
                            </a>
                            <a href="<?php echo esc_url($admin_url . '?page=sml-tracking'); ?>" 
                               style="color: #007cba; text-decoration: none; font-size: 13px; display: block;">
                                → <?php _e('Logs de sécurité', 'secure-media-link'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Best Practices -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #6c757d;">
                <h4 style="margin-top: 0; color: #495057; font-size: 16px;">
                    <?php _e('Bonnes Pratiques', 'secure-media-link'); ?>
                </h4>
                <ul style="color: #6c757d; margin-bottom: 0; padding-left: 20px; font-size: 14px;">
                    <li><?php _e('Examinez toujours les métadonnées EXIF des images pour des informations sensibles', 'secure-media-link'); ?></li>
                    <li><?php _e('Utilisez des noms de fichiers descriptifs mais non sensibles', 'secure-media-link'); ?></li>
                    <li><?php _e('Configurez des formats appropriés selon l\'usage prévu du média', 'secure-media-link'); ?></li>
                    <li><?php _e('Définissez des permissions restrictives par défaut', 'secure-media-link'); ?></li>
                    <li><?php _e('Surveillez régulièrement l\'espace de stockage utilisé', 'secure-media-link'); ?></li>
                </ul>
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
                    __('Notification envoyée le %s à %s', 'secure-media-link'),
                    date_i18n(get_option('date_format')),
                    date_i18n(get_option('time_format'))
                ); ?>
            </div>
        </div>
    </div>
</body>
</html>