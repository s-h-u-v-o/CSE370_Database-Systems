document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        try {
            const response = await fetch('backend/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.success) {
                // Store user session
                localStorage.setItem('user', JSON.stringify(data.user));
                // Redirect to dashboard
                window.location.href = 'index.html';
            } else {
                showError(data.message || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('Network error. Please try again.');
        }
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
        setTimeout(() => {
            errorMessage.style.display = 'none';
        }, 5000);
    }

    // Check if already logged in
    const user = localStorage.getItem('user');
    if (user) {
        window.location.href = 'index.html';
    }
});