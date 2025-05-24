# UnrePress changelog

# 0.7.0 - 2025-05-24
* Themes and Plugin discovery, search and install inside wp-admin, now is ready.
* Improved the WordPress core update logic.
* Now blocking wp.org and all the related "personal" domains by default. [This is too much](https://www.reddit.com/r/Wordpress/comments/1ktpzv3/wordpress_68_seems_to_be_breaking_update/). Will offer an options page to disable this. This can already be done by using the `UNREPRESS_BLOCK_WPORG` constant tho.
* Improved `unrepress_debug()` function.
* Now using [strauss](https://github.com/BrianHenryIE/strauss) to prefix vendor files.

# 0.5.0 - 2025-02-16
* UnrePress now supports Plugins and Themes installation.
* Fixed plugin and theme installation issues with correct slug detection.
* Added early slug capture from install button data and URL parameters.
* Improved source directory handling during installation process.

# 0.4.1 - 2025-01-03
* Added blocking of requests to "his ecommerce website". Reason: [Sybre post](https://x.com/SybreWaaijer/status/1875230654054752374). There is just [too much data](https://x.com/SybreWaaijer/status/1875230672756858980) being sent to that guy by his plugin.

# 0.4.0 - 2024-12-31
* Core update fallback. UnrePress will prioritize updates using its Index. But, if for some reason the Index is not available, it will fallback to use wp.org repository instead.

## 0.3.0 - 2024-12-24
* Working themes updater.
* Working plugins updater.
* Tweaked core updater.
* Spread the word!

## 0.2.0 - 2024-12-23
* Working plugins updater.

## 0.1.0 - 2024-11-03
* Initial public release.
* UnrePress can update WordPress core from GitHub. TODO: Add support for BitBucket and GitLab.

## 0.0.1 - 2024-10-03
* Project started
