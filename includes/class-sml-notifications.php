<?php
/**
 * Classe pour la gestion des notifications
 * includes/class-sml-notifications.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class SML_Notifications {
    
    /**
     * Types de notifications disponibles
     */
    const TYPE_SECURITY_VIOLATION = 'security_violation';
    const TYPE_AUTO_BLOCKING = 'auto_blocking_applied';
    const TYPE_NEW_UPLOAD = 'new_upload';
    const TYPE_LINK_EXPIRING = 'link_expiring';
    const TYPE_LINK_EXPIRED = 'link_expired';
    const TYPE_EXTERNAL_USAGE = 'unauthorized_external_usage';
    const TYPE_MAINTENANCE = 'maintenance';
    const TYPE_ERROR = 'error';
    
    /**
     * Priorités des notifications
     */
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH = 3;
    const PRIORITY_CRITICAL = 4;
    
    /**
     * Initialisation
     */
    public static function init() {
        add_action('wp_ajax_sml_mark_notification_read', array(__CLASS__, 'ajax_mark_notification_read'));
        add_action('wp_ajax_sml_mark_all_notifications_read', array(__CLASS__, 'ajax_mark_all_notifications_read'));
        add_action('wp_ajax_sml_delete_notification', array(__CLASS__, 'ajax_delete_notification'));
        add_action('wp_ajax_sml_get_notifications', array(__CLASS__, 'ajax_get_notifications'));
        
        // Hooks pour les notifications automatiques
        add_action('sml_check_expiring_links', array(__CLASS__, 'check_expiring_links'));
        add_action('admin_bar_menu', array(__CLASS__, 'add_admin_bar_notification'), 999);
        add_action('admin_notices', array(__CLASS__, 'show_admin_notices'));
        
        // Programmation des vérifications automatiques
        if (!wp_next_scheduled('sml_check_expiring_links')) {
            wp_schedule_event(time(), 'daily', 'sml_check_expiring_links');
        }
    }
    
    /**
     * Ajouter une nouvelle notification
     */
    public static function add_notification($type, $title, $message, $data = array(), $user_id = null, $priority = self::PRIORITY_NORMAL) {
        global $wpdb;
        
        // Validation du type
        if (!self::is_valid_type($type)) {
            return false;
        }
        
        // Si pas d'utilisateur spécifié, envoyer à tous les administrateurs
        if ($user_id === null) {
            $admin_users = get_users(array('role' => 'administrator'));
            
            foreach ($admin_users as $admin) {
                self::add_notification($type, $title, $message, $data, $admin->ID, $priority);
            }
            
            return true;
        }
        
        // Éviter les doublons récents (même type, même utilisateur, moins de 5 minutes)
        $recent_duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sml_notifications 
             WHERE type = %s 
             AND user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             AND title = %s
             LIMIT 1",
            $type,
            $user_id,
            $title
        ));
        
        if ($recent_duplicate) {
            return false; // Éviter le spam
        }
        
        // Insérer la notification
        $notification_data = array(
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => is_array($data) ? json_encode($data) : $data,
            'user_id' => $user_id,
            'priority' => $priority,
            'is_read' => 0
        );
        
        $result = $wpdb->insert($wpdb->prefix . 'sml_notifications', $notification_data);
        
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            // Envoyer par email si configuré
            self::maybe_send_email_notification($type, $title, $message, $data, $user_id, $priority);
            
            // Déclencher une action pour d'autres plugins
            do_action('sml_notification_added', $notification_id, $type, $title, $message, $data, $user_id, $priority);
            
            return $notification_id;
        }
        
        return false;
    }
    
    /**
     * Obtenir les notifications d'un utilisateur
     */
    public static function get_notifications($user_id = null, $limit = 50, $unread_only = false) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $where_clauses = array('user_id = %d');
        $params = array($user_id);
        
        if ($unread_only) {
            $where_clauses[] = 'is_read = 0';
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        $sql = "SELECT * FROM {$wpdb->prefix}sml_notifications 
                {$where_sql}
                ORDER BY priority DESC, created_at DESC 
                LIMIT %d";
        
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Marquer une notification comme lue
     */
    public static function mark_as_read($notification_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'sml_notifications',
            array('is_read' => 1),
            array(
                'id' => $notification_id,
                'user_id' => $user_id
            )
        );
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public static function mark_all_as_read($user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'sml_notifications',
            array('is_read' => 1),
            array('user_id' => $user_id)
        );
    }
    
    /**
     * Supprimer une notification
     */
    public static function delete_notification($notification_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->delete(
            $wpdb->prefix . 'sml_notifications',
            array(
                'id' => $notification_id,
                'user_id' => $user_id
            )
        );
    }
    
    /**
     * Obtenir le nombre de notifications non lues
     */
    public static function get_unread_count($user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_notifications 
             WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
    }
    
    /**
     * Vérifier si un type de notification est valide
     */
    private static function is_valid_type($type) {
        $valid_types = array(
            self::TYPE_SECURITY_VIOLATION,
            self::TYPE_AUTO_BLOCKING,
            self::TYPE_NEW_UPLOAD,
            self::TYPE_LINK_EXPIRING,
            self::TYPE_LINK_EXPIRED,
            self::TYPE_EXTERNAL_USAGE,
            self::TYPE_MAINTENANCE,
            self::TYPE_ERROR
        );
        
        return in_array($type, $valid_types);
    }
    
    /**
     * Envoyer une notification par email si configuré
     */
    private static function maybe_send_email_notification($type, $title, $message, $data, $user_id, $priority) {
        $settings = get_option('sml_settings', array());
        
        // Vérifier si les notifications email sont activées
        if (!isset($settings['violation_notifications']) || !$settings['violation_notifications']) {
            return;
        }
        
        // Types de notifications qui déclenchent un email
        $email_types = array(
            self::TYPE_SECURITY_VIOLATION,
            self::TYPE_AUTO_BLOCKING,
            self::TYPE_EXTERNAL_USAGE,
            self::TYPE_LINK_EXPIRED
        );
        
        if (!in_array($type, $email_types)) {
            return;
        }
        
        // Obtenir l'email de l'utilisateur ou l'email par défaut
        if ($user_id) {
            $user = get_userdata($user_id);
            $email = $user->user_email;
        } else {
            $email = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        }
        
        if (!$email) {
            return;
        }
        
        // Préparer le sujet et le contenu
        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] %s', $site_name, $title);
        
        // Charger le template email approprié
        $email_content = self::get_email_template($type, $title, $message, $data);
        
        // Headers pour l'email HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        );
        
        // Envoyer l'email
        wp_mail($email, $subject, $email_content, $headers);
    }
    
    /**
     * Obtenir le template email pour un type de notification
     */
    private static function get_email_template($type, $title, $message, $data) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $admin_url = admin_url('admin.php?page=secure-media-link');
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . esc_html($title) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .alert { padding: 15px; margin: 15px 0; border-left: 4px solid #d63384; background: #f8d7da; }
                .info { border-left-color: #0dcaf0; background: #d1ecf1; }
                .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . esc_html($site_name) . '</h1>
                    <p>Secure Media Link - Notification</p>
                </div>
                
                <div class="content">
                    <h2>' . esc_html($title) . '</h2>
                    
                    <div class="' . (in_array($type, array(self::TYPE_SECURITY_VIOLATION, self::TYPE_AUTO_BLOCKING)) ? 'alert' : 'info') . '">
                        ' . nl2br(esc_html($message)) . '
                    </div>';
        
        // Ajouter des détails spécifiques selon le type
        switch ($type) {
            case self::TYPE_SECURITY_VIOLATION:
                if (isset($data['tracking_data'])) {
                    $tracking = $data['tracking_data'];
                    $template .= '
                    <h3>Détails de la violation :</h3>
                    <ul>
                        <li><strong>IP :</strong> ' . esc_html($tracking['ip_address']) . '</li>
                        <li><strong>Domaine :</strong> ' . esc_html($tracking['domain']) . '</li>
                        <li><strong>Action :</strong> ' . esc_html($tracking['action_type']) . '</li>
                        <li><strong>Type de violation :</strong> ' . esc_html($tracking['violation_type']) . '</li>
                    </ul>';
                }
                break;
                
            case self::TYPE_NEW_UPLOAD:
                if (isset($data['media_id'])) {
                    $media = get_post($data['media_id']);
                    if ($media) {
                        $template .= '
                        <h3>Détails du nouveau média :</h3>
                        <ul>
                            <li><strong>Titre :</strong> ' . esc_html($media->post_title) . '</li>
                            <li><strong>Type :</strong> ' . esc_html($media->post_mime_type) . '</li>
                            <li><strong>Auteur :</strong> ' . esc_html(get_userdata($media->post_author)->display_name) . '</li>
                        </ul>';
                    }
                }
                break;
                
            case self::TYPE_EXTERNAL_USAGE:
                if (isset($data['domain']) && isset($data['url'])) {
                    $template .= '
                    <h3>Détails de l\'utilisation externe :</h3>
                    <ul>
                        <li><strong>Domaine :</strong> ' . esc_html($data['domain']) . '</li>
                        <li><strong>URL :</strong> <a href="' . esc_url($data['url']) . '">' . esc_html($data['url']) . '</a></li>
                    </ul>';
                }
                break;
        }
        
        $template .= '
                    <p style="margin-top: 30px;">
                        <a href="' . esc_url($admin_url) . '" class="button">Voir le tableau de bord</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>Cette notification a été générée automatiquement par Secure Media Link.</p>
                    <p><a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Vérifier les liens qui vont expirer
     */
    public static function check_expiring_links() {
        $settings = get_option('sml_settings', array());
        
        if (!isset($settings['expiry_notifications']) || !$settings['expiry_notifications']) {
            return;
        }
        
        $notice_days = isset($settings['expiry_notice_days']) ? $settings['expiry_notice_days'] : array(30, 7, 1);
        
        global $wpdb;
        
        foreach ($notice_days as $days) {
            // Chercher les liens qui expirent dans X jours
            $expiring_links = $wpdb->get_results($wpdb->prepare(
                "SELECT sl.*, p.post_title, mf.name as format_name
                 FROM {$wpdb->prefix}sml_secure_links sl
                 LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
                 LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
                 WHERE sl.is_active = 1 
                 AND DATE(sl.expires_at) = DATE(DATE_ADD(NOW(), INTERVAL %d DAY))
                 AND sl.id NOT IN (
                     SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.link_id')) AS UNSIGNED)
                     FROM {$wpdb->prefix}sml_notifications 
                     WHERE type = %s 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
                     AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.link_id')) IS NOT NULL
                 )",
                $days,
                self::TYPE_LINK_EXPIRING
            ));
            
            foreach ($expiring_links as $link) {
                $title = sprintf(
                    __('Lien expirant dans %d jour(s)', 'secure-media-link'),
                    $days
                );
                
                $message = sprintf(
                    __('Le lien sécurisé pour "%s" (format: %s) expire le %s.', 'secure-media-link'),
                    $link->post_title,
                    $link->format_name,
                    date_i18n(get_option('date_format'), strtotime($link->expires_at))
                );
                
                $data = array(
                    'link_id' => $link->id,
                    'media_id' => $link->media_id,
                    'format_id' => $link->format_id,
                    'expires_at' => $link->expires_at,
                    'days_remaining' => $days
                );
                
                self::add_notification(
                    self::TYPE_LINK_EXPIRING,
                    $title,
                    $message,
                    $data,
                    null,
                    $days <= 1 ? self::PRIORITY_HIGH : self::PRIORITY_NORMAL
                );
            }
        }
        
        // Chercher les liens qui ont expiré aujourd'hui
        $expired_links = $wpdb->get_results(
            "SELECT sl.*, p.post_title, mf.name as format_name
             FROM {$wpdb->prefix}sml_secure_links sl
             LEFT JOIN {$wpdb->posts} p ON sl.media_id = p.ID
             LEFT JOIN {$wpdb->prefix}sml_media_formats mf ON sl.format_id = mf.id
             WHERE sl.is_active = 1 
             AND DATE(sl.expires_at) = CURDATE()
             AND sl.id NOT IN (
                 SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.link_id')) AS UNSIGNED)
                 FROM {$wpdb->prefix}sml_notifications 
                 WHERE type = '" . self::TYPE_LINK_EXPIRED . "'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
                 AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.link_id')) IS NOT NULL
             )"
        );
        
        foreach ($expired_links as $link) {
            // Désactiver le lien expiré
            $wpdb->update(
                $wpdb->prefix . 'sml_secure_links',
                array('is_active' => 0),
                array('id' => $link->id)
            );
            
            $title = __('Lien expiré', 'secure-media-link');
            
            $message = sprintf(
                __('Le lien sécurisé pour "%s" (format: %s) a expiré et a été désactivé.', 'secure-media-link'),
                $link->post_title,
                $link->format_name
            );
            
            $data = array(
                'link_id' => $link->id,
                'media_id' => $link->media_id,
                'format_id' => $link->format_id,
                'expired_at' => $link->expires_at
            );
            
            self::add_notification(
                self::TYPE_LINK_EXPIRED,
                $title,
                $message,
                $data,
                null,
                self::PRIORITY_NORMAL
            );
        }
    }
    
    /**
     * Ajouter l'indicateur de notifications dans la barre d'admin
     */
    public static function add_admin_bar_notification($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $unread_count = self::get_unread_count();
        
        if ($unread_count > 0) {
            $wp_admin_bar->add_node(array(
                'id' => 'sml-notifications',
                'title' => '<span class="ab-icon dashicons dashicons-shield"></span> <span class="ab-label">' . $unread_count . '</span>',
                'href' => admin_url('admin.php?page=secure-media-link'),
                'meta' => array(
                    'title' => sprintf(
                        _n('%d notification Secure Media Link', '%d notifications Secure Media Link', $unread_count, 'secure-media-link'),
                        $unread_count
                    )
                )
            ));
        }
    }
    
    /**
     * Afficher les notices admin pour les notifications critiques
     */
    public static function show_admin_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Afficher seulement les notifications critiques récentes
        $critical_notifications = self::get_notifications(get_current_user_id(), 3, true);
        
        foreach ($critical_notifications as $notification) {
            if ($notification->priority >= self::PRIORITY_HIGH && 
                strtotime($notification->created_at) > (time() - 3600)) { // Dernière heure
                
                $class = $notification->priority >= self::PRIORITY_CRITICAL ? 'notice-error' : 'notice-warning';
                
                echo '<div class="notice ' . $class . ' is-dismissible" data-notification-id="' . $notification->id . '">';
                echo '<p><strong>' . esc_html($notification->title) . '</strong></p>';
                echo '<p>' . esc_html($notification->message) . '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Nettoyer les anciennes notifications
     */
    public static function cleanup_old_notifications($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sml_notifications 
             WHERE created_at < %s AND is_read = 1",
            $cutoff_date
        ));
    }
    
    /**
     * Obtenir les statistiques des notifications
     */
    public static function get_statistics($user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $stats = array();
        
        // Total notifications
        $stats['total'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_notifications WHERE user_id = %d",
            $user_id
        ));
        
        // Non lues
        $stats['unread'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sml_notifications WHERE user_id = %d AND is_read = 0",
            $user_id
        ));
        
        // Par type
        $stats['by_type'] = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) as count
             FROM {$wpdb->prefix}sml_notifications 
             WHERE user_id = %d
             GROUP BY type
             ORDER BY count DESC",
            $user_id
        ));
        
        // Par priorité
        $stats['by_priority'] = $wpdb->get_results($wpdb->prepare(
            "SELECT priority, COUNT(*) as count
             FROM {$wpdb->prefix}sml_notifications 
             WHERE user_id = %d
             GROUP BY priority
             ORDER BY priority DESC",
            $user_id
        ));
        
        return $stats;
    }
    
    /**
     * AJAX - Marquer une notification comme lue
     */
    public static function ajax_mark_notification_read() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        
        if (self::mark_as_read($notification_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erreur lors de la mise à jour', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Marquer toutes les notifications comme lues
     */
    public static function ajax_mark_all_notifications_read() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        if (self::mark_all_as_read()) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erreur lors de la mise à jour', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Supprimer une notification
     */
    public static function ajax_delete_notification() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $notification_id = intval($_POST['notification_id']);
        
        if (self::delete_notification($notification_id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Erreur lors de la suppression', 'secure-media-link'));
        }
    }
    
    /**
     * AJAX - Obtenir les notifications
     */
    public static function ajax_get_notifications() {
        check_ajax_referer('sml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Accès refusé', 'secure-media-link'));
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $unread_only = isset($_POST['unread_only']) ? (bool)$_POST['unread_only'] : false;
        
        $notifications = self::get_notifications(null, $limit, $unread_only);
        $unread_count = self::get_unread_count();
        
        // Formater les notifications pour l'affichage
        foreach ($notifications as &$notification) {
            $notification->formatted_date = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($notification->created_at)
            );
            
            $notification->priority_label = self::get_priority_label($notification->priority);
            $notification->type_label = self::get_type_label($notification->type);
        }
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ));
    }
    
    /**
     * Obtenir le libellé d'une priorité
     */
    private static function get_priority_label($priority) {
        switch ($priority) {
            case self::PRIORITY_LOW:
                return __('Faible', 'secure-media-link');
            case self::PRIORITY_NORMAL:
                return __('Normale', 'secure-media-link');
            case self::PRIORITY_HIGH:
                return __('Élevée', 'secure-media-link');
            case self::PRIORITY_CRITICAL:
                return __('Critique', 'secure-media-link');
            default:
                return __('Inconnue', 'secure-media-link');
        }
    }
    
    /**
     * Obtenir le libellé d'un type
     */
    private static function get_type_label($type) {
        switch ($type) {
            case self::TYPE_SECURITY_VIOLATION:
                return __('Violation de sécurité', 'secure-media-link');
            case self::TYPE_AUTO_BLOCKING:
                return __('Blocage automatique', 'secure-media-link');
            case self::TYPE_NEW_UPLOAD:
                return __('Nouveau média', 'secure-media-link');
            case self::TYPE_LINK_EXPIRING:
                return __('Lien expirant', 'secure-media-link');
            case self::TYPE_LINK_EXPIRED:
                return __('Lien expiré', 'secure-media-link');
            case self::TYPE_EXTERNAL_USAGE:
                return __('Utilisation externe', 'secure-media-link');
            case self::TYPE_MAINTENANCE:
                return __('Maintenance', 'secure-media-link');
            case self::TYPE_ERROR:
                return __('Erreur', 'secure-media-link');
            default:
                return __('Autre', 'secure-media-link');
        }
    }
}