# Laravel AutoLang

Laravel AutoLang automatise l’internationalisation de vos vues Blade en :

- détectant le texte "en dur" dans vos templates `*.blade.php`,
- remplaçant ce texte par `{{ __('...') }}`,
- ajoutant automatiquement les entrées manquantes dans `lang/<locale>.json` ou `lang/<locale>/<fichier>.php`.

L’objectif est de réduire le travail manuel lors de la mise en place (ou la maintenance) du multilingue dans un projet Laravel.

---

## Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commande disponible](#commande-disponible)
- [Exemples d’utilisation](#exemples-dutilisation)
- [Cas de figure pris en charge](#cas-de-figure-pris-en-charge)
- [Cas ignorés / limites connues](#cas-ignorés--limites-connues)
- [Workflow recommandé en équipe](#workflow-recommandé-en-équipe)
- [Dépannage](#dépannage)
- [Bonnes pratiques i18n Laravel](#bonnes-pratiques-i18n-laravel)

---

## Fonctionnalités

- Scan de un ou plusieurs dossiers de vues Blade.
- Détection des segments texte entre balises HTML.
- Remplacement automatique vers `{{ __('Texte') }}`.
- Génération / mise à jour de traductions au format JSON ou PHP.
- Déduplication des clés déjà existantes.
- Mode aperçu (`--dry`) pour valider avant écriture.
- Confirmation interactive (désactivable avec `--force`).

---

## Prérequis

- PHP compatible avec votre version Laravel.
- Un projet Laravel avec arborescence standard (`resources/views`, `lang`, etc.).
- Droits d’écriture sur les fichiers de vues et le dossier de langues.

---

## Installation

```bash
composer require --dev vnuswilliams/laravel-autolang
```

---

## Configuration

Publiez le fichier de configuration :

```bash
php artisan vendor:publish --provider="VnusWilliams\\LaravelAutoLang\\LaravelAutoLangServiceProvider" --tag=config
```

Le fichier `config/lang-auto.php` expose :

```php
return [
    'paths' => [
        resource_path('views'),
    ],

    'locale' => 'en',
    'output' => 'json',
    'php_file' => 'messages',
];
```

### `paths`

Liste des dossiers à scanner pour trouver les fichiers `*.blade.php`.

Exemple avec plusieurs emplacements :

```php
'paths' => [
    resource_path('views'),
    base_path('Modules/Blog/resources/views'),
],
```

### `locale`

Locale cible pour le fichier JSON de traduction (`lang/<locale>.json`).

Exemple :

```php
'locale' => 'fr',
```

---

## Commande disponible

```bash
php artisan lang:auto
```

Options :

- `--locale=fr` : surcharge ponctuelle de la locale de sortie.
- `--output=json|php` : choisit le format de sortie (surcharge la config).
- `--dry` : simule l’exécution sans modifier de fichiers.
- `--force` : applique directement sans demander de confirmation.

Signature complète :

```bash
php artisan lang:auto {--locale=} {--output=} {--dry} {--force}
```

---

## Exemples d’utilisation

### 1) Première migration vers les traductions

```bash
php artisan lang:auto --locale=fr
```

- Le package détecte les chaînes en dur.
- Il affiche les chaînes détectées + les fichiers impactés.
- Après confirmation, il modifie les vues et met à jour `lang/fr.json`.

### 2) Vérification avant commit (CI locale)

```bash
php artisan lang:auto --dry
```

- Aucun fichier modifié.
- Permet de voir ce qui serait changé avant d’appliquer.

### 3) Exécution non interactive (script / CI)

```bash
php artisan lang:auto --force
```

- Pas de prompt de confirmation.
- Utile dans un script automatisé.

### 4) Locale ponctuelle sans toucher la config

```bash
php artisan lang:auto --locale=de --force
```

- Crée/alimente `lang/de.json` même si `config('lang-auto.locale')` est différent.

---

## Cas de figure pris en charge

Voici les cas typiques gérés automatiquement.

### Texte HTML simple

Avant :

```blade
<h1>Bienvenue</h1>
```

Après :

```blade
<h1>{{ __('Bienvenue') }}</h1>
```

### Espaces autour du texte

Avant :

```blade
<p>   Bonjour tout le monde   </p>
```

Après (espaces structurels conservés) :

```blade
<p>   {{ __('Bonjour tout le monde') }}   </p>
```

### Apostrophes

Avant :

```blade
<span>C'est prêt</span>
```

Après :

```blade
<span>{{ __('C\'est prêt') }}</span>
```

### Déduplication des entrées JSON

Si une clé existe déjà dans `lang/<locale>.json`, elle n’est pas dupliquée.
Le fichier est trié alphabétiquement pour garder un diff propre.

---

## Cas ignorés / limites connues

Pour éviter les faux positifs, certains blocs sont ignorés pendant l’extraction :

- `<script>...</script>`
- `<style>...</style>`
- blocs `@php ... @endphp`
- expressions Blade `{{ ... }}` et `{!! ... !!}`
- directives Blade (`@if`, `@foreach`, etc.)
- composants Blade `<x-... />` et `<x-...>...</x-...>`

### Limites importantes à connaître

- Le package cible le **texte entre balises** (`>texte<`).
  - Les attributs HTML ne sont pas convertis automatiquement (`placeholder`, `title`, etc.).
- Le package ne "traduit" pas le contenu :
  - il crée des entrées clé=valeur (ex: `"Hello": "Hello"`) dans le JSON.
- Si vous avez du Blade très dynamique / atypique, relisez les changements avant commit.

---

## Workflow recommandé en équipe

1. Créer une branche dédiée.
2. Lancer un dry-run :

   ```bash
   php artisan lang:auto --dry
   ```

3. Exécuter réellement :

   ```bash
   php artisan lang:auto --force
   ```

4. Relire les diffs sur :
   - vues Blade modifiées,
   - fichier `lang/<locale>.json`.
5. Faire un commit clair (ex: `chore(i18n): auto-wrap blade strings`).

---

## Dépannage

### "No Blade files found."

- Vérifiez les chemins dans `config/lang-auto.php` (`paths`).
- Vérifiez que les dossiers existent et contiennent des fichiers `*.blade.php`.

### "No new translatable strings found."

- Les chaînes sont peut-être déjà traduites.
- Le contenu peut être dans un bloc ignoré (`script`, `style`, composants, Blade dynamique).

### Le fichier `lang/<locale>.json` n’est pas créé

- Vérifiez les permissions d’écriture dans le dossier `lang/`.
- Vérifiez la locale utilisée (`--locale` ou config).

---

## Bonnes pratiques i18n Laravel

- Utiliser des phrases stables et cohérentes.
- Éviter les concaténations de chaînes dans les vues.
- Préférer les paramètres Laravel (`__('Welcome :name', ['name' => $name])`) pour le dynamique.
- Mettre en place une revue des traductions côté QA / produit.

---

## Licence

MIT
