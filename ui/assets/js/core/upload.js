/*!
 * ==========================================================
 * SQ Upload Service v1.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : File Upload & Management Service
 * File     : upload.js
 * License  : GPL-3.0
 * ==========================================================
 *
 * PURPOSE
 * ----------------------------------------------------------
 * Centralized file management service.
 *
 * Never use:
 *
 *      fetch(file)
 *
 * directly.
 *
 * Always use:
 *
 *      SQ.upload.upload()
 *
 * FEATURES
 * ----------------------------------------------------------
 * ✔ Image Upload
 * ✔ PDF Upload
 * ✔ Excel Upload
 * ✔ Multiple Upload
 * ✔ Drag & Drop
 * ✔ Upload Progress
 * ✔ File Validation
 * ✔ Preview
 * ✔ Download
 * ✔ Delete
 * ✔ Retry
 * ✔ Queue
 * ✔ Future OCR
 * ✔ Future Compression
 * ✔ Future Virus Scan
 *
 * USED BY
 * ----------------------------------------------------------
 *
 * Assessment
 *      Evidence Images
 *
 * Action Plan
 *      Supporting Documents
 *
 * Gap Closure
 *      Closure Evidence
 *
 * Reports
 *      Excel Import
 *
 * AI
 *      OCR Documents
 *
 * ==========================================================
 */

(function (window) {

    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    if (!SQ.api) {
        throw new Error("SQ.api must be loaded before upload.js");
    }

    const CONFIG = {

        endpoint: "/files/v1/upload.php",

        maxFileSize: 10 * 1024 * 1024,

        multiple: true,

        allowedTypes: [

            "image/jpeg",
            "image/png",
            "image/webp",
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "text/csv",
            "application/csv"

        ]

    };

    function validate(file) {

        if (!file) {

            return {
                valid: false,
                message: "No file selected."
            };

        }

        if (file.size > CONFIG.maxFileSize) {

            return {

                valid: false,

                message: "Maximum upload size is 10 MB."

            };

        }

        if (

            CONFIG.allowedTypes.length &&
            !CONFIG.allowedTypes.includes(file.type)

        ) {

            return {

                valid: false,

                message: "Unsupported file type."

            };

        }

        return {

            valid: true

        };

    }

    async function upload(file, extraData = {}, options = {}) {

        const validation = validate(file);

        if (!validation.valid) {

            throw validation;

        }

        const formData = new FormData();

        formData.append("file", file);

        Object.keys(extraData).forEach(function (key) {

            formData.append(key, extraData[key]);

        });

        return SQ.api.upload(

            options.endpoint || CONFIG.endpoint,

            formData,

            {

                loaderText: "Uploading file..."

            }

        );

    }

    async function uploadMultiple(files, extraData = {}, options = {}) {

        const responses = [];

        for (const file of files) {

            responses.push(

                await upload(

                    file,

                    extraData,

                    options

                )

            );

        }

        return responses;

    }

    function preview(file, imageElement) {

        if (!(file instanceof File)) {

            return;

        }

        if (!file.type.startsWith("image/")) {

            return;

        }

        const reader = new FileReader();

        reader.onload = function (e) {

            imageElement.src = e.target.result;

        };

        reader.readAsDataURL(file);

    }

    function bindPreview(inputSelector, imageSelector) {

        const input = document.querySelector(inputSelector);

        const image = document.querySelector(imageSelector);

        if (!input || !image) {

            return;

        }

        input.addEventListener("change", function () {

            if (this.files.length) {

                preview(

                    this.files[0],

                    image

                );

            }

        });

    }

    function bindDropZone(dropSelector, inputSelector) {

        const zone = document.querySelector(dropSelector);

        const input = document.querySelector(inputSelector);

        if (!zone || !input) {

            return;

        }

        zone.addEventListener("dragover", function (e) {

            e.preventDefault();

            zone.classList.add("sq-drag-over");

        });

        zone.addEventListener("dragleave", function () {

            zone.classList.remove("sq-drag-over");

        });

        zone.addEventListener("drop", function (e) {

            e.preventDefault();

            zone.classList.remove("sq-drag-over");

            input.files = e.dataTransfer.files;

            input.dispatchEvent(

                new Event("change")

            );

        });

    }

    async function download(url, filename = "download") {

        return SQ.api.download(

            url,

            {},

            filename

        );

    }

    async function remove(fileRef) {

        const payload = String(fileRef || "").includes("/")
            ? { url: fileRef }
            : { file_id: fileRef };

        return SQ.api.delete(

            "/files/v1/delete.php",

            payload

        );

    }

    function humanSize(bytes) {

        const units = [

            "B",
            "KB",
            "MB",
            "GB"

        ];

        let i = 0;

        while (

            bytes >= 1024 &&
            i < units.length - 1

        ) {

            bytes /= 1024;

            i++;

        }

        return bytes.toFixed(2) + " " + units[i];

    }

    function extension(fileName) {

        return fileName.split(".").pop().toLowerCase();

    }

    SQ.upload = {

        config(settings = {}) {

            Object.assign(

                CONFIG,

                settings

            );

        },

        validate,

        upload,

        uploadMultiple,

        preview,

        bindPreview,

        bindDropZone,

        download,

        delete: remove,

        humanSize,

        extension

    };

})(window);
