database-command
================

Yii command to create database migrations from existing schema. 
Migration classes are created in application `runtime` folder.

Features
--------

* primary key generation
* foreign key generation (skipped for SQLite)
* complete data and schmema dump

Download
--------

[Get it from github](https://github.com/schmunk42/database-command/tags) and place it into your application.

Usage
-----

Run the command...

    ./yiic database
    
... to show the help page

    dump [<name>] [--prefix=<table_prefix,...>] [--dbConnection=<db>]
            [--createSchema=<1|0>] [--insertData=<1|0>] [--foreignKeyChecks=<1|0>]
            [--ignoreMigrationTable=<1|0>] [--truncateTable=<0|1>]
            [--insertAutoIncrementValues=<1|0>] [--migrationPath=<application.runtime>]



Param               | Default | Info
--------------------|---------|-----------------------------------
name                | dump    | migration class name
--prefix            |         | dump only tables with given prefix<br/>(specify multiple prefixes separated by commas)
--createSchema      | 1       | whether to create tables
--insertData        | 1       | whether to create insert statements
--foreignKeyChecks  | 1       | set to `0` to disable foreign key checks
--truncateTable     | 0       | whether to remove all records from the table first
--dbConnection      | db      | application component to use
--migrationPath     | application.runtime     | default dump folder
--ignoreMigrationTable | 1    | whether to ignore to migration table
--insertAutoIncrementValues | 1    | whether to include values from auto-increment column


#### Example

To create a migration from an existing application database schema, define an alternative database component in your 
application, e.g. `db-production`. 

This example shows data dumping, removes all data (truncate tables) and omits foreign key checks:

     ./yiic database dump p3_replace_data \
       --prefix=Auth,Rights,usr,p3 --createSchema=0 \
       --foreignKeyChecks=0 --truncateTable=1

Separate schema and data:

     ./yiic database dump my_schema --insertData=0
     ./yiic database dump my_data --createSchema=0

Replace your whole data with data from `dbProduction`:

     ./yiic database dump replace_data \
       --truncateTable=1 --foreignKeyChecks=0 \
       --createSchema=0 --dbConnection=dbProduction

The following command dumps all tables starting with `p3_media` and omits
the schema create statements:

    ./yiic database dump p3media_no_schema_production \
    --prefix=p3_media --createSchema=0 --dbConnection=dbProduction


Requirements
------------

 * Yii 1.1.*

Configuration
-------------

`config/console.php`

    'commandMap' => array(
        'database' => array(
            'class' => 'vendor.schmunk42.database-command.EDatabaseCommand',
        ),
    )

Resources
---------

* Availble via [Phundament 3](http://phundament.com) Composer Package Repository `http://packages.phundament.com`
* Fork on [github](https://github.com/schmunk42/database-command)
* [CHANGELOG](https://github.com/schmunk42/database-command/blob/master/CHANGELOG.md)
* View at [Yii Extensions](http://www.yiiframework.com/extension/database-command/)
