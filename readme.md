### About DB Data Importer
DB Data Importer is a tool that enables data for specific topics in your application (e.g. users, posts, comments, etc.) and for specific rows (e.g. ids = 100,101) to be imported from source into destination database.

The tool reads the table structure of your database (for each of your defined topics) from the configuration file and tries to fetch all the related data for these tables into your destination database.
 
Some useful applications of this tool could be:
* You want to recreate a bug that happens in production in your local environment, but you are missing data in your local database
* You want to write some tests for some edge cases that happens in production and you need the exact data from production database for these edge cases
* You want to setup your local database structure to look exactly like production and all the essential data is populated into your local tables

### Important Terminology
* **Tables Context:** You can group your tables into three contexts:
    * *Fixed Tables:* Tables that their data is not essential for the processes in your application (and usually have to be populated only once).
    * *Operational Tables:* Tables that their data is continuously changed by the processes in you application. So they need to be re-populated every time you import data for a topic.
    * *Ignored Tables* Tables that you only need their schema, but not their data in your local database.
* **Import Topics:** Topics represents different processes in your application. Since each process usually works with data from a certain number of tables, you can group these tables into a topic so that you can import data only for them.
> Import topics can reference each other by using `@` prefix

### Configuration File
The configuration contains information about the source and destination databases.

```php
// the source database (usually the production read-only database)
'export_database' => [
    'host'      => 'production-db-hostname',
    'database'  => 'production-db-name',
    'username'  => 'production-db-user',
    'port'      => 'production-db-port',
    'password'  => 'production-db-password'
]
```

```php
// the destination database (usually the local database)
'export_database' => [
    'host'      => 'local-db-hostname',
    'database'  => 'local-db-name',
    'username'  => 'local-db-user',
    'port'      => 'local-db-port',
    'password'  => 'local-db-password'
]
```

The configuration file also should contain the table structure of all the tables in your database, grouped in different contexts and topics. An example table structure configuration for a blog application is shown below:

```php
// operational_tables context groups all tables within specific topics
// that will be filled based in the given input 
'operational_tables' => [
    // users topic
    'users' => [
        // type of relationship
        'has_many' => [
            'roles_users' => [
                'foreign_key' => 'user_id',
                'belongs_to' => [
                    'roles' => [
                        'foreign_key' => 'role_id'
                    ]
                ]
            ]
        ]
    ],
    // comments topic
    'comments' => [
        'belongs_to' => [
            // referenced topic
            '@users' => [
                foreign_key => 'user_id'
            ]
        ]
    ],
    // posts topic
    'posts' => [
        'belongs_to' => [
            '@users' => [
                'foreign_key' => 'author_id'
            ]
        ],
        'has_many' => [
            // referenced topic
            '@comments' => [
                'foreign_key' => 'post_id'
            ]
        ]
    ]
]
```

In the example above, we can ask the tool to import data for `post id=100`. It will then import all the related data for this particular post according to the configured table structure above. 

If we assume that the `users` topic is not going to change very often and it make sense to download all data for that topic once and then exclude user data from the future imports, we can rewrite the configuration file as below:

```php
// fixed_tables context groups all tables that their data usually needed only once.
'fixed_tables' => [
    // users topic
    'users' => [
        // type of relationship
        'has_many' => [
            'roles_users' => [
                'foreign_key' => 'user_id',
                'belongs_to' => [
                    'roles' => [
                        'foreign_key' => 'role_id'
                    ]
                ]
            ]
        ]
    ]
],
// operational_tables context groups all tables within specific topics
// that will be filled based in the given input 
'operational_tables' =>
    // comments topic
    'comments' => [],
    // posts topic
    'posts' => [
        'has_many' => [
            // referenced topic
            '@comments' => [
                'foreign_key' => 'post_id'
            ]
        ]
    ]
]
```

### How to Use
First step is to create a configuration file located either in the root package directory, or in the root directory of your application (you can copy the `di_config.php` file already presented in the package).

Then you need to update the configuration file to include the required database connection information, as well as your application table structure.

Lastly, you can start using the tool in one of the two ways below:

##### Running Cli Command
The tool comes with a executable script located in `vendor/bin/data-importer`. Here are some use cases with cli command:
```bash
# clean all tables configured as operational_tables
vendor/bin/data-importer cleanup --context=operational_tables
# clean all tables in database
vendor/bin/data-importer cleanup --context=all
```

```bash
# export table schemas
vendor/bin/data-importer export --table-schemas=true
# export fixed_tables data
vendor/bin/data-importer export --fixed-tables=true
# export all data within posts topic for posts id IN (100,101,1002) 
vendor/bin/data-importer export --topic=posts --ids=100,101,102
# export everything (good for initializing the db for the first time
vendor/bin/data-importer export --topic=posts --ids=100,101,102 --table-schemas=true --fixed-tables=true 
```

##### Using It In Your Application
If you require this package in your application via composer, you can start using it as below:
```php
$client = new DataImporter\DataImporterClient\DataImporterClient();
$client->cleanup()
       ->export('posts', [100, 101, 102], false, false)
       ->import();
```
