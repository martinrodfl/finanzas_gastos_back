window.httpFetch = (url, options = {}) => {
    const headers = new Headers(options.headers ?? {});
    if (!headers.has("X-Requested-With")) {
        headers.set("X-Requested-With", "XMLHttpRequest");
    }

    return fetch(url, {
        ...options,
        headers,
    });
};
