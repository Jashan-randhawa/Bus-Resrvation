# @jashan-randhawa/busres-ui

Design tokens, component styling, and permanent light mode enforcement for the Bus Reservation System.

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

## Permanent Light Mode Enforcer & Storage Purger

Include the theme module in your page `<head>` or before `</body>`:

```html
<script src="node_modules/@jashan-randhawa/busres-ui/src/theme-toggle.js"></script>
```

### API Usage
```javascript
const theme = BusThemeToggle.init();

console.log('Current theme:', theme.getTheme()); // Always 'light'
```

### Features
- **Permanent Light Mode Enforcement:** Automatically sets `data-theme="light"` on `document.documentElement` and removes legacy `.dark` and `.dark-mode` classes.
- **Legacy Storage Purging:** Automatically purges deprecated local storage keys (`busres_theme_mode`, `busbook_theme`, `theme`) on load.
- **PWA Status Bar Synchronization:** Sets `<meta name="theme-color">` to `#ffffff`.
- **FOUC Prevention:** Immediately initializes before DOM ready to prevent visual flickering.

## License

MIT © Jashan Randhawa
