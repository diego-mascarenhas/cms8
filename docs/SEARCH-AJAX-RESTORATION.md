# Search AJAX Restoration - Implementation Guide

## Overview
This document tracks the restoration of the AJAX search functionality in Humano, moving away from Livewire back to the original Vuexy-style Typeahead.js implementation.

## Problem Statement
The search functionality stopped working after attempts to modularize the application into packages. The issue arose when trying to conditionally search in modules (like invoices) that might not be installed.

## Root Cause
The main issue was using **Bloodhound-style independent AJAX calls** for each dataset, which caused:
1. Multiple simultaneous AJAX requests for the same query
2. Race conditions in Typeahead.js rendering
3. Inconsistent results across datasets

## Solution: Shared AJAX Cache Pattern

### Key Implementation: `fetchSearchResponse()`

The working solution uses a **shared AJAX cache** that ensures only ONE request is made per query, and all datasets share the same response:

```javascript
// Shared AJAX cache to avoid multiple requests for the same query
var searchAjaxCache = {
  lastQuery: null,
  lastResponse: null,
  inflight: null,
  listeners: []
};

// Fetch search response (shared among all datasets)
function fetchSearchResponse(query, onDone) {
  // If we already have a response for this exact query, reuse it immediately
  if (searchAjaxCache.lastQuery === query && searchAjaxCache.lastResponse) {
    return onDone(searchAjaxCache.lastResponse);
  }
  // If there is a request in-flight for this query, attach as listener
  if (searchAjaxCache.inflight && searchAjaxCache.lastQuery === query) {
    searchAjaxCache.listeners.push(onDone);
    return;
  }
  // Start a new request for this query
  searchAjaxCache.lastQuery = query;
  searchAjaxCache.listeners = [onDone];
  searchAjaxCache.inflight = $.ajax({
    url: baseUrl + 'contact/search',
    dataType: 'json',
    data: { q: query }
  })
    .done(function (response) {
      searchAjaxCache.lastResponse = response;
      var cbs = searchAjaxCache.listeners.slice(0);
      searchAjaxCache.listeners = [];
      cbs.forEach(function (fn) { try { fn(response); } catch (e) { console.error(e); } });
    })
    .fail(function (xhr, status, error) {
      console.error('[Search] AJAX Error!', status, error);
      var cbs = searchAjaxCache.listeners.slice(0);
      searchAjaxCache.listeners = [];
      cbs.forEach(function (fn) { try { fn({}); } catch (e) { console.error(e); } });
    })
    .always(function () {
      searchAjaxCache.inflight = null;
    });
}
```

### Dynamic Search Function

Each dataset uses this simple function to extract its specific field from the shared response:

```javascript
// Dynamic search function - queries server on each keystroke
var dynamicSearch = function (field) {
  return function findMatches(q, cb) {
    if (!q || q.length < 1) {
      return cb([]);
    }

    fetchSearchResponse(q, function (response) {
      var results = response[field] || [];
      if (typeof results === 'object' && !Array.isArray(results)) {
        results = Object.values(results);
      }
      cb(results);
    });
  };
};
```

## Typeahead.js Configuration

### Correct Dataset Pattern ✅

Use a **single dataset per category** with `header`, `suggestion`, AND `notFound`:

```javascript
// Contacts
{
  name: 'contacts',
  display: 'name',
  limit: 10,
  source: dynamicSearch('members'),
  templates: {
    header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Contactos</h6>',
    suggestion: function (data) {
      if (!data || !data.name) {
        return '';
      }
      var name = data.name || '';
      var subtitle = data.subtitle || '';
      var url = data.url || '#';
      return (
        '<a href="' + url + '">' +
        '<div class="d-flex align-items-center">' +
        '<i class="ti ti-user me-2"></i>' +
        '<div class="user-info">' +
        '<h6 class="mb-0">' + name + '</h6>' +
        '<small class="text-muted">' + subtitle + '</small>' +
        '</div>' +
        '</div>' +
        '</a>'
      );
    },
    notFound:
      '<div class="not-found px-3 py-2">' +
      '<h6 class="suggestions-header text-primary mb-2">Contactos</h6>' +
      '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Contacto no encontrado</p>' +
      '</div>'
  }
}
```

