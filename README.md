[![Build Status](https://travis-ci.org/o10n-x/wordpress-file-page-cache.svg?branch=master)](https://travis-ci.org/o10n-x/wordpress-file-page-cache) ![Version](https://img.shields.io/github/release/o10n-x/wordpress-file-page-cache.svg)

# WordPress File Page Cache [beta]

Advanced file based page cache with PHP Opcache boost option ([500x faster than Redis and Memcached](https://blog.graphiq.com/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad)). 

* [Documentation](https://github.com/o10n-x/wordpress-file-page-cache/tree/master/docs)
* [Description](https://github.com/o10n-x/wordpress-file-page-cache#description)
* [Version history (Changelog)](https://github.com/o10n-x/wordpress-file-page-cache/releases)

## Installation

![Github Updater](https://github.com/afragen/github-updater/raw/develop/assets/GitHub_Updater_logo_small.png)

This plugin can be installed and updated using [Github Updater](https://github.com/afragen/github-updater) ([installation instructions](https://github.com/afragen/github-updater/wiki/Installation))

<details/>
  <summary>Installation instructions</summary>

### Step 1: Install Github Updater and first optimization plugin

Installing and updating the plugins is possible using Github Updater. It is easy to install one of the plugins. You simply need to download the Github Updater plugin ([zip file](https://github.com/afragen/github-updater/archive/develop.zip)), install it from the WordPress plugin admin panel and copy the Github URL of the plugin into the Github Updater installer.

![image](https://user-images.githubusercontent.com/8843669/39889846-46158cc2-5499-11e8-824d-720020f758db.png)

### Step 2: Install other optimization plugins with a single click

A recent update of all plugins contains a easy single click install button.

![image](https://user-images.githubusercontent.com/8843669/39661507-cc1eac5e-5052-11e8-8fba-33c0cc959b07.png)
</details>

## Beta Notice

This plugin is a **first beta** which was [banned from WordPress.org](https://docs.style.tools/cms-plugins/wordpress/ban) for a yet unknown motive. The plugin never received extensive user feedback which was originally planned and a lot of planned improvements and features were never added. Besides that, the investment by the ex-CEO of PageSpeed.pro Ltd. that was agreed mid 2017 was paid in the beginning of 2018, resulting in a severe delay and a greatly reduced version of the originally planned Advanced Optimization plugin (the bigger plugin was stripped of features and cut into individual plugins).

Thanks to the valuable feedback from Github users, the plugins did receive feedback. The quality of the plugins is very good however in sense of usability/documentation there are simply unresolved issues.

The plugins are able to deliver results for SEO. Our www.e-scooter.co demo website (started in 2018) ranks in the top 10 in Google in many countries and received visitors from 205 countries and is now visited from 174 countries per week on average. In The Netherlands, www.e-scooter.nl ranks #1 for many scooters. The website is currently at 1.5M visits per year. 

**The WPO plugins are operating sublimely.**

![gogoro-prijs](https://user-images.githubusercontent.com/8843669/48940358-90f40e80-ef17-11e8-8bbb-62c3187d8953.png)

![image](https://user-images.githubusercontent.com/8843669/49431914-c964d980-f7ae-11e8-9a60-f5fb3cfd733d.png)

It will take a bit more effort, and maybe some more feedback from users, but then the WPO plugins could simply be 'perfect' for getting Google Lighthouse 100-scores and even better performance results (speed) than would be required for a 100-score (think of the unique innovations such as timed javascript execution and CSS rendering, advanced Web Worker based preload logic, preload on mouse-down and [Service Worker Push](https://github.com/o10n-x/wordpress-http2-optimization/wiki/HTTP-2-Server-Push-vs-Service-Worker-Push) with better performance than HTTP/2 Server Push).

## Description

This plugin is a file based page cache with PHP Opcache support ([500x faster than Redis and Memcached](https://blog.graphiq.com/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad)).

### PHP Opcache

The PHP Opcache option enables to serve cache using WordPress `advanced-cache.php` (`WP_CACHE`) before MySQL and plugns are loaded, with **zero file IO** (full memory based cache without serialization overhead). The cache supports conditional requests (`304 - Not Modified`) to save bandwidth.

When using PHP Opcache, the cache is as fast as it can be in PHP.

Cache speed (full cache process time) on a 1-core VPS: 60μs (0.06ms or 0.00006 seconds). **No file IO!**

![Cache Speed](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/cache-speed.png)
 
### Advanced configuration

The plugin provides a cache policy editor that enables to selectively enable/disable or configure the page cache based on page URLs (with regular expression support) or conditional methods such as `is_page`. The cache policy enables to customize the cache expire time and stale update option for individual pages.

![Cache Policy Editor](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/cache-policy-editor.png)
 
PHP Opcache may have a limited total size. The PHP Opcache option can be configured for individual pages using a individual cache policy.

Other advanced configuration include a custom bypass policy, a HTTP header cache policy and more.

### Dynamic Content Cache

The plugin provides an option to create a custom MD5 cache hash using PHP variables and methods to enable caching of dynamic content (multiple cache versions for the same URL).

![Custom Cache Hash](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/cache-hash.png)

### Stale on update

The plugin provides an option to serve stale (expired) cache to visitors while the cache is updated in the background.

### Cache Preloader

The plugin provides an option to automatically preload the cache on a set time. An advanced preload policy enables to configure precisely what pages to preload and to set the preload interval, priority and to force a cache update for individual pages.

![Cache Preload](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/cache-preload.png)

### PHP Opcache Management

The plugin provides PHP Opcache status and management panel.

![PHP Opcache Management](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/opcache-status.png)

### HTML Search & Replace

A search & replace filter enables to modify the HTML before a page is cached. It supports regular expression.

![Search & Replace](https://github.com/o10n-x/wordpress-file-page-cache/blob/master/docs/images/searchreplace.png)

Additional features can be requested on the [Github forum](https://github.com/o10n-x/wordpress-file-page-cache/issues).

## WordPress WPO Collection

This plugin is part of a Website Performance Optimization collection that include [CSS](https://github.com/o10n-x/wordpress-css-optimization), [HTML](https://github.com/o10n-x/wordpress-html-optimization), [Web Font](https://github.com/o10n-x/wordpress-font-optimization), [HTTP/2](https://github.com/o10n-x/wordpress-http2-optimization), [Progressive Web App (Service Worker)](https://github.com/o10n-x/wordpress-pwa-optimization) and [Security Header](https://github.com/o10n-x/wordpress-security-header-optimization) optimization. 

The WPO optimization plugins provide in all essential tools that enable to achieve perfect [Google Lighthouse Test](https://developers.google.com/web/tools/lighthouse/) scores and to validate a website as [Google PWA](https://developers.google.com/web/progressive-web-apps/), an important ranking factor for Google's [Speed Update](https://searchengineland.com/google-speed-update-page-speed-will-become-ranking-factor-mobile-search-289904) (July 2018).

![Google Lighthouse Perfect Performance Scores](https://github.com/o10n-x/wordpress-css-optimization/blob/master/docs/images/google-lighthouse-pwa-validation.jpg)

The WPO optimization plugins are designed to work together with single plugin performance. The plugins provide the latest optimization technologies and many unique innovations.

### JSON configuration

100% of the WPO plugin settings are controlled by JSON. This means that you could use the plugins without ever using the WordPress admin forms.

The JSON is verified using JSON schema's. More info about [JSON schemas](https://github.com/o10n-x/wordpress-o10n-core/tree/master/schemas).

### Local editing of optimization settings

A recently added [Stealth Optimization Config Proxy](https://github.com/o10n-x/wordpress-http2-optimization/releases/tag/0.0.55) concept makes it possible to edit the plugin settings using physical `.json` files from a local editor (with auto upload) making it efficient for fine tuning optimization settings. An update would cost a second compared to using + saving a WordPress admin panel.

https://github.com/o10n-x/wordpress-http2-optimization/releases/tag/0.0.55

## Google PageSpeed vs Google Lighthouse Scores

While a Google PageSpeed 100 score is still of value, websites with a high Google PageSpeed score may score very bad in Google's new [Lighthouse performance test](https://developers.google.com/web/tools/lighthouse/). 

The following scores are for the same site. It shows that a perfect Google PageSpeed score does not correlate to a high Google Lighthouse performance score.

![Perfect Google PageSpeed 100 Score](https://github.com/o10n-x/wordpress-css-optimization/blob/master/docs/images/google-pagespeed-100.png) ![Google Lighthouse Critical Performance Score](https://github.com/o10n-x/wordpress-css-optimization/blob/master/docs/images/lighthouse-performance-15.png)

### Google PageSpeed score is outdated

For the open web to have a chance of survival in a mobile era it needs to compete with and win from native mobile apps. Google is dependent on the open web for it's advertising revenue. Google therefor seeks a way to secure the open web and the main objective is to rapidly enhance the quality of the open web to meet the standards of native mobile apps.

For SEO it is therefor simple: websites will need to meet the standards set by the [Google Lighthouse Test](https://developers.google.com/web/tools/lighthouse/) (or Google's future new tests). A website with perfect scores will be preferred in search over low performance websites. The officially announced [Google Speed Update](https://searchengineland.com/google-speed-update-page-speed-will-become-ranking-factor-mobile-search-289904) (July 2018) shows that Google is going as far as it can to drive people to enhance the quality to ultra high levels, to meet the quality of, and hopefully beat native mobile apps.

A perfect Google Lighthouse Score includes validation of a website as a [Progressive Web App (PWA)](https://developers.google.com/web/progressive-web-apps/).

Google offers another new website performance test that is much tougher than the Google PageSpeed score. It is based on a AI neural network and it can be accessed on https://testmysite.thinkwithgoogle.com
