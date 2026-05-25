# Laravel AutoLang

Laravel AutoLang automatise l'internationalisation de vos vues Blade en :

- détectant le texte "en dur" dans vos templates `*.blade.php`,
- remplaçant ce texte par `{{ __('...') }}`,
- ajoutant automatiquement les entrées manquantes dans `lang/<locale>.json` ou `lang/<locale>/<fichier>.php`.

L'objectif est de réduire le travail manuel lors de la mise en place (ou la maintenance) du multilingue dans un projet Laravel.

---

## Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commande disponible](#commande-disponible)
- [Exemples d'utilisation](#exemples-dutilisation)
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
- Nommage automatique du fichier PHP de traduction d'après le fichier Blade scanné.
- Déduplication des clés déjà existantes.
- Mode aperçu (`--dry`) pour valider avant écriture.
- Confirmation interactive (désactivable avec `--force`).

---

## Prérequis

- PHP compatible avec votre version Laravel.
- Un projet Laravel avec arborescence standard (`resources/views`, `lang`, etc.).
- Droits d'écriture sur les fichiers de vues et le dossier de langues.

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

    'extensions' => [
        '.blade.php',
    ],

    'locale' => 'en',
    'output' => 'json',
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

### `extensions`

Liste des extensions de fichiers autorisées pendant le scan.

Par défaut :

```php
'extensions' => ['.blade.php'],
```

### `locale`

Locale cible pour le fichier de traduction.

```php
'locale' => 'fr',
```

### `output`

Format de sortie : `json` (défaut) ou `php`.

Lorsque `output = php`, le nom du fichier PHP est **dérivé automatiquement** du fichier Blade scanné — aucune configuration supplémentaire n'est nécessaire.

---

## Nommage automatique des fichiers PHP

Quand `output = php`, le fichier de traduction prend le nom du fichier Blade, normalisé selon ces règles :

| Fichier Blade | Fichier de traduction |
|---|---|
| `welcome.blade.php` | `lang/<locale>/welcome.php` |
| `leave-balance.blade.php` | `lang/<locale>/leavebalance.php` |
| `⚡welcome-to.blade.php` | `lang/<locale>/welcometo.php` |
| `My_Cool View.blade.php` | `lang/<locale>/mycoolview.php` |

**Règles appliquées :**
1. Les extensions sont supprimées (`.blade.php` → deux passes).
2. Le résultat est mis en minuscules.
3. Tout caractère qui n'est pas une lettre Unicode ou un chiffre est retiré.

Avec `--all`, chaque fichier Blade produit son propre fichier PHP de traduction.

---

## Commande disponible

```bash
php artisan lang:auto {path?}
```

- `path` (optionnel) : chemin **relatif** depuis `resources/views` (ou le dossier racine défini dans la config), sans extension.
- Si `path` est absent, la commande le demande de façon interactive.
- Si l'utilisateur saisit un chemin commençant par `/`, le slash initial est retiré automatiquement.

Exemples de chemins :

- `welcome` ⟶ cherche `resources/views/welcome.blade.php`
- `pages/welcome` ⟶ cherche `resources/views/pages/welcome.blade.php`

Options :

- `--all` : scanne **tout** le dossier configuré (et sous-dossiers). Drapeau sans valeur.
- `--locale=fr` : surcharge ponctuelle de la locale de sortie.
- `--output=json|php` : choisit le format de sortie (surcharge la config).
- `--dry` : simule l'exécution sans modifier de fichiers.
- `--force` : applique directement sans demander de confirmation.

Avec `--all`, une confirmation supplémentaire est demandée avant de lancer le scan global (sauf si `--force` est présent).

Signature complète :

```bash
php artisan lang:auto {path?} {--all} {--locale=} {--output=} {--dry} {--force}
```

---

## Exemples d'utilisation

### 1) Première migration vers les traductions (JSON)

```bash
php artisan lang:auto --locale=fr
```

- Le package détecte les chaînes en dur.
- Il affiche les chaînes détectées + les fichiers impactés.
- Après confirmation, il modifie les vues et met à jour `lang/fr.json`.

### 2) Sortie PHP — nommage automatique

```bash
php artisan lang:auto leave-balance --locale=fr --output=php --force
```

- Scanne `resources/views/leave-balance.blade.php`.
- Crée/alimente `lang/fr/leavebalance.php` automatiquement.

### 3) Scan global en PHP

```bash
php artisan lang:auto --all --locale=fr --output=php --force
```

- Scanne toutes les vues.
- Chaque vue produit son propre fichier PHP :
  - `welcome.blade.php` → `lang/fr/welcome.php`
  - `hr/leave-balance.blade.php` → `lang/fr/leavebalance.php`

### 4) Vérification avant commit (CI locale)

```bash
php artisan lang:auto --dry
```

- Aucun fichier modifié.
- Permet de voir ce qui serait changé avant d'appliquer.

### 5) Exécution non interactive (script / CI)

```bash
php artisan lang:auto --force
```

---

## Cas de figure pris en charge

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

Si une clé existe déjà dans `lang/<locale>.json`, elle n'est pas dupliquée.
Le fichier est trié alphabétiquement pour garder un diff propre.

---

## Cas ignorés / limites connues

Pour éviter les faux positifs, certains blocs sont ignorés pendant l'extraction :

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
   - fichiers `lang/<locale>/*.php` ou `lang/<locale>.json`.
5. Faire un commit clair (ex: `chore(i18n): auto-wrap blade strings`).

---

## Dépannage

### "No Blade files found."

- Vérifiez les chemins dans `config/lang-auto.php` (`paths`).
- Vérifiez que les dossiers existent et contiennent des fichiers `*.blade.php`.

### "No new translatable strings found."

- Les chaînes sont peut-être déjà traduites.
- Le contenu peut être dans un bloc ignoré (`script`, `style`, composants, Blade dynamique).

### Le fichier de traduction PHP n'est pas créé

- Vérifiez les permissions d'écriture dans le dossier `lang/`.
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

---

## Commande inverse — `--reverse`

La commande `--reverse` permet de **revenir en arrière** : elle lit les valeurs dans le fichier de traduction, remplace les helpers `{{ __('...') }}` par le texte brut dans les vues Blade, puis supprime les clés utilisées du fichier de traduction.

```bash
php artisan lang:auto {path?} --reverse
php artisan lang:auto --all --reverse
```

Le flag ne prend aucune valeur. Tout se base sur la config (`locale`, `output`).

---

### Flux selon `output`

**Mode `json`**

1. Charge `lang/<locale>.json`
2. Dans chaque Blade ciblé, résout chaque `{{ __("...") }}` → remplace par la valeur brute
3. Supprime les clés utilisées du `.json` et réécrit le fichier

**Mode `php`**

1. Liste les fichiers `.php` présents dans `lang/<locale>/` et propose un choix interactif
2. Charge le tableau de traductions du fichier choisi
3. Dans chaque Blade ciblé, résout chaque `{{ __("...") }}` → remplace par la valeur brute
4. Supprime les clés utilisées du `.php` et réécrit le fichier

---

### Résolution de clé

Le dernier segment après le dernier `.` est utilisé comme clé de recherche dans le fichier de traduction.

| Helper dans Blade | Clé résolue |
|---|---|
| `{{ __("welcome") }}` | `welcome` |
| `{{ __("messages.welcome") }}` | `welcome` |
| `{{ __("a.b.c.myKey") }}` | `myKey` |

Si une clé n'est **pas trouvée** dans le fichier de traduction, le helper est laissé intact.

---

### Exemple complet

Blade **avant** :

```blade
<h1>{{ __("welcome") }}</h1>
<p>{{ __("messages.farewell") }}</p>
```

`lang/fr.json` :

```json
{
    "farewell": "Au revoir",
    "welcome": "Bienvenue"
}
```

Après `php artisan lang:auto welcome --reverse --locale=fr` :

```blade
<h1>Bienvenue</h1>
<p>Au revoir</p>
```

`lang/fr.json` après (clés supprimées) :

```json
{}
```

---

### Options compatibles avec `--reverse`

| Option | Comportement |
|---|---|
| `--dry` | Prévisualise sans modifier aucun fichier |
| `--force` | Applique sans demander de confirmation |
| `--all` | Traite toutes les vues du dossier configuré |
| `--locale=fr` | Surcharge ponctuelle de la locale |
| `--output=json\|php` | Surcharge ponctuelle du format |

---

### Limites

- Seules les clés présentes dans le fichier de traduction sélectionné sont résolues.
- Les attributs HTML (`placeholder`, `title`, etc.) ne sont pas traités — cohérent avec le comportement forward.
- Si le dossier `lang/<locale>/` ne contient aucun fichier `.php`, la commande s'arrête avec un avertissement.