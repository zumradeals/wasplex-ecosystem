# API Marchand GeniusPay

Intégrez les paiements mobiles (Wave, Orange Money, MTN Money) et par carte dans vos applications en quelques minutes.

## Base URL

```
http://pay.genius.ci/api/v1/merchant
```

### 🧪 Sandbox
Testez votre intégration sans frais. Les transactions sont simulées.

### 🚀 Production
Transactions réelles. Activez le mode live dans vos paramètres.

---

## Authentification

Toutes les requêtes doivent inclure vos clés API dans les headers HTTP.

| Header | Description |
|--------|-------------|
| `X-API-Key` | Votre clé publique (`pk_sandbox_...` ou `pk_live_...`) |
| `X-API-Secret` | Votre clé secrète (`sk_sandbox_...` ou `sk_live_...`) |
| `Content-Type` | `application/json` |

### Exemple avec cURL

```bash
curl -X POST http://pay.genius.ci/api/v1/merchant/payments \
  -H "X-API-Key: pk_sandbox_xxxxxxxx" \
  -H "X-API-Secret: sk_sandbox_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"amount": 5000, "customer": {"phone": "+221771234567"}}'
```

---

## 🚀 Démarrage rapide - 2 lignes de code

La façon la plus simple d'intégrer GeniusPay. Créez un paiement et redirigez votre client vers notre page de checkout sécurisée où il choisira son moyen de paiement.

### ✨ Intégration minimale

```php
// 1. Créer le paiement (sans spécifier payment_method)
$response = Http::withHeaders([
    'X-API-Key' => 'pk_live_xxx',
    'X-API-Secret' => 'sk_live_xxx',
])->post('http://pay.genius.ci/api/v1/merchant/payments', [
    'amount' => 15000,
    'description' => 'Commande #123',
]);

// 2. Rediriger vers la page de checkout GeniusPay
return redirect($response['data']['checkout_url']);
```

**Avantages :**
- ✅ **Rapide** : Intégration en 2 lignes, aucune logique de paiement à gérer
- ✅ **Sécurisé** : Page de checkout hébergée et sécurisée par GeniusPay
- ✅ **Tous les moyens** : Wave, Orange, MTN, Moov, Cartes bancaires

> 💡 **Comment ça marche ?**
> En omettant le paramètre `payment_method`, GeniusPay génère une URL vers notre page de checkout personnalisée. Votre client y choisira son moyen de paiement préféré. Une fois le paiement effectué, il sera redirigé et vous recevrez un webhook.

---

## Endpoints API

### POST /payments - Initier un paiement

Crée une nouvelle transaction et retourne une URL de paiement.

#### Paramètres

| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| `amount` | number | ✓ | Montant en XOF (min: 200) |
| `currency` | string | - | Devise (défaut: XOF) |
| `payment_method` | string | - | `wave`, `paystack`, `orange_money`, `mtn_money` |
| `description` | string | - | Description du paiement (max 500 car.) |
| `customer.name` | string | - | Nom du client |
| `customer.email` | string | - | Email du client |
| `customer.phone` | string | - | Téléphone du client |
| `success_url` | string | - | URL de redirection après succès |
| `error_url` | string | - | URL de redirection après échec |
| `metadata` | object | - | Données personnalisées |

#### Requête

```json
{
  "amount": 15000,
  "payment_method": "wave",
  "description": "Commande #12345",
  "customer": {
    "name": "Amadou Diallo",
    "email": "amadou@example.com",
    "phone": "+221771234567"
  },
  "metadata": {
    "order_id": "12345"
  }
}
```

#### Réponse 201

```json
{
  "success": true,
  "data": {
    "id": 456,
    "reference": "MTX-A1B2C3D4E5",
    "amount": 15000,
    "fees": 450,
    "net_amount": 14550,
    "status": "pending",
    "payment_url": "https://wave.com/...",
    "gateway": "wave",
    "environment": "sandbox"
  }
}
```

---

### GET /payments - Lister les paiements

#### Paramètres de query

| Paramètre | Description |
|-----------|-------------|
| `status` | Filtrer par statut (`pending`, `completed`, `failed`) |
| `from` | Date de début (YYYY-MM-DD) |
| `to` | Date de fin (YYYY-MM-DD) |
| `per_page` | Résultats par page (défaut: 20, max: 100) |

#### Réponse 200

```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "reference": "MTX-A1B2C3D4E5",
      "amount": 15000,
      "status": "completed",
      "created_at": "2025-12-08T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 150
  }
}
```

---

### GET /payments/{reference} - Récupérer un paiement

Récupère les détails d'une transaction spécifique.

#### Réponse 200

```json
{
  "success": true,
  "data": {
    "id": 456,
    "reference": "MTX-A1B2C3D4E5",
    "amount": 15000,
    "fees": 450,
    "net_amount": 14550,
    "status": "completed",
    "payment_method": "wave",
    "customer": {
      "name": "Amadou Diallo",
      "phone": "+221771234567"
    },
    "created_at": "2025-12-08T10:30:00.000000Z",
    "completed_at": "2025-12-08T10:32:15.000000Z"
  }
}
```

---

### GET /account - Informations du compte

Récupère les informations de votre compte marchand.

#### Réponse 200

```json
{
  "success": true,
  "data": {
    "id": "uuid-merchant",
    "name": "Ma Boutique",
    "email": "contact@maboutique.com",
    "status": "active",
    "created_at": "2025-01-01T00:00:00.000000Z"
  }
}
```

---

### GET /account/balance - Solde du compte

Récupère le solde de votre compte.

#### Réponse 200

```json
{
  "success": true,
  "data": {
    "available": 1250000,
    "pending": 75000,
    "total": 1325000,
    "currency": "XOF"
  }
}
```

