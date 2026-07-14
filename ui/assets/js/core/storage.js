/*!
 * ==========================================================
 * SQ Storage Service v1.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : Browser Storage Service
 * File     : storage.js
 * License  : MIT
 * ==========================================================
 *
 * PURPOSE
 * ----------------------------------------------------------
 * Central storage manager used by the entire application.
 *
 * Never use:
 *
 *      localStorage.setItem(...)
 *      localStorage.getItem(...)
 *      sessionStorage.setItem(...)
 *
 * directly anywhere in the application.
 *
 * Always use:
 *
 *      SQ.storage.set(...)
 *      SQ.storage.get(...)
 *
 * BENEFITS
 * ----------------------------------------------------------
 * ✔ Single Storage Layer
 * ✔ Automatic JSON conversion
 * ✔ Namespace support
 * ✔ Optional Expiry (TTL)
 * ✔ Session Storage
 * ✔ Local Storage
 * ✔ Future Encryption
 * ✔ Future IndexedDB Migration
 * ✔ Easy Testing
 *
 * USED BY
 * ----------------------------------------------------------
 *
 * Authentication
 *      User Session
 *      User Profile
 *      Login Token
 *      CSRF Token
 *
 * Assessment
 *      Active Assessment
 *      Active Department
 *      Current Checkpoint
 *
 * Dashboard
 *      Dashboard Filters
 *      Facility Selection
 *
 * Reports
 *      Last Selected Report
 *
 * Theme
 *      Dark / Light Mode
 *
 * Chat Assistant
 *      Chat History
 *      Prompt History
 *
 * Offline Mode
 *      Cached API Responses
 *
 * File Upload
 *      Upload Queue
 *
 * Notification
 *      Read Notifications
 *
 * Future Mobile App
 *
 * ==========================================================
 */

(function (window) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const DEFAULT_NAMESPACE = "sq";

    function storage(type = "local") {
        return type === "session"
            ? sessionStorage
            : localStorage;
    }

    function key(name, namespace = DEFAULT_NAMESPACE) {
        return namespace + ":" + name;
    }

    function now() {
        return Date.now();
    }

    function encode(value, ttl = null) {

        const payload = {

            value: value,

            created_at: now(),

            expires_at: ttl
                ? now() + ttl
                : null

        };

        return JSON.stringify(payload);

    }

    function decode(value) {

        if (!value) {
            return null;
        }

        try {

            const payload = JSON.parse(value);

            if (
                payload.expires_at &&
                payload.expires_at < now()
            ) {

                return "__expired__";

            }

            return payload.value;

        } catch (e) {

            return null;

        }

    }

    const Storage = {

        /*
        --------------------------------------------------
        Save value
        --------------------------------------------------
        */

        set(name, value, options = {}) {

            const namespace =
                options.namespace || DEFAULT_NAMESPACE;

            const type =
                options.type || "local";

            const ttl =
                options.ttl || null;

            storage(type).setItem(
                key(name, namespace),
                encode(value, ttl)
            );

            return true;

        },

        /*
        --------------------------------------------------
        Read value
        --------------------------------------------------
        */

        get(name, fallback = null, options = {}) {

            const namespace =
                options.namespace || DEFAULT_NAMESPACE;

            const type =
                options.type || "local";

            const value = storage(type).getItem(
                key(name, namespace)
            );

            const result = decode(value);

            if (result === "__expired__") {

                this.remove(name, options);

                return fallback;

            }

            return result ?? fallback;

        },

        /*
        --------------------------------------------------
        Exists
        --------------------------------------------------
        */

        has(name, options = {}) {

            return this.get(name, null, options) !== null;

        },

        /*
        --------------------------------------------------
        Remove
        --------------------------------------------------
        */

        remove(name, options = {}) {

            const namespace =
                options.namespace || DEFAULT_NAMESPACE;

            const type =
                options.type || "local";

            storage(type).removeItem(
                key(name, namespace)
            );

        },

        /*
        --------------------------------------------------
        Clear namespace
        --------------------------------------------------
        */

        clear(options = {}) {

            const namespace =
                options.namespace || DEFAULT_NAMESPACE;

            const type =
                options.type || "local";

            const s = storage(type);

            const prefix = namespace + ":";

            Object.keys(s).forEach(function (k) {

                if (k.startsWith(prefix)) {

                    s.removeItem(k);

                }

            });

        },

        /*
        --------------------------------------------------
        Clear everything
        --------------------------------------------------
        */

        clearAll() {

            localStorage.clear();

            sessionStorage.clear();

        },

        /*
        --------------------------------------------------
        Increment Number
        --------------------------------------------------
        */

        increment(name, amount = 1, options = {}) {

            let value = Number(
                this.get(name, 0, options)
            );

            value += amount;

            this.set(name, value, options);

            return value;

        },

        /*
        --------------------------------------------------
        Decrement Number
        --------------------------------------------------
        */

        decrement(name, amount = 1, options = {}) {

            let value = Number(
                this.get(name, 0, options)
            );

            value -= amount;

            this.set(name, value, options);

            return value;

        },

        /*
        --------------------------------------------------
        Push Item
        --------------------------------------------------
        */

        push(name, value, options = {}) {

            const array = this.get(
                name,
                [],
                options
            );

            array.push(value);

            this.set(
                name,
                array,
                options
            );

            return array;

        },

        /*
        --------------------------------------------------
        Keys
        --------------------------------------------------
        */

        keys(options = {}) {

            const namespace =
                options.namespace || DEFAULT_NAMESPACE;

            const type =
                options.type || "local";

            const s = storage(type);

            const prefix = namespace + ":";

            return Object.keys(s)

                .filter(k => k.startsWith(prefix))

                .map(k => k.replace(prefix, ""));

        },

        /*
        --------------------------------------------------
        Size
        --------------------------------------------------
        */

        size(options = {}) {

            return this.keys(options).length;

        },

        /*
        --------------------------------------------------
        Export
        --------------------------------------------------
        */

        export(options = {}) {

            const data = {};

            this.keys(options).forEach(keyName => {

                data[keyName] = this.get(
                    keyName,
                    null,
                    options
                );

            });

            return data;

        },

        /*
        --------------------------------------------------
        Import
        --------------------------------------------------
        */

        import(data = {}, options = {}) {

            Object.keys(data).forEach(name => {

                this.set(
                    name,
                    data[name],
                    options
                );

            });

        }

    };

    window.SQ.storage = Storage;

})(window);