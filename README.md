database-command
================

Yii command to create database migrations from existing schema.

    Usage: yiic database dump

    Available actions: dump [--prefix=<table_prefix>] [--createSchema=<0|1>] [--insertData=<0|1>] [--dbConnection=<db>]

Param            | Default | Info
-----------------|---------|-----------------------------------
--prefix         |         | dump only tables with given prefix
--createSchema   | 1       | wheter to create tables 
--insertData     | 1       | wheter to create insert statements
--dbConnection   | db      | application component to use