---

## Webhooks

Recevez des notifications en temps réel sur les événements de vos paiements.

### Endpoints Webhooks

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/webhooks` | Lister les webhooks |
| POST | `/webhooks` | Créer un webhook |
| PUT | `/webhooks/{id}` | Modifier un webhook |
| DELETE | `/webhooks/{id}` | Supprimer un webhook |
| POST | `/webhooks/{id}/test` | Tester un webhook |

### Événements webhook

| Événement | Description |
|-----------|-------------|
| `payment.initiated` | Paiement initié |
| `payment.success` | Paiement réussi |
| `payment.failed` | Paiement échoué |
| `payment.cancelled` | Paiement annulé |
| `payment.refunded` | Paiement remboursé |

### Payload webhook

```json
{
  "event": "payment.success",
  "timestamp": "2025-12-08T10:32:15.000000Z",
  "data": {
    "transaction": {
      "id": 456,
      "reference": "MTX-A1B2C3D4E5",
      "amount": 15000,
      "status": "completed",
      "customer": {
        "name": "Amadou Diallo",
        "phone": "+221771234567"
      },
      "metadata": {
        "order_id": "12345"
      }
    },
    "merchant": {
      "id": "uuid-merchant",
      "name": "Ma Boutique"
    },
    "environment": "sandbox"
  }
}
```

---

## Sécurité des webhooks

> ⚠️ **Important** : Ne traitez jamais un webhook sans vérifier sa signature.

### Headers webhook

| Header | Description |
|--------|-------------|
| `X-GeniusPay-Signature` | Signature HMAC-SHA256 |
| `X-GeniusPay-Timestamp` | Timestamp Unix |
| `X-GeniusPay-Event` | Type d'événement |

### Vérification PHP

```php
function verifySignature($payload, $signature, $secret) {
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_GENIUSPAY_SIGNATURE'];
$secret = 'whsec_xxxxxxxx';

if (!verifySignature($payload, $signature, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
// Traiter l'événement...
```

---

## Statuts des paiements

| Statut | Description |
|--------|-------------|
| `pending` | En attente de paiement |
| `processing` | En cours de traitement |
| `completed` | Paiement réussi |
| `failed` | Paiement échoué |
| `cancelled` | Paiement annulé |
| `refunded` | Paiement remboursé |

---

## Méthodes de paiement

| Code | Nom | Pays |
|------|-----|------|
| `wave` | Wave | SN, CI, ML, BF |
| `orange_money` | Orange Money | SN, CI, ML, BF |
| `mtn_money` | MTN Mobile Money | CI, BF |
| `card` | Carte bancaire (Visa, Mastercard) | International |

> 💡 **Conseil** : Omettez le paramètre `payment_method` pour laisser le client choisir sur la page de checkout GeniusPay. C'est l'approche recommandée pour maximiser les conversions.

---

## Codes d'erreur

| Code | HTTP | Description |
|------|------|-------------|
| `MISSING_API_KEY` | 401 | Clé API manquante |
| `INVALID_API_KEY` | 401 | Clé API invalide |
| `MERCHANT_INACTIVE` | 403 | Compte désactivé |
| `PAYMENT_INIT_FAILED` | 400 | Échec d'initialisation |
| `TRANSACTION_NOT_FOUND` | 404 | Transaction introuvable |
| `VALIDATION_ERROR` | 422 | Données invalides |

---

## Exemples d'intégration

### Mode Checkout (Recommandé)

Sans `payment_method`, le client choisit son moyen de paiement sur la page GeniusPay.

#### PHP / Laravel

```php
// 🚀 Intégration en 2 lignes
$response = Http::withHeaders([
    'X-API-Key' => 'pk_live_xxx',
    'X-API-Secret' => 'sk_live_xxx',
])->post('http://pay.genius.ci/api/v1/merchant/payments', [
    'amount' => 15000,
    'description' => 'Commande #123',
    // PAS de payment_method = Page checkout GeniusPay
]);

return redirect($response['data']['checkout_url']);
```

#### JavaScript

```javascript
const response = await fetch('http://pay.genius.ci/api/v1/merchant/payments', {
  method: 'POST',
  headers: {
    'X-API-Key': 'pk_live_xxx',
    'X-API-Secret': 'sk_live_xxx',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    amount: 15000,
    description: 'Commande #123'
  })
});

const data = await response.json();
window.location.href = data.data.checkout_url;
```

#### Python

```python
import requests

response = requests.post(
    'http://pay.genius.ci/api/v1/merchant/payments',
    headers={
        'X-API-Key': 'pk_live_xxx',
        'X-API-Secret': 'sk_live_xxx',
        'Content-Type': 'application/json'
    },
    json={
        'amount': 15000,
        'description': 'Commande #123'
    }
)

data = response.json()
checkout_url = data['data']['checkout_url']
```

### Mode Direct (Alternatif)

Avec `payment_method` spécifié, redirection directe vers le gateway.

```php
$response = Http::withHeaders([
    'X-API-Key' => 'pk_live_xxx',
    'X-API-Secret' => 'sk_live_xxx',
])->post('http://pay.genius.ci/api/v1/merchant/payments', [
    'amount' => 15000,
    'payment_method' => 'wave', // Redirection directe vers Wave
    'customer' => [
        'phone' => '+221771234567',
    ],
]);

return redirect($response['data']['payment_url']);
```

---

## Support

- **Email** : support@geniuspay.ci
- **Dashboard** : http://pay.genius.ci/dashboard
- **Documentation** : http://pay.genius.ci/docs/api

---

© 2025 GeniusPay. Tous droits réservés.
