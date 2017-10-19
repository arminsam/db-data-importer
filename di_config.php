<?php

return [
    // the path to save the export file
    'export_path' => __DIR__ . DIRECTORY_SEPARATOR . 'di_export.sql',

    // the database to use for exporting data from
    'export_database' => [
        'host'      => 'production-db-host',
        'database'  => 'production-db-name',
        'username'  => 'production-db-username',
        'port'      => 'production-db-port',
        'password'  => 'production-db-password'
    ],

    // the database to use for importing data into
    'import_database' => [
        'host'      => 'localhost',
        'database'  => 'homestead',
        'username'  => 'homestead',
        'port'      => '3306',
        'password'  => 'secret'
    ],

    // tables to be ignored in your database (you need only their schema)
    'ignored_tables' => [
        'countries' => [],
        'images' => []
    ],

    // tables that only have to be filled once
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

    // tables that can be filled based on the given input
    'operational_tables' => [
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
];