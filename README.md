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

`config/autoload/doctrine.global.php`:

```php
return ['doctrine' => [
    'default'     => 'main',
    'connections' => [
        'main'      => ['driver' => 'pdo_sqlite', 'path' => 'data/main.sqlite'],
        'reporting' => ['driver' => 'pdo_mysql', 'host' => '...', 'dbname' => '...', 'user' => '...', 'password' => '...'],
    ],
    'entity_managers' => [
        'main' => ['mapping' => ['paths' => ['src/App/Entity']]],
    ],
    'migrations' => [
        'defaults'    => ['table_storage' => ['table_name' => 'doctrine_migration_versions']],
        'connections' => [],
    ],
]];
```

| Key | Scope | Meaning |
| --- | --- | --- |
| `default` | root | connection and entity manager used when none is named |
| `dev_mode` | root, entity manager | array caches instead of filesystem caches |
| `base_path` | root | root for relative paths (defaults to the working directory) |
| `cache_dir` | root | defaults to `data/cache/doctrine` |
| `types` | root | custom DBAL types, `name => class` |
| `logger` | connection | PSR-3 service id |
| `middlewares` | connection | list of DBAL middleware service ids |
| `connection` | entity manager | connection to use, defaults to the entity manager's own name |
| `mapping.driver` | entity manager | `attributes` (default), `xml`, or a `MappingDriver` service id |
| `mapping.paths` | entity manager | directories to scan |
| `cache` | entity manager | PSR-6 pool service id |
| `naming_strategy` | entity manager | `NamingStrategy` service id, defaults to underscore |
| `event_subscribers` | entity manager | list of event subscriber service ids |

Any other connection key is passed to `DriverManager::getConnection()`.

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

Inject the `Registry` itself only when the connection is chosen at runtime, for example per tenant.

## Custom factories

`UseEntityManager` and `UseConnection` are `ParameterSource` attributes from
[ssmiff/mezzio-container-helpers](https://github.com/ssmiff/mezzio-container-helpers), which also provides `AttributeFactory`, `#[ConfigValue]` and the
`ConstructorResolver` for factories that need to mix attribute-driven services with values made up in the factory. That package is a dependency of this one.

## Development

```
composer install
composer check
```
