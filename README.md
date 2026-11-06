# Storefront WooCommerce React

Ce projet est un front-end React/Vite prêt à brancher sur votre back-office WooCommerce.

## Démarrage

1. Installez les dépendances : `npm install`
2. Lancez le serveur de dev : `npm run dev`
3. Consultez le site en developpement sur : `http://localhost:5173`

## Configuration API

Les fichiers `.env` ne sont pas versionnés. Copiez `.env.example` pour créer les deux fichiers suivants :

- `.env.development` : `VITE_API_URL=/woo-api` (le navigateur passe par le proxy Vite, ce qui évite les erreurs CORS)
- `.env.production` : `VITE_API_URL=http://localhost/votre-wordpress`, c'est ici que vous renseignerez l'adresse du site woocommerce (cible du proxy en dev, URL de l'API au build)

Relancez `npm run dev` après chaque modification d'un fichier `.env`.

### Performances locales avec Wamp

Si les appels WordPress prennent plusieurs secondes, verifiez qu'OPcache est
effectivement charge dans le PHP utilise par Apache, pas seulement dans PHP CLI.
Pour l'utilisation courante, `xdebug.mode=off` dans ce PHP evite le cout de
l'instrumentation. Reactivez Xdebug lorsque vous avez besoin de ses diagnostics.
Redemarrez le service Apache apres ces changements ; la premiere requete peut
rester plus lente pendant le remplissage du cache. Ces reglages Wamp sont locaux
et ne sont pas inclus dans le depot.

## Panier et connexion

A l'inscription ou a la connexion, les articles du panier invite sont repris dans
le panier du compte via l'API WooCommerce. Pour un meme article ou une meme
variation, la quantite la plus elevee des deux paniers est conservee, sans cumul
ni doublon. Les articles deja presents uniquement dans le compte sont conserves.
Les codes promotionnels sont revalides par WooCommerce et le mode de livraison
invite est repris s'il reste disponible. Un echec de reprise est signale au client.

Tests de regression du changement d'identite : `node --test`.

## Build

`npm run build`

## Config wordpress

1. creez un dossier /wooc sur le server pour deployer wordpress/woocommerce.
2. installez les plug-in WooCommerce, WooCommerce Stripe Gateway, WooCommerce Tax, JWT Authentication for WP-API et Advanced Custom Fields (version gratuite).
3. renseignez le fichier wp-config.php.
4. copiez le dossier /mu-plugins dans /wp-content.

## Contenus modifiables

Avec ACF activé et les fichiers `mu-plugins` installés :

- **Pages > Accueil** : coordonnées, horaires, bandeau, textes de l’accueil et message de confirmation du formulaire de contact. La page « Accueil » est créée automatiquement si elle n’existe pas.
- **Produits > Modifier un produit** : sous-titre, note de compatibilité et logo de marque personnalisés. Le titre, les descriptions, les images et les données commerciales restent gérés dans WooCommerce.
- **Produits > Véhicules** : renseignez la hiérarchie marque, modèle, motorisation, puis associez les véhicules aux produits. Le formulaire d'accueil indique lorsqu'une liste est vide ou ne peut pas être chargée ; la recherche par référence ou mot-clé reste disponible.
- **Pages Contact et légales** : des brouillons vides sont créés automatiquement s’ils n’existent pas. Rédigez leur contenu dans WordPress puis publiez-les ; le front-end récupère le contenu des pages publiées. Les textes légaux doivent être fournis et validés par le propriétaire du site.

## Wireframes

[Le lien Figma](https://www.figma.com/design/118Mfb9r0dApZskAojI4c4/Untitled?node-id=0-1&p=f)
