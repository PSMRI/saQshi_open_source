/*!
 * ==========================================================
 * SQ Validator Service v1.0
 * ----------------------------------------------------------
 * Project  : SaQshi Open Source
 * Module   : Form Validation Service
 * File     : validator.js
 * License  : GPL-3.0
 * ==========================================================
 *
 * PURPOSE
 * ----------------------------------------------------------
 * Central client-side validation service.
 *
 * Used for:
 * - Login form
 * - Assessment creation
 * - Department activation
 * - Assessor / assessee information
 * - Checklist response validation
 * - Action plan form
 * - Gap closure form
 * - Evidence upload form
 * - Reports filters
 * - Admin forms
 *
 * This does NOT replace backend validation.
 * Backend validation is always final.
 *
 * BASIC USAGE
 * ----------------------------------------------------------
 *
 * const result = SQ.validator.validate(form, {
 *     assessment_name: ["required", "min:3"],
 *     end_date: ["required", "date"],
 *     mobile: ["mobile"]
 * });
 *
 * if (!result.valid) {
 *     SQ.validator.showErrors(form, result.errors);
 * }
 *
 * ==========================================================
 */

(function (window, document) {
    "use strict";

    if (!window.SQ) {
        window.SQ = {};
    }

    const SQ = window.SQ;

    const Validator = {};

    /* ======================================================
       Utility
    ====================================================== */

    function isEmpty(value) {
        return value === null ||
            value === undefined ||
            String(value).trim() === "";
    }

    function getValue(form, field) {
        const input = form.querySelector("[name='" + field + "']");

        if (!input) {
            return "";
        }

        if (input.type === "checkbox") {
            return input.checked ? input.value || true : "";
        }

        if (input.type === "radio") {
            const checked = form.querySelector("[name='" + field + "']:checked");
            return checked ? checked.value : "";
        }

        return input.value;
    }

    function getLabel(form, field) {
        const input = form.querySelector("[name='" + field + "']");

        if (!input) {
            return field;
        }

        const id = input.getAttribute("id");

        if (id) {
            const label = form.querySelector("label[for='" + id + "']");

            if (label) {
                return label.textContent.replace("*", "").trim();
            }
        }

        return input.getAttribute("data-label") || field;
    }

    function addError(errors, field, message) {
        if (!errors[field]) {
            errors[field] = message;
        }
    }

    /* ======================================================
       Rules
    ====================================================== */

    Validator.rules = {
        required: function (value) {
            return !isEmpty(value);
        },

        email: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },

        mobile: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return /^[6-9]\d{9}$/.test(String(value).trim());
        },

        number: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return !Number.isNaN(Number(value));
        },

        integer: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return Number.isInteger(Number(value));
        },

        decimal: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return /^\d+(\.\d{1,2})?$/.test(String(value).trim());
        },

        date: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return !Number.isNaN(Date.parse(value));
        },

        url: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            try {
                new URL(value);
                return true;
            } catch (e) {
                return false;
            }
        },

        nin: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return /^\d{10}$/.test(String(value).trim());
        },

        pincode: function (value) {
            if (isEmpty(value)) {
                return true;
            }

            return /^\d{6}$/.test(String(value).trim());
        }
    };

    /* ======================================================
       Validation Messages
    ====================================================== */

    Validator.messages = {
        required: "{label} is required",
        email: "{label} must be a valid email address",
        mobile: "{label} must be a valid 10 digit mobile number",
        number: "{label} must be a valid number",
        integer: "{label} must be a valid integer",
        decimal: "{label} must be a valid decimal value",
        date: "{label} must be a valid date",
        url: "{label} must be a valid URL",
        nin: "{label} must be a valid 10 digit NIN number",
        pincode: "{label} must be a valid 6 digit PIN code",
        min: "{label} must be at least {param} characters",
        max: "{label} must not be more than {param} characters",
        minValue: "{label} must be at least {param}",
        maxValue: "{label} must not be greater than {param}",
        match: "{label} does not match {param}",
        in: "{label} has invalid value"
    };

    function message(rule, label, param) {
        const template = Validator.messages[rule] || "{label} is invalid";

        return template
            .replace("{label}", label)
            .replace("{param}", param || "");
    }

    /* ======================================================
       Main Validate
    ====================================================== */

    Validator.validate = function (form, rules = {}) {
        const formEl = typeof form === "string"
            ? document.querySelector(form)
            : form;

        const errors = {};

        if (!formEl) {
            return {
                valid: false,
                errors: {
                    form: "Form not found"
                }
            };
        }

        Object.keys(rules).forEach(function (field) {
            const fieldRules = rules[field];
            const value = getValue(formEl, field);
            const label = getLabel(formEl, field);

            fieldRules.forEach(function (ruleText) {
                if (errors[field]) {
                    return;
                }

                const parts = String(ruleText).split(":");
                const rule = parts[0];
                const param = parts[1] || null;

                if (Validator.rules[rule]) {
                    if (!Validator.rules[rule](value, param)) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "min") {
                    if (!isEmpty(value) && String(value).length < Number(param)) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "max") {
                    if (!isEmpty(value) && String(value).length > Number(param)) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "minValue") {
                    if (!isEmpty(value) && Number(value) < Number(param)) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "maxValue") {
                    if (!isEmpty(value) && Number(value) > Number(param)) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "match") {
                    const otherValue = getValue(formEl, param);

                    if (value !== otherValue) {
                        addError(errors, field, message(rule, label, param));
                    }

                    return;
                }

                if (rule === "in") {
                    const allowed = String(param).split(",");

                    if (!allowed.includes(String(value))) {
                        addError(errors, field, message(rule, label, param));
                    }
                }
            });
        });

        return {
            valid: Object.keys(errors).length === 0,
            errors: errors
        };
    };

    /* ======================================================
       Show Errors
    ====================================================== */

    Validator.showErrors = function (form, errors = {}) {
        const formEl = typeof form === "string"
            ? document.querySelector(form)
            : form;

        if (!formEl) {
            return;
        }

        Validator.clearErrors(formEl);

        Object.keys(errors).forEach(function (field) {
            const input = formEl.querySelector("[name='" + field + "']");

            if (!input) {
                return;
            }

            input.classList.add("sq-is-invalid");
            input.setAttribute("aria-invalid", "true");

            let errorEl = input
                .closest(".sq-form-group")
                ?.querySelector(".sq-field-error");

            if (!errorEl) {
                errorEl = document.createElement("div");
                errorEl.className = "sq-field-error";
                errorEl.setAttribute("role", "alert");

                const group = input.closest(".sq-form-group");

                if (group) {
                    group.appendChild(errorEl);
                } else {
                    input.insertAdjacentElement("afterend", errorEl);
                }
            }

            errorEl.textContent = errors[field];
        });
    };

    /* ======================================================
       Clear Errors
    ====================================================== */

    Validator.clearErrors = function (form) {
        const formEl = typeof form === "string"
            ? document.querySelector(form)
            : form;

        if (!formEl) {
            return;
        }

        formEl.querySelectorAll(".sq-is-invalid").forEach(function (input) {
            input.classList.remove("sq-is-invalid");
            input.removeAttribute("aria-invalid");
        });

        formEl.querySelectorAll(".sq-field-error").forEach(function (error) {
            error.remove();
        });
    };

    /* ======================================================
       Validate And Show
    ====================================================== */

    Validator.validateAndShow = function (form, rules = {}) {
        const result = Validator.validate(form, rules);

        if (!result.valid) {
            Validator.showErrors(form, result.errors);
        } else {
            Validator.clearErrors(form);
        }

        return result;
    };

    /* ======================================================
       Custom Rule
    ====================================================== */

    Validator.addRule = function (name, callback, messageText) {
        Validator.rules[name] = callback;

        if (messageText) {
            Validator.messages[name] = messageText;
        }
    };

    /* ======================================================
       Predefined SaQshi Validation Sets
    ====================================================== */

    Validator.presets = {
        login: {
            username: ["required"],
            password: ["required"]
        },

        assessmentCreate: {
            assessment_name: ["required", "min:3", "max:150"],
            start_date: ["required", "date"],
            end_date: ["required", "date"]
        },

        assessorInfo: {
            assessment_date: ["required", "date"],
            assessor_name: ["required", "min:3"],
            assessee_name: ["required", "min:3"],
            assessor_mobile: ["mobile"],
            assessee_mobile: ["mobile"],
            assessor_email: ["email"],
            assessee_email: ["email"]
        },

        actionPlan: {
            user_action_plan: ["required", "min:3"],
            achievability: ["required", "in:ACHIEVABLE,NON_ACHIEVABLE"],
            priority: ["required", "in:LOW,MEDIUM,HIGH"],
            target_date: ["date"]
        },

        gapClosure: {
            closure_remarks: ["required", "min:3"],
            revised_score: ["number", "minValue:0", "maxValue:2"]
        }
    };

    window.SQ.validator = Validator;

})(window, document);