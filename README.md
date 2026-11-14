# rss-of-rss

Un petit outil en ligne de commande qui lit un flux RSS, visite chaque lien qu'il contient et liste les flux RSS proposés par les sites visités. Autrement dit : les **flux RSS de flux RSS**.

## Pourquoi ce projet ?

C'est un projet **artisanal**, écrit pour un besoin précis : découvrir les blogs et les sites partagés sur [Journal du Hacker](https://www.journalduhacker.net/) et récupérer leurs flux pour s'y abonner.

Rien n'empêche de l'utiliser ailleurs. N'importe quel flux RSS 2.0 dont les articles pointent vers des sites externes fait l'affaire : un agrégateur de liens, une veille, un Shaarli, etc.

## Comment ça marche

1. La commande télécharge le flux RSS donné en argument et en extrait les liens (`<item><link>`).
2. Elle télécharge toutes les pages liées, en parallèle.
3. Dans chaque page, elle cherche les balises `<link rel="alternate" type="application/rss+xml">` et affiche les flux trouvés. Les flux de commentaires sont ignorés.
4. Avec l'option `--output`, elle enregistre les URLs des flux trouvés dans un fichier. Avec `--append`, elle complète ce fichier au lieu de l'écraser.

## Prérequis

- PHP 8.4 ou plus récent, avec les extensions `ctype`, `iconv`, `dom` et `simplexml`
- [Composer](https://getcomposer.org/)
- Facultatif : le [Symfony CLI](https://symfony.com/download)

## Installation

```bash
git clone https://github.com/chemel/rss-of-rss.git
cd rss-of-rss
composer install
```

## Utilisation

```bash
php bin/console app:fetch https://www.journalduhacker.net/rss
```

Exemple de sortie :

```
 ! [NOTE] Getting rss of rss from: https://www.journalduhacker.net/rss

 [OK] Found 25 links

[200] https://exemple-de-blog.fr/un-article
	-> https://exemple-de-blog.fr/feed.xml
[404] https://site-disparu.com/page
[ERR] https://site-injoignable.org/ (Failed to connect ...)
...

 [OK] All done.
```

- `[200]` : la page a été lue. Les lignes `->` en dessous sont les flux RSS trouvés.
- `[404]`, `[500]`… : le site a répondu par une erreur.
- `[ERR]` : le site est injoignable ou n'a pas répondu à temps.

Pour l'utiliser avec un autre flux, il suffit de changer l'URL :

```bash
php bin/console app:fetch https://exemple.com/rss.xml
```

### Sauvegarder les flux dans un fichier

L'option `--output` (ou `-o`) enregistre les URLs des flux trouvés dans un fichier, une par ligne :

```bash
php bin/console app:fetch https://www.journalduhacker.net/rss --output=feeds.txt
```

```
https://exemple-de-blog.fr/feed.xml
https://autre-blog.org/rss
```

- Un flux trouvé sur plusieurs pages n'est écrit qu'une seule fois.
- Le fichier est écrasé à chaque exécution. Les dossiers manquants sont créés.
- L'affichage dans le terminal reste le même, avec une note finale indiquant le nombre de flux sauvegardés.

### Compléter un fichier existant

Ajoutée à `--output`, l'option `--append` (ou `-a`) complète le fichier au lieu de l'écraser :

```bash
php bin/console app:fetch https://www.journalduhacker.net/rss --output=feeds.txt --append
```

- Le fichier sert d'historique : les URLs déjà présentes sont conservées, dans leur ordre.
- Seuls les flux qui n'y figurent pas encore sont ajoutés, à la fin.
- La note finale indique le nombre de flux ajoutés et le total.
- Si le fichier n'existe pas encore, il est créé.

`--append` ne s'utilise qu'avec `--output` : seule, la commande s'arrête avec une erreur.

### Mode silencieux

L'option `--quiet` (ou `-q`), fournie par défaut par Symfony, masque l'affichage normal de la commande. Elle est pratique pour une exécution planifiée, par exemple avec cron :

```bash
php bin/console app:fetch https://www.journalduhacker.net/rss --output=feeds.txt --append --quiet
```

Le fichier est écrit comme d'habitude, et le code de retour indique toujours si la commande a réussi.

## Développement

Le projet est basé sur Symfony 8.1.

```
src/
├── Command/FetchCommand.php   # la commande app:fetch
└── Service/XmlParser.php      # lecture du flux RSS et détection des flux dans le HTML
tests/
└── Command/FetchCommandTest.php   # test de la commande, avec des réponses HTTP simulées
```

Pour lancer les tests :

```bash
php bin/phpunit
```

Pour vérifier et corriger le style du code (règles `@Symfony`) :

```bash
vendor/bin/php-cs-fixer fix
```

## Forkez-le !

Ce projet fait une chose simple pour un usage précis, et il a sûrement ses limites. Par exemple, il ne gère pas encore les flux Atom. Si vous avez un autre besoin, **forkez-le** et adaptez-le à votre sauce : exporter les flux en OPML, filtrer par domaine, garder un historique, gérer d'autres formats…

Les issues et les pull requests sont aussi les bienvenues.
