# Guide de déploiement — NAB Pièces Auto (NPA)

Ce guide explique comment mettre en ligne le site chez le client, une fois son nom de domaine acheté.
Le projet est composé de deux parties hébergées séparément :

- **le front** : le site React (dossier `dist/` produit par `npm run build`), que voient les visiteurs ;
- **le back** : WordPress + WooCommerce, qui gère les produits, le panier, les commandes, les comptes et les paiements.

Le front ne fait qu'appeler l'API de WordPress (`/wp-json/...`). Les fonctionnalités sont décrites dans [FONCTIONNALITES.md](FONCTIONNALITES.md).

---

## 1. Choisir l'organisation des domaines (à décider en premier)

La connexion des clients utilise un **cookie HttpOnly** (`npa_auth`, voir `mu-plugins/auth.php`). Pour que tous les navigateurs l'acceptent, **iPhone compris**, le front et WordPress doivent être vus comme « le même site ». Trois cas :

| Cas | Exemple | `VITE_API_URL` au build | `wp-config.php` | Remarque |
|---|---|---|---|---|
| **A. Même domaine**, WordPress dans un sous-dossier | front `https://nabpieces.fr`, WordPress `https://nabpieces.fr/wp` | `https://nabpieces.fr/wp` | rien de plus | le plus simple techniquement |
| **B. Sous-domaine** ⭐ recommandé | front `https://www.nabpieces.fr`, WordPress `https://api.nabpieces.fr` | `https://api.nabpieces.fr` | `define('NPA_FRONT_ORIGINS', 'https://www.nabpieces.fr');` | un seul nom de domaine à acheter, deux hébergements possibles |
| **C. Domaines différents** | front `https://nabpieces.fr`, WordPress `https://npa.hebergeur.com` | `/woo-api` | rien de plus | **proxy obligatoire** sur l'hébergement du front (section 4.3) |

> ⚠️ **À ne pas faire :** mettre `NPA_AUTH_SAMESITE` à `'None'` pour éviter le proxy dans le cas C. Safari (iPhone, iPad, Mac) bloque ces cookies : les clients ne pourraient plus se connecter.

