database-command
================

Yii command to create database migrations from existing schema. 
Migration classes are created in application `runtime` folder.

    Usage: yiic database dump

    Available actions: dump [--prefix=<table_prefix>] [--createSchema=<0|1>] [--insertData=<0|1>] [--dbConnection=<db>]

Param            | Default | Info
-----------------|---------|-----------------------------------
--prefix         |         | dump only tables with given prefix
--createSchema   | 1       | wheter to create tables 
--insertData     | 1       | wheter to create insert statements
--dbConnection   | db      | application component to use


Configuration
-------------

`config/console.php`

    'commandMap' => array(
        'database' => array(
            'class' => 'vendor.schmunk42.database-command.EDatabaseCommand',
        ),
    )

Features
--------

* primary key generation
* foreign key generation (skipped for SQLite)
* complete data and schmema dump

Resources
----------

* [github](https://github.com/schmunk42/database-command)
* [Yii Extensions]()
* [Phundament 3](http://phundament.com) Package