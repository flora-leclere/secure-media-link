# Secure Media Link

Un plugin WordPress professionnel pour générer des liens signés, sécurisés et temporaires pour les médias avec tracking avancé et gestion des autorisations.

## 🚀 Fonctionnalités principales

### 🔐 Sécurité avancée
- **Liens cryptographiquement signés** avec RSA 2048-bit
- **Expiration automatique** des liens
- **Système de permissions** basé sur IP et domaines (whitelist/blacklist)
- **Blocage automatique** des adresses IP suspectes
- **Protection contre le hotlinking** non autorisé

### 📊 Tracking et statistiques
- **Suivi en temps réel** des téléchargements, copies et vues
- **Géolocalisation** des utilisateurs (IP → Pays/Ville)
- **Tableaux de bord** avec graphiques interactifs
- **Export des données** (CSV, JSON)
- **Notifications** de violations de sécurité

### 🎨 Formats de médias personnalisés
- **Redimensionnement automatique** selon vos besoins
- **Formats prédéfinis** (web, impression, réseaux sociaux)
- **Optimisation qualité/taille** configurable
- **Support multi-formats** (JPG, PNG, WebP)

### 🌐 Interface utilisateur
- **Frontend d'upload** avec shortcodes
- **Galeries sécurisées** personnalisables
- **Boutons de téléchargement** et copie
- **Modal de prévisualisation** (lightbox)
- **Interface admin** intuitive

### 🔌 API REST complète
- **Endpoints RESTful** pour intégration externe
- **Authentification par tokens** JWT
- **Rate limiting** configurable
- **Documentation OpenAPI** intégrée

## 📋 Prérequis

- **WordPress** 5.0 ou supérieur
- **PHP** 7.4 ou supérieur
- **Extension OpenSSL** activée
- **Extension GD** ou **ImageMagick** pour le traitement d'images
- **mod_rewrite** activé (pour les liens personnalisés)

## 🛠️ Installation

### Installation automatique

1. Téléchargez le plugin depuis le repository WordPress
2. Allez dans **Extensions > Ajouter**
3. Cliquez sur **Téléverser une extension**
4. Sélectionnez le fichier ZIP et cliquez **Installer**
5. **Activez** le plugin

### Installation manuelle

1. Décompressez le fichier ZIP
2. Uploadez le dossier `secure-media-link` dans `/wp-content/plugins/`
3. Activez le plugin depuis l'interface d'administration

### Configuration initiale

Après activation, le plugin :
- Créera automatiquement les tables en base de données
- Générera une paire de clés RSA pour la signature
- Installera les formats de médias par défaut
- Configurera les tâches cron automatiques

## ⚙️ Configuration

### Paramètres généraux

Allez dans **Secure Media > Paramètres** :

```
Durée d'expiration par défaut : 3 ans
Domaine personnalisé : https://media.example.com (optionnel)
```

### Sécurité

```
✅ Blocage automatique des IPs suspectes
Seuil de violations : 10 tentatives
Fenêtre de temps : 24 heures
```

### Notifications

```
Email de notification : admin@example.com
✅ Notifications de violations
✅ Notifications d'expiration
Délais de notification : 30,7,1 jours
```

### API

```
✅ Activer l'API REST
Limite de taux : 1000 requêtes/heure
```

## 🎯 Utilisation

### 1. Génération de liens sécurisés

#### Via l'interface admin

1. Allez dans **Secure Media > Médiathèque**
2. Sélectionnez vos médias
3. Choisissez **Actions groupées > Générer des liens**
4. Sélectionnez les formats désirés
5. Définissez la date d'expiration (optionnel)

#### Via l'API

```php
POST /wp-json/sml/v1/links
{
    "media_id": 123,
    "format_id": 2,
    "expires_at": "2025-12-31 23:59:59"
}
```

### 2. Shortcodes frontend

#### Formulaire d'upload

```wordpress
[sml_upload_form multiple="true" max_files="5" max_size="10485760"]
```

