# Publishing your Plugin in The Index

How to publish your plugin into UnrePress's Index.

## Requirements

Before publishing your plugin, ensure it meets these requirements:

1. **Version Control**
   - Hosted on GitHub, GitLab, or BitBucket
   - Uses semantic versioning (X.Y.Z)
   - Has proper release tags, that follow semantic versioning

2. **Plugin Structure**
   - Following WordPress plugin directory structure
   - Main plugin file with proper header comment
   - README.md with installation and usage instructions

3. **.wp-meta Folder** (optional)
   - Contains any additional files needed for the plugin to be shown in The Index inside WordPress or the future public Index vía web.
   - Contains an additional .json file, with extra information from you plugin.
   - This folder is not required, but it's recommended to have it.

## Publishing Steps

1. Go to [UnrePress-index](https://github.com/EstebanForge/UnrePress-index)
2. Fork and clone the repository
3. Create a new JSON file in the `plugins` directory with your plugin information
4. Submit a Pull Request (PR) for review

**Important:** Submit only ONE plugin per Pull Request.

## JSON File Format

Create a new file named `your-plugin-slug.json` in the `plugins` directory with the following structure:

```json
{
  "schema_version": 1,
  "name": "Your Plugin Name",
  "slug": "your-plugin-slug",
  "author": "Your Name",
  "author_profile": "https://yoursite.com",
  "sections": {
    "description": "A clear description of what your plugin does",
    "installation": "",
    "changelog": "https://raw.githubusercontent.com/username/plugin/master/CHANGELOG.md"
  },
  "banners": {
    "low": "https://example.com/banner-772x250.jpg",
    "high": "https://example.com/banner-1544x500.jpg"
  },
  "type": "plugin",
  "wp-meta": false,
  "free": true,
  "paid_features": false,
  "date_added": "2024-12-30",
  "homepage": "https://yourplugin.com",
  "repository": "https://github.com/username/plugin",
  "tags": "https://github.com/username/plugin/tags",
  "update_from": "tags",
  "readme_md": "https://raw.githubusercontent.com/username/plugin/master/README.md",
  "readme_txt": "https://raw.githubusercontent.com/username/plugin/master/readme.txt"
}
```

### JSON Schema Properties

- `schema_version`: Must be 1
- `type`: Must be "plugin"
- `name`: Your plugin's display name
- `slug`: Unique identifier (lowercase, dashes for spaces), following WordPress plugin directory structure if your plugin already exists in there
- `author`: Plugin author's name
- `author_profile`: URL to author's website or profile
- `sections`: Object containing plugin documentation
  - `description`: Plugin description
  - `installation`: Installation instructions. Optional
  - `changelog`: URL to changelog file
- `banners`: Marketing images for your plugin
  - `low`: jpg or png file. 772x250 pixels
  - `high`: jpg or png file. 1544x500 pixels
- `wp-meta`: Boolean indicating if .wp-meta folder exists for this plugin
- `free`: Boolean indicating if plugin is free or not
- `paid_features`: Boolean indicating if plugin has paid features or not
- `date_added`: Date when the plugin was added to The Index
- `homepage`: Plugin's website
- `repository`: Full URL to your git repository
- `tags`: URL to repository tags
- `update_from`: Source for updates. For now: "tags" must be used
- `readme_md`: URL to README.md file
- `readme_txt`: URL to readme.txt file (WordPress format)

### .wp-meta Folder

Please, refer to the [Meta Folder docs](Repo-Meta-Folder) for more information about the `.wp-meta` folder.

## After Submission

1. Wait for the PR review
2. Address any feedback or requested changes
3. Once approved, your plugin will be added to The Index
