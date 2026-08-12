<!-- Vendor -->
{{-- <script src="assets/libs/jquery/jquery.min.js"></script>
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/libs/simplebar/simplebar.min.js"></script>
<script src="assets/libs/node-waves/waves.min.js"></script>
<script src="assets/libs/waypoints/lib/jquery.waypoints.min.js"></script>
<script src="assets/libs/jquery.counterup/jquery.counterup.min.js"></script>
<script src="assets/libs/feather-icons/feather.min.js"></script> --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            xhrFields: {
                withCredentials: true
            }
        });

        $('.page-loading').fadeIn();
        setTimeout(function() {
            $('.page-loading').fadeOut();
        }, 1500); // Adjust the timeout duration as needed
    });

    function showLoading() {
        $('#page-loading').fadeIn();
    }

    function hideLoading() {
        $('#page-loading').fadeOut();
    }

    function platformToast(icon, message) {
        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: message,
            showConfirmButton: false,
            timer: icon === 'success' ? 1400 : 3200,
            timerProgressBar: true,
        });
    }

    $('form').on('submit', function(e) {
        if ($(this).hasClass('native-submit-form')) {
            return true;
        }

        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var buttonText = $button.text();
        $form.find('button[type="submit"]').attr('disabled', true);
        $button.text('Processing…');

        var formData = new FormData(this);

        $.ajax({
            type: $form.attr('method'), // Method form POST atau GET
            url: $form.attr('action'), // URL tujuan
            data: formData, // Gunakan FormData
            processData: false, // Jangan memproses data
            contentType: false, // Jangan set content type
            headers: $form.hasClass('auth-json-form') ? {
                'Accept': 'application/json'
            } : {},
            beforeSend: function() {
                showLoading();
                $form.find('button[type="submit"]').attr('disabled', true);
                $form.find('button[type="submit"]').text('Loading...');
            },
            success: function(response) {
                // Proses selesai, enable kembali tombol
                $button.attr('disabled', false).text(buttonText);

                // Opsional: tangani respons dari Laravel
                if (response.success) {
                    platformToast('success', response.message || 'Saved successfully.').then(function() {
                        if (response.redirect) window.location.href = response.redirect;
                    });
                }
                else {
                    platformToast('error', response.message || 'Unable to complete the request.');
                }
            },
            error: function(xhr) {
                $button.attr('disabled', false).text(buttonText);

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;

                    // Hapus pesan error sebelumnya
                    $('.invalid-feedback').remove();
                    $('.is-invalid').removeClass('is-invalid');

                    var firstErrorField; // Variabel untuk menyimpan elemen error pertama

                    // Tampilkan pesan error
                    $.each(errors, function(key, value) {
                        var inputField = $form.find(`[name="${key}"]`);
                        inputField.addClass('is-invalid');
                        inputField.after(
                            `<span class="invalid-feedback" role="alert"><strong>${value[0]}</strong></span>`
                        );

                        // Simpan elemen error pertama
                        if (!firstErrorField) {
                            firstErrorField = inputField;
                        }
                    });

                    // Scroll ke elemen error pertama
                    if (firstErrorField) {
                        $('html, body').animate({
                            scrollTop: firstErrorField.offset().top -
                                100 // Offset agar tidak terlalu menempel di atas
                        }, 'slow');
                    }
                } else platformToast('error', xhr.responseJSON?.message || 'An unexpected error occurred. Please try again.');
            },
            complete: function() {
                hideLoading();
                $button.attr('disabled', false).text(buttonText);
            }
        });
    });

</script>

@yield('script')
@vite(['resources/js/app.js'])
@yield('script-bottom')
