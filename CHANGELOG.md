# Yii Request Body Parser Change Log

## 1.1.2 under development

- Chg #44: Bump minimum PHP version to 8.1 (@vjik) 
- Chg #44: Change PHP constraint in composer.json to `8.1 - 8.4` (@vjik)
- Enh #44: Add psalm type `int<1, 2147483647>` to `depth` parameter in `JsonParser` constructor (@vjik)
- Bug #44: Explicitly mark nullable parameters (@vjik)
- Bug #46: Explicitly add transitive dependency `psr/http-factory` (@vjik)

## 1.1.1 June 03, 2024

- Enh #39: Add support for `psr/http-message` version `^2.0` (@bautrukevich)

## 1.1.0 July 01, 2022

- Chg #18: Update `yiisoft/http` dependency (@devanych)
- Enh #14: Add support for `psr/container` version `^2.0` (@roxblnfk)

## 1.0.0 February 02, 2021

- Initial release.
