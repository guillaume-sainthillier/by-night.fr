# By Night

![Build And Deploy](https://github.com/guillaume-sainthillier/by-night.fr/workflows/Build%20And%20Deploy%20Release/badge.svg)
[![SymfonyInsight](https://insight.symfony.com/projects/a11fedf7-0560-449b-bbfa-d38fe90a99ee/mini.svg)](https://insight.symfony.com/projects/a11fedf7-0560-449b-bbfa-d38fe90a99ee)

Plateforme de gestion d'événements en France.

## DEMO

https://by-night.fr

## Stack technique

-   PHP 8.0
-   Symfony 5.1
-   MySQL 5.7
-   Elastic Search7
-   Varnish 6
-   Redis 5
-   Sass
-   Webpack
-   Docker
-   Amazon S3 / Cloudfront

## Commandes utiles

```bash
bin/console rabbitmq:setup-fabric
bin/console app:events:import toulouse.opendata
bin/console rabbitmq:batch:consumer add_event -vv
```
