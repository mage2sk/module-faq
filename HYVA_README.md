# Hyva Theme Compatibility

This FAQ module is fully compatible with Hyva themes and provides optimized templates for the best performance.

## Features

### Alpine.js Integration
- Accordion functionality powered by Alpine.js
- No jQuery dependencies
- Lightweight and fast

### Tailwind CSS Styling
- Modern, responsive design using Tailwind CSS utility classes
- Customizable through Tailwind configuration
- Mobile-first approach

### Performance Optimizations
- No RequireJS/AMD module loading
- Reduced JavaScript bundle size
- Native browser APIs for AJAX requests

## Automatic Theme Detection

The module automatically detects if you're using a Hyva theme and loads the appropriate templates:

- **Hyva Theme**: Uses templates from `view/frontend/templates/hyva/`
- **Standard Theme**: Uses templates from `view/frontend/templates/`

## Layout Overrides

Hyva-specific layouts are defined in:
- `faq_index_index_hyva.xml` - FAQ listing page
- `faq_index_view_hyva.xml` - Individual FAQ item view
- `faq_category_view_hyva.xml` - Category view page

## Template Structure

### Main Templates
- `hyva/index/index.phtml` - FAQ listing with search and category filters
- `hyva/index/view.phtml` - Single FAQ item detail view
- `hyva/category/view.phtml` - Category-specific FAQ listing

### Partial Templates
- `hyva/partials/faq-item.phtml` - Reusable FAQ item component

## Alpine.js Components

### faqAccordion
Manages the FAQ accordion functionality:
- Toggle FAQ items open/closed
- URL hash handling for direct linking
- Helpful voting system

### faqView
Handles single FAQ item view:
- Helpful voting functionality
- LocalStorage integration for vote tracking

## Customization

### Styling
All Hyva templates use Tailwind CSS utility classes. You can customize the appearance by:

1. **Extending Tailwind Configuration**: Add custom colors, spacing, etc. in your theme's `tailwind.config.js`

2. **Overriding Templates**: Copy templates to your theme:
   ```
   app/design/frontend/[Vendor]/[Theme]/Panth_Faq/templates/hyva/
   ```

3. **Custom CSS**: Add custom styles in your theme's CSS files

### JavaScript Functionality
Alpine.js functions are defined inline in the templates. To customize:

1. Copy the template to your theme
2. Modify the Alpine.js functions as needed
3. Or create a custom Alpine component in your theme's JavaScript

## Browser Support

The Hyva-compatible templates use modern JavaScript features:
- Fetch API for AJAX requests
- LocalStorage for vote tracking
- ES6+ syntax

Supported browsers:
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Performance Benefits

Compared to standard Magento templates:
- **60-70% smaller JavaScript bundle** - No jQuery, no RequireJS
- **Faster page load** - Native APIs and minimal dependencies
- **Better Core Web Vitals** - Optimized for LCP, FID, and CLS

## Troubleshooting

### Templates Not Loading
1. Clear cache: `bin/magento cache:clean`
2. Verify Hyva theme is active
3. Check layout XML files are present

### Alpine.js Not Working
1. Ensure Hyva theme includes Alpine.js
2. Check browser console for JavaScript errors
3. Verify Alpine.js version compatibility (2.x or 3.x)

### Styling Issues
1. Verify Tailwind CSS is compiled
2. Check that custom classes are included in Tailwind purge configuration
3. Run `npm run build` in your theme directory

## Migration from Standard Templates

If you're migrating from standard Magento templates to Hyva:

1. **No code changes required** - The module automatically detects Hyva
2. **Custom styling may need adjustment** - Convert custom CSS to Tailwind classes
3. **Custom JavaScript** - Rewrite jQuery code to Alpine.js if you have customizations

## Support

For Hyva-specific issues:
- Check Hyva documentation: https://docs.hyva.io
- Review Alpine.js docs: https://alpinejs.dev
- Tailwind CSS reference: https://tailwindcss.com

## Version Compatibility

- Magento 2.4.x
- Hyva Theme 1.1.x and above
- Alpine.js 2.x or 3.x
- Tailwind CSS 2.x or 3.x