> ⚠️ **HTTPS obligatoire** sur le front et sur WordPress (certificat Let's Encrypt gratuit chez tous les hébergeurs). Sans HTTPS : pas de paiement Stripe, cookies refusés, e-mails en spam.

---

## 2. Installer WordPress (le back)

### 2.1 Extensions à installer et activer

- **WooCommerce**
- **WooCommerce Stripe Payment Gateway** (paiement par carte)
- **JWT Authentication for WP REST API** (génère le jeton de connexion, utilisé par `mu-plugins/auth.php`)
- **WP Mail SMTP** (envoi fiable des e-mails, section 6)

### 2.2 Réglages WordPress

- **Réglages → Permaliens** : choisir « Titre de la publication » (adresses propres pour `/wp-json/`).
- **Réglages → Général → Titre du site** : `NAB Pièces Auto` (apparaît dans les e-mails).
- **Réglages → Général → Langue** : Français.

### 2.3 `wp-config.php`

Ajouter avant la ligne `/* C'est tout, ne touchez pas à ce qui suit ! */` :

```php
/** Jeton de connexion : générer une clé longue et aléatoire, DIFFÉRENTE de celle de dev.
 *  Ex. https://api.wordpress.org/secret-key/1.1/salt/ (prendre une des valeurs). */
define('JWT_AUTH_SECRET_KEY', 'COLLER-ICI-UNE-CLE-ALEATOIRE');
define('JWT_AUTH_CORS_ENABLE', true);

/** Connexion par cookie (mu-plugins/auth.php) : garder 'Lax' (voir section 1). */
define('NPA_AUTH_SAMESITE', 'Lax');

/** Adresse du site React : liens des e-mails (mu-plugins/emails.php). Sans "/" à la fin. */
define('NPA_FRONT_URL', 'https://www.nabpieces.fr');

/** Seulement dans le cas B (sous-domaine) : adresse exacte du front autorisée à appeler l'API. */
// define('NPA_FRONT_ORIGINS', 'https://www.nabpieces.fr');

/** En production : ne jamais afficher les erreurs PHP aux visiteurs. */
define('WP_DEBUG', false);
```

Si l'hébergeur bloque l'en-tête `Authorization` (rare), ajouter dans le `.htaccess` de WordPress :
`SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`

### 2.4 Copier les mu-plugins

Copier **tout** le dossier `mu-plugins/` du projet dans `wp-content/mu-plugins/` du serveur (FTP ou gestionnaire de fichiers). En local, vérifier si ce dossier est un lien vers le projet ou une **copie** : dans le second cas, reporter également les modifications dans WordPress local. En production, la copie est à refaire à chaque mise à jour des mu-plugins.

### 2.5 Lien « mot de passe oublié »

Le lien envoyé par e-mail (`mu-plugins/reset-password.php`) est construit avec `NPA_FRONT_URL`. **Sans cette constante dans `wp-config.php`, il pointe vers `http://localhost:5173`** : les clients recevraient un lien qui ne fonctionne pas. Vérifier en production en demandant un nouveau mot de passe.

### 2.6 Configurer WooCommerce avec le script (une seule fois)

Le script `scripts/setup-woocommerce.php` règle en une commande : adresse du comptoir, livraison en France uniquement, **TVA 20 % avec prix saisis TTC**, zones et modes de livraison (retrait comptoir, navette PACA gratuite dès 80 €, Colissimo), paiement au comptoir réservé au retrait.

1. Vérifier les valeurs en haut du script (prix de la navette **à confirmer avec le client**, voir section 9).
2. Lancer en SSH, depuis le dossier du projet :
   ```bash
   php scripts/setup-woocommerce.php /chemin/vers/wordpress
   ```
3. Le script affiche « Terminé. ».

> ⚠️ **Une seule fois, avant que le client ne commence à gérer la boutique.** Le script supprime et recrée les zones « Marseille & PACA » et « France » : relancé plus tard, il effacerait les tarifs modifiés par le client.

**Sans accès SSH**, faire les mêmes réglages à la main :

| Où | Réglage |
|---|---|
| WooCommerce → Réglages → Général | Adresse : 158 Avenue de la Capelette, 13010 Marseille · Livrer uniquement en France · **Activer les taxes** · Devise Euro |
| WooCommerce → Réglages → TVA | Prix saisis **TTC** · Affichage TTC (boutique et panier) · Taux standard : France, 20 %, appliqué à la livraison |
| WooCommerce → Réglages → Expédition | Zone « Marseille & PACA » (codes postaux `13*`, `83*`, `84*`, `04*`, `05*`, `06*`) : Retrait comptoir Capelette (0 €), Livraison gratuite « Navette express 13 / PACA (24h) » dès 80 €, Forfait du **même nom** à 9,90 €, Forfait « Colissimo domicile 48h » à 7,50 € · Zone « France » : Retrait comptoir, Colissimo 7,50 € |
| WooCommerce → Réglages → Paiements | « Paiement à la livraison » : titre « Paiement au comptoir lors du retrait », activé seulement pour « Retrait comptoir » |

Les frais de port se saisissent **TTC** (le mu-plugin `cart.php` en déduit la TVA).

Le front React utilise le retrait classique WooCommerce (`local_pickup`) dans les zones
d'expédition, et non le retrait réservé au checkout par blocs. WooCommerce peut masquer
ce mode dans l'admin si la page de commande WordPress utilise les blocs et qu'aucun
retrait classique n'est encore actif. Ne pas relancer le script de configuration sur une
boutique déjà configurée pour contourner ce masquage.

Pour les comptes sans pays renseigné, `cart.php` utilise le pays de la boutique pour
estimer les tarifs et la TVA, comme pour les invités. Ce calcul ne sauvegarde aucune
adresse sur le compte et respecte les pays déjà renseignés. Le code postal du comptoir
n'est utilisé par défaut que pour une destination dans le pays de la boutique.

Vérification locale (WordPress avec retrait classique gratuit actif, produit simple
physique publié et taxable, prix saisi TTC) :

```powershell
php scripts/test-cart-shipping.php C:\wamp64\www\wooc_npa 12
```

Remplacer `12` par l'identifiant du produit de test. Ce test ne crée ni compte ni commande
et ne modifie pas les adresses clients. Vérifier également dans React le panier invité,
le panier connecté sans adresse, le passage retrait/livraison et l'affichage du tiret
dans le nom du retrait.

### Retrait : notification « Prête à retirer »

Le mu-plugin `pickup.php` et son fichier `includes/class-npa-pickup-email.php` ajoutent
le statut **Prête à retirer** aux commandes WooCommerce (compatible avec le stockage HPOS).
Copier également le sous-dossier `includes` lors du déploiement.

1. Vérifier le fuseau **Europe/Paris** dans Réglages → Général, puis l'adresse
   de retrait et les horaires dans WooCommerce → Réglages → E-mails →
   **Commande prête à retirer**. Ces champs sont préremplis pour le comptoir
   du 158 Avenue de la Capelette, 13010 Marseille et doivent rester cohérents
   avec les coordonnées affichées sur le front.
2. Préparer une commande avec retrait classique `local_pickup`, au statut
   **En préparation** ou **En attente**, puis la passer manuellement à **Prête à retirer**.
   Une commande en livraison, annulée, remboursée ou pas encore préparée est refusée,
   avec une note de commande et une entrée dans le journal `npa-pickup`.
3. Le changement déclenche l'e-mail client : numéro, adresse, horaires et date limite,
   **sept jours calendaires après la première mise à disposition**, à la même heure
   dans le fuseau du magasin. La date apparaît aussi dans l'admin et le compte React.
   Ce statut ne confirme pas à lui seul un paiement, notamment au comptoir.
4. Au retrait effectif, l'équipe passe la commande à **Terminée**. Aucun SMS, rappel,
   remboursement ou changement de statut automatique n'est déclenché à l'échéance.

Une sauvegarde ordinaire ne renvoie pas l'e-mail. L'action de commande
**Renvoyer l'e-mail « Prête à retirer »** permet une nouvelle tentative manuelle.
Un renvoi, ou un retour au statut prêt, conserve la date limite initiale.
Les échecs d'envoi sont notés sur la commande et dans le journal WooCommerce ;
un envoi accepté ne garantit pas la remise dans la boîte du client.
Une notification désactivée laisse également une note sur la commande.

Test local uniquement (commandes temporaires supprimées à la fin, tous les e-mails
interceptés sans livraison et aucun paiement ni appel transporteur) :

```powershell
php -d memory_limit=256M scripts/test-pickup.php C:\wamp64\www\wooc_npa
```

Le site hébergé doit encore être testé pour la remise réelle des e-mails, avec
des destinataires de test, avant utilisation par le client.

### 2.7 Contenus

- **Produits** : saisis par le client (prix TTC, marque, catégorie, véhicules compatibles dans la taxonomie « Véhicules »). Pour une démo, `scripts/seed-products.php` crée des produits de test — **ne pas l'utiliser sur la boutique réelle**.
- **Pages** lues par le site, avec ces **slugs exacts** :

  | Page du site | Slug WordPress |
  |---|---|
  | `/a-propos` | `a-propos` |
  | `/mentions-legales` | `mentions-legales` |
  | `/contact` | `contact` (texte d'introduction) |
  | `/cgv` | `cgv` |
  | `/cgu` | `cgu` |
  | `/politique-de-confidentialite` | `politique-de-confidentialite` |

  Pour récupérer celles du WordPress de dev : **Outils → Exporter → Pages**, puis **Outils → Importer → WordPress** sur la production. Si une page du même slug existe déjà (même dans la corbeille), la supprimer et vider la corbeille avant d'importer.
- **Mentions légales** : compléter tous les `[À COMPLÉTER]` (Kbis, directeur de publication, **hébergeur**, **médiateur de la consommation**, obligatoire pour vendre aux particuliers).

---

## 3. Paiement Stripe (compte du client)

L'argent arrive sur le compte Stripe **du client**, pas sur les vôtres.

1. Le client crée son compte sur stripe.com et l'**active** (SIRET, IBAN, pièce d'identité du gérant).
2. **WooCommerce → Réglages → Paiements → Stripe** : décocher le **mode test**, saisir les clés **live** (`pk_live_…`, `sk_live_…`, menu Développeurs → Clés API du tableau de bord Stripe).
3. **Webhook** : dans les réglages de l'extension Stripe, configurer le webhook (l'extension affiche l'URL à déclarer dans Stripe, de la forme `https://<wordpress>/?wc-api=wc_stripe`). Il permet à Stripe de prévenir WordPress (paiement confirmé, remboursement, litige).
4. Vérifier que `https://<wordpress>/wp-json/custom/v1/stripe` renvoie `"testmode": false` et une clé `pk_live_…`.

> Les clés secrètes ne vont **jamais** dans Git ni dans le front.

---

## 4. Mettre en ligne le front (React)

### 4.1 Construire le site

Sur votre poste, à la racine du projet :

```bash
npm install
VITE_API_URL=https://api.nabpieces.fr npm run build
```

Remplacer l'adresse selon le cas de la section 1 (cas A : `https://nabpieces.fr/wp` · cas B : `https://api.nabpieces.fr` · cas C : `/woo-api`).

> Pourquoi passer `VITE_API_URL` dans la commande ? En local, `.env.production` sert **aussi** de cible au proxy de Vite (`vite.config.js`) et pointe vers votre WordPress local. La variable passée dans la commande est prioritaire et évite de modifier ce fichier.
> Sous Windows (PowerShell) : `$env:VITE_API_URL="https://api.nabpieces.fr"; npm run build`

Le build refuse une API HTTP, sauf autorisation explicite réservée aux adresses
locales pour les tests. Voir [README.md](README.md) pour cette exception ; ne
jamais déployer le résultat correspondant. Le proxy `/woo-api` reste autorisé,
mais doit lui-même joindre WordPress en HTTPS sur l'hébergement. Ce contrôle ne
vérifie pas que le front est servi en HTTPS ni la validité des certificats :
vérifier les deux avant toute connexion ou donnée réelle. Ne pas redéployer
l'ancienne archive `npa-front-test.zip`, construite avec une API en HTTP.
Un build refusé peut laisser un ancien dossier `dist/` intact : ne le déployez
pas. Relancez un build réussi avec la bonne adresse et vérifiez son contenu.

Le résultat est dans `dist/` : c'est ce dossier qu'on envoie sur l'hébergement du front.

### 4.2 Toutes les adresses doivent renvoyer `index.html`

Le site est une application React : `/panier`, `/catalogue`, `/product/27`... n'existent pas comme fichiers. L'hébergeur doit renvoyer `index.html` pour toute adresse inconnue, sinon un rechargement de page donne une erreur 404.

**Apache** (`.htaccess` dans le dossier du site) :

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]
```

**Nginx** :

```nginx
location / {
    try_files $uri /index.html;
}
```

**Netlify** (fichier `public/_redirects`, copié dans `dist/` au build) :

```
/*  /index.html  200
```

**Vercel** (`vercel.json`) :

```json
{ "rewrites": [{ "source": "/(.*)", "destination": "/index.html" }] }
```

### 4.3 Cas C seulement : le proxy `/woo-api`

Le front transmet `/woo-api/...` à WordPress, exactement comme Vite en local. Pour le navigateur, tout vient du même site.

**Nginx** (à placer **avant** le bloc `location /`) :

```nginx
location /woo-api/ {
    proxy_pass https://npa.hebergeur.com/;
    proxy_set_header Host npa.hebergeur.com;
    proxy_ssl_server_name on;
    proxy_set_header X-Forwarded-For $remote_addr;
    proxy_set_header X-Forwarded-Proto https;
}
```

**Netlify** (`_redirects`, ligne à placer **avant** `/*`) :

```
/woo-api/*  https://npa.hebergeur.com/:splat  200
/*          /index.html                       200
```

**Vercel** (`vercel.json`) :

```json
{
  "rewrites": [
    { "source": "/woo-api/:path*", "destination": "https://npa.hebergeur.com/:path*" },
    { "source": "/(.*)", "destination": "/index.html" }
  ]
}
```

**Apache mutualisé** : un proxy vers un autre domaine demande les modules `mod_proxy` / `SSLProxyEngine`, rarement autorisés en `.htaccess` sur un mutualisé. Demander à l'hébergeur ; sinon, choisir le **cas B** (sous-domaine).

> ⚠️ **Limite connue du cas C :** derrière un proxy, WordPress voit l'adresse IP du proxy pour tous les visiteurs. Les limites anti-abus (connexion : 10 essais / 15 min, inscription et contact : 5 / heure) sont alors **partagées entre tous les visiteurs**. Il faudra adapter `auth.php`, `register.php` et `contact.php` pour lire l'en-tête `X-Forwarded-For` envoyé par **votre** proxy (et seulement lui). Ce problème n'existe pas dans les cas A et B.

---

## 5. Vérifier la connexion par cookie

Après la mise en ligne, dans le navigateur (outils de développement → Application → Cookies) :

- après connexion, un cookie `npa_auth` existe avec **HttpOnly** et **Secure** cochés ;
- `localStorage` ne contient **aucun** jeton ;
- `https://<adresse-api>/wp-json/custom/v1/auth/me` renvoie `"logged_in": true` quand on est connecté.

---

## 6. E-mails (WP Mail SMTP)

### Tests locaux sans envoi reel (Mailpit)

Le mu-plugin `local-mail.php` redirige les e-mails WordPress vers
`127.0.0.1:1025` uniquement lorsque le domaine de `home_url()` est `localhost`,
`127.0.0.1` ou `::1`. Sur l'hebergement, il ne change pas la configuration d'envoi.
Si Mailpit est arrete, l'envoi local echoue : aucun repli vers un envoi reel.

Installer [Mailpit](https://github.com/axllent/mailpit/releases) pour Windows,
puis le lancer dans PowerShell (adapter le chemin si necessaire) :

```powershell
& "$env:LOCALAPPDATA\NPA\Mailpit\mailpit.exe" --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025 --database "$env:LOCALAPPDATA\NPA\Mailpit\messages.db"
```

Garder le processus ouvert pendant les tests. La boite de capture est accessible
sur `http://127.0.0.1:8025`. Les interfaces sont limitees a cet ordinateur ;
ne pas configurer de relais ou de transfert SMTP. Tous les e-mails locaux,
quel que soit le destinataire, sont captures ici et ne sont pas livres aux
boites reelles. Utiliser uniquement des donnees fictives pour les tests.

Le bouton WooCommerce « Send a test email » permet de verifier la capture.
Pour l'e-mail de retrait, l'apercu generique ne contient pas une vraie commande
prete : il ne valide donc pas a lui seul le contenu avec son echeance.

### Envoi reel sur l'hebergement

Sans configuration, les e-mails partent souvent en spam, voire pas du tout.

1. Créer une boîte e-mail sur le domaine du client chez l'hébergeur (ex. `contact@nabpieces.fr`).
2. Dans la zone DNS du domaine, activer **SPF** et **DKIM** (souvent un clic chez l'hébergeur), idéalement **DMARC**.
3. **WP Mail SMTP → Réglages** : mailer « Autre SMTP », hôte / port / identifiant / mot de passe fournis par l'hébergeur ; « E-mail de l'expéditeur » = cette boîte, cocher « Forcer ».
4. **WP Mail SMTP → Outils → Test d'e-mail** vers une adresse Gmail : doit arriver en **boîte de réception**.
5. **WooCommerce → Réglages → E-mails** : nom et adresse d'expéditeur, logo, couleurs ; destinataire de « Nouvelle commande » = adresse du comptoir.

E-mails envoyés par le site : bienvenue à l'inscription (client), nouvelle commande (comptoir), commande en cours / terminée (client), mot de passe oublié (client), message du formulaire de contact (comptoir), avis à modérer (comptoir).

### Affichage fiscal du catalogue et du panier

Les prix principaux restent TTC pour tous, sans tarif professionnel distinct.
Le complement HT est affiche uniquement aux comptes connectes dont
`accountType` vaut `pro`. La fiche utilise `npa.price_excluding_tax`, calcule
par WooCommerce dans la devise et l'unite monetaire de la reponse produit.
Pour un produit variable, ce montant correspond a la variante la moins chere
du prix « Des » affiche. Le panier utilise les montants HT et TVA de la Store API,
sans taux fixe dans React ; les remises restent presentees separement.

Deployer `mu-plugins/products.php` et reconstruire le front ensemble.
Ces changements ne modifient aucun taux fiscal, ne mettent en place aucun
regime de TVA sur marge et ne constituent pas une validation comptable.

Verification locale du calcul HT (produits temporaires supprimes en fin de test) :

```powershell
php -d memory_limit=256M scripts/test-product-tax.php C:\wamp64\www\wooc_npa
```

Avec Vite lance, executer dans la console du navigateur sur le front local :

```javascript
(await import('/scripts/test-tax-display.mjs')).runTaxDisplayTests()
```

Ce test rend les composants avec des donnees fictives invite/particulier/pro,
sans connexion, commande ou modification du panier reel.

---

## 7. Liste de vérification finale

À faire sur le site en ligne, sur ordinateur **et sur iPhone** :

- [ ] La page d'accueil, le catalogue et une fiche produit s'affichent ; recharger une page (F5) ne donne pas d'erreur 404.
- [ ] Inscription d'un compte → e-mail de bienvenue reçu, lien « Mon compte » qui mène au site.
- [ ] Connexion / déconnexion, y compris **sur iPhone (Safari)**.
- [ ] Panier : ajout, quantités, choix retrait / navette / Colissimo, montants HT / TVA / TTC cohérents.
- [ ] Commande payée par carte (petite somme réelle, puis remboursement depuis Stripe) → page de confirmation, e-mail client, e-mail comptoir.
- [ ] Mot de passe oublié → e-mail reçu, lien qui mène au site (voir 2.5).
- [ ] Formulaire de contact → message dans l'admin (« Messages contact ») et e-mail reçu.
- [ ] Un message non lu ajoute une pastille au menu « Messages contact » ; son ouverture par un vendeur autorisé le marque lu pour toute l'équipe. Les anciens messages sans état de lecture sont initialement considérés non lus. Le compteur inclut les messages privés et ceux restaurés de la corbeille en brouillon ; il se met à jour lors du chargement d'une page d'administration, pas en temps réel.
- [ ] Avis client sur un produit acheté → en attente, puis visible après validation (Produits → Avis).
- [ ] Mentions légales, CGV, CGU, Contact affichent leur contenu.

---

## 8. Ce qu'il ne faut jamais faire

- Relancer `scripts/setup-woocommerce.php` sur la production après la livraison au client.
- Lancer `scripts/seed-products.php` sur la boutique réelle.
- Mettre une clé (Stripe, JWT, SMTP) dans Git ou dans le code React.
- Réutiliser la `JWT_AUTH_SECRET_KEY` de dev.
- Passer `NPA_AUTH_SAMESITE` à `'None'` (voir section 1).

---

## 9. Décisions à valider avec le client

Points laissés en suspens pendant le développement :

- **Prix de la navette** sous 80 € : 9,90 € TTC provisoire.
- **Panier sans adresse** : les frais sont estimés pour le 13010 tant que le client n'a pas saisi son adresse.
- **Inscription obligatoire** avant de commander ? Aujourd'hui, commande possible en invité.
- **Comptes professionnels** : le SIRET est vérifié (API publique recherche-entreprises), mais cela ne prouve pas que l'inscrit travaille dans l'entreprise. Prévoir une validation manuelle par le comptoir avant tout tarif pro.
- **TVA des professionnels** : un pro français paie la TVA (il la récupère) ; exonération seulement pour un pro d'un autre pays de l'UE (n° de TVA intracommunautaire) ou à l'export. À confirmer avec le comptable du client.
- **Avis Google** affichés dans le panier : texte statique dans `src/config/site.js`.
