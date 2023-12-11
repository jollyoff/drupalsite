# EXCHANGE RATES

## INTRODUCTION

The **Exchange Rates** module provides a block in Drupal 8 which converts a base
currency to multiple foreign currencies using
[exchangeratesapi.io](https://exchangeratesapi.io/), a free service that
provides the latest foreign exchange rates from the European Central Bank. The
block supports up to 32 foreign currencies:

* Australian Dollar (A$)
* Bulgarian Lev (BGN)
* Brazilian Real (R$)
* Canadian Dollar (CA$)
* Swiss Franc (CHF)
* Chinese Yuan (CN¥)
* Czech Republic Koruna (CZK)
* Danish Krone (DKK)
* Euro (€)
* British Pound (£)
* Hong Kong Dollar (HK$)
* Croatian Kuna (HRK)
* Hungarian Forint (HUF)
* Indonesian Rupiah (IDR)
* Israeli New Sheqel (₪)
* Indian Rupee (Rs.)
* Icelandic Króna (ISK)
* Japanese Yen (¥)
* South Korean Won (₩)
* Mexican Peso (MX$)
* Malaysian Ringgit (MYR)
* Norwegian Krone (NOK)
* New Zealand Dollar (NZ$)
* Philippine Peso (Php)
* Polish Zloty (PLN)
* Romanian Leu (RON)
* Russian Ruble (RUB)
* Swedish Krona (SEK)
* Singapore Dollar (SGD)
* Thai Baht (THB)
* Turkish Lira (TRY)
* US Dollar ($)
* South African Rand (ZAR)

For a full description of the module, visit the project page:
[https://www.drupal.org/project/exchange_rates](https://www.drupal.org/project/exchange_rates)

To submit bug reports and feature suggestions, or track changes:
[https://www.drupal.org/project/issues/exchange_rates](https://www.drupal.org/project/issues/exchange_rates)

## REQUIREMENTS

No special requirements.

## INSTALLATION

* Install as you would normally install a contributed Drupal module. Visit
[https://www.drupal.org/docs/user_guide/en/extend-module-install.html](https://www.drupal.org/docs/user_guide/en/extend-module-install.html)
for further information.

* To display the Exchange Rates block, go to */admin/structure/block* and select
**Place block** for the region where you want the block displayed.  The block
name is **Exchange Rates**.

## CONFIGURATION

The Exchange Rates settings page is at */admin/config/regional/exchange-rates*
where the base currency and up to 32 foreign currencies can be selected for
display.

## MAINTAINERS

* Ray Yick ([dystopianblue](https://www.drupal.org/u/dystopianblue))
