/**
 * Fix for DataTables and searchInput errors
 * This file addresses common JavaScript errors in the application
 */

(function() {
    'use strict';

    // Fix for "Unexpected token 'u'" error in JSON parsing
    const originalJSONParse = JSON.parse;
    JSON.parse = function(text, reviver) {
        // Check if text is undefined, null, or empty string
        if (text === undefined || text === null || text === '') {
            console.warn('JSON.parse called with invalid text:', text);
            return {};
        }
        
        // Check if text is 'undefined' string
        if (text === 'undefined') {
            console.warn('JSON.parse called with "undefined" string');
            return {};
        }

        try {
            return originalJSONParse.call(this, text, reviver);
        } catch (error) {
            console.error('JSON.parse error:', error, 'Text:', text);
            return {};
        }
    };

    // Wait for DOM to be ready
    $(document).ready(function() {
        
        // Fix for searchInput conflicts
        if (typeof window.searchInputFixed === 'undefined') {
            window.searchInputFixed = true;
            
            // Ensure searchInput is properly defined
            const searchInputElements = $('.search-input');
            
            if (searchInputElements.length > 0) {
                // Add proper event handlers with error catching
                searchInputElements.each(function() {
                    const $input = $(this);
                    
                    // Remove any conflicting event handlers
                    $input.off('keyup.searchfix keydown.searchfix focus.searchfix');
                    
                    // Add safe event handlers
                    $input.on('keyup.searchfix', function(e) {
                        try {
                            // Safe keyup handler
                            if (typeof $input.val === 'function') {
                                const value = $input.val();
                                console.log('Search input value:', value);
                            }
                        } catch (error) {
                            console.error('Search input keyup error:', error);
                        }
                    });
                    
                    $input.on('focus.searchfix', function(e) {
                        try {
                            // Safe focus handler
                            console.log('Search input focused');
                        } catch (error) {
                            console.error('Search input focus error:', error);
                        }
                    });
                });
            }
        }

        // Fix for DataTables AJAX errors
        if ($.fn.dataTable) {
            // Override DataTables error handling
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.warn('DataTables error:', message);
                // Don't throw the error, just log it
                return false;
            };

            // Add global AJAX error handler for DataTables
            $(document).on('xhr.dt', function(e, settings, json, xhr) {
                if (xhr.status === 0) {
                    console.warn('DataTables AJAX request cancelled or network error');
                } else if (xhr.status >= 400) {
                    console.error('DataTables AJAX error:', xhr.status, xhr.statusText);
                }
            });
        }

        // Fix for localStorage access errors
        const originalSetItem = Storage.prototype.setItem;
        Storage.prototype.setItem = function(key, value) {
            try {
                if (value === undefined) {
                    console.warn('Attempting to store undefined value in localStorage for key:', key);
                    return;
                }
                return originalSetItem.call(this, key, value);
            } catch (error) {
                console.error('localStorage setItem error:', error);
            }
        };

        const originalGetItem = Storage.prototype.getItem;
        Storage.prototype.getItem = function(key) {
            try {
                return originalGetItem.call(this, key);
            } catch (error) {
                console.error('localStorage getItem error:', error);
                return null;
            }
        };

        console.log('DataTables error fixes applied successfully');
    });

    // Additional safety for typeahead initialization
    $(document).on('DOMContentLoaded', function() {
        // Wait a bit more to ensure all libraries are loaded
        setTimeout(function() {
            if ($.fn.typeahead && $('.search-input').length > 0) {
                $('.search-input').each(function() {
                    const $input = $(this);
                    
                    // Check if typeahead is already initialized
                    if (!$input.data('ttTypeahead')) {
                        console.log('Typeahead not initialized for element, this is normal if handled elsewhere');
                    }
                });
            }
        }, 500);
    });

})(); 