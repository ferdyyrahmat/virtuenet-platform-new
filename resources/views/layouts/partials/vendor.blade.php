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

    $('form').on('submit', function(e) {
        // Authentication/session transition forms must use the browser's native
        // submit so their current CSRF token is sent with the active session.
        if ($(this).hasClass('native-submit-form')) {
            return true;
        }

        e.preventDefault();

        // Disable tombol submit setelah form disubmit
        var $form = $(this);
        $form.find('button[type="submit"]').attr('disabled', true);
        $form.find('button[type="submit"]').text('Loading...');

        var formData = new FormData(this);

        $.ajax({
            type: $form.attr('method'), // Method form POST atau GET
            url: $form.attr('action'), // URL tujuan
            data: formData, // Gunakan FormData
            processData: false, // Jangan memproses data
            contentType: false, // Jangan set content type
            beforeSend: function() {
                showLoading();
                $form.find('button[type="submit"]').attr('disabled', true);
                $form.find('button[type="submit"]').text('Loading...');
            },
            success: function(response) {
                // Proses selesai, enable kembali tombol
                $form.find('button[type="submit"]').attr('disabled', false);
                $form.find('button[type="submit"]').text('Submit');
                // console.log(response);

                // Opsional: tangani respons dari Laravel
                if (response.success) {
                    // Menampilkan SweetAlert dengan pesan sukses
                    Swal.fire({
                        title: 'Success!',
                        html: response.message,
                        icon: 'success',
                        allowOutsideClick: false, // Tidak bisa ditutup dengan klik luar
                        allowEscapeKey: false, // Tidak bisa ditutup dengan tombol escape
                        timer: 3000, // Timer 3 detik sebelum redirect
                        timerProgressBar: true, // Progress bar di bawah modal
                        didOpen: () => {
                            Swal.showLoading(); // Menampilkan loading di dalam modal
                        },
                        willClose: () => {
                            // Redirect ke halaman setelah timer selesai
                            window.location.href = response.redirect;
                        }
                    });
                }
                else {
                    // Menampilkan SweetAlert dengan pesan error
                    Swal.fire({
                        title: 'Error System',
                        text: response.message,
                        icon: 'error',
                        allowOutsideClick: false, // Tidak bisa ditutup dengan klik luar
                        allowEscapeKey: false, // Tidak bisa ditutup dengan tombol escape
                    });

                    // Enable kembali tombol submit
                    $form.find('button[type="submit"]').attr('disabled', false).text(
                        'Submit');
                }
            },
            error: function(xhr) {
                // Enable kembali tombol submit
                $form.find('button[type="submit"]').attr('disabled', false).text(
                    'Submit');

                // Tangani error validasi dari Laravel
                if (xhr.status === 422) {
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
                } else {
                    alert('Terjadi kesalahan, coba lagi.');
                }
            },
            complete: function() {
                hideLoading();
                $form.find('button[type="submit"]').attr('disabled', false);
            }
        });
    });

</script>

@yield('script')
@vite(['resources/js/app.js'])
@yield('script-bottom')
