# AccountReview

**AccountReview** est un bundle Symfony conçu pour extraire les données des utilisateurs dans le cadre d'un audit ISO 27001. Il offre des fonctionnalités pour récupérer et exporter les informations des utilisateurs dans différents formats (JSON, CSV, XML).

## Table des matières

- [Installation](#installation)
- [Prérequis](#prérequis)
- [Conception et version Symfony](#conception-et-version-symfony)
- [Configuration](#configuration)
    - [Configuration du Mailer](#configuration-du-mailer)
- [Utilisation](#utilisation)
  - [Options de base](#options-de-base)
  - [Export local](#export-local)
  - [Envoi par mail](#envoi-par-email)
- [Commandes](#commandes)

## Installation

Pour installer `AccountReview`, ajoutez-le à votre projet Symfony via Composer :

```bash
composer require cordon/account-review
```

Ensuite, activez le bundle en ajoutant les lignes suivantes dans le fichier `config/bundles.php` :

```php
return [
    // ...
    Cordon\AccountReview\AccountReviewBundle::class => ['all' => true],
];
```

## Prérequis

* PHP 7.4 ou supérieur
* Symfony 4.4, 5.x, 6.x ou 7.0
* Doctrine ORM
* Symfony Mailer (pour l'envoi par email)

## Conception et version Symfony

Le bundle est compatible de la version 4.4 à 7.0 de Symfony.

La nécessité de la compatibilité des versions Symfony 4.4 à 7.0 a pour conséquence de ne pas utiliser les nouvelles fonctionnalités de Symfony 5.0 et 6.0.

## Configuration

### Configuration du Mailer
Pour utiliser la fonctionnalité d'envoi par email, configurez le DSN du mailer dans votre fichier .env :

```dotenv
MAILER_DSN=smtp://user:pass@smtp.example.com:25
```

Un autre exemple pour utiliser un MailCatcher avec Docker :

```dotenv
MAILER_DSN=smtp://host.docker.internal:1025
```

### Configuration de l'entité

Il est possible de configurer l'entité à utiliser pour l'extraction des données. Pour ce faire, ajoutez le tag `cordon.exportable_entity` à votre entité User depuis le fichier `services.yaml` :

```yaml
services:
    App\Entity\User:
      tags:
        - { name: 'cordon.exportable_entity', alias: 'user' }
```

## Utilisation

Pour extraire les données des utilisateurs, exécutez la commande suivante :

```bash
php bin/console app:account-review
```

### Options de base

La commande principale supporte plusieurs options :
```
php bin/console app:account-review [options]
```
**Options disponibles :**
* --entity-tag ou -t : Tag de l'entité à utiliser pour l'extraction
* --class ou -c : Classe d'entité User à utiliser (défaut: 'App\Entity\User')
* --method ou -m : Méthode d'envoi des données (log, local, mail) (défaut: 'log')
* --format ou -f : Format de sortie (json, csv, xml) (défaut: 'json')

### Exemple

Pour utiliser l'option **--entity-tag**, il faudra ajouter le tag à l'entité User 
```bash
php bin/console app:account-review --entity-tag=user --method=log --format=csv
```

Ou bien, pour utiliser l'option **--class** :

```bash
php bin/console app:account-review --class=App\Entity\User --method=log --format=xml
```

Si on utilise l'option **--entity-tag** et **--class** en même temps, c'est l'option **--entity-tag** qui sera prioritaire.

### Export local
Pour sauvegarder les données dans un fichier local :

```bash
php bin/console app:account-review --method=local --format=json --output=users.json
```

### Envoi par email
Pour envoyer les données par email :

```bash
php bin/console app:account-review --method=mail --format=csv --recipient=audit@example.com --emitter=no-reply@company.com
```

**Options spécifiques à l'email :**
* --recipient ou -r : Adresse email du destinataire
* --emitter ou -em : Adresse email de l'émetteur (défaut : 'no-reply@account-review.com')

## Commandes

Pour afficher la liste des commandes disponibles, exécutez la commande suivante :

```bash
php bin/console app:account-review --help
```

## Licence
Ce bundle est sous licence MIT.
