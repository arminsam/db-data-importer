# DB Data Importer
DDI is a tool that enables data for specific _topics_ in your application (e.g. products, orders, shipments, etc.) to be imported for the given ids (e.g. product id = 100,101,102) from a source database into a destination database.

> A __topic__ can be a single table, or a collection of related tables.

> A __source database__ is usually the production database where the data is exported from.

> A __destination database__ is usually the local database where the data is imported to.

The tool provides three commands, each for specific purpose:
1. The __cleanup__ commnad can be optionally used before an import to truncate all the tables within the provided _context_.
2. The __export__ command is used for exporting data and/or table schema into a .sql dump file.
3. The __import__ command is used for importing the generated .sql file into the destination database.

The tool lets you define three types of __contexts__ to group your database tables into:
1. __Ignored Tables Context:__ should contain all tables in your application database that you don't care about their data in your local database (you only need their table schema and that's it!). The data for these tables are never going to be exported from the source database.
2. __Fixed Tables Context:__ should countain all tables in your application database that you only need their data imported once. Usually, the processes in your application only read data from these tables but not insert/update data in them.
3. __Operational Tables Context:__ should contain all tables in your application database that hold dynamic operational data. You are going to use topics within this context to import selective data that you need in your local database.
 
### Use Cases
Some useful applications of this tool could be:
* You want to test a feature in your local environment, but you don't have the necessary/relevant data.
* You are unable to reproduce a bug that happens in production, because your local database is missing necessary/relevant data.
* You want to write some tests for some edge cases that happens in production and you need the exact data from production database to do that.
* You want to be able to setup your local database and have all the relevant data inside in less than a few clicks!

### Configuration File
The configuration file is where you define your source and destination database connection data, as well as the contexts, topics, and table structures for your use case. There is an example `di_config.php` file inside the package root directory. In case you are using this package as a dependency in your application, you need to copy the config file to the root directory of your application.

##### Configuration For A Simple Online Shop Application

```php
// ignored tables context contains all tables which you don't need the data for
'ignored_tables' => [
    'process_logs' => [],
    'product_history' => [],
    // ...
],

// fixed tables context contains all tables which you probabely need their data only once
'fixed_tables' => [
    'categories' => [],
    'countries' => [],
    'languages' => [],
    'users' => [
        'has_many' => [
            'users_roles' => [
                'foreign_key' => 'user_id'
                'belongs_to' => [
                    'roles' => [
                        'foreign_key' => 'role_id'
                    ]
                ]
            ]
        ]
    ],
    // ...
],

// operational tables context contains all tables  which you are going to regularly import the data for specific ids
'operational_tables' => [
    // topic: products
    'products' => [
        // we should add the pk of a table if it's not "id"
        'pk' => 'sku',
        'has_many' => [
            'images' => [
                'foreign_key' => 'product_id'
            ],
            'reviews' => [
                'foreign_key' => 'product_id'
            ]
        ]
    ],
    
    // topic: orders
    'orders' => [
        'has_many' => [
            'order_items' => [
                'foreign_key' => 'order_id',
                'belongs_to' => [
                    // this is how you reference from one topic to another
                    '@products' => [
                        'foreign_key' => 'product_id',
                        // we should add the other_key if the foreign_key does not reference to other tables "id" column
                        'other_key' => 'sku'
                    ]
                ]
            ]
        ]
    ]
]
```

### How to Use
You can start using the tool in one of the two ways below:

##### Running Cli Command
The tool comes with an executable script located in `bin/data-importer` (or `vendor/bin/data-importer` if you've installed it as a dependency of your application). Here are some ways you can use the cli command:

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
You can also instantiate the `DataImporterClient` class in your code and use it as below:

```php
$client = new DataImporter\DataImporterClient\DataImporterClient();
$client->cleanup()
       ->export('posts', [100, 101, 102], false, false)
       ->import();
```
