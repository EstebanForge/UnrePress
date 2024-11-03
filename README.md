# UnrePress

UnrePress is a WordPress plugin that allows you to update WordPress core and plugins/themes directly from git providers (like GitHub, BitBucket or GitLab), instead of the "official" WordPress dot org repository.

## Why?

Although I'm grateful to him for co-creating WordPress (along with Mike Little), I'm even more grateful to the WordPress community and their work throughout all these years.

(I feel that) This is something that WordPress now needs: to liberate the core and plugin/theme updates, so we — the people who work with and on WordPress and sustain our lives and families with it — don't have to depend on a single person and live in uncertainty about their moods.

So we, the ones who extend and make WordPress better for everyone, can continue contributing and extending it freely and in peace.

Without fear of retaliation or repression.

## Screenshots

[![UnrePress updates, pending core update](.wp-meta/screenshot-01.png)](https://github.com/TCattd/UnrePress/blob/main/.wp-meta/screenshot-01.png)

[![UnrePress, updating core](.wp-meta/screenshot-02.png)](https://github.com/TCattd/UnrePress/blob/main/.wp-meta/screenshot-02.png)

[![UnrePress, updating core](.wp-meta/screenshot-03.png)](https://github.com/TCattd/UnrePress/blob/main/.wp-meta/screenshot-03.png)

[![UnrePress, core updated](.wp-meta/screenshot-04.png)](https://github.com/TCattd/UnrePress/blob/main/.wp-meta/screenshot-04.png)

## Features

- Fetches WordPress core updates from the official WordPress GitHub repository.
- Updates WordPress core seamlessly from git providers, using WordPress Filesystem API.
- Blocks all requests to the "official" .org, .net and .com WordPress domains.

### Planned Features

- Auto-update UnrePress itself from GitHub.
- Plugin updates from git providers.
- Theme updates from git providers.
- Configuration interface.
- Add more git providers: BitBucket, GitLab, etc.
- Create a community curated index of plugins and themes, so UnrePress can update them somewhat like package managers do.

## Requirements

- PHP 8.1 or higher
- WordPress 6.5 or higher

## Installation

For now, download the zip from the `main` branch, and install it as any other WordPress plugin.

## Usage

1. Go to the WordPress admin panel.
2. Navigate to Dashboard > Updates.
3. Update your Core.

Now, when WordPress checks for updates, it will use the official GitHub repository for core updates and all other providers for plugin and theme updates.

## Development

To set up the development environment:

1. Install the development dependencies:
   ```
   composer install
   ```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. But please, follow the PHP-CS-Fixer rules.

Consider opening a discussion if you wan't to help out in any planned feature.

## License

This project is licensed under the GPL-2.0+ License.
