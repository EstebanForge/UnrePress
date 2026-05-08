# Publishing your Theme in The Index

How to publish your theme into UnrePress's Index.

## Requirements

Before publishing your theme, ensure it meets these requirements:

1. **Version Control**
   - Hosted on GitHub, GitLab, or BitBucket
   - Uses semantic versioning (X.Y.Z)
   - Has proper release tags, that follow semantic versioning

2. **Theme Structure**
   - Following WordPress theme directory structure
   - Style.css file with proper header comment
   - README.md with installation and usage instructions

3. **.wp-meta Folder** (optional)
   - Contains any additional files needed for the theme to be shown in The Index inside WordPress or the future public Index vía web.
   - Contains an additional .json file, with extra information from your theme.
   - This folder is not required, but it's recommended to have it.

## Publishing Steps

1. Go to [UnrePress-index](https://github.com/EstebanForge/UnrePress-index)
2. Fork and clone the repository
3. Create a new JSON file in the `themes` directory with your theme information
4. Submit a Pull Request (PR) for review

**Important:** Submit only ONE theme per Pull Request.

## JSON File Format

Create a new file named `your-theme-slug.json` in the `themes` directory with the following structure:

```json
{
  "schema_version": 1,
  "name": "Your Theme Name",
  "slug": "your-theme-slug",
  "author": "Your Name",
  "author_profile": "https://yoursite.com",
  "sections": {
    "description": "A clear description of what your theme offers",
    "installation": "",
    "changelog": "https://raw.githubusercontent.com/username/theme/master/CHANGELOG.md"
  },
  "banners": {
    "low": "https://example.com/banner-772x250.jpg",
    "high": "https://example.com/banner-1544x500.jpg"
  },
  "screenshots": [
    "https://example.com/screenshot-1.jpg",
    "https://example.com/screenshot-2.jpg"
  ],
  "type": "theme",
  "wp-meta": false,
  "free": true,
  "paid_features": false,
  "date_added": "2024-12-30",
  "homepage": "https://yourtheme.com",
  "repository": "https://github.com/username/theme",
  "tags": "https://github.com/username/theme/tags",
  "update_from": "tags",
  "readme_md": "https://raw.githubusercontent.com/username/theme/master/README.md",
  "readme_txt": "https://raw.githubusercontent.com/username/theme/master/readme.txt"
}
```

### JSON Schema Properties

- `schema_version`: Must be 1
- `type`: Must be "theme"
- `name`: Your theme's display name
- `slug`: Unique identifier (lowercase, dashes for spaces), following WordPress theme directory structure if your theme already exists in there
- `author`: Theme author's name
- `author_profile`: URL to author's website or profile
- `sections`: Object containing theme documentation
  - `description`: Theme description
  - `installation`: Installation instructions. Optional
  - `changelog`: URL to changelog file
- `banners`: Marketing images for your theme
  - `low`: jpg or png file. 772x250 pixels
  - `high`: jpg or png file. 1544x500 pixels
- `screenshots`: Array of URLs to theme screenshots (recommended size: 1200x900 pixels)
- `wp-meta`: Boolean indicating if .wp-meta folder exists for this theme
- `free`: Boolean indicating if theme is free or not
- `paid_features`: Boolean indicating if theme has paid features or not
- `date_added`: Date when the theme was added to The Index
- `homepage`: Theme's website
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
3. Once approved, your theme will be added to The Index
