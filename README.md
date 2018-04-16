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
- add missing shad at end of tib strings?
- fix 'og pages in ganden
- duplicates : W10MS11138 and W4CZ15240
- remove shejawashuso
- replace 'a -> A
- replace pad ma -> pad+ma
- not reflected in rktst.xml, to be inspected:
  - D4407 = D4408
  - D4388 = D4158
  - D4383 = D3871
  - D3849 = D3845
  - D3848 = D3844
  - D3840 = D4152
  - D3918 = D4462
  - D4022 ~= D4023 (different translation of same text?)

Questions:
- why the note "17 chapters" in dergue.xml for rKTs1 while there are 36?
- why are some location of bampos ending with a closing parenthesis? like <bampo>2.<p>110b4-131a4)</p></bampo> in dergue.xml line 1763 in rKTs 47
