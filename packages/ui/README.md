# @jashan-randhawa/busres-ui

Design tokens, component styling, and dark mode theme switcher for the Bus Reservation System.

## Installation

Configure your `.npmrc` to authenticate with GitHub Packages:

```ini
@jashan-randhawa:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${GITHUB_TOKEN}
```

Then install the package:

```bash
npm install @jashan-randhawa/busres-ui
```

## CSS Usage

### Combined Stylesheet
Import the complete design system (tokens + components):

```css
@import "@jashan-randhawa/busres-ui";
/* or @import "@jashan-randhawa/busres-ui/dist/busres-ui.css"; */
```

### Modular Imports
If you only need design tokens without component resets and classes:

```css
@import "@jashan-randhawa/busres-ui/tokens.css";
```

Or component classes:

```css
@import "@jashan-randhawa/busres-ui/components.css";
```

## Theme Switcher

Include the theme toggle script in your page `<head>` or before `</body>`:

```html
<script src="node_modules/@jashan-randhawa/busres-ui/src/theme-toggle.js"></script>
```

Add theme toggle buttons anywhere in your markup:

```html
<button class="theme-toggle-btn" aria-label="Toggle theme">
  <span class="theme-toggle-icon"></span>
  <span class="theme-toggle-text">Theme</span>
</button>
```

### Configurable Storage Key
```javascript
const theme = BusThemeToggle.init({
  storageKey: 'my_custom_theme_key'
});

// Programmatic control:
theme.toggle();
console.log('Current theme:', theme.getTheme());
```

## License

MIT © Jashan Randhawa
