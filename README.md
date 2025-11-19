# SBO — Site vitrine

Un site vitrine simple, professionnel et performant pour présenter vos activités de développeur web, avec un focus Prestashop, et un formulaire Contact/Devis (traité côté serveur en PHP).

## Contenu

- **Prestashop** : bugs, modules, mises à jour, performance, intégrations, sécurité.
- **Autres services** : Laravel, Django, Python, scraping, assistance à distance, conseil.
- **Processus**: de la prise de brief à la livraison.
- **Formulaire**: Contact & Devis avec validation côté client et protections anti‑spam (honeypot, question, délai minimal).

## Lancer en local

- Option statique: ouvrez `index.html` directement dans votre navigateur (affichage uniquement, envoi non fonctionnel).
- Option avec PHP (recommandé pour tester l'envoi):
  - Installez PHP (si besoin), puis lancez un serveur local à la racine du projet:
    - PowerShell (Windows):
      ```powershell
      php -S localhost:8000 -t .
      ```
    - Ensuite ouvrez: http://localhost:8000/

## Configuration du formulaire (PHP)

- Le formulaire envoie vers `contact.php`.
- Configurez l'email de destination via variables d'environnement (recommandé):
  - PowerShell (session courante):
    ```powershell
    $env:SBO_CONTACT_TO = "votre.email@domaine.com"
    $env:SBO_CONTACT_FROM = "no-reply@votre-domaine.com"
    ```
  - Sans variables, modifiez directement les constantes en haut de `contact.php`.
- Anti‑spam côté serveur: honeypot, question (3+2), délai minimal (3 s) entre affichage et soumission.
- En cas d'échec de `mail()`, un fallback écrit dans `storage/messages.log` (créé automatiquement). Vous pouvez consulter ce fichier pour vérifier la réception.

### Alternative sans PHP (Formspree)

Si vous déployez sur un hébergement statique (Netlify/Vercel/GitHub Pages), remplacez l'attribut `action` du formulaire dans `index.html` par votre endpoint Formspree:

```html
<form action="https://formspree.io/f/XXXXYYYY" method="POST">
```

## Personnalisation rapide

- **Textes** : éditez les sections dans `index.html` (langue FR par défaut).
- **Couleurs**: changez la palette `brand` dans le bloc `tailwind.config` en haut du fichier.
- **SEO**: mettez à jour `<title>`, `meta description`, et les balises Open Graph/Twitter.
- **JSON‑LD**: ajustez les blocs `Organization` et `Service` si besoin.

## Déploiement

- **Hébergement PHP (recommandé pour `contact.php`)**: OVH/IONOS/AlwaysData/cPanel, ou tout serveur avec PHP activé.
- **Hébergement statique** (si vous utilisez Formspree): Netlify, GitHub Pages, Vercel.

## Licence

Vous êtes libre d'utiliser et de modifier ce modèle pour vos besoins.
