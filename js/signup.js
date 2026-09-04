document.getElementById('signupForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    // Build the student data object (no club membership)
    const studentData = {
        name: document.getElementById('signupName').value.trim(),
        email: document.getElementById('signupEmail').value.trim(),
        password: document.getElementById('signupPassword').value,
        street: document.getElementById('signupStreet').value.trim(),
        sub_district: document.getElementById('signupSubDistrict').value.trim(),
        district: document.getElementById('signupDistrict').value.trim(),
        phones: [document.getElementById('signupPhone').value.trim()] // Array of phone numbers
    };

    try {
        const response = await fetch('backend/students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(studentData)
        });

        const data = await response.json();

        if (data.success) {
            alert('Sign up successful! Please log in.');
            window.location.href = 'login.html';
        } else {
            alert('Sign up failed: ' + data.error);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during sign up. Please try again.');
    }
});