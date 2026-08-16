/* Browser-local queue for unsent checklist responses. No credentials or tokens are stored. */
(function (window) {
    "use strict";

    const DB_NAME = "saqshi-offline";
    const STORE = "response_queue";

    function database() {
        return new Promise(function (resolve, reject) {
            const request = indexedDB.open(DB_NAME, 1);
            request.onupgradeneeded = function () {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE, { keyPath: "id" });
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
    }

    async function all() {
        const db = await database();
        return new Promise(function (resolve, reject) {
            const request = db.transaction(STORE, "readonly").objectStore(STORE).getAll();
            request.onsuccess = function () { resolve(request.result || []); db.close(); };
            request.onerror = function () { reject(request.error); db.close(); };
        });
    }

    async function put(item) {
        const db = await database();
        return new Promise(function (resolve, reject) {
            const request = db.transaction(STORE, "readwrite").objectStore(STORE).put(item);
            request.onsuccess = function () { resolve(); db.close(); };
            request.onerror = function () { reject(request.error); db.close(); };
        });
    }

    async function remove(id) {
        const db = await database();
        return new Promise(function (resolve, reject) {
            const request = db.transaction(STORE, "readwrite").objectStore(STORE).delete(id);
            request.onsuccess = function () { resolve(); db.close(); };
            request.onerror = function () { reject(request.error); db.close(); };
        });
    }

    window.SQ = window.SQ || {};
    window.SQ.offlineResponseQueue = {
        async enqueue(userId, payload) {
            const id = [userId || 0, payload.assessment_id, payload.dept_id, payload.checkpoint_id].join(":");
            await put({ id: id, user_id: Number(userId || 0), payload: payload, queued_at: Date.now() });
        },
        async count(userId) {
            return (await all()).filter(item => Number(item.user_id) === Number(userId || 0)).length;
        },
        async flush(userId, send) {
            const items = (await all()).filter(item => Number(item.user_id) === Number(userId || 0)).sort((a, b) => a.queued_at - b.queued_at);
            let sent = 0;
            for (const item of items) {
                await send(item.payload);
                await remove(item.id);
                sent++;
            }
            return sent;
        }
    };
})(window);
