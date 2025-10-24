<?php
include 'connection.php';
session_start();

$registration_success = false; // flag to show modal later

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // Check if passwords match
    if ($password !== $confirm) {
        echo "<script>alert('Passwords do not match.');</script>";
        exit;
    }

    // Check if email already exists
    $checkStatement = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $checkStatement->bind_param("s", $email);
    $checkStatement->execute();
    $checkResult = $checkStatement->get_result();

    if ($checkResult->num_rows > 0) {
        echo "<script>alert('Email already registered.');</script>";
        $checkStatement->close();
        exit;
    }
    $checkStatement->close();

    // Hash password and insert new user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertStatement = $conn->prepare("INSERT INTO users (firstName, lastName, email, password) VALUES (?, ?, ?, ?)");
    $insertStatement->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);

    if ($insertStatement->execute()) {
        $registration_success = true; // trigger modal
    } else {
        echo "<script>alert('Error: " . $insertStatement->error . "');</script>";
    }

    $insertStatement->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Registration | GrocerEase</title>
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    display: flex;
    height: 100vh;
    width: 100%;
    font-family: "Helvetica", Arial, sans-serif;
    overflow: hidden;
    background-color: lightgray;
    align-items: stretch;
  }

  .form-container {
    width: 35%;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.12);
    margin: 40px;
    z-index: 2;
    position: relative;
    max-height: calc(100vh - 80px);
    display: flex;
    flex-direction: column;
  }

  .form-inner {
    padding: 28px 36px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow: auto;
  }

  .form-inner h2 { color: #009933; font-size: 26px; margin-bottom: 6px; text-align: left; }
  .form-inner .intro { color: #777; font-size: 14px; margin-bottom: 12px; text-align: left; }

  label { font-weight:600; color:#333; font-size:14px; }
  input {
    padding:10px 12px; border:1.5px solid #ccc; border-radius:8px; font-size:14px;
  }
  input:focus {
    border-color:#009933; outline:none;
    box-shadow:0 0 0 3px rgba(0,153,51,0.08);
  }

  .signin-text { font-size:13px; text-align:center; color:#555; margin-top:8px; }
  .signin-text a { color:#009933; font-weight:600; text-decoration:none; }

  .btn {
    background-color:#009933; color:#fff; border:none;
    padding:14px; border-radius:8px; font-weight:bold; font-size:15px;
    cursor:pointer; transition:0.2s; margin-top:12px; width:100%;
  }
  .btn:hover { background-color:#007a29; }

  .brand-container {
    width: 60%;
    height: 100vh;
    background: #fff;
    position: relative;
    padding: 80px 40px 80px 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    margin: 0;
    border-top-left-radius: 80px;
    border-bottom-left-radius: 80px;
    box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
  }

  .green-circle {
    position: absolute;
    top: -150px;
    right: -150px;
    width: 300px;
    height: 300px;
    background:#009933;
    border-radius:50%;
    z-index:0;
  }
  .brand-container > *:not(.green-circle){ position:relative; z-index:1; }

  .brand-title{ display:flex; align-items:center; gap:10px; font-size:50px; color:#333; margin-bottom:20px; }
  .green{ color:#009933; }
  .logo-img{ height:65px; width:auto; }

  .brand-container h2{ font-size:24px; color:#333; margin-bottom:10px; }
  .subtext{ color:#777; font-size:15px; margin-bottom:30px; line-height:1.4; }
  .features{ list-style:none; display:flex; flex-direction:column; gap:15px; font-size:16px; color:#333; font-weight:600; }

  /* Modal styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
  }
  .modal-content {
    background: #fff;
    border-radius: 10px;
    padding: 30px;
    width: 400px;
    margin: 15% auto;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }
  .modal-content h2 {
    color: #009933;
    margin-bottom: 10px;
  }
  .modal-content button {
    background-color:#009933;
    color:white;
    border:none;
    padding:10px 18px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
  }
  .modal-content button:hover { background-color:#007a29; }

  /* small screens */
  @media (max-width:900px){
    body { flex-direction:column; overflow-y:auto; }
    .form-container { width:90%; margin:20px auto; max-height:none; }
    .brand-container { width:100%; height:auto; padding:40px; order:2; }
    .green-circle { display:none; }
  }
</style>
</head>
<body>

  <div class="form-container">
    <form class="form-inner" action="registration.php" method="POST">
      <h2>Create your account</h2>
      <p class="intro">It only takes a minute.</p>

      <label>First Name:</label>
      <input type="text" name="firstName" required />

      <label>Last Name:</label>
      <input type="text" name="lastName" required />

      <label>Email:</label>
      <input type="email" name="email" required />

      <label>Password:</label>
      <input type="password" name="password" required />

      <label>Confirm Password:</label>
      <input type="password" name="confirm" required />

      <p class="signin-text">Already have an account? <a href="login.php">Sign in</a></p>

      <button type="submit" class="btn">CREATE ACCOUNT</button>
    </form>
  </div>

  <div class="brand-container">
    <div class="green-circle"></div>

    <h1 class="brand-title">
      Grocer<span class="green">Ease</span>
      <img src="image/logo.png" alt="GrocerEase Logo" class="logo-img" />
    </h1>

    <h2>Join Grocer<span class="green">Ease</span> today.</h2>
    <p class="subtext">Create an account and start managing groceries the smarter way.</p>

    <ul class="features">
      <li>🧾 Grocery list that stays in sync.</li>
      <li>🥗 Personalized meal suggestions.</li>
      <li>💰 Waste and cost insights.</li>
    </ul>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <h2>Registration Successful!</h2>
      <p>Your account has been created successfully. You can now sign in.</p>
      <button onclick="window.location.href='login.php'">Go to Login</button>
    </div>
  </div>

  <?php if ($registration_success): ?>
  <script>
    window.onload = function() {
      document.getElementById('successModal').style.display = 'block';
    };
  </script>
  <?php endif; ?>

</body>
</html>