**Attributs disponibles :**
- `multiple` : Autoriser plusieurs fichiers (true/false)
- `max_files` : Nombre maximum de fichiers (défaut: 10)
- `max_size` : Taille maximale par fichier en bytes (défaut: 10MB)
- `allowed_types` : Types MIME autorisés (défaut: image/*)
- `show_preview` : Afficher la prévisualisation (true/false)
- `redirect_after` : URL de redirection après upload

#### Galerie de médias sécurisés

```wordpress
[sml_media_gallery ids="1,2,3" columns="3" lightbox="true"]
```

**Attributs disponibles :**
- `ids` : IDs des médias (séparés par virgules)
- `author` : Filtrer par auteur (ID utilisateur)
- `limit` : Nombre de médias à afficher (défaut: 12)
- `columns` : Nombre de colonnes (défaut: 3)
- `show_title` : Afficher le titre (true/false)
- `show_description` : Afficher la description (true/false)
- `show_buttons` : Afficher les boutons d'action (true/false)
- `lightbox` : Activer la lightbox (true/false)

#### Bouton de téléchargement

```wordpress
[sml_download_button link_id="123" text="Télécharger HD" icon="true"]
```

#### Bouton de copie de lien

```wordpress
[sml_copy_button media_id="123" format_id="2" text="Copier le lien" show_input="true"]
```

#### Médias de l'utilisateur

```wordpress
[sml_user_media user_id="456" limit="20" show_stats="true"]
```

#### Statistiques d'un média

```wordpress
[sml_media_stats media_id="123" period="month" show_chart="true"]
```

### 3. Gestion des permissions

#### IPs et domaines

Allez dans **Secure Media > Autorisations** :

**Whitelist :** Seules les IPs/domaines listés sont autorisés
**Blacklist :** Les IPs/domaines listés sont bloqués

#### Formats supportés

**IPs :**
- IP complète : `192.168.1.100`
- Plage CIDR : `192.168.1.0/24`
- Wildcard : `192.168.1.*`

**Domaines :**
- Domaine exact : `example.com`
- Sous-domaines : `*.example.com`
- Pattern : `*example*`

### 4. Formats de médias

#### Créer un format personnalisé

1. Allez dans **Secure Media > Formats**
2. Cliquez **Ajouter un Format**
3. Configurez les paramètres :

```
Nom : thumbnail_square
Type : web
Dimensions : 300x300 px
Qualité : 85%
Format : JPG
Mode : Recadrer (crop)
Position : Centre
```

#### Formats prédéfinis

Le plugin inclut des formats optimisés :

| Format | Type | Dimensions | Usage |
|--------|------|------------|-------|
| web_small | Web | 400px largeur | Aperçus |
| web_medium | Web | 800px largeur | Affichage standard |
| web_large | Web | 1200px largeur | Haute qualité |
| print_hd | Impression | 2400px largeur | Impression 300 DPI |
| social_square | Social | 1080x1080 | Instagram/Facebook |
| social_story | Social | 1080x1920 | Stories Instagram |

## 🔧 API REST

### Authentification

#### Génération d'un token

```bash
curl -X POST https://example.com/wp-json/sml/v1/token \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "permissions": ["read", "write"]}'
```

#### Utilisation du token

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://example.com/wp-json/sml/v1/media
```

### Endpoints principaux

#### Médias

```http
GET    /wp-json/sml/v1/media              # Liste des médias
GET    /wp-json/sml/v1/media/{id}         # Détails d'un média
GET    /wp-json/sml/v1/media/{id}/links   # Liens d'un média
```

#### Liens sécurisés

```http
POST   /wp-json/sml/v1/links              # Créer un lien
GET    /wp-json/sml/v1/links/{id}         # Détails d'un lien
DELETE /wp-json/sml/v1/links/{id}         # Supprimer un lien
```

#### Permissions

```http
GET    /wp-json/sml/v1/permissions        # Liste des permissions
POST   /wp-json/sml/v1/permissions        # Créer une permission
POST   /wp-json/sml/v1/permissions/check  # Vérifier des permissions
```

#### Statistiques

```http
GET    /wp-json/sml/v1/stats?period=month # Statistiques globales
POST   /wp-json/sml/v1/track              # Tracker une utilisation
```

### Exemples d'intégration

#### JavaScript

```javascript
const SMLClient = {
    baseURL: 'https://example.com/wp-json/sml/v1',
    token: 'YOUR_TOKEN',
    
    async getMedia() {
        const response = await fetch(`${this.baseURL}/media`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });
        return response.json();
    },
    
    async createSecureLink(mediaId, formatId) {
        const response = await fetch(`${this.baseURL}/links`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                media_id: mediaId,
                format_id: formatId
            })
        });
        return response.json();
    }
};
```

#### PHP

```php
class SMLClient {
    private $baseURL = 'https://example.com/wp-json/sml/v1';
    private $token;
    
    public function __construct($token) {
        $this->token = $token;
    }
    
    public function getMedia() {
        $response = wp_remote_get($this->baseURL . '/media', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    public function createSecureLink($mediaId, $formatId) {
        $response = wp_remote_post($this->baseURL . '/links', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'media_id' => $mediaId,
                'format_id' => $formatId
            ])
        ]);
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```

## 📈 Monitoring et maintenance

### Tableaux de bord

#### Dashboard principal

- **Statistiques en temps réel** (médias, liens, téléchargements, violations)
- **Graphiques d'activité** sur 30 jours
- **Violations récentes** avec actions rapides
- **Suggestions de sécurité** automatiques

#### Page de tracking

- **Historique complet** des accès
- **Filtres avancés** (date, action, IP, domaine)
- **Géolocalisation** des utilisateurs
- **Export de données** pour analyse

### Tâches automatiques

Le plugin programme plusieurs tâches cron :

| Tâche | Fréquence | Description |
|-------|-----------|-------------|
| `sml_check_expiring_links` | Quotidienne | Vérification des liens expirants |
| `sml_cleanup_cache` | Horaire | Nettoyage du cache expiré |
| `sml_scan_external_usage` | Configurable | Scan d'utilisation externe |
| `sml_generate_statistics` | Quotidienne | Génération des statistiques |

### Optimisation des performances

#### Cache multi-niveaux

1. **Cache mémoire** (10MB max) - Données fréquemment utilisées
2. **Cache objet WordPress** - Intégration avec Redis/Memcached
3. **Cache transients** - Persistance base de données

#### Optimisations base de données

- **Index optimisés** sur les colonnes de recherche fréquente
- **Partitioning automatique** des données de tracking anciennes
- **Requêtes optimisées** avec mise en cache des résultats

## 🛡️ Sécurité

### Mesures de protection

#### Cryptographie

- **Algorithme RSA** avec clés 2048-bit
- **Signatures SHA-256** pour intégrité des liens
- **Chiffrement AES-256** pour données sensibles
- **Tokens JWT** pour authentification API

#### Protection contre les attaques

- **Rate limiting** configurable par IP
- **Protection CSRF** avec nonces WordPress
- **Validation stricte** de tous les inputs
- **Échappement** de toutes les sorties
- **Blocage automatique** des IPs malveillantes

#### Audit et logging

- **Tracking complet** de tous les accès
- **Logs de sécurité** pour violations
- **Notifications temps réel** des incidents
- **Export forensique** des données

### Conformité RGPD

- **Anonymisation** des IPs après 30 jours
- **Droit à l'oubli** via nettoyage automatique
- **Consentement explicite** pour géolocalisation
- **Export des données** utilisateur sur demande

## 🚨 Dépannage

### Problèmes courants

#### Les liens ne fonctionnent pas

1. Vérifiez que **mod_rewrite** est activé
2. Contrôlez les **règles .htaccess**
3. Vérifiez les **permissions de fichiers**
4. Consultez les **logs d'erreur** WordPress

#### Erreurs de génération de liens

```
Solution 1: Vérifier la paire de clés RSA
wp_option: sml_key_pair

Solution 2: Régénérer les clés
WP-CLI: wp option delete sml_key_pair
Puis réactiver le plugin

Solution 3: Vérifier l'extension OpenSSL
phpinfo() | grep -i openssl
```

#### Performance dégradée

1. **Activer le cache** dans les paramètres
2. **Nettoyer les anciennes données** (> 1 an)
3. **Optimiser la base de données**
4. **Configurer un cache objet** externe

#### API non accessible

1. Vérifiez que **l'API est activée** dans les paramètres
2. Contrôlez les **permaliens** WordPress
3. Testez avec un **token valide**
4. Vérifiez le **rate limiting**

### Logs et debugging

#### Activer les logs de debug

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SML_DEBUG', true);
```

#### Fichiers de logs

```
/wp-content/debug.log          # Logs WordPress généraux
/wp-content/uploads/sml-logs/  # Logs spécifiques SML
```

#### Commandes WP-CLI utiles

```bash
# Statistiques du plugin
wp sml stats

# Vérifier l'intégrité de la base
wp sml db-check

# Nettoyer les données anciennes
wp sml cleanup --days=365

# Régénérer les clés de sécurité
wp sml regenerate-keys

# Tester les permissions
wp sml test-permissions --ip=192.168.1.1 --domain=example.com
```

## 🔄 Migration et sauvegarde

### Sauvegarde des données

#### Export complet

```bash
# Via WP-CLI
wp sml export --all --file=sml-backup.json

# Ou via interface admin
Secure Media > Paramètres > Export/Import
```

#### Données à sauvegarder

- **Tables de base de données** (8 tables préfixées `sml_`)
- **Clés de chiffrement** (option `sml_key_pair`)
- **Paramètres** (option `sml_settings`)
- **Fichiers générés** (/wp-content/uploads/sml-formats/)

### Migration vers un nouveau site

1. **Exporter** les données depuis l'ancien site
2. **Installer** le plugin sur le nouveau site
3. **Importer** les paramètres et données
4. **Régénérer** les liens si changement de domaine
5. **Tester** les fonctionnalités critiques

### Mise à jour du plugin

#### Avant la mise à jour

1. **Sauvegarder** la base de données
2. **Exporter** les paramètres
3. **Tester** sur un environnement de staging

#### Après la mise à jour

1. **Vérifier** la compatibilité des formats
2. **Contrôler** les nouvelles fonctionnalités
3. **Mettre à jour** les shortcodes si nécessaire

## 📚 Ressources

### Documentation développeur

- **Hooks et filtres** : `/docs/hooks.md`
- **API Reference** : `/docs/api.md`
- **Architecture** : `/docs/architecture.md`
- **Exemples de code** : `/examples/`

### Support et communauté

- **Issues GitHub** : [github.com/secure-media-link/issues](https://github.com/secure-media-link/issues)
- **Documentation** : [docs.secure-media-link.com](https://docs.secure-media-link.com)
- **Forum WordPress** : [wordpress.org/support/plugin/secure-media-link](https://wordpress.org/support/plugin/secure-media-link)

## 📄 Licence

Ce plugin est distribué sous licence **GPL v3** ou ultérieure.

## 🤝 Contribution

Les contributions sont les bienvenues ! Consultez `CONTRIBUTING.md` pour les guidelines.

### Développement local

```bash
# Cloner le repository
git clone https://github.com/secure-media-link/secure-media-link.git

# Installer les dépendances
composer install
npm install

# Lancer les tests
phpunit
npm test

# Build des assets
npm run build
```

## 🏆 Crédits

- **Développeur principal** : [Votre Nom]
- **Contributeurs** : Voir `CONTRIBUTORS.md`
- **Librairies tierces** : Voir `composer.json`

---

**Version actuelle** : 1.0.0  
**Compatibilité WordPress** : 5.0+  
**Compatibilité PHP** : 7.4+  
**Dernière mise à jour** : Juillet 2025