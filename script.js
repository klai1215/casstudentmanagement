document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('password');
        const eyeIcon = document.getElementById('togglePassword').querySelector('i');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordField.type = 'password';
            eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Check URL for error parameters
    function checkForErrors() {
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        
        if (error === 'incorrect_password') {
            document.getElementById('passwordError').style.display = 'block';
        }
    }

    // Initialize functions
    document.getElementById('togglePassword').addEventListener('click', togglePasswordVisibility);
    checkForErrors();
});