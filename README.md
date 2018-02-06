# rKTs XML to RDF migration script

This script is only for internal purposes and requires non-public data to be run.

To run the migration script first install the required dependencies:

```
composer install
```

then either run the script with default options or look at the options in

```
./migrate.php --help
```

TODO:
- handle (?) and (distorded) and other parenthesis in kernel titles

Questions:
- why the note "17 chapters" in dergue.xml for rKTs1 while there are 36?
- why are some location of bampos ending with a closing parenthesis? like <bampo>2.<p>110b4-131a4)</p></bampo> in dergue.xml line 1763 in rKTs 47
