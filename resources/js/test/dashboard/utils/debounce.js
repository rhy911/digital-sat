const debounceTimers = new Map();

/**
 * Run fn after delay ms, resetting the timer if called again with the same key
 * before it fires. Shared so callers don't each reimplement their own timer map.
 */
export function debounce(key, fn, delay) {
    if (debounceTimers.has(key)) clearTimeout(debounceTimers.get(key));
    debounceTimers.set(key, setTimeout(() => {
        debounceTimers.delete(key);
        fn();
    }, delay));
}
