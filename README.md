# Aide pour dev le back en symfony

> J’explique ici les différents fichiers et leur utilité pour le développement. Pour toute autre question, me contacter en MP  (Nicolas Martinez).
> 

# .env / .env.local

Pour les variables d’environement il faut faire un .env et un .env.local 

> *le local est pour le dev, l’autre pour la prod, on va principalement faire du local et on mettra tout sur l’autre au moment du deploy*
> 

Pour l’instant on a juste ces variables : 

- DATABASE_URL
- DB_CONNECTION
- DEFAULT_URI
- APP_ENV
- APP_SECRET
- APP_SHARE_DIR

exemple : 

```php
# PostgreSQL
databaseUrl=postgres://username:password@localhost:5432/nom_de_la_db

# URL par défaut de l'application (nom possible: defaultURL ou DEFAULT_URL)
defaultURL=http://localhost:3000
DEFAULT_URL=https://mon-app.example.com
```

Plus tard, il faudra également ajouter une variable **MAILER_DSN** afin de gérer l’envoi d’e-mails pour les comptes créés.

> Évitez de committer les credentials ; utilisez des variables d'environnement, pour les utiliser, il faut faire (env: 'envTest:test') dans un paramètre de méthode
> 

exemple : 

```php
// Dans le .env : 
RECETTE_API=blablablatestcléapi

// Dans le controller : 
    public function __construct(
        #[Autowire(env: 'RecetteAPi:RECETTE_API')] private array $recetteApi,
    ) {
        return $recetteApi
        }
```

# Les controllers

Les controllers permettent de traiter des logiques métier et de renvoyer une réponse (définie avec l’équipe front) afin qu’elle puisse être exploitée.

L’utilité principale de nos controllers est d’utiliser un service, de traiter la donnée reçue puis de la renvoyer au front.

> Ils servent également à interagir avec la base de données créée par l’équipe Data.
> 

# Les services

Les services permettent d’alléger le **controller**. Ils constituent le **Model** dans le schéma MVC. Par exemple, ils peuvent être utilisés pour récupérer des données via un endpoint fourni par l’équipe Data.

> Un service peut être intégré dans un controller en l’injectant en paramètre d’une méthode.
>