**Key Points:**
- ✅ `header`: Displays category name (always shown)
- ✅ `suggestion`: Template for each result
- ✅ `notFound`: Message when no results (prevents empty sections)
- ✅ Validation in `suggestion` function (`if (!data || !data.name)`)
- ✅ Safe property access with fallbacks (`data.name || ''`)

### ❌ Incorrect Pattern (Double Dataset)

**Don't** use two datasets (header+notFound and results) as it causes "not found" messages to appear above results:

```javascript
// ❌ DON'T DO THIS - causes duplicate headers
{
  name: 'contacts',
  limit: 0,
  source: dynamicSearch('members'),
  templates: {
    header: '...',
    notFound: '...'
  }
},
{
  name: 'contacts-results',
  limit: 10,
  source: dynamicSearch('members'),
  templates: {
    suggestion: function (data) { ... }
  }
}
```

## Backend Response Format

The `contactController@search` method returns:

```json
{
  "pages": [...],
  "files": [...],
  "members": [
    {
      "name": "pepe",
      "subtitle": "pepe@pepe.com",
      "url": "/contact/123"
    }
  ],
  "enterprises": [...],
  "services": [...],
  "projects": [...],
  "invoices": [...]
}
```

Each dataset extracts its field (`members`, `enterprises`, etc.) from this single response.

## Files Modified

1. **resources/assets/js/main.js**
   - Added `fetchSearchResponse()` function
   - Updated `dynamicSearch()` to use shared cache
   - Simplified datasets (removed double-dataset pattern)
   - Removed hardcoded debug data

2. **public/assets/js/main.js**
   - Manually copied from resources (Vite wasn't updating it correctly)

3. **resources/views/layouts/sections/navbar/navbar.blade.php**
   - Reverted from Livewire to original AJAX pattern
   - Added search toggler and hidden input

4. **config/custom.php**
   - Enabled search: `'showSearch' => true`

5. **resources/views/layouts/sections/scripts.blade.php**
   - Added Select2 globally to fix JavaScript errors

6. **resources/views/layouts/sections/styles.blade.php**
   - Added Select2 CSS globally

## Testing

### Manual Testing Steps

1. Navigate to any page (e.g., `/contact/list`)
2. Press `Ctrl+/` to open search
3. Type "pepe" (or any search term)
4. Verify dropdown appears with results grouped by category
5. Click a result to navigate

### Verification

- ✅ Search opens with `Ctrl+/`
- ✅ AJAX request goes to `/contact/search?q=...`
- ✅ Results display grouped by: Contactos, Empresas, Servicios, Proyectos, Facturas
- ✅ Clicking a result navigates to the correct URL
- ✅ Search closes on selection or Escape

## Troubleshooting History

### Issue 1: Search Bar Hidden
**Solution**: Changed `config/custom.php` → `'showSearch' => true`

### Issue 2: Select2 Not Defined
**Solution**: Added Select2 globally in layouts

### Issue 3: Ctrl+/ Not Working
**Solution**: Uncommented keydown event listener

### Issue 4: Vite Not Updating public/assets/js/main.js
**Solution**: Manual copy required:
```bash
cp resources/assets/js/main.js public/assets/js/main.js
```

### Issue 5: Typeahead Not Rendering
**Root Cause**: Using independent AJAX calls per dataset
**Solution**: Implemented shared `fetchSearchResponse()` cache

### Issue 6: "Not Found" Message Above Results
**Root Cause**: Using double-dataset pattern (header+results)
**Solution**: Use single dataset with both header and suggestion

## Key Learnings

1. **Shared AJAX is Critical**: Multiple Typeahead datasets need to share ONE AJAX response
2. **Single Dataset Pattern**: Use one dataset per category with header+suggestion, not header+results
3. **Vite Doesn't Auto-Copy**: The `vite-plugin-static-copy` wasn't updating `public/assets/js/main.js` correctly - manual copy required
4. **Debug Carefully**: Browser console logs are essential for debugging Typeahead.js
5. **Git History is Gold**: Finding the working version before package separation was key to solving this

## References

- Original working commit: `f5f701a3` (before Livewire integration)
- Typeahead.js docs: https://github.com/twitter/typeahead.js/
- Related issue: Package separation causing conditional module searches to fail

---

**Status**: ✅ **WORKING** as of 2026-01-11  
**Last Updated**: 2026-01-11 23:00 UTC
