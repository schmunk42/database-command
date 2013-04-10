0.8.9
-----

 * [UPD] chnaged default dump directory to `application.runtime` (schmunk42)
 * [ENH] ignore migration table by default (schmunk42)

0.8.8
-----

 * [FIX] fixed insert creation, removed obsolete check (schmunk42)

0.8.7
-----

 * [ENH] updated extension to support migrationsPath configuration (cajoy1981)
 * [FIX] fixed missing insert code (schmunk42)

0.8.6
-----

 * [ENH] updated generated code order (1.truncate), 2.schema, 3.foreign-keys, 4.inserts (le_top)
 * [ENH] updated unsigned primary keys / unique name for keys (Alan Lobo)

0.8.5
-----

 * [ENH] Added options `--truncateTable` and `--foreignKeyChecks` (schmunk42)
 * [UPD] Updated help text (schmunk42)

0.8.4
-----

 * [ENH] Updated `--prefix` option; multiple values (schmunk42)

0.8.3
-----

 * [ENH] Proper quotation in inserts (maxxer)
 * [ENH] Migration class name check (schmunk42)
 * [ENH] Add foreign key ON CASCADE warning output (maxxer)

0.8.2
-----

 * first public release
