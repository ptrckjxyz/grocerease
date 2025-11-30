<?php
include 'connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $insertStatement = $conn->prepare('SELECT * FROM users WHERE email = ?');
    if (!$insertStatement) {
        die("Prepare failed: " . $conn->error);
    }

    $insertStatement->bind_param('s', $email);
    $insertStatement->execute();
    $result = $insertStatement->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['lastName'] = $user['lastName'];
            $_SESSION['email'] = $user['email'];

            header('Location: dashboard.php'); 
            exit();
        } else {
            echo "<script>window.onload = function(){document.getElementById('errorMessage').textContent = 'Invalid password. Please try again.'; document.getElementById('errorModal').style.display = 'block';}</script>";
        }
    } else {
        echo "<script>window.onload = function(){document.getElementById('errorMessage').textContent = 'Email not found. Please try again.'; document.getElementById('errorModal').style.display = 'block';}</script>";
    }

    $insertStatement->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Login | GrocerEase</title>
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">

<style>
  * { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

  body {
    display: flex;
    height: 100vh;
    width: 100%;
    font-family: "Helvetica", Arial, sans-serif;
    overflow: hidden;
    background-color: lightgray;
    align-items: center; 
    justify-content: space-between;
  }

  .brand-container {
    width: 50%;
    height: 100vh;
    background: #fff;
    position: relative;
    padding: 80px 80px 80px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    margin: 0;
    border-top-right-radius: 150px;
    border-bottom-right-radius: 150px;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
  }

  .green-circle {
    position: absolute;
    top: -150px;
    left: -150px;
    width: 300px;
    height: 300px;
    background: #009933;
    border-radius: 50%;
    z-index: 0;
  }
  .brand-container > *:not(.green-circle) {
    position: relative;
    z-index: 1;
  }

  .brand-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 50px;
    color: #333;
    margin-bottom: 20px;
  }

  .green { color: #009933; }
  .logo-img { height: 65px; width: auto; }

  .brand-container h2 {
    font-size: 24px;
    color: #333;
    margin-bottom: 10px;
  }

  .subtext {
    color: #777;
    font-size: 15px;
    margin-bottom: 30px;
    line-height: 1.4;
  }

  .form-container {
    width: 35%;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.12);
    margin: 100px;
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

  .form-inner h2 {
    color: #009933;
    font-size: 26px;
    margin-bottom: 6px;
    text-align: left;
  }

  .form-inner .intro {
    color: #777;
    font-size: 14px;
    margin-bottom: 12px;
    text-align: left;
  }

  label { font-weight:600; color:#333; font-size:14px; }
  input {
    padding:10px 12px;
    border:1.5px solid #ccc;
    border-radius:8px;
    font-size:14px;
  }
  input:focus {
    border-color:#009933;
    outline:none;
    box-shadow:0 0 0 3px rgba(0,153,51,0.08);
  }

  .signin-text { font-size:13px; text-align:center; color:#555; margin-top:8px; }
  .signin-text a { color:#009933; font-weight:600; text-decoration:none; }

  .btn {
    background-color:#009933;
    color:#fff;
    border:none;
    padding:14px;
    border-radius:8px;
    font-weight:bold;
    font-size:15px;
    cursor:pointer;
    transition:0.2s;
    margin-top:12px;
    width:100%;
  }
  .btn:hover { background-color:#007a29; }

  @media (max-width:900px) {
    body { flex-direction:column; overflow-y:auto; }
    .brand-container { width:100%; height:auto; border-radius:0; padding:40px; order:1; }
    .form-container { width:90%; margin:20px auto; max-height:none; order:2; }
    .green-circle { display:none; }
  }

  .modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
  }

  .modal-content {
    background-color: #fff;
    margin: 15% auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  }

  .modal-content h2 {
    color: #009933;
    margin-bottom: 10px;
  }

  #errorMessage {
    margin-bottom: 15px;
    color: #333;
  }

  .close {
    color: #aaa;
    float: right;
    font-size: 28px;
    cursor: pointer;
  }
  .close:hover { color: #000; }

  #okButton {
    background-color: #009933;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
  }
  #okButton:hover { background-color: #007a29; }
</style>
</head>
<body>

  <div class="brand-container">
    <div class="green-circle"></div>

    <h1 class="brand-title">
      Grocer<span class="green">Ease</span>
      <img src="image/logo.png" alt="GrocerEase Logo" class="logo-img">
    </h1>

    <h2>Welcome back to <span class="green">GrocerEase</span>.</h2>
    <p class="subtext">Sign in and keep your grocery plans in sync and your pantry organized effortlessly.</p>
  </div>

  <div class="form-container">
    <form class="form-inner" action="login.php" method="POST">
      <h2>Sign in to your account</h2>
      <p class="intro">Enter your credentials to access GrocerEase.</p>

      <label>Email:</label>
      <input type="email" name="email" required>

      <label>Password:</label>
      <input type="password" name="password" required>

      <p class="signin-text">New to GrocerEase? <a href="registration.php">Create an account</a></p>

      <button type="submit" class="btn">SIGN IN</button>
    </form>
  </div>

  <div id="errorModal" class="modal">
    <div class="modal-content">
      <span class="close" id="closeModal">&times;</span>
      <h2>Login Failed</h2>
      <p id="errorMessage">Invalid email or password.</p>
      <button id="okButton">OK</button>
    </div>
  </div>

  <script>
    const modal = document.getElementById('errorModal');
    const closeBtn = document.getElementById('closeModal');
    const okBtn = document.getElementById('okButton');

    closeBtn.onclick = function() {
      modal.style.display = 'none';
    };
    okBtn.onclick = function() {
      modal.style.display = 'none';
    };
    window.onclick = function(event) {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    };
  </script>

</body>
</html>
