<!DOCTYPE html>
<html>
<head>

<style>

body{
    margin:0;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#050816;
    font-family:Arial;
}

.forgot-container{
    width:450px;
    background:#0b1020;
    padding:40px;
    border-radius:16px;
}

.forgot-container h2{
    text-align:center;
    color:white;
    margin-bottom:30px;
}

.input-group{
    display:flex;
    flex-direction:column;
    margin-bottom:20px;
}

.input-label{
    color:white;
    margin-bottom:10px;
}

.input-field{
    width:100%;
    padding:18px;
    border:none;
    border-radius:12px;
    background:#151b2e;
    color:white;
    font-size:16px;
    box-sizing:border-box;
}

.btn-primary{
    width:100%;
    padding:18px;
    border:none;
    border-radius:12px;
    background:#ff5a5f;
    color:white;
    font-size:18px;
    cursor:pointer;
}

#message{
    margin-top:15px;
    color:#4ade80;
    text-align:center;
}

</style>

</head>

<body>

<div class="forgot-container">

    <h2>Forgot Password</h2>

    <form id="forgotForm">

        <div class="input-group">

            <label class="input-label">
                Email Id
            </label>

            <input
                type="email"
                name="email"
                class="input-field"
                placeholder="Enter your email"
                required
            >

        </div>

        <button type="submit" class="btn-primary">
            Send Reset Link
        </button>

        <div id="message"></div>

    </form>

</div>

</body>
</html>
<script>
document.getElementById('forgotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = this.querySelector('[name=email]').value;
    const res = await fetch('/api/forgot-password', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({email})
    });
    const data = await res.json();
    document.getElementById('message').innerText = data.message || data.error;
});
</script>