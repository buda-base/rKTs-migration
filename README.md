# rKTs XML to RDF migration script 

This script is only for internal purposes and requires non-public data to be run.

To run the migration script first install the required dependencies _in the rKTs-migration directory_:

```
composer install
```

For help with this see [install composer](https://nomadphp.com/blog/13/How-do-I-install-composer-), for example. It may be necessary to run ```composer.phar``` or ```php composer.phar``` depending on how the composer-setup.php leaves things.

Several php packages are needed:
```
apt-get install php7.0-mbstring php7.0-zip php7.0-xml
```

and then the first time
```
git submodule update --init
```

Running from the rKTs-migration directory:
```
./migrate.php -o ../rkts-out/
```
the final ```/``` is required. More options cam be seen via:
```
./migrate.php --help
```

TODO:
- handle (?) and (distorded) and other parenthesis in kernel titles
- add missing shad at end of tib strings?
- fix 'og pages in ganden
- remove shejawa?
- replace 'a -> A
- replace pad ma -> pad+ma, pandi -> paN+Di

Questions:
- why the note "17 chapters" in dergue.xml for rKTs1 while there are 36?
