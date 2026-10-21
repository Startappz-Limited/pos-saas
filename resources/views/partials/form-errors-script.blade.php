{{--
    Shows each validation error on its own field, on every form (included by
    every layout): the field is marked invalid and its first message is shown
    under it, unless the form already prints one there; a field inside a
    modal or a tab re-opens it; the first one gets the focus. The summary at
    the top of the page (partials.flash-messages) still lists every error,
    including those with no field on the page.

    window.responseErrorText(data, fallback) gives scripts that send forms
    with fetch() the same: every field error of a 422, else the message.
--}}
<script>
    window.responseErrorText = function (data, fallback) {
        if (data && data.errors && typeof data.errors === 'object') {
            var lines = Object.values(data.errors).flat().filter(Boolean);
            if (lines.length) {
                return lines.join('\n');
            }
        }

        return (data && data.message) || fallback || 'Something went wrong. Please try again.';
    };
</script>
@if ($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var errors = @json($errors->getMessages());
            var first = null;

            Object.keys(errors).forEach(function (key) {
                // items.0.quantity -> items[0][quantity]
                var parts = key.split('.');
                var name = parts[0] + parts.slice(1).map(function (part) { return '[' + part + ']'; }).join('');
                var fields = document.querySelectorAll('[name="' + CSS.escape(name) + '"], [name="' + CSS.escape(name) + '[]"]');
                if (! fields.length) {
                    return;
                }

                fields.forEach(function (field) { field.classList.add('is-invalid'); });

                var field = fields[fields.length - 1];
                var anchor = field.closest('.input-group, .form-check') || field;
                var next = anchor.nextElementSibling;
                if (! (next && next.classList.contains('invalid-feedback'))) {
                    var message = document.createElement('div');
                    message.className = 'invalid-feedback d-block';
                    message.textContent = errors[key][0];
                    anchor.after(message);
                }

                first = first || field;
            });

            if (! first || ! window.bootstrap) {
                return;
            }

            var modal = first.closest('.modal');
            if (modal) {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }

            var pane = first.closest('.tab-pane');
            if (pane && pane.id) {
                var tab = document.querySelector('[data-bs-target="#' + pane.id + '"], [href="#' + pane.id + '"]');
                if (tab) {
                    bootstrap.Tab.getOrCreateInstance(tab).show();
                }
            }

            first.focus({ preventScroll: false });
        });
    </script>
@endif
