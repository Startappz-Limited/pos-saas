{{--
    Confirms a form with a SweetAlert2 dialog before it is sent (Phase 5).

    A form opts in with data-swal-confirm and carries a hidden "confirm" field
    the dialog sets to 1; the server refuses the request without it, so nothing
    happens by accident. Without JavaScript, a checkbox inside <noscript> sets
    the same field.

        <form method="POST" action="…" data-swal-confirm
              data-swal-title="Reset this role?" data-swal-text="…" data-swal-button="Reset">
            @csrf
            <input type="hidden" name="confirm" value="0">
            …
        </form>

    data-swal-when (optional): the name of a checkbox in the form; the dialog
    shows only while that checkbox is unticked (switching a role off).
--}}
@once
    @push('scripts')
        <script>
            document.addEventListener('submit', function (event) {
                const form = event.target.closest('form[data-swal-confirm]');

                if (!form || form.dataset.swalConfirmed === '1' || typeof window.Swal === 'undefined') {
                    return;
                }

                const confirmField = form.querySelector('input[type="hidden"][name="confirm"]');
                const when = form.dataset.swalWhen ? form.querySelector('[name="' + form.dataset.swalWhen + '"]') : null;

                if (when && when.checked) {
                    return;
                }

                event.preventDefault();

                window.Swal.fire({
                    title: form.dataset.swalTitle || @json(__('Are you sure?')),
                    text: form.dataset.swalText || '',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: form.dataset.swalButton || @json(__('Confirm')),
                    cancelButtonText: @json(__('Cancel')),
                    focusCancel: true,
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-light' },
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    if (confirmField) {
                        confirmField.value = '1';
                    }
                    form.dataset.swalConfirmed = '1';
                    form.submit();
                });
            });
        </script>
    @endpush
@endonce
