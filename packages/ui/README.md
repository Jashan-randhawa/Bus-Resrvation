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

### Features
- **PWA Status Bar Synchronization:** Automatically detects `<meta name="theme-color">` elements (including media-query dark/light variants) and syncs `#0B0F19` / `#ffffff` status bar colors dynamically on theme switch.
- **Dual Element & Key Support:** Sets `data-theme="dark"` and `.dark-mode` class on both `document.documentElement` and `document.body`, with fallback synchronization for both `theme` and `busres_theme_mode` local storage keys.
- **FOUC Prevention:** Immediately initializes before DOM ready to prevent visual flickering.

## License

MIT © Jashan Randhawa

