
- [UnrePress](#unrepress)
  - [Main goal](#main-goal)
    - [Why?](#why)
  - [Screenshots](#screenshots)
- [Features](#features)
  - [Planned Features](#planned-features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [Usage](#usage)
- [Developers](#developers)
  - [How to add your plugin or theme to The Index](#how-to-add-your-plugin-or-theme-to-the-index)
  - [Contributing to UnrePress](#contributing-to-unrepress)
- [License](#license)

# UnrePress

UnrePress is a WordPress plugin that allows you to update WordPress core and plugins/themes directly from git providers (like GitHub, BitBucket or GitLab), instead of the "official" WordPress dot org repository.

## Main goal

To serve as a drop-in replacement for all "my personal site" functionality embeded into WordPress, but managed and administrated by the community itself.

UnrePress should eventually replace: WP core updates (done), plugins and themes installation, plugins and themes search and discover, plugins and themes updates, and all functionality that comes from dot-org.

Hoping to serve all of this, for free. Gratis.

### Why?

Although I'm grateful to him for co-creating WordPress (along with Mike Little), I'm even more grateful to the WordPress community and their work throughout all these years.

(I feel that) This is something that WordPress now needs: to liberate the core and plugin/theme updates, so we — the people who work with and on WordPress and sustain their lives and families with it — don't have to depend on a single person and live in uncertainty about their moods.

So we, the ones who extend and make WordPress better for everyone, can continue contributing and extending it freely and in peace.

Without fear of retaliation or repression.

## Screenshots

[![UnrePress updates, pending core update](.wp-meta/screenshot-01.png)](https://github.com/EstebanForge/UnrePress/blob/main/.wp-meta/screenshot-01.png)

[![UnrePress, updating core](.wp-meta/screenshot-02.png)](https://github.com/EstebanForge/UnrePress/blob/main/.wp-meta/screenshot-02.png)

[![UnrePress, updating core](.wp-meta/screenshot-03.png)](https://github.com/EstebanForge/UnrePress/blob/main/.wp-meta/screenshot-03.png)

[![UnrePress, core updated](.wp-meta/screenshot-04.png)](https://github.com/EstebanForge/UnrePress/blob/main/.wp-meta/screenshot-04.png)

# Features

- Fetches WordPress core updates from the official WordPress GitHub repository.
- Updates WordPress core seamlessly from git providers, using WordPress Filesystem API.
- Blocks all requests to the "official" .org, .net and .com WordPress domains.
- Auto-update UnrePress itself from GitHub.
- Plugin updates from git providers.
- Theme updates from git providers.
- Community maintained index of plugins and themes, so UnrePress can update them. Somewhat like package managers do (dnf, brew, npm, etc.).

## Planned Features

- Integrate the index into WordPress itself, so users can search and install plugins/themes from within the admin panel.
- Add more git providers: BitBucket, GitLab, etc.
- Add the ability to point UnrePress to a different index.
- Configuration interface.
- Expose the index vía web, for easy plugin/theme discoverability.

See more in [Planned Features Discussions](https://github.com/EstebanForge/UnrePress/discussions/categories/planned-features).


# Installation

For now, go to [tags](https://github.com/EstebanForge/UnrePress/tags) and download the zip of the most recent one.

Consider that this proyect is still in active development and not stable. Don't use this on production, until 1.0 is released.

## Requirements

- PHP 8.1 or higher
- WordPress 6.5 or higher

## Usage

1. Go to the WordPress admin panel.
2. Navigate to Dashboard > Updates.
3. Update your WordPress core, plugins and themes as always do.

Now, when WordPress checks for updates, it will use the official GitHub repository for core updates and all other providers for plugin and theme updates.

# Developers

## How to add your plugin or theme to The Index

Do you want to add your plugin or theme to the UnrePress Index?

Check our [Wiki for instructions](https://github.com/EstebanForge/UnrePress/wiki).

## Contributing to UnrePress

Want to contribute? Check our [Contributing Guidelines](https://github.com/EstebanForge/UnrePress/wiki) at our Wiki.

# License

This project is licensed under the GPL-2.0+ License.
