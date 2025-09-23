# HORM Logger - Package de Monitoring HTTP pour Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ncoo-dev/horm-logger.svg?style=flat-square)](https://packagist.org/packages/ncoo-dev/horm-logger)
[![Tests](https://img.shields.io/github/actions/workflow/status/ncoo-dev/horm-logger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ncoo-dev/horm-logger/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/ncoo-dev/horm-logger.svg?style=flat-square)](https://packagist.org/packages/ncoo-dev/horm-logger)

**HORM Logger** est un package Laravel conçu pour capturer précisément toutes les requêtes HTTP entrantes et sortantes de vos applications. Il s'intègre parfaitement avec la plateforme HORM pour un monitoring complet de vos communications HTTP.

## 🎯 Fonctionnalités

### Capture Automatique des Requêtes HTTP
- **✅ Requêtes Sortantes** : Capture automatique de toutes les requêtes via `Http::` facade
- **✅ Requêtes Entrantes** : Monitoring via middleware des requêtes reçues
- **✅ Gestion des Échecs** : Logging des tentatives de connexion échouées
- **✅ Données Complètes** : Headers, payload, temps de réponse, codes de statut

### Données Capturées
- 📦 **Headers complets** (requête et réponse)
- 💾 **Contenu intégral** des requêtes et réponses stocké en JSON
- 🕒 **Timestamps précis** pour chaque transaction
- 🔄 **Direction** : distinction entrante/sortante
- 🏷️ **Type de requête** : classification automatique (response, request_failed, connection_failed)
- ⏱️ **Temps de réponse** et métriques de performance

Note: Les URL, méthodes HTTP et codes de statut sont extraits directement des données JSON de request/response pour éviter la redondance.

## 🚀 Installation

### 1. Installation via Composer

```bash
composer require ncoo-dev/horm-logger
```

### 2. Publication des Assets (Optionnel)

```bash
# Publier la configuration
php artisan vendor:publish --provider="NcooDev\HormLogger\HormLoggerServiceProvider" --tag=horm-logger-config

# Publier les migrations
php artisan vendor:publish --provider="NcooDev\HormLogger\HormLoggerServiceProvider" --tag=horm-logger-migrations

# Ou utiliser la commande d'installation complète
php artisan horm:install
```

### 3. Migration de la Base de Données

```bash
php artisan migrate
```

## ⚙️ Configuration

### Configuration de Base

Le package fonctionne directement après installation. Les requêtes HTTP sortantes sont automatiquement capturées.

### Configuration Avancée

Créez le fichier `config/horm.php` pour personnaliser le comportement :

```php
return [
    // Configuration de la base de données
    'database' => [
        'connection' => env('HORM_DB_CONNECTION', null),
        'table_name' => 'horm_entries',
    ],
    
    // Configuration du modèle
    'model' => [
        'entry' => \NcooDev\HormLogger\Models\Entry::class,
        'keep_history_for_days' => 2, // Rétention des logs
    ],
    
    // Configuration de l'endpoint d'export
    'endpoint' => [
        'enabled' => env('HORM_ENDPOINT_ENABLED', true),
        'secret' => env('HORM_ENDPOINT_SECRET', 'my-little-secret-with-horm'),
        'url' => env('HORM_ENDPOINT_URL', 'horm-logger-get-entries'),
    ],
];
```

### Variables d'Environnement

Ajoutez à votre fichier `.env` :

```env
# Configuration HORM Logger
HORM_DB_CONNECTION=mysql
HORM_ENDPOINT_ENABLED=true
HORM_ENDPOINT_SECRET=votre-secret-securise
HORM_ENDPOINT_URL=horm-logger-get-entries
```

## 🔧 Utilisation

### Capture Automatique (Requêtes Sortantes)

Les requêtes HTTP sortantes sont automatiquement capturées :

```php
// Ces requêtes seront automatiquement loggées
Http::get('https://api.example.com/users');
Http::post('https://api.example.com/orders', ['data' => 'value']);
Http::withHeaders(['Authorization' => 'Bearer token'])->get('https://api.example.com/secure');
```

### Capture Manuelle (Requêtes Entrantes)

Pour capturer les requêtes entrantes, ajoutez le middleware aux routes :

```php
// Dans vos routes (web.php ou api.php)
Route::middleware(['horm.save-log'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    Route::post('/api/orders', [OrderController::class, 'store']);
});

// Ou sur une route spécifique
Route::get('/api/monitored-endpoint', [Controller::class, 'method'])
    ->middleware('horm.save-log');
```

### Classification Automatique

Le package classe automatiquement les requêtes :

- **✅ RESPONSE** : Requêtes réussies (status < 400)
- **❌ REQUEST_FAILED** : Erreurs HTTP (status >= 400)  
- **🔌 CONNECTION_FAILED** : Échecs de connexion réseau

## 🗂️ Gestion des Données

### Nettoyage Automatique

Configurez le nettoyage automatique des logs dans `app/Console/Kernel.php` :

```php
protected function schedule(Schedule $schedule)
{
    // Nettoie les logs de plus de 2 jours (configurable)
    $schedule->command('horm:prune')->daily();
}
```

### Rétention Personnalisée

Modifiez la durée de rétention dans la configuration :

```php
'model' => [
    'keep_history_for_days' => 7, // Garder 7 jours
],
```

## 🔗 Intégration avec HORM

### Endpoint d'Export

Le package expose automatiquement un endpoint sécurisé pour l'export des données vers la plateforme HORM :

```
GET /horm-logger-get-entries?from=2024-01-01&to=2024-01-31
Headers: horm-check-secret: votre-secret-securise
```

### Sécurité de l'API

- **Authentification** : Header `horm-check-secret` requis
- **Limitation** : Maximum 1000 entrées par requête
- **Filtrage** : Par plage de dates via paramètres `from` et `to`

## 📊 Structure des Données

### Table `horm_entries`

```sql
CREATE TABLE horm_entries (
    id VARCHAR(36) PRIMARY KEY,
    direction ENUM('incoming', 'outgoing'),
    type ENUM('response', 'connection_failed', 'request_failed'),
    request LONGTEXT,   -- Données de requête en JSON
    response LONGTEXT,  -- Données de réponse en JSON
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

Note: Les données sont stockées en JSON non-sérialisé. Les informations comme l'URL, la méthode et le code de statut sont directement accessibles dans les champs JSON request et response.

### Format des Données Exportées

```json
{
    "data": [
        {
            "id": "uuid",
            "direction": "outgoing",
            "type": "response",
            "request": {
                "headers": {"Accept": "application/json"},
                "method": "GET",
                "url": "https://api.example.com/users",
                "body": null
            },
            "response": {
                "headers": {"Content-Type": "application/json"},
                "status": 200,
                "body": {"users": []}
            },
            "created_at": "2024-01-01T10:00:00Z"
        }
    ]
}
```

## ⚠️ Considérations Importantes

### Sécurité
- **Données Sensibles** : Le package capture et stocke l'intégralité des requêtes/réponses
- **Headers d'Authentification** : Peuvent contenir des tokens d'accès
- **Recommandation** : Utilisez une base de données dédiée avec chiffrement

### Performance
- **Impact Minimal** : Event listeners non-bloquants pour les requêtes sortantes
- **Middleware** : Léger impact sur les requêtes entrantes
- **Stockage** : Prévoyez l'espace disque nécessaire (données JSON)

### Conformité
- **RGPD** : Attention aux données personnelles capturées
- **Rétention** : Configurez la durée de conservation appropriée
- **Audit** : Logs disponibles pour audit de sécurité

## 🧪 Tests

```bash
composer test
```

## 📋 Changelog

Consultez [CHANGELOG](CHANGELOG.md) pour voir les dernières modifications.

## 🤝 Contribution

Pour contribuer au projet, consultez le guide de [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md).

## 🔒 Vulnérabilités de Sécurité

Consultez notre [politique de sécurité](../../security/policy) pour signaler des vulnérabilités.

## 👥 Crédits

- [Dominique Thomas](https://github.com/ncoo-dev)
- [Tous les contributeurs](../../contributors)

## 📄 License

MIT License - voir le fichier [LICENSE](LICENSE.md) pour plus d'informations.
