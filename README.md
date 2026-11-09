# ssmiff/mezzio-doctrine

Doctrine DBAL, ORM and Migrations for Mezzio, with named connections selectable on every console command via `--conn`.

```
vendor/bin/laminas migrations:migrate
vendor/bin/laminas migrations:migrate --conn=reporting
vendor/bin/laminas migrations:generate --conn=reporting
vendor/bin/laminas orm:validate-schema --conn=main
```

Requires PHP 8.5. Licensed under the [MIT licence](LICENSE).

## Requires ssmiff/mezzio-container-helpers

The `#[UseEntityManager]` and `#[UseConnection]` attributes, `AttributeFactory` and `ConstructorResolver` come from
[ssmiff/mezzio-container-helpers](https://github.com/ssmiff/mezzio-container-helpers). This package depends on it, so Composer installs it for you, but its
`Ssmiff\MezzioContainerHelpers\ConfigProvider` must be registered too, because it provides the `ConstructorResolver` service that `AttributeFactory` uses.
`laminas-component-installer` does that automatically; without it, add the provider to `config/config.php` before this one.
Both attributes implement the helpers' `ParameterSource` interface, so your own attributes can be mixed with them.

## Install

```
composer require ssmiff/mezzio-doctrine
```

`laminas-component-installer` registers `Ssmiff\MezzioDoctrine\ConfigProvider`. Commands are exposed through `laminas-cli`.

## Configure

Create one file, `config/autoload/doctrine.global.php`. Everything goes under the `doctrine` key.

### Step 1: the smallest working setup

One database, entities in `src/App/Entity`. Copy this and change the connection details:

```php
<?php

return [
    'doctrine' => [
        'connections' => [
            'default' => [
                'driver'   => 'pdo_mysql',
                'host'     => 'localhost',
                'dbname'   => 'my_app',
                'user'     => 'root',
                'password' => 'secret',
            ],
        ],
        'entity_managers' => [
            'default' => [
                'mapping' => ['paths' => ['src/App/Entity']],
            ],
        ],
    ],
];
```

That's it. You now have:

- a database connection called `default`
- an entity manager that reads `#[ORM\Entity]` attributes from `src/App/Entity`
- migrations in `database/migrations/default` (created for you the first time you run a migrations command)

Try it:

```
vendor/bin/laminas db:connections
vendor/bin/laminas migrations:status
```

> **Using SQLite?** Replace the connection with `['driver' => 'pdo_sqlite', 'path' => 'data/app.sqlite']`.
>
> **Have a URL instead?** Use `['url' => 'postgresql://user:pass@host/dbname']`.
>
> Any option Doctrine DBAL accepts can go in a connection: see the
> [DBAL configuration docs](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html).

> **Keep passwords out of git.** Put the connection in `doctrine.local.php` (which Mezzio ignores) or read it from the
> environment, e.g. `'password' => getenv('DB_PASSWORD')`.

### Step 2 (optional): add another database

Give it a name and add it next to `default`. Here we add `reporting`:

```php
'connections' => [
    'default'   => [/* as before */],
    'reporting' => [
        'driver'   => 'pdo_pgsql',
        'host'     => 'reports.internal',
        'dbname'   => 'reports',
        'user'     => 'app',
        'password' => 'secret',
    ],
],
'entity_managers' => [
    'default'   => ['mapping' => ['paths' => ['src/App/Entity']]],
    'reporting' => ['mapping' => ['paths' => ['src/Reporting/Entity']]],
],
```

Then choose it with `--conn` on any command:

```
vendor/bin/laminas migrations:migrate --conn=reporting
```

Its migrations live in `database/migrations/reporting` by default.
Leave out the `entity_managers` entry if a database has no entities (for example, you only run migrations or raw SQL against it).

### Step 3 (optional): everything else

You only need these if the defaults don't suit you. Each one goes under `doctrine`.

| I want to... | Add this |
| --- | --- |
| make a connection other than `default` the default | `'default' => 'reporting'` |
| use the faster in-memory caches while developing | `'dev_mode' => true` (put it in `doctrine.local.php`) |
| log every SQL query | `'logger' => 'my.psr3.logger'` inside the connection |
| set the PostgreSQL `search_path` | `'search_path' => ['app', 'public']` inside the connection |
| use XML mapping instead of attributes | `'driver' => 'xml'` inside `entity_managers.<name>.mapping` |
| put migrations somewhere else | see [Migrations](#migrations-settings) below |
| rename the migrations version table | see [Migrations](#migrations-settings) below |
| add a custom column type | `'types' => ['money' => App\Dbal\MoneyType::class]` |
| store caches somewhere other than `data/cache/doctrine` | `'cache_dir' => 'var/cache/doctrine'` |

### Full reference

<details>
<summary><strong>Top-level settings</strong> (directly under <code>doctrine</code>)</summary>

| Key | Default | What it does |
| --- | --- | --- |
| `connections` | *required* | Your databases, keyed by name. See below. |
| `entity_managers` | `[]` | ORM settings per database, using the same names. See below. |
| `migrations` | `[]` | Migrations settings. See below. |
| `default` | `'default'` | Which connection is used when you don't pick one. It must exist in `connections`. |
| `dev_mode` | `false` | `true` uses in-memory caches, so entity changes are picked up immediately. |
| `base_path` | current directory | Relative paths (entities, migrations, SQLite files, cache) are resolved from here. |
| `cache_dir` | `'data/cache/doctrine'` | Where metadata/query caches are written when `dev_mode` is off. |
| `types` | `[]` | Custom DBAL column types, as `'name' => ClassName::class`. |

</details>

<details>
<summary><strong>Connection settings</strong> (<code>connections.&lt;name&gt;</code>)</summary>

Any [Doctrine DBAL connection option](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/configuration.html)
(`driver`, `host`, `port`, `dbname`, `user`, `password`, `url`, `path`, `charset`, ...), plus:

| Key | What it does |
| --- | --- |
| `logger` | Service id of a PSR-3 logger. Every query is logged to it. |
| `middlewares` | List of service ids of DBAL middlewares to wrap the connection in. |
| `params_provider` | Service id of a `ParamsProvider` that supplies the connection options at runtime instead of config. See [Extension points](#extension-points). |
| `search_path` | PostgreSQL only. A schema name or a list of them, set with `SET search_path` each time the connection opens. See below. |

#### `search_path`

```php
'connections' => [
    'default' => [
        'driver'      => 'pdo_pgsql',
        'host'        => 'db',
        'dbname'      => 'app',
        'user'        => 'app',
        'password'    => '...',
        'search_path' => ['tenant_a', 'public'],
    ],
],
```

- It is applied by `Ssmiff\MezzioDoctrine\Dbal\SearchPathMiddleware`, added after your own `middlewares` and the `logger`, so they
  see the `SET` statement. It runs on every physical connect, including reconnects, migrations and console commands.
- Each name is quoted as an identifier, so write schemas exactly as they are named (`'$user'` works too).
- A `driver` other than `pdo_pgsql` or `pgsql` is rejected. With only `driverClass`, it is not checked.
- Unqualified `CREATE TABLE` goes into the first schema in the path. That includes the migrations version table.
- A `ParamsProvider` can return `search_path` from `params()`. That replaces any `search_path` set next to
  `params_provider` in config, which otherwise still applies.
- Behind PgBouncer in transaction pooling mode, a session `SET` does not reliably stick. Use
  `ALTER ROLE ... SET search_path` there instead.

You can also use the middleware directly, for example in your own `middlewares` list:
`new SearchPathMiddleware(['tenant_a', 'public'])`.

</details>

<details>
<summary><strong>Entity manager settings</strong> (<code>entity_managers.&lt;name&gt;</code>)</summary>

| Key | Default | What it does |
| --- | --- | --- |
| `mapping.paths` | `[]` | Folders containing your entities. |
| `mapping.driver` | `'attributes'` | How entities are mapped: `'attributes'`, `'xml'`, or the service id of your own `MappingDriver`. |
| `connection` | same as the entity manager's name | Which connection it uses. |
| `dev_mode` | top-level `dev_mode` | Override `dev_mode` for just this entity manager. |
| `cache` | built-in file cache | Service id of a PSR-6 cache pool to use for metadata, query and result caches. |
| `naming_strategy` | underscore (`firstName` → `first_name`) | Service id of a Doctrine `NamingStrategy`. |
| `event_subscribers` | `[]` | List of service ids of Doctrine event subscribers. |

</details>

<details id="migrations-settings">
<summary><strong>Migrations settings</strong> (<code>migrations</code>)</summary>

With no settings, connection `my-db` keeps its migrations in `database/migrations/my-db`, namespace `Database\Migrations\MyDb`,
and records them in the table `doctrine_migration_versions`.

To change that, use any [Doctrine Migrations option](https://www.doctrine-project.org/projects/doctrine-migrations/en/current/reference/configuration.html)
(`migrations_paths`, `table_storage`, `all_or_nothing`, `transactional`, `check_database_platform`, `organize_migrations`, `custom_template`), in one of two places:

- `migrations.defaults` applies to **every** connection.
- `migrations.connections.<name>` applies to **one** connection, and wins over `defaults`.

```php
'migrations' => [
    // every connection: use a different version table name
    'defaults' => [
        'table_storage' => ['table_name' => 'migration_versions'],
    ],
    // just "reporting": keep its migrations in db/reporting
    'connections' => [
        'reporting' => [
            'migrations_paths' => ['Reporting\\Migrations' => 'db/reporting'],
        ],
    ],
],
```

</details>

## Use

Every connection has an entity manager of the same name.

```php
$registry = $container->get(Doctrine\Persistence\ManagerRegistry::class);

$registry->connection();
$registry->connection('reporting');
$registry->entityManager('main');
$registry->getRepository(User::class);
```

`Doctrine\DBAL\Connection` and `Doctrine\ORM\EntityManagerInterface` are registered for the default connection.

## Commands

Doctrine's migrations and ORM commands are registered, each accepting `--conn=<name>`
(on migrations commands `--em` is a synonym; on ORM commands `--conn` and `--em` are the same selector).

| Commands |
| --- |
| `migrations:migrate`, `:status`, `:generate`, `:diff`, `:list`, `:execute`, `:version`, `:current`, `:latest`, `:up-to-date`, `:rollup`, `:dump-schema`, `:sync-metadata-storage` |
| `orm:info`, `orm:validate-schema`, `orm:mapping:describe`, `orm:run-dql`, `orm:schema-tool:create`, `:update`, `:drop`, `orm:clear-cache:metadata`, `:query`, `:result` |
| `db:connections` |

### Migrations per connection

Each connection has its own folder, namespace and version table:

| | default |
| --- | --- |
| directory | `database/migrations/<connection>`, created on demand |
| namespace | `Database\Migrations\<Connection>` |
| version table | `doctrine_migration_versions` |

Override under `doctrine.migrations.connections.<name>` with the usual `migrations_paths` and `table_storage` keys.

#### Migrations for a connection the app names

A package that ships migrations for a connection whose name the app chooses can't key its config by that name. It
provides a `Ssmiff\MezzioDoctrine\Migrations\MigrationsPathsProvider` instead, which is asked for the migrations of the
connection being migrated:

```php
use Ssmiff\MezzioContainerHelpers\Attribute\ConfigValue;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsPathsProvider;

final readonly class AuditMigrations implements MigrationsPathsProvider
{
    public function __construct(
        #[ConfigValue('audit.connection', 'audit')]
        private string $connection,
    ) {}

    public function migrationsPaths(string $connection): array
    {
        return $connection === $this->connection ? ['Acme\\Audit\\Migrations' => __DIR__ . '/../migrations'] : [];
    }
}
```

and registers it in its `ConfigProvider`:

```php
'dependencies' => ['factories' => [AuditMigrations::class => AttributeFactory::class]],
'doctrine'     => ['migrations' => ['paths_providers' => [AuditMigrations::class]]],
```

With `'audit' => ['connection' => 'reporting']` in the app's config, `migrations:migrate --conn=reporting` runs them.
A provider's paths are added to the connection's own `migrations_paths` (or the defaults); for a namespace both name,
the app's directory wins. [ssmiff/mezzio-tenancy](https://github.com/ssmiff/mezzio-tenancy) supplies its landlord
migrations this way.

`migrations:diff` needs an entity manager with mappings; on a connection without any it refuses, since the result would drop every table. Use `migrations:generate` there.

## How `--conn` works

Doctrine's migrations commands are `final` and read their configuration once. `ConnectionOptionListener`, a `ConsoleEvents::COMMAND`
listener provided to laminas-cli as `Laminas\Cli\SymfonyEventDispatcher`, selects the connection on `MigrationsConfigurationLoader` or
`RegistryEntityManagerProvider` before the command runs. A project that defines its own `Laminas\Cli\SymfonyEventDispatcher` must attach
the listener itself.

## Injecting connections and entity managers

The default connection and entity manager are ordinary container services:

```php
public function __construct(private EntityManagerInterface $entityManager) {}
```

Every named connection and entity manager is a container service too, with ids from `ServiceId`:

```php
ServiceId::entityManager('reporting')
ServiceId::connection('audit')
```

Better still, the class can say which database it uses on its constructor property, and `AttributeFactory` (from the helpers package) reads it:

```php
final readonly class RecordPageViewHandler
{
    public function __construct(
        #[UseEntityManager('reporting')]
        private EntityManagerInterface $entityManager,
    ) {
    }
}
```

```php
'dependencies' => ['factories' => [RecordPageViewHandler::class => AttributeFactory::class]],
```

`#[UseEntityManager('name')]` and `#[UseConnection('name')]` pick the named service; a parameter without an attribute is resolved by its class type
(so a bare `EntityManagerInterface` is the default entity manager). Without the attribute factory, `ConfigAbstractFactory` with `ServiceId` ids does the same from config.

Inject the `Registry` itself only when the connection is chosen at runtime, for example one database per customer (see [Extension points](#extension-points)).

## Custom factories

`UseEntityManager` and `UseConnection` are `ParameterSource` attributes from
[ssmiff/mezzio-container-helpers](https://github.com/ssmiff/mezzio-container-helpers), which also provides `AttributeFactory`, `#[ConfigValue]` and the
`ConstructorResolver` for factories that need to mix attribute-driven services with values made up in the factory. That package is a dependency of this one.

## Development

```
composer install
composer check
```

## Extension points

Most apps never need these. Use them when **the database isn't known until the app is running**, for example
one database per customer, where the customer is chosen by the request or by a command-line option.

There are three pieces:

| Piece | What it does |
| --- | --- |
| `ParamsProvider` | Supplies a connection's settings (host, dbname, ...) from code instead of config. |
| `Registry::reset($name)` | Throws away a connection and its entity manager, so the next use builds them again, asking the `ParamsProvider` again. |
| `console_listeners` | Runs your code before any console command, e.g. to choose the customer before `migrations:migrate` runs. |

### Full example: one database per customer

Every customer has their own database named `customer_<id>`. The app uses a connection called `customer`
whose database changes depending on who is being served.

**1. Somewhere to store the current customer**

```php
namespace App\Customer;

final class CurrentCustomer
{
    public ?string $id = null;
}
```

**2. A `ParamsProvider` that builds the connection settings for that customer**

```php
namespace App\Customer;

use RuntimeException;
use Ssmiff\MezzioDoctrine\ParamsProvider;

final readonly class CustomerDatabase implements ParamsProvider
{
    public function __construct(private CurrentCustomer $customer) {}

    public function params(): array
    {
        if ($this->customer->id === null) {
            throw new RuntimeException('No customer selected.');
        }

        return [
            'driver'   => 'pdo_mysql',
            'host'     => 'localhost',
            'dbname'   => 'customer_' . $this->customer->id,
            'user'     => 'app',
            'password' => 'secret',
        ];
    }
}
```

`params()` returns exactly what you would otherwise write in `doctrine.connections.<name>`.
It is called the first time the connection is used, and again after each `reset()`.

**3. Config**

```php
use App\Customer\CurrentCustomer;
use App\Customer\CustomerDatabase;
use App\Customer\SelectCustomerFromEnv;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Ssmiff\MezzioContainerHelpers\Factory\AttributeFactory;

return [
    'doctrine' => [
        'connections' => [
            'default'  => [/* your normal app database */],
            'customer' => ['params_provider' => CustomerDatabase::class],
        ],
        'entity_managers' => [
            'customer' => ['mapping' => ['paths' => ['src/Customer/Entity']]],
        ],
        'console_listeners' => [
            SelectCustomerFromEnv::class => 10, // above 0 = runs before --conn is handled
        ],
    ],
    'dependencies' => [
        'factories' => [
            CurrentCustomer::class       => InvokableFactory::class,
            CustomerDatabase::class      => AttributeFactory::class,
            SelectCustomerFromEnv::class => AttributeFactory::class,
        ],
    ],
];
```

`logger`, `middlewares` and `search_path` can still go next to `params_provider`; every other connection setting comes from `params()`.
For one schema per customer instead of one database, return `'search_path' => [$schema]` from `params()`.

**4. Use it in a web request**

A middleware sets the customer before anything touches the database:

```php
namespace App\Customer;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class SelectCustomerMiddleware implements MiddlewareInterface
{
    public function __construct(private CurrentCustomer $customer) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->customer->id = $request->getHeaderLine('X-Customer-Id');

        return $handler->handle($request);
    }
}
```

Handlers then get the entity manager from the registry when they need it:

```php
$orders = $registry->entityManager('customer')->getRepository(Order::class)->findAll();
```

With PHP-FPM every request starts fresh, so you don't need `reset()` here.
On a long-running server (RoadRunner, Swoole, FrankenPHP worker mode), call `$registry->reset('customer')`
after changing the customer, so the next request doesn't reuse the previous customer's connection.

> Inject `Registry`, not `EntityManagerInterface`, into anything that uses the `customer` connection.
> After `reset()` an entity manager you were handed earlier is closed and still points at the old database.

**5. Use it from the command line**

A console listener reads the customer from an environment variable before the command runs:

```php
namespace App\Customer;

use Ssmiff\MezzioDoctrine\Console\ConsoleCommandListener;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

final readonly class SelectCustomerFromEnv implements ConsoleCommandListener
{
    public function __construct(private CurrentCustomer $customer) {}

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $this->customer->id = getenv('CUSTOMER') ?: null;
    }
}
```

```
CUSTOMER=42 vendor/bin/laminas migrations:migrate --conn=customer
```

**6. Do something for every customer: `reset()`**

To run work against each customer database in one process, change the customer and reset between them:

```php
foreach (['41', '42', '43'] as $id) {
    $currentCustomer->id = $id;
    $registry->reset('customer'); // next line connects to customer_<id>

    $registry->connection('customer')->executeStatement('UPDATE orders SET archived = 1 WHERE ...');
}
```

### Console listener details

- `console_listeners` maps a service id to a priority. Each service must implement `Ssmiff\MezzioDoctrine\Console\ConsoleCommandListener`.
- Listeners run on Symfony's `ConsoleEvents::COMMAND`, before every command. The built-in `--conn` handling runs at priority 0,
  so use a priority **above 0** if your listener must run first (as above, so the customer is set before the connection is chosen).
