<?php
session_start();

// If the user confirmed logout (clicked "Yes")
if (isset($_POST['confirm_logout'])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logout | GrocerEase</title>
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
<style>
  body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #e8f5e9, #f1f8f6);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
  }

  /* Modal Background */
  .modal {
    display: flex;
    justify-content: center;
    align-items: center;
    position: fixed;
    z-index: 1000;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.4);
    animation: fadeIn 0.3s ease;
  }

  /* Modal Box */
  .modal-content {
    background: #ffffff;
    border-radius: 16px;
    padding: 40px 30px;
    width: 380px;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
    transform: scale(0.9);
    animation: popIn 0.3s forwards;
  }

  /* Header */
  .modal-content h2 {
    margin-bottom: 10px;
    color: #1b5e20;
    font-size: 22px;
    letter-spacing: 0.5px;
  }

  /* Message */
  .modal-content p {
    font-size: 15px;
    color: #555;
    margin-bottom: 30px;
    line-height: 1.6;
  }

  /* Buttons container */
  .buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
  }

  /* Buttons base */
  .btn {
    padding: 10px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-size: 15px;
    transition: all 0.25s ease;
  }

  /* Yes button */
  .btn-yes {
    background: linear-gradient(135deg, #43a047, #2e7d32);
    color: #fff;
    box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
  }

  .btn-yes:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(46, 125, 50, 0.4);
  }

  /* No button */
  .btn-no {
    background: #f5f5f5;
    color: #444;
    border: 1px solid #ccc;
  }

  .btn-no:hover {
    background: #eeeeee;
    transform: translateY(-2px);
  }

  /* Animation */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes popIn {
    to { transform: scale(1); }
  }

  /* Small icon */
  .logout-icon {
    width: 60px;
    height: 60px;
    margin-bottom: 15px;
  }

  /* SVG styling */
  .logout-icon svg {
    width: 100%;
    height: 100%;
    fill: #43a047;
  }

</style>
</head>
<body>

  <!-- Logout Confirmation Modal -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <div class="logout-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M16 13v-2H7V8l-5 4 5 4v-3zM20 3H10c-1.1 0-2 .9-2 2v4h2V5h10v14H10v-4H8v4c0 1.1.9 2 2 2h10c1.1 
          0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
        </svg>
      </div>
      <h2>Confirm Logout</h2>
      <p>Are you sure you want to log out of your account?</p>
      <form method="POST">
        <div class="buttons">
          <button type="submit" name="confirm_logout" class="btn btn-yes">Yes</button>
          <button type="button" class="btn btn-no" id="cancelLogout">Cancel</button>
        </div>
      </form>
    </div>
  </div>

<script>
  // When user clicks "Cancel", return to dashboard
  document.getElementById('cancelLogout').addEventListener('click', function() {
    window.location.href = 'dashboard.php';
  });
</script>

</body>
</html>
